<?php
// export_log.php
require_once 'log.php';

$log_file = 'data/access_log.csv';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="access_log_' . date('Y-m-d') . '.csv"');

if (file_exists($log_file)) {
    $handle = fopen($log_file, 'r');
    
    // Output header
    $header = fgetcsv($handle);
    echo implode(',', $header) . "\n";
    
    // Output data
    while (($data = fgetcsv($handle)) !== false) {
        echo implode(',', $data) . "\n";
    }
    
    fclose($handle);
} else {
    echo "No data available";
}
exit;
?>