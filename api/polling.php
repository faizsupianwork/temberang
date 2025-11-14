<?php
/**
 * Long Polling API - Real-time Updates without WebSocket
 * This replaces WebSocket for free hosting compatibility
 */

require_once '../config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['action'])) {
    jsonResponse(['error' => 'Action required'], 400);
}

$action = $data['action'];
$playerId = $data['player_id'] ?? null;
$roomCode = $data['room_code'] ?? null;

try {
    $db = getDBConnection();
    
    switch ($action) {
        case 'join_room':
            handleJoinRoom($db, $data);
            break;
            
        case 'update_settings':
            handleUpdateSettings($db, $data);
            break;
            
        case 'start_game':
            handleStartGame($db, $data);
            break;
            
        case 'next_turn':
            handleNextTurn($db, $data);
            break;
            
        case 'submit_vote':
            handleSubmitVote($db, $data);
            break;
            
        case 'reveal_role':
            handleRevealRole($db, $data);
            break;
            
        case 'play_again':
            handlePlayAgain($db, $data);
            break;
            
        case 'get_updates':
            handleGetUpdates($db, $data);
            break;
            
        case 'heartbeat':
            handleHeartbeat($db, $data);
            break;
            
        default:
            jsonResponse(['error' => 'Unknown action'], 400);
    }
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}

