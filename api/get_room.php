<?php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$roomCode = isset($_GET['room_code']) ? strtoupper(sanitize($_GET['room_code'])) : null;

if (!$roomCode) {
    jsonResponse(['error' => 'Kod bilik diperlukan'], 400);
}

try {
    $db = getDBConnection();
    
    // Get room
    $stmt = $db->prepare("SELECT * FROM rooms WHERE room_code = ?");
    $stmt->execute([$roomCode]);
    $room = $stmt->fetch();
    
    if (!$room) {
        jsonResponse(['error' => 'Bilik tidak dijumpai'], 404);
    }
    
    // Get players
    $stmt = $db->prepare("SELECT player_id, player_name, is_host, is_alive, role FROM players WHERE room_id = ?");
    $stmt->execute([$room['id']]);
    $players = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'room' => [
            'room_code' => $room['room_code'],
            'host_id' => $room['host_id'],
            'status' => $room['status'],
            'settings' => json_decode($room['settings'], true),
            'game_state' => json_decode($room['game_state'], true),
            'players' => $players
        ]
    ]);
    
} catch (PDOException $e) {
    jsonResponse(['error' => 'Gagal mendapatkan maklumat bilik: ' . $e->getMessage()], 500);
}
?>