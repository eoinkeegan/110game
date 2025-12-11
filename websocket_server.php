<?php
// filepath: /Users/ekeegan/Development/110game/websocket_server.php

require_once __DIR__ . '/vendor/autoload.php';

// Load configuration
$config = require __DIR__ . '/config.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class GameWebSocketServer implements MessageComponentInterface {
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        // Store the new connection
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);

        // Broadcast the message to all clients
        foreach ($this->clients as $client) {
            if ($from !== $client) { // Exclude the sender
                $client->send($msg);
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        // Remove the connection
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Run the WebSocket server
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

// Get port from config (default to 8081)
$port = $config['websocket']['port'] ?? 8081;
$host = $config['websocket']['host'] ?? '0.0.0.0';

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new GameWebSocketServer()
        )
    ),
    $port,
    $host
);

echo "WebSocket server running on ws://{$host}:{$port}\n";
$server->run();