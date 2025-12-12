<?php
// rfid_control.php
//
// API kecil untuk Start/Stop backend RFID:
//
//   ?action=start  -> set enabled=true + coba jalankan run_registrasi.py di background
//   ?action=stop   -> set enabled=false
//   ?action=status -> baca status enabled
//
// Dipanggil dari JavaScript di halaman registrasi.

header('Content-Type: application/json');

// ------------------------------------------------------------------
// 1. Lokasi file kontrol (HARUS sama dengan di run_registrasi.py)
// ------------------------------------------------------------------
$ctrlDir  = __DIR__ . '/static_files';
$ctrlPath = $ctrlDir . '/rfid_control.json';

// Pastikan folder ada
if (!is_dir($ctrlDir)) {
    mkdir($ctrlDir, 0777, true);
}

// Baca state sekarang (default: enabled=false)
$state = ['enabled' => false];
if (file_exists($ctrlPath)) {
    $json = file_get_contents($ctrlPath);
    if ($json !== false && $json !== '') {
        $tmp = json_decode($json, true);
        if (is_array($tmp)) {
            $state = array_merge($state, $tmp);
        }
    }
}

// ------------------------------------------------------------------
// 2. Konfigurasi Python & script
// ------------------------------------------------------------------

// Path ke Python di Windows (hasil "where python")
$pythonPath = 'C:\\Users\\User\\AppData\\Local\\Microsoft\\WindowsApps\\python.exe';

// Nama file script backend
$scriptPath = __DIR__ . DIRECTORY_SEPARATOR . 'run_registrasi.py';

// ------------------------------------------------------------------
// 3. Ambil action dari request
// ------------------------------------------------------------------
$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

// ------------------------------------------------------------------
// Helper: jalankan Python di background
// ------------------------------------------------------------------
function start_python_backend($pythonPath, $scriptPath)
{
    if (!file_exists($scriptPath)) {
        return ['success' => false, 'message' => 'Script run_registrasi.py tidak ditemukan'];
    }

    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

    if ($isWindows) {
        // Windows: gunakan "start /B" supaya jalan di background
        $cmd = 'start /B "" ' . escapeshellarg($pythonPath) . ' ' . escapeshellarg($scriptPath);
        @pclose(@popen($cmd, 'r'));
    } else {
        // Linux: gunakan nohup & disown
        $cmd = escapeshellarg($pythonPath) . ' ' . escapeshellarg($scriptPath) . ' > /dev/null 2>&1 &';
        @shell_exec($cmd);
    }

    return ['success' => true, 'message' => 'Backend RFID dicoba dijalankan'];
}

// ------------------------------------------------------------------
// 4. Handle ACTION
// ------------------------------------------------------------------
if ($action === 'start') {
    // Set enabled = true
    $state['enabled'] = true;
    file_put_contents($ctrlPath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Coba jalankan backend Python di background
    $startInfo = start_python_backend($pythonPath, $scriptPath);

    echo json_encode([
        'success' => true,
        'status'  => 'started',
        'enabled' => true,
        'backend' => $startInfo,
    ]);
    exit;
}

if ($action === 'stop') {
    // Set enabled = false -> run_registrasi.py akan berhenti baca RFID,
    // tapi prosesnya tetap hidup dan idle.
    $state['enabled'] = false;
    file_put_contents($ctrlPath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'status'  => 'stopped',
        'enabled' => false,
    ]);
    exit;
}

// Default: status
echo json_encode([
    'success' => true,
    'status'  => 'status',
    'enabled' => (bool)$state['enabled'],
]);
