# -*- coding: utf-8 -*-
# RFID cepat + ringkasan per detik (NO DATABASE VERSION)
# Hopeland HF340 – support 4 antena (ANT1..ANT4)

import os, socket, json, csv, select, threading, time
from collections import deque, defaultdict
from datetime import datetime

# =======================
# KONFIG READER HF340
# =======================
# IP default HF340 di manual adalah 192.168.1.116, tapi pakai IP jaringanmu:
RFID_IP = "172.16.36.4"
RFID_PORTTCP = 8282

# Perintah inventory per antena (ISI DENGAN HEX RESMI DARI HOPELAND)
# Contoh resmi untuk ANT1 (continuous read) dari dokumen protokol:
#   AA 02 10 00 02 01 01 71 AD
EPC1 = "AA 02 10 00 02 01 01 71 AD"   # ANT1
EPC2 = "AA 02 10 00 02 01 01 71 AD"                             # ANT2 -> isi hex dari dokumen Hopeland
EPC3 = ""                             # ANT3 -> isi hex
EPC4 = ""                             # ANT4 -> isi hex

# Stop / reset command (umum di contoh Hopeland)
CMD_STOP = "AA 02 FF 00 00 A4 0F"

# Berapa antena yang dipakai secara fisik (untuk informasi saja)
ANT_COUNT = 4

# =======================
# TUNING PERFORMA
# =======================
POLL_TIMEOUT_S = 0.015
MAX_POLL_LOOPS = 4
SEND_ROUNDS = 1              # berapa command dikirim per loop (1 = 1 antena per loop)
BATCH_FLUSH_MS = 250
BATCH_SIZE_LIMIT = 200
SO_RCVBUF = 1 << 20

ONLY_ANTENNA = None          # None = semua antena diterima
REQUIRE_WHITELIST = False    # False = semua EPC diterima
PRINT_EACH_EVENT = True

REPORT_INTERVAL_SEC = 1.0
REPORT_TOPK = 5

# =======================
# PATH FILE
# =======================
PARENT_FOLDER = "static_files"
PATH_TRUCKS   = os.path.join(PARENT_FOLDER, "rfid_trucks.txt")
PATH_READCSV  = os.path.join(PARENT_FOLDER, "read_log.csv")
PATH_READJSONL= os.path.join(PARENT_FOLDER, "read_log.jsonl")
PATH_LATEST   = os.path.join(PARENT_FOLDER, "reads_latest.json")

PARENT_FOLDER2 = "public"
PARENT_FOLDER3 = "C:\\rfid"
PATH_RESULT    = "C:\\rfid\\rfid.txt"

# Status per antena (kalau mau dibedakan Masuk/Keluar, dsb)
ANT_STATUS = {
    1: "Masuk",
    2: "Masuk",
    3: "Masuk",
    4: "Masuk",
}

# =======================
# Util
# =======================
def ensure_files():
    os.makedirs(PARENT_FOLDER, exist_ok=True)
    if not os.path.exists(PATH_TRUCKS):
        with open(PATH_TRUCKS, "w", encoding="utf-8") as f:
            f.write(json.dumps({}, ensure_ascii=False))
    if not os.path.exists(PATH_READCSV):
        with open(PATH_READCSV, "w", newline="", encoding="utf-8") as f:
            csv.writer(f).writerow(["timestamp","antenna","epc","code","rssi_raw","keterangan"])
    if not os.path.exists(PATH_READJSONL):
        open(PATH_READJSONL, "a", encoding="utf-8").close()
    if not os.path.exists(PATH_LATEST):
        with open(PATH_LATEST, "w", encoding="utf-8") as f:
            f.write(json.dumps({}, ensure_ascii=False))

    os.makedirs(PARENT_FOLDER2, exist_ok=True)
    os.makedirs(PARENT_FOLDER3, exist_ok=True)
    if not os.path.exists(PATH_RESULT):
        with open(PATH_RESULT, "w", encoding="utf-8") as f:
            f.write('')

def load_json(path, default):
    try:
        with open(path, "r", encoding="utf-8") as f:
            t = f.read().strip()
            return json.loads(t) if t else default
    except:
        return default

def save_json(path, obj):
    with open(path, "w", encoding="utf-8") as f:
        json.dump(obj, f, ensure_ascii=False)
        f.flush()

def norm_hex(s): return (s or "").replace(" ", "").upper()
def hex_to_bytes(hexstr): return bytes.fromhex(norm_hex(hexstr))
def bytes_to_hex(b): return " ".join(f"{x:02X}" for x in b)