// Join Room
function handleJoinRoom($db, $data) {
    $roomCode = strtoupper($data['room_code']);
    $playerId = $data['player_id'];
    $playerName = sanitize($data['player_name']);
    
    // Check room exists
    $stmt = $db->prepare("SELECT * FROM rooms WHERE room_code = ? AND status = 'lobby'");
    $stmt->execute([$roomCode]);
    $room = $stmt->fetch();
    
    if (!$room) {
        jsonResponse(['error' => 'Bilik tidak dijumpai atau sudah bermula'], 404);
    }
    
    // Check player count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM players WHERE room_id = ?");
    $stmt->execute([$room['id']]);
    $playerCount = $stmt->fetch()['count'];
    
    if ($playerCount >= MAX_PLAYERS) {
        jsonResponse(['error' => 'Bilik sudah penuh'], 400);
    }
    
    // Add player
    $stmt = $db->prepare("INSERT INTO players (player_id, room_id, player_name, is_host) VALUES (?, ?, ?, 0) 
                         ON DUPLICATE KEY UPDATE player_name = ?, room_id = ?");
    $stmt->execute([$playerId, $room['id'], $playerName, $playerName, $room['id']]);
    
    // Update room timestamp to trigger updates
    $stmt = $db->prepare("UPDATE rooms SET updated_at = NOW() WHERE id = ?");
    $stmt->execute([$room['id']]);
    
    jsonResponse([
        'success' => true,
        'room' => getRoomData($db, $roomCode)
    ]);
}

// Update Settings
function handleUpdateSettings($db, $data) {
    $roomCode = $data['room_code'];
    $settings = $data['settings'];
    
    $stmt = $db->prepare("UPDATE rooms SET settings = ?, updated_at = NOW() WHERE room_code = ?");
    $stmt->execute([json_encode($settings), $roomCode]);
    
    jsonResponse([
        'success' => true,
        'settings' => $settings
    ]);
}

// Start Game
function handleStartGame($db, $data) {
    $roomCode = $data['room_code'];
    
    $stmt = $db->prepare("SELECT * FROM rooms WHERE room_code = ?");
    $stmt->execute([$roomCode]);
    $room = $stmt->fetch();
    
    if (!$room) {
        jsonResponse(['error' => 'Bilik tidak dijumpai'], 404);
    }
    
    // Get players
    $stmt = $db->prepare("SELECT * FROM players WHERE room_id = ?");
    $stmt->execute([$room['id']]);
    $players = $stmt->fetchAll();
    
    if (count($players) < MIN_PLAYERS) {
        jsonResponse(['error' => 'Minimum 3 pemain diperlukan'], 400);
    }
    
    // Assign roles
    assignRoles($db, $room, $players);
    
    // Get word pair
    $settings = json_decode($room['settings'], true);
    $wordPair = getWordPair($db, $settings);
    
    // Create speaking order
    $playerIds = array_column($players, 'player_id');
    shuffle($playerIds);
    
    // Initialize game state
    $gameState = [
        'round' => 1,
        'phase' => 'discussion',
        'majority_word' => $wordPair['majority'],
        'imposter_word' => $wordPair['imposter'],
        'category' => $wordPair['category'],
        'speaking_order' => $playerIds,
        'current_speaker_index' => 0,
        'votes' => [],
        'eliminated' => [],
        'revealed_roles' => []
    ];
    
    // Update room
    $stmt = $db->prepare("UPDATE rooms SET status = 'playing', game_state = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([json_encode($gameState), $room['id']]);
    
    jsonResponse([
        'success' => true,
        'game_state' => $gameState
    ]);
}

// Assign Roles
function assignRoles($db, $room, $players) {
    $playerIds = array_column($players, 'player_id');
    shuffle($playerIds);
    
    $playerCount = count($playerIds);
    $imposterCount = $playerCount <= 6 ? 1 : 2;
    
    $settings = json_decode($room['settings'], true);
    
    // Assign imposters
    $imposters = array_slice($playerIds, 0, $imposterCount);
    foreach ($imposters as $playerId) {
        $stmt = $db->prepare("UPDATE players SET role = 'imposter' WHERE player_id = ?");
        $stmt->execute([$playerId]);
    }
    
    // Assign Mr. White if enabled
    $remainingPlayers = array_slice($playerIds, $imposterCount);
    if (isset($settings['enable_mr_white']) && $settings['enable_mr_white'] && count($remainingPlayers) > 0) {
        $mrWhiteId = $remainingPlayers[0];
        $stmt = $db->prepare("UPDATE players SET role = 'mrwhite' WHERE player_id = ?");
        $stmt->execute([$mrWhiteId]);
        $remainingPlayers = array_slice($remainingPlayers, 1);
    }
    
    // Assign majority
    foreach ($remainingPlayers as $playerId) {
        $stmt = $db->prepare("UPDATE players SET role = 'majority' WHERE player_id = ?");
        $stmt->execute([$playerId]);
    }
}

// Get Word Pair
function getWordPair($db, $settings) {
    $categories = $settings['categories'] ?? ['basic_words'];
    $placeholders = str_repeat('?,', count($categories) - 1) . '?';
    
    $stmt = $db->prepare("
        SELECT wp.*, c.name_ms as category_name 
        FROM word_pairs wp 
        JOIN categories c ON wp.category_id = c.id 
        WHERE c.name IN ($placeholders) 
        ORDER BY RAND() 
        LIMIT 1
    ");
    $stmt->execute($categories);
    $pair = $stmt->fetch();
    
    return [
        'majority' => $pair['majority_word'],
        'imposter' => $pair['imposter_word'],
        'category' => $pair['category_name']
    ];
}

// Next Turn
function handleNextTurn($db, $data) {
    $roomCode = $data['room_code'];
    
    $stmt = $db->prepare("SELECT * FROM rooms WHERE room_code = ?");
    $stmt->execute([$roomCode]);
    $room = $stmt->fetch();
    
    $gameState = json_decode($room['game_state'], true);
    $gameState['current_speaker_index']++;
    
    // Check if everyone has spoken
    if ($gameState['current_speaker_index'] >= count($gameState['speaking_order'])) {
        $gameState['phase'] = 'voting';
        $gameState['votes'] = [];
    }
    
    $stmt = $db->prepare("UPDATE rooms SET game_state = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([json_encode($gameState), $room['id']]);
    
    jsonResponse([
        'success' => true,
        'game_state' => $gameState
    ]);
}

// Submit Vote
function handleSubmitVote($db, $data) {
    $roomCode = $data['room_code'];
    $voterId = $data['voter_id'];
    $targetId = $data['target_id'];
    
    $stmt = $db->prepare("SELECT * FROM rooms WHERE room_code = ?");
    $stmt->execute([$roomCode]);
    $room = $stmt->fetch();
    
    $gameState = json_decode($room['game_state'], true);
    $gameState['votes'][$voterId] = $targetId;
    
    // Get alive players count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM players WHERE room_id = ? AND is_alive = 1");
    $stmt->execute([$room['id']]);
    $aliveCount = $stmt->fetch()['count'];
    
    // Check if all voted
    if (count($gameState['votes']) >= $aliveCount) {
        processVotes($db, $room, $gameState);
    } else {
        $stmt = $db->prepare("UPDATE rooms SET game_state = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([json_encode($gameState), $room['id']]);
    }
    
    jsonResponse([
        'success' => true,
        'votes_count' => count($gameState['votes']),
        'total_voters' => $aliveCount
    ]);
}

// Process Votes
function processVotes($db, $room, &$gameState) {
    $voteCounts = array_count_values($gameState['votes']);
    arsort($voteCounts);
    
    $maxVotes = max($voteCounts);
    $topVoted = array_keys($voteCounts, $maxVotes);
    
    // Check for tie
    if (count($topVoted) > 1) {
        $gameState['phase'] = 'revote';
        $gameState['revote_candidates'] = $topVoted;
        $gameState['votes'] = [];
    } else {
        // Eliminate player
        $eliminatedId = $topVoted[0];
        
        $stmt = $db->prepare("UPDATE players SET is_alive = 0 WHERE player_id = ?");
        $stmt->execute([$eliminatedId]);
        
        $gameState['eliminated'][] = $eliminatedId;
        $gameState['phase'] = 'elimination';
        $gameState['last_eliminated'] = $eliminatedId;
    }
    
    $stmt = $db->prepare("UPDATE rooms SET game_state = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([json_encode($gameState), $room['id']]);
}

// Reveal Role
function handleRevealRole($db, $data) {
    $roomCode = $data['room_code'];
    $playerId = $data['player_id'];
    
    $stmt = $db->prepare("SELECT * FROM rooms WHERE room_code = ?");
    $stmt->execute([$roomCode]);
    $room = $stmt->fetch();
    
    // Get player role
    $stmt = $db->prepare("SELECT role FROM players WHERE player_id = ?");
    $stmt->execute([$playerId]);
    $player = $stmt->fetch();
    
    $gameState = json_decode($room['game_state'], true);
    $gameState['revealed_roles'][$playerId] = $player['role'];
    
    // Check win conditions
    $winner = checkWinCondition($db, $room['id']);
    
    if ($winner) {
        $gameState['winner'] = $winner;
        $gameState['phase'] = 'game_over';
        
        $stmt = $db->prepare("UPDATE rooms SET status = 'ended', game_state = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([json_encode($gameState), $room['id']]);
    } else {
        $gameState['phase'] = 'next_round';
        $stmt = $db->prepare("UPDATE rooms SET game_state = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([json_encode($gameState), $room['id']]);
    }
    
    jsonResponse([
        'success' => true,
        'role' => $player['role'],
        'winner' => $winner
    ]);
}

// Check Win Condition
function checkWinCondition($db, $roomId) {
    $stmt = $db->prepare("
        SELECT role, COUNT(*) as count 
        FROM players 
        WHERE room_id = ? AND is_alive = 1 
        GROUP BY role
    ");
    $stmt->execute([$roomId]);
    $roleCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $aliveImposters = $roleCounts['imposter'] ?? 0;
    $aliveMrWhite = $roleCounts['mrwhite'] ?? 0;
    $aliveMajority = $roleCounts['majority'] ?? 0;
    
    // Imposters and Mr. White eliminated
    if ($aliveImposters === 0 && $aliveMrWhite === 0) {
        return 'majority';
    }
    
    // Imposters + Mr. White >= Majority
    if (($aliveImposters + $aliveMrWhite) >= $aliveMajority) {
        return 'imposter';
    }
    
    return null;
}

// Play Again
function handlePlayAgain($db, $data) {
    $roomCode = $data['room_code'];
    
    $stmt = $db->prepare("SELECT id FROM rooms WHERE room_code = ?");
    $stmt->execute([$roomCode]);
    $room = $stmt->fetch();
    
    // Reset players
    $stmt = $db->prepare("UPDATE players SET role = NULL, is_alive = 1 WHERE room_id = ?");
    $stmt->execute([$room['id']]);
    
    // Reset room
    $stmt = $db->prepare("UPDATE rooms SET status = 'lobby', game_state = NULL, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$room['id']]);
    
    jsonResponse(['success' => true]);
}

// Get Updates (Long Polling)
function handleGetUpdates($db, $data) {
    $roomCode = $data['room_code'];
    $lastUpdate = $data['last_update'] ?? 0;
    
    // Poll for up to 25 seconds
    $timeout = time() + 25;
    
    while (time() < $timeout) {
        $roomData = getRoomData($db, $roomCode);
        
        if ($roomData['updated_at'] > $lastUpdate) {
            jsonResponse([
                'success' => true,
                'room' => $roomData,
                'timestamp' => time()
            ]);
            return;
        }
        
        usleep(500000); // Sleep 0.5 seconds
    }
    
    // Timeout - return current state
    jsonResponse([
        'success' => true,
        'room' => getRoomData($db, $roomCode),
        'timestamp' => time()
    ]);
}

// Heartbeat
function handleHeartbeat($db, $data) {
    $playerId = $data['player_id'];
    
    $stmt = $db->prepare("UPDATE players SET updated_at = NOW() WHERE player_id = ?");
    $stmt->execute([$playerId]);
    
    jsonResponse(['success' => true]);
}

// Get Room Data
function getRoomData($db, $roomCode) {
    $stmt = $db->prepare("SELECT *, UNIX_TIMESTAMP(updated_at) as updated_at FROM rooms WHERE room_code = ?");
    $stmt->execute([$roomCode]);
    $room = $stmt->fetch();
    
    if (!$room) {
        return null;
    }
    
    $stmt = $db->prepare("SELECT player_id, player_name, role, is_alive, is_host FROM players WHERE room_id = ? ORDER BY joined_at");
    $stmt->execute([$room['id']]);
    $players = $stmt->fetchAll();
    
    return [
        'room_code' => $room['room_code'],
        'host_id' => $room['host_id'],
        'status' => $room['status'],
        'settings' => json_decode($room['settings'], true),
        'game_state' => json_decode($room['game_state'], true),
        'players' => $players,
        'updated_at' => $room['updated_at']
    ];
}
?>