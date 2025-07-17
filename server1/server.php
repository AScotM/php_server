<?php

$HOST = "0.0.0.0";
$PORT = 3333;

$TCP_STATES = [
    '01' => 'ESTABLISHED',
    '02' => 'SYN_SENT',
    '03' => 'SYN_RECV',
    '04' => 'FIN_WAIT1',
    '05' => 'FIN_WAIT2',
    '06' => 'TIME_WAIT',
    '07' => 'CLOSE',
    '08' => 'CLOSE_WAIT',
    '09' => 'LAST_ACK',
    '0A' => 'LISTEN',
    '0B' => 'CLOSING'
];

function log_message($level, $message) {
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents("php://stdout", "[$timestamp] - $level - $message\n");
}

function parse_tcp_states() {
    global $TCP_STATES;
    
    $state_count = array_fill_keys(array_values($TCP_STATES), 0);
    
    try {
        $lines = file('/proc/net/tcp');
        if ($lines === false) {
            throw new Exception("Could not read /proc/net/tcp");
        }
        
        array_shift($lines);
        
        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            $state_code = $parts[3];
            $state_name = $TCP_STATES[$state_code] ?? 'UNKNOWN';
            $state_count[$state_name] = ($state_count[$state_name] ?? 0) + 1;
        }
    } catch (Exception $e) {
        log_message("ERROR", "Failed to parse /proc/net/tcp: " . $e->getMessage());
    }
    
    return $state_count;
}

function handle_request() {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    if ($path === '/tcpstates') {
        $state_data = parse_tcp_states();
        $response = [
            'timestamp' => time(),
            'tcp_states' => $state_data
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
        
        $client_ip = $_SERVER['REMOTE_ADDR'];
        log_message("INFO", "Returned TCP state info to $client_ip");
    } else {
        http_response_code(404);
        echo "Not Found";
    }
}

if (php_sapi_name() === 'cli-server') {
    log_message("INFO", "Serving HTTP with TCP state endpoint at http://$HOST:$PORT/tcpstates");
    handle_request();
} else {
    log_message("ERROR", "This script is meant to be run with PHP's built-in development server");
    exit(1);
}
?>
