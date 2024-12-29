<?php
// metrics.php
header('Content-Type: application/json');
$history_file = 'data.json';

function getDiskUsage() {
   $free = disk_free_space("/");
   $total = disk_total_space("/");
   $used = $total - $free;
   return [
       'total' => round($total / 1024 / 1024 / 1024, 2),
       'used' => round($used / 1024 / 1024 / 1024, 2),
       'free' => round($free / 1024 / 1024 / 1024, 2),
       'percent' => round(($used / $total) * 100, 1)
   ];
}

function getNetworkStats() {
   $rx = (int)trim(shell_exec("cat /sys/class/net/eth0/statistics/rx_bytes"));
   $tx = (int)trim(shell_exec("cat /sys/class/net/eth0/statistics/tx_bytes"));
   return [
       'rx' => round($rx / 1024 / 1024, 2),
       'tx' => round($tx / 1024 / 1024, 2)
   ];
}

function getLocalIP() {
   return trim(shell_exec("hostname -I | cut -d' ' -f1"));
}

function getUptime() {
    return trim(shell_exec('uptime -p'));
}

function getMetrics() {
   $temp = round(shell_exec('cat /sys/class/thermal/thermal_zone0/temp') / 1000, 2);
   $cpu_percent = (float)trim(shell_exec("top -bn1 | grep 'Cpu(s)' | awk '{print $2}'"));
   $mem = array_values(array_filter(explode(" ", explode("\n", shell_exec('free'))[1])));
    
   return [
       'temperature' => $temp ?: 0,
       'cpu_percent' => $cpu_percent ?: 0,
       'memory' => [
           'total' => round($mem[1] / 1024, 2) ?: 0,
           'used' => round($mem[2] / 1024, 2) ?: 0,
           'percent' => round((($mem[2] / $mem[1]) * 100), 1) ?: 0
       ],
       'disk' => getDiskUsage(),
       'network' => getNetworkStats(),
       'local_ip' => getLocalIP(),
        'uptime' => getUptime(),
       'timestamp' => date('H:i:s')
   ];
}

try {
   if (!file_exists($history_file)) {
       file_put_contents($history_file, json_encode([]));
       chmod($history_file, 0666);
   }

    $filesize = filesize($history_file);
    if ($filesize > 5 * 1024 * 1024) { // 5MB limit
        $history = json_decode(file_get_contents($history_file), true) ?: [];
        $history = array_slice($history, -100); // Keep last 100 entries
        file_put_contents($history_file, json_encode($history));
    }

   $current = getMetrics();
   $history = json_decode(file_get_contents($history_file), true) ?: [];
   
   $history[] = $current;
   if (count($history) > 30) array_shift($history);
   
   file_put_contents($history_file, json_encode($history));
   echo json_encode(['current' => $current, 'history' => $history]);
} catch (Exception $e) {
   http_response_code(500);
   echo json_encode(['error' => $e->getMessage()]);
}