<?php
// log.php

function logAccess($nisn = '') {
    // Pastikan direktori data ada
	if (!file_exists('data')) {
		mkdir('data', 0755, true);
	}
    $log_file = __DIR__ . '/data/access_log.csv';
   // $log_file = '/data/access_log.csv';
    
    // Dapatkan informasi pengakses
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    $access_time = date('Y-m-d H:i:s');
    
    // Parse informasi browser dan perangkat
    $browser = 'Unknown';
    $device = 'Unknown';
    
    // Deteksi browser
    if (preg_match('/MSIE/i', $user_agent)) {
        $browser = 'Internet Explorer';
    } elseif (preg_match('/Firefox/i', $user_agent)) {
        $browser = 'Mozilla Firefox';
    } elseif (preg_match('/Chrome/i', $user_agent)) {
        $browser = 'Google Chrome';
    } elseif (preg_match('/Safari/i', $user_agent)) {
        $browser = 'Apple Safari';
    } elseif (preg_match('/Opera/i', $user_agent)) {
        $browser = 'Opera';
    } elseif (preg_match('/Edge/i', $user_agent)) {
        $browser = 'Microsoft Edge';
    }
    
    // Deteksi perangkat
    if (preg_match('/Mobile/i', $user_agent)) {
        $device = 'Mobile';
    } elseif (preg_match('/Tablet/i', $user_agent)) {
        $device = 'Tablet';
    } elseif (preg_match('/Android/i', $user_agent)) {
        $device = 'Android';
    } elseif (preg_match('/iPhone/i', $user_agent)) {
        $device = 'iPhone';
    } elseif (preg_match('/iPad/i', $user_agent)) {
        $device = 'iPad';
    } elseif (preg_match('/Windows/i', $user_agent)) {
        $device = 'Windows PC';
    } elseif (preg_match('/Macintosh/i', $user_agent)) {
        $device = 'Mac';
    } elseif (preg_match('/Linux/i', $user_agent)) {
        $device = 'Linux PC';
    }
    
    // Format data untuk log
    $log_data = [
        $access_time,
        $nisn ?: 'N/A',
        $ip_address,
        $device,
        $browser,
        $user_agent
    ];
    
    // Buat file log jika belum ada dan tulis header
    if (!file_exists($log_file)) {
	$header = ['Waktu', 'NISN', 'IP Address', 'Perangkat', 'Browser', 'User Agent'];
        $handle = fopen($log_file, 'w');
        fputcsv($handle, $header);
        fclose($handle);
    }
    
    // Tambahkan data log ke file
    $handle = fopen($log_file, 'a');
    fputcsv($handle, $log_data);
    fclose($handle);
}
?>