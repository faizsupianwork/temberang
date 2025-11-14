<?php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['player_name']) || empty(trim($data['player_name']))) {
    jsonResponse(['error' => 'Nama pemain diperlukan'], 400);
}

$playerName = sanitize($data['player_name']);
$playerId = generatePlayerId();
$roomCode = generateRoomCode();

try {
    $db = getDBConnection();
    
    // Check if room code exists (very unlikely, but just in case)
    $stmt = $db->prepare("SELECT id FROM rooms WHERE room_code = ?");
    $stmt->execute([$roomCode]);
    if ($stmt->fetch()) {
        // Generate new code
        $roomCode = generateRoomCode();
    }
    
    // Default settings
    $settings = [
        'categories' => ['basic_words', 'animal_kingdoms', 'food'],
        'enable_mr_white' => false,
        'imposter_awareness' => true,
        'custom_wordpack_id' => null
    ];
    
    // Create room
    $stmt = $db->prepare("INSERT INTO rooms (room_code, host_id, status, settings) VALUES (?, ?, 'lobby', ?)");
    $stmt->execute([$roomCode, $playerId, json_encode($settings)]);
    $roomId = $db->lastInsertId();
    
    // Add host as player
    $stmt = $db->prepare("INSERT INTO players (player_id, room_id, player_name, is_host) VALUES (?, ?, ?, 1)");
    $stmt->execute([$playerId, $roomId, $playerName]);
    
    jsonResponse([
        'success' => true,
        'room_code' => $roomCode,
        'player_id' => $playerId,
        'player_name' => $playerName,
        'is_host' => true
    ]);
    
} catch (PDOException $e) {
    jsonResponse(['error' => 'Gagal membuat bilik: ' . $e->getMessage()], 500);
}
?>