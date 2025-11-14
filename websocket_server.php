<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class TemberangServer implements MessageComponentInterface {
    protected $clients;
    protected $rooms;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->rooms = [];
        echo "Temberang WebSocket Server Started on port " . WS_PORT . "\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if (!$data || !isset($data['type'])) {
            return;
        }

        echo "Message from {$from->resourceId}: {$data['type']}\n";

        switch ($data['type']) {
            case 'register':
                $this->handleRegister($from, $data);
                break;
            case 'join_room':
                $this->handleJoinRoom($from, $data);
                break;
            case 'leave_room':
                $this->handleLeaveRoom($from, $data);
                break;
            case 'update_settings':
                $this->handleUpdateSettings($from, $data);
                break;
            case 'start_game':
                $this->handleStartGame($from, $data);
                break;
            case 'next_turn':
                $this->handleNextTurn($from, $data);
                break;
            case 'submit_vote':
                $this->handleSubmitVote($from, $data);
                break;
            case 'reveal_role':
                $this->handleRevealRole($from, $data);
                break;
            case 'play_again':
                $this->handlePlayAgain($from, $data);
                break;
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        
        // Handle disconnect - remove player from rooms
        foreach ($this->rooms as $roomCode => &$room) {
            foreach ($room['players'] as $playerId => $player) {
                if ($player['conn'] === $conn) {
                    unset($room['players'][$playerId]);
                    $this->broadcastToRoom($roomCode, [
                        'type' => 'player_left',
                        'player_id' => $playerId
                    ]);
                    
                    // If host left, assign new host
                    if ($playerId === $room['host_id'] && count($room['players']) > 0) {
                        $newHostId = array_key_first($room['players']);
                        $room['host_id'] = $newHostId;
                        $this->broadcastToRoom($roomCode, [
                            'type' => 'new_host',
                            'host_id' => $newHostId
                        ]);
                    }
                    
                    // If no players left, remove room
                    if (count($room['players']) === 0) {
                        unset($this->rooms[$roomCode]);
                    }
                    break 2;
                }
            }
        }
        
        echo "Connection closed: {$conn->resourceId}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

    private function handleRegister($conn, $data) {
        $playerId = $data['player_id'];
        $roomCode = $data['room_code'];
        
        if (!isset($this->rooms[$roomCode])) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Bilik tidak dijumpai']));
            return;
        }
        
        // Update connection for existing player (reconnection)
        if (isset($this->rooms[$roomCode]['players'][$playerId])) {
            $this->rooms[$roomCode]['players'][$playerId]['conn'] = $conn;
            $conn->send(json_encode([
                'type' => 'reconnected',
                'room' => $this->getRoomState($roomCode),
                'player_id' => $playerId
            ]));
        } else {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Pemain tidak dijumpai']));
        }
    }

    private function handleJoinRoom($conn, $data) {
        $roomCode = strtoupper($data['room_code']);
        $playerId = $data['player_id'];
        $playerName = $data['player_name'];
        
        // Get room from database
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM rooms WHERE room_code = ? AND status = 'lobby'");
        $stmt->execute([$roomCode]);
        $room = $stmt->fetch();
        
        if (!$room) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Bilik tidak dijumpai atau sudah bermula']));
            return;
        }
        
        // Check if room is full
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM players WHERE room_id = ?");
        $stmt->execute([$room['id']]);
        $playerCount = $stmt->fetch()['count'];
        
        if ($playerCount >= MAX_PLAYERS) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Bilik sudah penuh']));
            return;
        }
        
        // Add player to database
        $stmt = $db->prepare("INSERT INTO players (player_id, room_id, player_name, is_host) VALUES (?, ?, ?, 0)");
        $stmt->execute([$playerId, $room['id'], $playerName]);
        
        // Initialize room in memory if not exists
        if (!isset($this->rooms[$roomCode])) {
            $this->rooms[$roomCode] = [
                'room_id' => $room['id'],
                'host_id' => $room['host_id'],
                'players' => [],
                'settings' => json_decode($room['settings'], true),
                'game_state' => null
            ];
        }
        
        // Add player to memory
        $this->rooms[$roomCode]['players'][$playerId] = [
            'name' => $playerName,
            'conn' => $conn,
            'is_alive' => true,
            'role' => null
        ];
        
        // Notify all players
        $this->broadcastToRoom($roomCode, [
            'type' => 'player_joined',
            'room' => $this->getRoomState($roomCode)
        ]);
        
        // Send success to new player
        $conn->send(json_encode([
            'type' => 'joined',
            'room' => $this->getRoomState($roomCode),
            'player_id' => $playerId
        ]));
    }

    private function handleLeaveRoom($conn, $data) {
        $playerId = $data['player_id'];
        $roomCode = $data['room_code'];
        
        if (!isset($this->rooms[$roomCode]['players'][$playerId])) {
            return;
        }
        
        unset($this->rooms[$roomCode]['players'][$playerId]);
        
        // Update database
        $db = getDBConnection();
        $stmt = $db->prepare("DELETE FROM players WHERE player_id = ?");
        $stmt->execute([$playerId]);
        
        $this->broadcastToRoom($roomCode, [
            'type' => 'player_left',
            'room' => $this->getRoomState($roomCode)
        ]);
    }

    private function handleUpdateSettings($conn, $data) {
        $roomCode = $data['room_code'];
        $settings = $data['settings'];
        
        if (!isset($this->rooms[$roomCode])) {
            return;
        }
        
        $this->rooms[$roomCode]['settings'] = $settings;
        
        // Update database
        $db = getDBConnection();
        $stmt = $db->prepare("UPDATE rooms SET settings = ? WHERE room_code = ?");
        $stmt->execute([json_encode($settings), $roomCode]);
        
        $this->broadcastToRoom($roomCode, [
            'type' => 'settings_updated',
            'settings' => $settings
        ]);
    }

    private function handleStartGame($conn, $data) {
        $roomCode = $data['room_code'];
        
        if (!isset($this->rooms[$roomCode])) {
            return;
        }
        
        $room = &$this->rooms[$roomCode];
        $playerCount = count($room['players']);
        
        if ($playerCount < MIN_PLAYERS) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Minimum 3 pemain diperlukan']));
            return;
        }
        
        // Assign roles
        $this->assignRoles($roomCode);
        
        // Get word pair
        $wordPair = $this->getWordPair($room['settings']);
        
        // Initialize game state
        $playerIds = array_keys($room['players']);
        shuffle($playerIds);
        
        $room['game_state'] = [
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
        
        // Update database
        $db = getDBConnection();
        $stmt = $db->prepare("UPDATE rooms SET status = 'playing', game_state = ? WHERE room_code = ?");
        $stmt->execute([json_encode($room['game_state']), $roomCode]);
        
        // Send game start to all players
        foreach ($room['players'] as $playerId => $player) {
            $this->sendGameStart($player['conn'], $playerId, $room);
        }
    }

    private function assignRoles($roomCode) {
        $room = &$this->rooms[$roomCode];
        $playerIds = array_keys($room['players']);
        shuffle($playerIds);
        
        $playerCount = count($playerIds);
        
        // Determine imposter count
        $imposterCount = $playerCount <= 6 ? 1 : 2;
        
        // Assign imposters
        $imposters = array_slice($playerIds, 0, $imposterCount);
        foreach ($imposters as $playerId) {
            $room['players'][$playerId]['role'] = 'imposter';
        }
        
        // Assign Mr. White if enabled
        $mrWhiteId = null;
        if ($room['settings']['enable_mr_white']) {
            $remainingPlayers = array_slice($playerIds, $imposterCount);
            if (count($remainingPlayers) > 0) {
                $mrWhiteId = $remainingPlayers[0];
                $room['players'][$mrWhiteId]['role'] = 'mrwhite';
                $remainingPlayers = array_slice($remainingPlayers, 1);
            }
        } else {
            $remainingPlayers = array_slice($playerIds, $imposterCount);
        }
        
        // Assign majority
        foreach ($remainingPlayers as $playerId) {
            $room['players'][$playerId]['role'] = 'majority';
        }
        
        // Update database
        $db = getDBConnection();
        foreach ($room['players'] as $playerId => $player) {
            $stmt = $db->prepare("UPDATE players SET role = ? WHERE player_id = ?");
            $stmt->execute([$player['role'], $playerId]);
        }
    }

    private function getWordPair($settings) {
        $db = getDBConnection();
        
        // Check if using custom word pack
        if (isset($settings['custom_wordpack_id']) && $settings['custom_wordpack_id']) {
            // Custom word pack logic (simplified)
            $stmt = $db->prepare("SELECT * FROM word_pairs ORDER BY RAND() LIMIT 1");
            $stmt->execute();
        } else {
            // Get from selected categories
            $categories = $settings['categories'];
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
        }
        
        $pair = $stmt->fetch();
        
        return [
            'majority' => $pair['majority_word'],
            'imposter' => $pair['imposter_word'],
            'category' => $pair['category_name'] ?? 'Umum'
        ];
    }

    private function sendGameStart($conn, $playerId, $room) {
        $player = $room['players'][$playerId];
        $role = $player['role'];
        $gameState = $room['game_state'];
        
        $message = [
            'type' => 'game_started',
            'role' => $role,
            'game_state' => [
                'phase' => $gameState['phase'],
                'round' => $gameState['round'],
                'current_speaker' => $gameState['speaking_order'][$gameState['current_speaker_index']],
                'speaking_order' => $gameState['speaking_order'],
                'players' => $this->getPlayerList($room)
            ]
        ];
        
        // Add word based on role
        if ($role === 'majority') {
            $message['word'] = $gameState['majority_word'];
            $message['message'] = "Anda adalah pemain majoriti. Kata anda: {$gameState['majority_word']}";
        } elseif ($role === 'imposter') {
            $message['word'] = $gameState['imposter_word'];
            if ($room['settings']['imposter_awareness']) {
                $message['message'] = "Anda adalah imposter! Kata anda: {$gameState['imposter_word']}";
            } else {
                $message['message'] = "Kata anda: {$gameState['imposter_word']}";
            }
        } elseif ($role === 'mrwhite') {
            $message['word'] = null;
            $message['message'] = "Anda adalah Mr. White. Anda tidak menerima sebarang kata. Berlakon seolah-olah anda tahu!";
        }
        
        $conn->send(json_encode($message));
    }

    private function handleNextTurn($conn, $data) {
        $roomCode = $data['room_code'];
        $room = &$this->rooms[$roomCode];
        
        if (!$room || !$room['game_state']) {
            return;
        }
        
        $gameState = &$room['game_state'];
        $gameState['current_speaker_index']++;
        
        // Check if everyone has spoken
        if ($gameState['current_speaker_index'] >= count($gameState['speaking_order'])) {
            // Move to voting phase
            $gameState['phase'] = 'voting';
            $gameState['votes'] = [];
            
            $this->broadcastToRoom($roomCode, [
                'type' => 'phase_change',
                'phase' => 'voting',
                'players' => $this->getPlayerList($room)
            ]);
        } else {
            // Next speaker
            $this->broadcastToRoom($roomCode, [
                'type' => 'next_speaker',
                'current_speaker' => $gameState['speaking_order'][$gameState['current_speaker_index']]
            ]);
        }
        
        // Update database
        $db = getDBConnection();
        $stmt = $db->prepare("UPDATE rooms SET game_state = ? WHERE room_code = ?");
        $stmt->execute([json_encode($gameState), $roomCode]);
    }

    private function handleSubmitVote($conn, $data) {
        $roomCode = $data['room_code'];
        $voterId = $data['voter_id'];
        $targetId = $data['target_id'];
        
        $room = &$this->rooms[$roomCode];
        if (!$room || !$room['game_state']) {
            return;
        }
        
        $gameState = &$room['game_state'];
        $gameState['votes'][$voterId] = $targetId;
        
        // Broadcast vote count update
        $this->broadcastToRoom($roomCode, [
            'type' => 'vote_update',
            'votes_count' => count($gameState['votes']),
            'total_voters' => $this->getAlivePlayersCount($room)
        ]);
        
        // Check if all alive players have voted
        $aliveCount = $this->getAlivePlayersCount($room);
        if (count($gameState['votes']) >= $aliveCount) {
            $this->processVotes($roomCode);
        }
        
        // Update database
        $db = getDBConnection();
        $stmt = $db->prepare("UPDATE rooms SET game_state = ? WHERE room_code = ?");
        $stmt->execute([json_encode($gameState), $roomCode]);
    }

    private function processVotes($roomCode) {
        $room = &$this->rooms[$roomCode];
        $gameState = &$room['game_state'];
        
        // Tally votes
        $voteCounts = array_count_values($gameState['votes']);
        arsort($voteCounts);
        
        $maxVotes = max($voteCounts);
        $topVoted = array_keys($voteCounts, $maxVotes);
        
        // Check for tie
        if (count($topVoted) > 1) {
            // Tie - revote
            $gameState['phase'] = 'revote';
            $gameState['revote_candidates'] = $topVoted;
            $gameState['votes'] = [];
            
            $this->broadcastToRoom($roomCode, [
                'type' => 'tie_vote',
                'candidates' => $topVoted,
                'candidate_names' => array_map(function($id) use ($room) {
                    return $room['players'][$id]['name'];
                }, $topVoted)
            ]);
        } else {
            // Eliminate player
            $eliminatedId = $topVoted[0];
            $room['players'][$eliminatedId]['is_alive'] = false;
            $gameState['eliminated'][] = $eliminatedId;
            $gameState['phase'] = 'elimination';
            
            $this->broadcastToRoom($roomCode, [
                'type' => 'player_eliminated',
                'eliminated_id' => $eliminatedId,
                'eliminated_name' => $room['players'][$eliminatedId]['name'],
                'vote_counts' => $voteCounts
            ]);
        }
        
        // Update database
        $db = getDBConnection();
        $stmt = $db->prepare("UPDATE rooms SET game_state = ? WHERE room_code = ?");
        $stmt->execute([json_encode($gameState), $roomCode]);
    }

    private function handleRevealRole($conn, $data) {
        $roomCode = $data['room_code'];
        $playerId = $data['player_id'];
        
        $room = &$this->rooms[$roomCode];
        if (!$room) {
            return;
        }
        
        $role = $room['players'][$playerId]['role'];
        $gameState = &$room['game_state'];
        $gameState['revealed_roles'][$playerId] = $role;
        
        $this->broadcastToRoom($roomCode, [
            'type' => 'role_revealed',
            'player_id' => $playerId,
            'player_name' => $room['players'][$playerId]['name'],
            'role' => $role
        ]);
        
        // Check win conditions
        $winner = $this->checkWinCondition($roomCode);
        if ($winner) {
            $this->broadcastToRoom($roomCode, [
                'type' => 'game_over',
                'winner' => $winner,
                'majority_word' => $gameState['majority_word'],
                'imposter_word' => $gameState['imposter_word']
            ]);
            
            // Update database
            $db = getDBConnection();
            $stmt = $db->prepare("UPDATE rooms SET status = 'ended' WHERE room_code = ?");
            $stmt->execute([$roomCode]);
        } else {
            // Continue to next round
            $gameState['phase'] = 'next_round';
            $this->broadcastToRoom($roomCode, [
                'type' => 'continue_game'
            ]);
        }
        
        // Update database
        $db = getDBConnection();
        $stmt = $db->prepare("UPDATE rooms SET game_state = ? WHERE room_code = ?");
        $stmt->execute([json_encode($gameState), $roomCode]);
    }

    private function checkWinCondition($roomCode) {
        $room = $this->rooms[$roomCode];
        
        $aliveImposters = 0;
        $aliveMrWhite = 0;
        $aliveMajority = 0;
        
        foreach ($room['players'] as $player) {
            if ($player['is_alive']) {
                if ($player['role'] === 'imposter') {
                    $aliveImposters++;
                } elseif ($player['role'] === 'mrwhite') {
                    $aliveMrWhite++;
                } elseif ($player['role'] === 'majority') {
                    $aliveMajority++;
                }
            }
        }
        
        // Imposters and Mr. White eliminated - Majority wins
        if ($aliveImposters === 0 && $aliveMrWhite === 0) {
            return 'majority';
        }
        
        // Imposters + Mr. White >= Majority - Imposters win
        if (($aliveImposters + $aliveMrWhite) >= $aliveMajority) {
            return 'imposter';
        }
        
        return null;
    }

    private function handlePlayAgain($conn, $data) {
        $roomCode = $data['room_code'];
        
        if (!isset($this->rooms[$roomCode])) {
            return;
        }
        
        $room = &$this->rooms[$roomCode];
        
        // Reset all players
        foreach ($room['players'] as &$player) {
            $player['is_alive'] = true;
            $player['role'] = null;
        }
        
        // Reset game state
        $room['game_state'] = null;
        
        // Update database
        $db = getDBConnection();
        $stmt = $db->prepare("UPDATE rooms SET status = 'lobby', game_state = NULL WHERE room_code = ?");
        $stmt->execute([$roomCode]);
        
        $stmt = $db->prepare("UPDATE players SET role = NULL, is_alive = 1 WHERE room_id = (SELECT id FROM rooms WHERE room_code = ?)");
        $stmt->execute([$roomCode]);
        
        $this->broadcastToRoom($roomCode, [
            'type' => 'back_to_lobby',
            'room' => $this->getRoomState($roomCode)
        ]);
    }

    private function broadcastToRoom($roomCode, $message) {
        if (!isset($this->rooms[$roomCode])) {
            return;
        }
        
        $messageJson = json_encode($message);
        foreach ($this->rooms[$roomCode]['players'] as $player) {
            $player['conn']->send($messageJson);
        }
    }

    private function getRoomState($roomCode) {
        if (!isset($this->rooms[$roomCode])) {
            return null;
        }
        
        $room = $this->rooms[$roomCode];
        return [
            'room_code' => $roomCode,
            'host_id' => $room['host_id'],
            'players' => $this->getPlayerList($room),
            'settings' => $room['settings'],
            'game_state' => $room['game_state']
        ];
    }

    private function getPlayerList($room) {
        $players = [];
        foreach ($room['players'] as $playerId => $player) {
            $players[] = [
                'id' => $playerId,
                'name' => $player['name'],
                'is_host' => $playerId === $room['host_id'],
                'is_alive' => $player['is_alive']
            ];
        }
        return $players;
    }

    private function getAlivePlayersCount($room) {
        $count = 0;
        foreach ($room['players'] as $player) {
            if ($player['is_alive']) {
                $count++;
            }
        }
        return $count;
    }
}

// Start the server
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new TemberangServer()
        )
    ),
    WS_PORT
);

$server->run();