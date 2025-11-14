<?php
require_once '../config.php';

// Prevent any HTML output
ob_start();

header('Content-Type: application/json');

// Handle preflight CORS if needed
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    jsonResponse(['error' => 'Invalid JSON input'], 400);
}

if (!isset($data['room_code']) || !isset($data['player_name'])) {
    jsonResponse(['error' => 'Kod bilik dan nama pemain diperlukan'], 400);
}

$roomCode = strtoupper(sanitize($data['room_code']));
$playerName = sanitize($data['player_name']);
$playerId = generatePlayerId();

try {
    $db = getDBConnection();
    
    // Check if room exists and is in lobby
    $stmt = $db->prepare("SELECT * FROM rooms WHERE room_code = ?");
    $stmt->execute([$roomCode]);
    $room = $stmt->fetch();
    
    if (!$room) {
        jsonResponse(['error' => 'Bilik tidak dijumpai'], 404);
    }
    
    if ($room['status'] !== 'lobby') {
        jsonResponse(['error' => 'Permainan sudah bermula'], 400);
    }
    
    // Check player count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM players WHERE room_id = ?");
    $stmt->execute([$room['id']]);
    $playerCount = $stmt->fetch()['count'];
    
    if ($playerCount >= MAX_PLAYERS) {
        jsonResponse(['error' => 'Bilik sudah penuh'], 400);
    }
    
    // Success response - Note: Actual join happens via polling.php
    ob_end_clean(); // Clear any output buffer
    jsonResponse([
        'success' => true,
        'room_code' => $roomCode,
        'player_id' => $playerId,
        'player_name' => $playerName,
        'is_host' => false
    ]);
    
} catch (PDOException $e) {
    ob_end_clean();
    error_log("Join room error: " . $e->getMessage());
    jsonResponse(['error' => 'Gagal menyertai bilik. Sila cuba lagi.'], 500);
} catch (Exception $e) {
    ob_end_clean();
    error_log("Join room error: " . $e->getMessage());
    jsonResponse(['error' => 'Ralat sistem. Sila cuba lagi.'], 500);
}
?>