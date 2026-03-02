<?php
/**
 * WebSocket Server Stub for Proctoring
 * This is a simplified example - in production, you'd use a proper WebSocket server
 * like Ratchet for PHP or Socket.io with Node.js
 */

// This file would typically be run as a separate process using:
// php ws_server_stub.php

class ProctoringWebSocketServer {
    private $clients = [];
    private $sockets = [];
    
    public function __construct() {
        echo "Starting Proctoring WebSocket Server...\n";
        $this->startServer();
    }
    
    private function startServer() {
        // Create TCP socket
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        
        $bind = socket_bind($socket, '0.0.0.0', 8080);
        $listen = socket_listen($socket);
        
        $this->sockets[] = $socket;
        
        echo "Server listening on ws://localhost:8080\n";
        
        while (true) {
            $changed = $this->sockets;
            
            // Block until input is available
            $num_changed = socket_select($changed, $null, $null, 0, 100000);
            
            if ($num_changed === false) {
                continue;
            }
            
            // Check for new connections
            if (in_array($socket, $changed)) {
                $new_socket = socket_accept($socket);
                $this->sockets[] = $new_socket;
                
                // Remove from changed array
                $found_socket = array_search($socket, $changed);
                unset($changed[$found_socket]);
            }
            
            // Loop through all connected sockets
            foreach ($changed as $socket_item) {
                $header = socket_read($socket_item, 1024);
                
                // Handle handshake for new clients
                if (preg_match('/Sec-WebSocket-Key: (.*)\r\n/', $header, $matches)) {
                    $this->performHandshake($socket_item, $matches[1]);
                    continue;
                }
                
                // Handle incoming messages
                $message = $this->unmask($this->receiveData($socket_item));
                
                if ($message != '') {
                    $decoded_message = json_decode($message, true);
                    
                    if ($decoded_message) {
                        $this->handleMessage($decoded_message, $socket_item);
                    }
                }
            }
        }
    }
    
    private function performHandshake($socket, $key) {
        $accept_key = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        
        $upgrade = "HTTP/1.1 101 Switching Protocols\r\n" .
                   "Upgrade: websocket\r\n" .
                   "Connection: Upgrade\r\n" .
                   "Sec-WebSocket-Accept: $accept_key\r\n\r\n";
        
        socket_write($socket, $upgrade, strlen($upgrade));
    }
    
    private function receiveData($socket) {
        $data = socket_read($socket, 1024, PHP_BINARY_READ);
        
        if ($data === false) {
            return '';
        }
        
        return $data;
    }
    
    private function unmask($data) {
        if (empty($data)) {
            return '';
        }
        
        $len = ord($data[1]) & 127;
        $indexAfterLength = 2;
        
        if ($len === 126) {
            $len = unpack("n", substr($data, 2, 2))[1];
            $indexAfterLength = 4;
        } elseif ($len === 127) {
            $len = unpack("N", substr($data, 2, 8))[1];
            $indexAfterLength = 10;
        }
        
        $mask = substr($data, $indexAfterLength, 4);
        $coded_data = substr($data, $indexAfterLength + 4, $len);
        $decoded = '';
        
        for ($i = 0; $i < $len; $i++) {
            $decoded .= $coded_data[$i] ^ $mask[$i % 4];
        }
        
        return $decoded;
    }
    
    private function handleMessage($message, $socket) {
        switch ($message['type']) {
            case 'security_alert':
                echo "Security Alert: " . $message['message'] . "\n";
                // Log the security event
                $this->logSecurityEvent($message);
                
                // Broadcast to monitoring dashboards
                $this->broadcastToMonitors($message);
                break;
                
            case 'heartbeat':
                // Respond to heartbeat
                $this->sendToClient($socket, json_encode(['type' => 'pong']));
                break;
                
            default:
                echo "Unknown message type: " . $message['type'] . "\n";
        }
    }
    
    private function logSecurityEvent($message) {
        // In a real implementation, log to database
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'exam_attempt_id' => $message['exam_attempt_id'] ?? 'unknown',
            'message' => $message['message'],
            'client_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        // Would insert into database in real implementation
        error_log('Proctoring Security Event: ' . json_encode($log_entry));
    }
    
    private function broadcastToMonitors($message) {
        // Send the security alert to all connected monitoring stations
        $encoded_msg = json_encode($message);
        
        foreach ($this->sockets as $socket) {
            if ($socket !== $this->sockets[0]) { // Don't send back to sender
                $this->sendToClient($socket, $encoded_msg);
            }
        }
    }
    
    private function sendToClient($socket, $message) {
        $encoded = $this->encode($message);
        socket_write($socket, $encoded, strlen($encoded));
    }
    
    private function encode($text) {
        $b1 = 0x80 | (0x1 & 0x0f);
        $length = strlen($text);
        
        if ($length <= 125) {
            $header = pack('CC', $b1, $length);
        } elseif ($length > 125 && $length < 65536) {
            $header = pack('CCn', $b1, 126, $length);
        } elseif ($length >= 65536) {
            $header = pack('CCNN', $b1, 127, $length);
        }
        
        return $header . $text;
    }
}

// Uncomment the following line to run the server
// new ProctoringWebSocketServer();