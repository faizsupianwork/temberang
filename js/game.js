// Temberang - Game Logic
class TemberangGame {
    constructor() {
        this.ws = null;
        this.playerName = null;
        this.playerId = null;
        this.roomCode = null;
        this.isHost = false;
        this.currentRoom = null;
        this.gameState = null;
        this.myRole = null;
        this.myWord = null;
        this.categories = [];
        this.selectedCategories = ['basic_words', 'animal_kingdoms', 'food'];
        this.hasVoted = false;
        
        this.init();
    }
    
    init() {
        this.loadPlayerName();
        this.setupEventListeners();
        this.loadCategories();
    }
    
    // Session Storage
    loadPlayerName() {
        const saved = sessionStorage.getItem('temberang_player_name');
        if (saved) {
            this.playerName = saved;
            
            // Check if there's a pending join code FIRST
            const pendingJoinCode = sessionStorage.getItem('temberang_join_code');
            if (pendingJoinCode) {
                // Don't show main menu, auto-join instead
                sessionStorage.removeItem('temberang_join_code');
                this.autoJoinWithCode(pendingJoinCode);
                return; // Don't show main menu
            }
            
            this.showPage('main-menu');
            document.getElementById('display-name').textContent = this.playerName;
        }
    }
    
    savePlayerName(name) {
        this.playerName = name;
        sessionStorage.setItem('temberang_player_name', name);
    }
    
    // Page Navigation
    showPage(pageId) {
        document.querySelectorAll('.page').forEach(page => {
            page.classList.remove('active');
        });
        document.getElementById(pageId).classList.add('active');
    }
    
    showModal(modalId) {
        document.getElementById(modalId).classList.add('active');
    }
    
    hideModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
    }
    
    showLoading() {
        document.getElementById('loading').style.display = 'flex';
    }
    
    hideLoading() {
        document.getElementById('loading').style.display = 'none';
    }
    
    // Event Listeners
    setupEventListeners() {
        // Landing Page
        document.getElementById('btn-continue').addEventListener('click', () => {
            const name = document.getElementById('player-name').value.trim();
            if (name) {
                this.savePlayerName(name);
                
                // Check if there's a pending join code
                const pendingJoinCode = sessionStorage.getItem('temberang_join_code');
                if (pendingJoinCode) {
                    sessionStorage.removeItem('temberang_join_code');
                    this.autoJoinWithCode(pendingJoinCode);
                } else {
                    this.showPage('main-menu');
                    document.getElementById('display-name').textContent = name;
                }
            }
        });
        
        document.getElementById('player-name').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                document.getElementById('btn-continue').click();
            }
        });
        
        // Main Menu
        document.getElementById('btn-new-game').addEventListener('click', () => {
            this.createNewGame();
        });
        
        document.getElementById('btn-join-game').addEventListener('click', () => {
            this.showModal('join-modal');
        });
        
        // Join Modal
        document.getElementById('btn-join-confirm').addEventListener('click', () => {
            this.joinGame();
        });
        
        document.getElementById('btn-join-cancel').addEventListener('click', () => {
            this.hideModal('join-modal');
            document.getElementById('join-error').textContent = '';
        });
        
        document.getElementById('room-code-input').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.joinGame();
            }
        });
        
        // Host Lobby
        document.getElementById('btn-copy-code').addEventListener('click', () => {
            this.copyRoomCode();
        });
        
        document.getElementById('btn-copy-link').addEventListener('click', () => {
            this.copyInviteLink();
        });
        
        document.getElementById('btn-show-qr').addEventListener('click', () => {
            this.showQRCode();
        });
        
        document.getElementById('btn-game-settings').addEventListener('click', () => {
            this.showGameSettings();
        });
        
        document.getElementById('btn-start-game').addEventListener('click', () => {
            this.startGame();
        });
        
        // Player Lobby
        document.getElementById('btn-leave-lobby').addEventListener('click', () => {
            this.leaveLobby();
        });
        
        // Settings Modal
        document.getElementById('btn-save-settings').addEventListener('click', () => {
            this.saveSettings();
        });
        
        document.getElementById('btn-cancel-settings').addEventListener('click', () => {
            this.hideModal('settings-modal');
        });
        
        document.getElementById('btn-upload-wordpack').addEventListener('click', () => {
            document.getElementById('wordpack-file').click();
        });
        
        document.getElementById('wordpack-file').addEventListener('change', (e) => {
            this.uploadWordpack(e.target.files[0]);
        });
        
        // Game Screen
        document.getElementById('btn-next-turn').addEventListener('click', () => {
            this.nextTurn();
        });
        
        document.getElementById('btn-reveal-role').addEventListener('click', () => {
            this.revealRole();
        });
        
        document.getElementById('btn-play-again').addEventListener('click', () => {
            this.playAgain();
        });
        
        // QR Modal
        document.getElementById('btn-close-qr').addEventListener('click', () => {
            this.hideModal('qr-modal');
        });
    }
    
    // API Calls
    async createNewGame() {
        this.showLoading();
        try {
            const response = await fetch('api/create_room.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ player_name: this.playerName })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.playerId = data.player_id;
                this.roomCode = data.room_code;
                this.isHost = true;
                
                this.connectWebSocket();
                this.showHostLobby();
            } else {
                alert(data.error || 'Gagal membuat bilik');
            }
        } catch (error) {
            console.error('Error creating game:', error);
            alert('Ralat: ' + error.message);
        } finally {
            this.hideLoading();
        }
    }
    
    async joinGame() {
        const roomCode = document.getElementById('room-code-input').value.trim().toUpperCase();
        const errorEl = document.getElementById('join-error');
        
        if (!roomCode) {
            errorEl.textContent = 'Sila masukkan kod bilik';
            return;
        }
        
        this.showLoading();
        try {
            const response = await fetch('api/join_room.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    room_code: roomCode,
                    player_name: this.playerName 
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.playerId = data.player_id;
                this.roomCode = data.room_code;
                this.isHost = false;
                
                this.hideModal('join-modal');
                errorEl.textContent = '';
                
                this.connectWebSocket();
                this.showPlayerLobby();
            } else {
                errorEl.textContent = data.error || 'Gagal menyertai bilik';
            }
        } catch (error) {
            console.error('Error joining game:', error);
            errorEl.textContent = 'Ralat: ' + error.message;
        } finally {
            this.hideLoading();
        }
    }
    
    // Auto-join with room code from URL
    async autoJoinWithCode(roomCode) {
        this.showLoading();
        try {
            const response = await fetch('api/join_room.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    room_code: roomCode,
                    player_name: this.playerName 
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.playerId = data.player_id;
                this.roomCode = data.room_code;
                this.isHost = false;
                
                this.connectWebSocket();
                this.showPlayerLobby();
            } else {
                alert('Bilik tidak dijumpai: ' + data.error);
                this.showPage('main-menu');
                document.getElementById('display-name').textContent = this.playerName;
            }
        } catch (error) {
            console.error('Error auto-joining:', error);
            alert('Ralat menyertai bilik: ' + error.message);
            this.showPage('main-menu');
            document.getElementById('display-name').textContent = this.playerName;
        } finally {
            this.hideLoading();
        }
    }
    
    async loadCategories() {
        try {
            const response = await fetch('api/get_categories.php');
            const data = await response.json();
            
            if (data.success) {
                this.categories = data.categories;
            }
        } catch (error) {
            console.error('Error loading categories:', error);
        }
    }
    
    async uploadWordpack(file) {
        if (!file) return;
        
        const formData = new FormData();
        formData.append('wordpack', file);
        
        this.showLoading();
        try {
            const response = await fetch('api/upload_wordpack.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('wordpack-status').textContent = data.message;
                // Store filename for later use
                this.customWordpackFile = data.filename;
            } else {
                alert(data.error || 'Gagal memuat naik fail');
            }
        } catch (error) {
            console.error('Error uploading wordpack:', error);
            alert('Ralat: ' + error.message);
        } finally {
            this.hideLoading();
        }
    }
    
    // WebSocket
    connectWebSocket() {
        this.ws = new WebSocket('ws://localhost:8080');
        
        this.ws.onopen = () => {
            console.log('WebSocket connected');
            
            // Register or join room
            if (this.isHost) {
                // Host already registered via API, just need to register connection
                this.sendMessage({
                    type: 'register',
                    player_id: this.playerId,
                    room_code: this.roomCode
                });
            } else {
                // Join room via WebSocket
                this.sendMessage({
                    type: 'join_room',
                    player_id: this.playerId,
                    player_name: this.playerName,
                    room_code: this.roomCode
                });
            }
        };
        
        this.ws.onmessage = (event) => {
            const message = JSON.parse(event.data);
            this.handleWebSocketMessage(message);
        };
        
        this.ws.onerror = (error) => {
            console.error('WebSocket error:', error);
            alert('Ralat sambungan. Sila pastikan WebSocket server berjalan.');
        };
        
        this.ws.onclose = () => {
            console.log('WebSocket disconnected');
            // Try to reconnect after 3 seconds
            setTimeout(() => {
                if (this.roomCode) {
                    this.connectWebSocket();
                }
            }, 3000);
        };
    }
    
    sendMessage(message) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify(message));
        }
    }
    
    handleWebSocketMessage(message) {
        console.log('WebSocket message:', message);
        
        switch (message.type) {
            case 'error':
                alert(message.message);
                break;
                
            case 'joined':
            case 'reconnected':
                this.currentRoom = message.room;
                this.updateLobby();
                break;
                
            case 'player_joined':
            case 'player_left':
            case 'settings_updated':
                this.currentRoom = message.room;
                this.updateLobby();
                break;
                
            case 'new_host':
                if (message.host_id === this.playerId) {
                    this.isHost = true;
                    this.showHostLobby();
                }
                break;
                
            case 'game_started':
                this.handleGameStart(message);
                break;
                
            case 'next_speaker':
                this.updateCurrentSpeaker(message.current_speaker);
                break;
                
            case 'phase_change':
                this.handlePhaseChange(message);
                break;
                
            case 'vote_update':
                this.updateVoteCount(message.votes_count, message.total_voters);
                break;
                
            case 'tie_vote':
                this.handleTieVote(message);
                break;
                
            case 'player_eliminated':
                this.handleElimination(message);
                break;
                
            case 'role_revealed':
                this.showRoleRevealed(message);
                break;
                
            case 'continue_game':
                this.setupNextRound();
                break;
                
            case 'game_over':
                this.handleGameOver(message);
                break;
                
            case 'back_to_lobby':
                this.currentRoom = message.room;
                this.resetGameState();
                if (this.isHost) {
                    this.showHostLobby();
                } else {
                    this.showPlayerLobby();
                }
                break;
        }
    }
    
    // Lobby UI
    showHostLobby() {
        this.showPage('host-lobby');
        document.getElementById('host-room-code').textContent = this.roomCode;
        this.updateLobby();
    }
    
    showPlayerLobby() {
        this.showPage('player-lobby');
        document.getElementById('player-room-code').textContent = this.roomCode;
        this.updateLobby();
    }
    
    updateLobby() {
        if (!this.currentRoom) return;
        
        const players = this.currentRoom.players || [];
        const playerCount = players.length;
        
        if (this.isHost) {
            // Update host lobby
            document.getElementById('host-player-count').textContent = playerCount;
            
            const listEl = document.getElementById('host-players-list');
            listEl.innerHTML = '';
            
            players.forEach(player => {
                const div = document.createElement('div');
                div.className = 'player-item' + (player.is_host ? ' host' : '');
                div.innerHTML = `
                    <span class="player-name">${player.name}</span>
                    ${player.is_host ? '<span class="player-badge">Tuan Rumah</span>' : ''}
                `;
                listEl.appendChild(div);
            });
            
            // Enable/disable start button
            const startBtn = document.getElementById('btn-start-game');
            startBtn.disabled = playerCount < 3;
        } else {
            // Update player lobby
            document.getElementById('player-player-count').textContent = playerCount;
            
            const hostPlayer = players.find(p => p.is_host);
            if (hostPlayer) {
                document.getElementById('player-host-name').textContent = hostPlayer.name;
            }
            
            const listEl = document.getElementById('player-players-list');
            listEl.innerHTML = '';
            
            players.forEach(player => {
                const div = document.createElement('div');
                div.className = 'player-item' + (player.is_host ? ' host' : '');
                div.innerHTML = `
                    <span class="player-name">${player.name}</span>
                    ${player.is_host ? '<span class="player-badge">Tuan Rumah</span>' : ''}
                `;
                listEl.appendChild(div);
            });
        }
    }
    
    leaveLobby() {
        if (confirm('Adakah anda pasti mahu keluar dari lobi?')) {
            this.sendMessage({
                type: 'leave_room',
                player_id: this.playerId,
                room_code: this.roomCode
            });
            
            if (this.ws) {
                this.ws.close();
            }
            
            this.roomCode = null;
            this.playerId = null;
            this.isHost = false;
            this.currentRoom = null;
            
            this.showPage('main-menu');
        }
    }
    
    // Game Settings
    showGameSettings() {
        this.showModal('settings-modal');
        this.renderCategories();
        
        // Load current settings
        if (this.currentRoom && this.currentRoom.settings) {
            const settings = this.currentRoom.settings;
            this.selectedCategories = settings.categories || [];
            document.getElementById('toggle-mrwhite').checked = settings.enable_mr_white || false;
            document.getElementById('toggle-awareness').checked = settings.imposter_awareness !== false;
        }
        
        this.updateCategorySelection();
    }
    
    renderCategories() {
        const listEl = document.getElementById('categories-list');
        listEl.innerHTML = '';
        
        this.categories.forEach(cat => {
            const div = document.createElement('div');
            div.className = 'category-item';
            div.textContent = cat.name_ms;
            div.dataset.categoryName = cat.name;
            
            div.addEventListener('click', () => {
                this.toggleCategory(cat.name);
            });
            
            listEl.appendChild(div);
        });
    }
    
    toggleCategory(categoryName) {
        const index = this.selectedCategories.indexOf(categoryName);
        if (index > -1) {
            if (this.selectedCategories.length > 1) {
                this.selectedCategories.splice(index, 1);
            } else {
                alert('Sekurang-kurangnya satu kategori mesti dipilih');
            }
        } else {
            this.selectedCategories.push(categoryName);
        }
        this.updateCategorySelection();
    }
    
    updateCategorySelection() {
        document.querySelectorAll('.category-item').forEach(el => {
            const catName = el.dataset.categoryName;
            if (this.selectedCategories.includes(catName)) {
                el.classList.add('selected');
            } else {
                el.classList.remove('selected');
            }
        });
    }
    
    saveSettings() {
        const settings = {
            categories: this.selectedCategories,
            enable_mr_white: document.getElementById('toggle-mrwhite').checked,
            imposter_awareness: document.getElementById('toggle-awareness').checked,
            custom_wordpack_id: this.customWordpackFile || null
        };
        
        this.sendMessage({
            type: 'update_settings',
            room_code: this.roomCode,
            settings: settings
        });
        
        this.hideModal('settings-modal');
    }
    
    // Room Actions
    copyRoomCode() {
        navigator.clipboard.writeText(this.roomCode).then(() => {
            alert('Kod bilik disalin: ' + this.roomCode);
        });
    }
    
    copyInviteLink() {
        const link = window.location.origin + window.location.pathname + '?join=' + this.roomCode;
        navigator.clipboard.writeText(link).then(() => {
            alert('Pautan jemputan disalin!');
        }).catch(err => {
            // Fallback if clipboard API fails
            const textArea = document.createElement('textarea');
            textArea.value = link;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                alert('Pautan jemputan disalin!');
            } catch (e) {
                alert('Gagal menyalin. Link: ' + link);
            }
            document.body.removeChild(textArea);
        });
    }
    
    showQRCode() {
        const link = window.location.origin + window.location.pathname + '?join=' + this.roomCode;
        const qrEl = document.getElementById('qr-code');
        
        // Simple QR code generation using Google Charts API
        qrEl.innerHTML = `<img src="https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl=${encodeURIComponent(link)}" alt="QR Code">`;
        
        this.showModal('qr-modal');
    }
    
    // Game Flow
    startGame() {
        if (!this.isHost) return;
        
        this.sendMessage({
            type: 'start_game',
            room_code: this.roomCode
        });
    }
    
    handleGameStart(message) {
        this.myRole = message.role;
        this.myWord = message.word;
        this.gameState = message.game_state;
        
        this.showPage('game-screen');
        
        // Display role and word
        document.getElementById('role-message').textContent = message.message;
        document.getElementById('word-text').textContent = message.word || '???';
        
        // Update game info
        document.getElementById('game-round').textContent = this.gameState.round;
        document.getElementById('game-phase').textContent = 'Perbincangan';
        
        // Show discussion phase
        this.setupDiscussionPhase();
    }
    
    setupDiscussionPhase() {
        document.getElementById('discussion-phase').style.display = 'block';
        document.getElementById('voting-phase').style.display = 'none';
        document.getElementById('revote-phase').style.display = 'none';
        document.getElementById('elimination-phase').style.display = 'none';
        document.getElementById('game-over').style.display = 'none';
        
        // Render speaking order
        const listEl = document.getElementById('speaking-list');
        listEl.innerHTML = '';
        
        const players = this.currentRoom.players || [];
        this.gameState.speaking_order.forEach((playerId, index) => {
            const player = players.find(p => p.id === playerId);
            if (!player) return;
            
            const div = document.createElement('div');
            div.className = 'speaker-item';
            div.dataset.playerId = playerId;
            div.innerHTML = `
                <span class="speaker-number">${index + 1}</span>
                <span>${player.name}</span>
            `;
            listEl.appendChild(div);
        });
        
        this.updateCurrentSpeaker(this.gameState.current_speaker);
    }
    
    updateCurrentSpeaker(speakerId) {
        // Update speaker list highlighting
        document.querySelectorAll('.speaker-item').forEach(el => {
            el.classList.remove('current', 'done');
            const playerId = el.dataset.playerId;
            
            const speakerIndex = this.gameState.speaking_order.indexOf(playerId);
            const currentIndex = this.gameState.speaking_order.indexOf(speakerId);
            
            if (playerId === speakerId) {
                el.classList.add('current');
            } else if (speakerIndex < currentIndex) {
                el.classList.add('done');
            }
        });
        
        // Update current speaker display
        const player = this.currentRoom.players.find(p => p.id === speakerId);
        if (player) {
            document.getElementById('current-speaker-name').textContent = player.name;
        }
        
        // Show "Saya Dah Siap" button only for current speaker
        const nextBtn = document.getElementById('btn-next-turn');
        if (speakerId === this.playerId) {
            nextBtn.style.display = 'block';
        } else {
            nextBtn.style.display = 'none';
        }
    }
    
    nextTurn() {
        this.sendMessage({
            type: 'next_turn',
            room_code: this.roomCode
        });
    }
    
    handlePhaseChange(message) {
        if (message.phase === 'voting') {
            this.setupVotingPhase();
        }
    }
    
    setupVotingPhase() {
        document.getElementById('game-phase').textContent = 'Mengundi';
        document.getElementById('discussion-phase').style.display = 'none';
        document.getElementById('voting-phase').style.display = 'block';
        
        this.hasVoted = false;
        
        // Render voting options
        const listEl = document.getElementById('voting-list');
        listEl.innerHTML = '';
        
        const alivePlayers = (this.currentRoom.players || []).filter(p => p.is_alive && p.id !== this.playerId);
        
        alivePlayers.forEach(player => {
            const div = document.createElement('div');
            div.className = 'vote-button';
            div.textContent = player.name;
            div.addEventListener('click', () => {
                this.submitVote(player.id, div);
            });
            listEl.appendChild(div);
        });
        
        // Update vote status
        document.getElementById('votes-count').textContent = '0';
        const aliveCount = (this.currentRoom.players || []).filter(p => p.is_alive).length;
        document.getElementById('total-voters').textContent = aliveCount;
    }
    
    submitVote(targetId, buttonEl) {
        if (this.hasVoted) return;
        
        // Visual feedback
        document.querySelectorAll('.vote-button').forEach(btn => {
            btn.classList.remove('selected');
        });
        buttonEl.classList.add('selected');
        
        this.hasVoted = true;
        
        this.sendMessage({
            type: 'submit_vote',
            room_code: this.roomCode,
            voter_id: this.playerId,
            target_id: targetId
        });
    }
    
    updateVoteCount(count, total) {
        document.getElementById('votes-count').textContent = count;
    }
    
    handleTieVote(message) {
        document.getElementById('voting-phase').style.display = 'none';
        document.getElementById('revote-phase').style.display = 'block';
        
        this.hasVoted = false;
        
        // Render revote options
        const listEl = document.getElementById('revote-list');
        listEl.innerHTML = '';
        
        message.candidate_names.forEach((name, index) => {
            const playerId = message.candidates[index];
            const div = document.createElement('div');
            div.className = 'vote-button';
            div.textContent = name;
            div.addEventListener('click', () => {
                this.submitVote(playerId, div);
            });
            listEl.appendChild(div);
        });
    }
    
    handleElimination(message) {
        document.getElementById('voting-phase').style.display = 'none';
        document.getElementById('revote-phase').style.display = 'none';
        document.getElementById('elimination-phase').style.display = 'block';
        document.getElementById('game-phase').textContent = 'Penyingkiran';
        
        document.getElementById('eliminated-name').textContent = message.eliminated_name;
        document.getElementById('role-reveal').style.display = 'none';
        
        // Update player list to mark as dead
        const player = this.currentRoom.players.find(p => p.id === message.eliminated_id);
        if (player) {
            player.is_alive = false;
        }
    }
    
    revealRole() {
        // Get the eliminated player ID from the last elimination
        const eliminatedPlayer = this.currentRoom.players.find(p => !p.is_alive && 
            this.gameState.eliminated.includes(p.id) &&
            !this.gameState.revealed_roles[p.id]);
        
        if (eliminatedPlayer) {
            this.sendMessage({
                type: 'reveal_role',
                room_code: this.roomCode,
                player_id: eliminatedPlayer.id
            });
        }
    }
    
    showRoleRevealed(message) {
        const roleText = {
            'majority': 'Majoriti',
            'imposter': 'Imposter',
            'mrwhite': 'Mr. White'
        };
        
        document.getElementById('revealed-role').textContent = roleText[message.role] || message.role;
        document.getElementById('role-reveal').style.display = 'block';
    }
    
    setupNextRound() {
        // Reset for next discussion round
        this.gameState.round++;
        document.getElementById('game-round').textContent = this.gameState.round;
        
        // Wait a bit then start next discussion
        setTimeout(() => {
            this.setupDiscussionPhase();
        }, 2000);
    }
    
    handleGameOver(message) {
        document.getElementById('discussion-phase').style.display = 'none';
        document.getElementById('voting-phase').style.display = 'none';
        document.getElementById('revote-phase').style.display = 'none';
        document.getElementById('elimination-phase').style.display = 'none';
        document.getElementById('game-over').style.display = 'block';
        document.getElementById('game-phase').textContent = 'Tamat';
        
        const winnerText = message.winner === 'majority' ? 'Majoriti' : 'Imposter';
        document.getElementById('winner-side').textContent = winnerText;
        
        document.getElementById('final-majority-word').textContent = message.majority_word;
        document.getElementById('final-imposter-word').textContent = message.imposter_word;
    }
    
    playAgain() {
        if (!this.isHost) {
            alert('Hanya tuan rumah boleh memulakan permainan baru');
            return;
        }
        
        this.sendMessage({
            type: 'play_again',
            room_code: this.roomCode
        });
    }
    
    resetGameState() {
        this.myRole = null;
        this.myWord = null;
        this.gameState = null;
        this.hasVoted = false;
    }
}

// Initialize game when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.game = new TemberangGame();
    
    // Check for join code in URL
    const urlParams = new URLSearchParams(window.location.search);
    const joinCode = urlParams.get('join');
    
    if (joinCode) {
        // Store join code for after name input
        sessionStorage.setItem('temberang_join_code', joinCode);
        
        // If player already has name, auto-join
        if (window.game.playerName) {
            window.game.autoJoinWithCode(joinCode);
        } else {
            // Show name input with hint
            document.getElementById('landing-page').classList.add('active');
            const subtitle = document.querySelector('.subtitle');
            subtitle.textContent = `Sertai bilik: ${joinCode}`;
            subtitle.style.color = '#10b981';
        }
    }
});