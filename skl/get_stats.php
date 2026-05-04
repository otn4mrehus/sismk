<?php
// Konfigurasi error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Nonaktifkan display error di output
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/data/php_errors.log');

header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

$log_file = __DIR__ . '/data/access_log.csv';
$response = [
    'success' => false,
    'error' => null,
    'total_access' => 0,
    'total_searches' => 0,
    'mobile_devices' => 0,
    'chrome_users' => 0,
    'hourly_data' => array_fill(0, 24, 0),
    'device_data' => [
        'desktop' => 0,
        'mobile' => 0,
        'tablet' => 0,
        'other' => 0
    ],
    'popular_searches' => [],
    'daily_data' => [],
    'visitors' => [],
    'raw_logs' => []  // Tambahan untuk log akses terperinci
];

// Fungsi untuk mendapatkan lokasi dari IP dengan timeout
function getLocationFromIP($ip) {
    if ($ip === '127.0.0.1' || $ip === '::1') {
        return 'Localhost';
    }
    
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return 'Private IP';
    }

    $context = stream_context_create([
        'http' => ['timeout' => 2]  // Timeout 2 detik
    ]);
    
    try {
        $url = "http://ip-api.com/json/{$ip}?fields=status,message,country,regionName,city,isp";
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return 'Unknown (API timeout)';
        }
        
        $data = json_decode($response, true);
        
        if ($data && $data['status'] === 'success') {
            $locationParts = [];
            if (!empty($data['city'])) $locationParts[] = $data['city'];
            if (!empty($data['regionName'])) $locationParts[] = $data['regionName'];
            if (!empty($data['country'])) $locationParts[] = $data['country'];
            if (!empty($data['isp'])) $locationParts[] = '('.$data['isp'].')';
            
            return implode(', ', $locationParts);
        }
        return 'Unknown location';
    } catch (Exception $e) {
        error_log("IP location error: " . $e->getMessage());
        return 'Location service error';
    }
}

try {
    // Validasi 1: Cek keberadaan file
    if (!file_exists($log_file)) {
        throw new Exception("File log tidak ditemukan di: {$log_file}");
    }

    // Validasi 2: Cek hak akses
    if (!is_readable($log_file)) {
        $perms = substr(sprintf('%o', fileperms($log_file)), -4);
        throw new Exception("File log tidak dapat dibaca. Permissions: {$perms}");
    }

    // Buka file dengan error handling
    $handle = @fopen($log_file, 'r');
    if ($handle === false) {
        throw new Exception("Gagal membuka file log. Error: " . error_get_last()['message']);
    }

    // Lewati header
    $header = fgetcsv($handle);
    if ($header === false) {
        throw new Exception("File log kosong atau format header salah");
    }

    // Proses setiap baris log
    while (($data = fgetcsv($handle)) !== false) {
        if (count($data) < 5) {
            error_log("Baris log tidak valid: " . implode(',', $data));
            continue;
        }
        
        // Simpan raw data untuk log terperinci
        $response['raw_logs'][] = $data;
        
        $response['total_access']++;
        
        // Proses NISN
        $nisn = trim($data[1]);
        if (!empty($nisn)) {
            $response['total_searches']++;
            $nisn_counts[$nisn] = ($nisn_counts[$nisn] ?? 0) + 1;
        }
        
        // Proses device
        $device = strtolower(trim($data[3]));
        $deviceType = 'other';
        if (strpos($device, 'mobile') !== false) {
            $deviceType = 'mobile';
            $response['mobile_devices']++;
        } elseif (strpos($device, 'tablet') !== false) {
            $deviceType = 'tablet';
        } elseif (strpos($device, 'desktop') !== false || 
                 strpos($device, 'windows') !== false || 
                 strpos($device, 'mac') !== false || 
                 strpos($device, 'linux') !== false) {
            $deviceType = 'desktop';
        }
        $response['device_data'][$deviceType]++;
        
        // Proses browser
        if (strpos(strtolower(trim($data[4])), 'chrome') !== false) {
            $response['chrome_users']++;
        }
        
        // Proses waktu
        try {
            $date = new DateTime(trim($data[0]));
            $hour = (int)$date->format('H');
            $response['hourly_data'][$hour]++;
            
            $date_str = $date->format('Y-m-d');
            $seven_days_ago = date('Y-m-d', strtotime('-6 days'));
            
            if ($date_str >= $seven_days_ago) {
                $response['daily_data'][$date_str] = ($response['daily_data'][$date_str] ?? 0) + 1;
            }
            
            // Catat pengunjung unik
            $ip_address = trim($data[2]);
            if (!empty($ip_address)) {
                $ip_date_key = $date_str . '_' . $ip_address;
                if (!isset($unique_ips[$ip_date_key])) {
                    $unique_ips[$ip_date_key] = true;
                    
                    $response['visitors'][] = [
                        'ip' => $ip_address,
                        'date' => $date_str,
                        'time' => $date->format('H:i:s'),
                        'device' => $deviceType,
                        'location' => getLocationFromIP($ip_address),
                        'nisn' => $nisn ?: null
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("Error parsing date: " . $e->getMessage());
            continue;
        }
    }
    
    fclose($handle);
    
    // Urutkan NISN terbanyak
    if (!empty($nisn_counts)) {
        arsort($nisn_counts);
        $response['popular_searches'] = array_map(function($nisn, $count) {
            return ['nisn' => $nisn, 'count' => $count];
        }, array_keys($nisn_counts), array_values($nisn_counts));
    }
    
    // Urutkan pengunjung berdasarkan waktu terbaru
    usort($response['visitors'], function($a, $b) {
        return strcmp($b['date'] . $b['time'], $a['date'] . $a['time']);
    });
    
    $response['success'] = true;

} catch (Exception $e) {
    http_response_code(500);
    $response['error'] = $e->getMessage();
    error_log("Error in get_stats.php: " . $e->getMessage());
    
    // Tambahkan debug info
    $response['debug'] = [
        'log_file' => $log_file,
        'file_exists' => file_exists($log_file),
        'is_readable' => is_readable($log_file),
        'last_error' => error_get_last()
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>