def keterangan_for(ant): return ANT_STATUS.get(ant, "Unknown")

# =======================
# Reader Socket
# =======================
class ReaderClient:
    def __init__(self, ip, port):
        self.ip, self.port = ip, port
        self.sock = None
        self.rx = bytearray()

    def connect(self):
        self.close()
        s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        s.setsockopt(socket.IPPROTO_TCP, socket.TCP_NODELAY, 1)
        s.setsockopt(socket.SOL_SOCKET, socket.SO_KEEPALIVE, 1)
        try:
            s.setsockopt(socket.SOL_SOCKET, socket.SO_RCVBUF, SO_RCVBUF)
        except:
            pass
        s.settimeout(2.0)
        s.connect((self.ip, self.port))
        s.setblocking(False)
        self.sock = s
        # sinkron awal
        self.send_hex(CMD_STOP)

    def close(self):
        if self.sock:
            try: self.sock.close()
            except: pass
        self.sock = None

    def send_hex(self, cmd_hex):
        if not self.sock or not cmd_hex:
            return False
        try:
            self.sock.sendall(bytes.fromhex(norm_hex(cmd_hex)))
            return True
        except:
            return False

    def poll_read(self):
        if not self.sock:
            return b""
        chunks = []
        for _ in range(MAX_POLL_LOOPS):
            r, _, _ = select.select([self.sock], [], [], POLL_TIMEOUT_S)
            if not r:
                break
            try:
                data = self.sock.recv(16384)
                if data:
                    chunks.append(data)
                else:
                    # peer closed
                    self.close()
                    break
            except (BlockingIOError, socket.timeout, OSError):
                break
        return b"".join(chunks)

# =======================
# Frame Parser HF340
# =======================
def parse_frames_into(rx: bytearray):
    """
    Parse banyak frame balasan mulai header AA 12 ...
    Return list dict: {epc_b:bytes, antenna:int, rssi:int}
    """
    out = []
    i = 0
    while True:
        start = rx.find(b"\xAA\x12", i)
        if start < 0:
            if i > 0:
                del rx[:i]
            break
        if len(rx) - start < 5:
            if start > 0:
                del rx[:start]
            break

        length = int.from_bytes(rx[start+2:start+5], "big")
        end = start + 5 + length
        if len(rx) < end:
            if start > 0:
                del rx[:start]
            break

        frame = bytes(rx[start:end])
        i = end

        try:
            payload = frame[5:]
            epc_len = int.from_bytes(payload[0:2], "big")
            epc_b   = payload[2:2+epc_len]
            antenna = payload[4+epc_len]
            rssi    = payload[6+epc_len]
            out.append({"epc_b": bytes(epc_b), "antenna": int(antenna), "rssi": int(rssi)})
        except Exception:
            # kalau frame aneh, abaikan saja
            pass

    if i > 0:
        del rx[:i]
    return out

# =======================
# Stats
# =======================
class StatsPerSecond:
    def __init__(self):
        self.lock = threading.Lock()
        self.count_total = 0

    def add(self):
        with self.lock:
            self.count_total += 1

    def snap(self):
        with self.lock:
            c = self.count_total
            self.count_total = 0
            return c

class PerSecondReporter(threading.Thread):
    def __init__(self, stats):
        super().__init__(daemon=True)
        self.stats = stats
        self.stop_event = threading.Event()

    def stop(self):
        self.stop_event.set()

    def run(self):
        while not self.stop_event.is_set():
            time.sleep(1.0)
            c = self.stats.snap()
            print(f"[1s] {c} reads")

# =======================
# Async Logger (NO DATABASE)
# =======================
class AsyncLogger(threading.Thread):
    def __init__(self, queue, whitelist, stats):
        super().__init__(daemon=True)
        self.q = queue
        self.whitelist = whitelist
        self.stats = stats
        self.latest = load_json(PATH_LATEST, {})
        self.stop_event = threading.Event()
        self.csv_f = open(PATH_READCSV, "a", newline="", encoding="utf-8")
        self.csv_w = csv.writer(self.csv_f)
        self.jsonl_f = open(PATH_READJSONL, "a", encoding="utf-8")
        self.batch = []

    def stop(self):
        self.stop_event.set()

    def flush(self):
        if not self.batch:
            return
        for ev in self.batch:
            ts   = ev["ts"]
            epc  = ev["epc"]
            ant  = ev["ant"]
            rssi = ev["rssi"]
            code = epc
            ket  = keterangan_for(ant)

            # CSV
            self.csv_w.writerow([ts, ant, epc, code, rssi, ket])

            # JSONL
            self.jsonl_f.write(json.dumps({
                "timestamp": ts,
                "antenna": ant,
                "epc": epc,
                "code": code,
                "rssi_raw": rssi,
                "keterangan": ket
            }) + "\n")

            # latest snapshot
            self.latest[code] = {
                "epc": epc,
                "antenna": ant,
                "rssi_raw": rssi,
                "keterangan": ket,
                "timestamp": ts
            }

            # Print ke console + tulis file hasil
            if PRINT_EACH_EVENT:
                print(f"[READ] ANT{ant} EPC={epc} RSSI={rssi} {ket}")
            try:
                with open(PATH_RESULT, "w", encoding="utf-8") as f:
                    f.write(epc)
            except Exception as e:
                print("Gagal tulis PATH_RESULT:", e)

        self.csv_f.flush()
        self.jsonl_f.flush()
        save_json(PATH_LATEST, self.latest)
        self.batch.clear()

    def run(self):
        last_flush = time.time()
        while not self.stop_event.is_set():
            # tarik semua event yang ada di queue
            while self.q:
                ev = self.q.popleft()
                epc_hex = ev["epc_b"].hex().upper()
                ant = ev["antenna"]
                rssi = ev["rssi"]

                if ONLY_ANTENNA is not None and ant != ONLY_ANTENNA:
                    continue

                # optional whitelist (kalau mau dipakai)
                if REQUIRE_WHITELIST and self.whitelist:
                    if ev["epc_b"] not in self.whitelist:
                        continue

                ts = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                self.stats.add()

                self.batch.append({"ts": ts, "epc": epc_hex, "ant": ant, "rssi": rssi})
                if len(self.batch) >= BATCH_SIZE_LIMIT:
                    break

            now = time.time()
            if self.batch and (now - last_flush) * 1000 >= BATCH_FLUSH_MS:
                self.flush()
                last_flush = now

            time.sleep(0.003)

        # stop -> flush sisa
        self.flush()

# =======================
# MAIN
# =======================
def main():
    ensure_files()

    trucks = load_json(PATH_TRUCKS, {})
    whitelist = {hex_to_bytes(k): v for k, v in trucks.items()} if isinstance(trucks, dict) else {}

    print("RFID Reader Started (NO DATABASE MODE / Hopeland HF340)")
    print("FILTER => ANT =", ONLY_ANTENNA if ONLY_ANTENNA is not None else "ALL")

    # Reader
    rc = ReaderClient(RFID_IP, RFID_PORTTCP)
    try:
        rc.connect()
    except Exception as e:
        print("Gagal konek reader:", e)

    # ====== BUILD COMMAND CYCLE UNTUK ANT1..ANT4 ======
    epc_cmds = [
        ("ANT1", EPC1),
        ("ANT2", EPC2),
        ("ANT3", EPC3),
        ("ANT4", EPC4),
    ]
    cmd_cycle = [(name, cmd) for (name, cmd) in epc_cmds if cmd and cmd.strip()]

    if not cmd_cycle:
        # fallback terakhir: kalau user lupa isi, pakai EPC1 kalau ada
        if EPC1 and EPC1.strip():
            cmd_cycle = [("ANT1", EPC1)]
        else:
            print("ERROR: Tidak ada command EPC yang diisi. Isi EPC1..EPC4 dulu.")
            return

    print("[INIT] Command cycle:")
    for name, cmd in cmd_cycle:
        print("   ", name, "->", cmd)

    cycle_idx = 0

    # Pipeline
    q = deque()
    stats = StatsPerSecond()
    logger = AsyncLogger(q, whitelist, stats)
    rep = PerSecondReporter(stats)
    logger.start()
    rep.start()

    try:
        while True:
            if not rc.sock:
                # coba reconnect
                try:
                    rc.connect()
                except:
                    time.sleep(0.1)
                    continue

            # KIRIM PERINTAH INVENTORY SECARA ROUND-ROBIN
            for _ in range(SEND_ROUNDS):
                name, cmd = cmd_cycle[cycle_idx % len(cmd_cycle)]
                ok = rc.send_hex(cmd)
                cycle_idx += 1
                if not ok:
                    rc.close()
                    break

            # BACA & PARSE
            data = rc.poll_read()
            if data:
                rc.rx.extend(data)
                events = parse_frames_into(rc.rx)
                if events:
                    q.extend(events)

    except KeyboardInterrupt:
        print("STOP oleh user.")
    finally:
        logger.stop()
        rep.stop()
        logger.join()
        rep.join()
        rc.close()

if __name__ == "__main__":
    main()
