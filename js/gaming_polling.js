// Temberang - Game Logic with Long Polling (Free Hosting Compatible)
class TemberangGame {
    constructor() {
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
        this.pollingActive = false;
        this.lastUpdate = 0;
        this.heartbeatInterval = null;
        
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
    async apiCall(endpoint, data = {}) {
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON response from', endpoint, ':', text.substring(0, 200));
                throw new Error('Server error. Please check if all API files are uploaded correctly.');
            }
            
            return await response.json();
        } catch (error) {
            console.error('API call error:', endpoint, error);
            throw error;
        }
    }
    
    async createNewGame() {
        this.showLoading();
        try {
            const data = await this.apiCall('api/create_room.php', { 
                player_name: this.playerName 
            });
            
            if (data.success) {
                this.playerId = data.player_id;
                this.roomCode = data.room_code;
                this.isHost = true;
                
                this.startPolling();
                this.startHeartbeat();
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
            const data = await this.apiCall('api/join_room.php', { 
                room_code: roomCode,
                player_name: this.playerName 
            });
            
            if (data.success) {
                this.playerId = data.player_id;
                this.roomCode = data.room_code;
                this.isHost = false;
                
                // Now join via polling API
                const joinData = await this.apiCall('api/polling.php', {
                    action: 'join_room',
                    room_code: roomCode,
                    player_id: this.playerId,
                    player_name: this.playerName
                });
                
                if (joinData.success) {
                    this.currentRoom = joinData.room;
                    this.hideModal('join-modal');
                    errorEl.textContent = '';
                    
                    this.startPolling();
                    this.startHeartbeat();
                    this.showPlayerLobby();
                } else {
                    errorEl.textContent = joinData.error;
                }
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
            const data = await this.apiCall('api/join_room.php', { 
                room_code: roomCode,
                player_name: this.playerName 
            });
            
            if (data.success) {
                this.playerId = data.player_id;
                this.roomCode = data.room_code;
                this.isHost = false;
                
                // Now join via polling API
                const joinData = await this.apiCall('api/polling.php', {
                    action: 'join_room',
                    room_code: roomCode,
                    player_id: this.playerId,
                    player_name: this.playerName
                });
                
                if (joinData.success) {
                    this.currentRoom = joinData.room;
                    this.startPolling();
                    this.startHeartbeat();
                    this.showPlayerLobby();
                } else {
                    alert('Gagal menyertai bilik: ' + joinData.error);
                    this.showPage('main-menu');
                    document.getElementById('display-name').textContent = this.playerName;
                }
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
    
    // Long Polling
    startPolling() {
        if (this.pollingActive) return;
        this.pollingActive = true;
        this.poll();
    }
    
    stopPolling() {
        this.pollingActive = false;
    }
    
    async poll() {
        if (!this.pollingActive || !this.roomCode) return;
        
        try {
            const data = await this.apiCall('api/polling.php', {
                action: 'get_updates',
                room_code: this.roomCode,
                last_update: this.lastUpdate
            });
            
            if (data.success && data.room) {
                this.handleUpdate(data.room);
                this.lastUpdate = data.timestamp;
            }
        } catch (error) {
            console.error('Polling error:', error);
        }
        
        // Continue polling
        if (this.pollingActive) {
            setTimeout(() => this.poll(), 1000);
        }
    }
    
    handleUpdate(room) {
        const prevRoom = this.currentRoom;
        this.currentRoom = room;
        
        // Check for state changes
        if (!prevRoom) {
            this.updateLobby();
            return;
        }
        
        // Status changed
        if (prevRoom.status !== room.status) {
            if (room.status === 'playing' && prevRoom.status === 'lobby') {
                this.handleGameStartUpdate(room);
            } else if (room.status === 'lobby' && prevRoom.status !== 'lobby') {
                this.resetGameState();
                if (this.isHost) {
                    this.showHostLobby();
                } else {
                    this.showPlayerLobby();
                }
            }
        }
        
        // Players changed
        if (JSON.stringify(prevRoom.players) !== JSON.stringify(room.players)) {
            this.updateLobby();
        }
        
        // Game state changed
        if (room.game_state && JSON.stringify(prevRoom.game_state) !== JSON.stringify(room.game_state)) {
            this.handleGameStateUpdate(room.game_state);
        }
    }
    
    // Heartbeat
    startHeartbeat() {
        if (this.heartbeatInterval) return;
        
        this.heartbeatInterval = setInterval(async () => {
            try {
                await this.apiCall('api/polling.php', {
                    action: 'heartbeat',
                    player_id: this.playerId
                });
            } catch (error) {
                console.error('Heartbeat error:', error);
            }
        }, 10000); // Every 10 seconds
    }
    
    stopHeartbeat() {
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
            this.heartbeatInterval = null;
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
            document.getElementById('host-player-count').textContent = playerCount;
            
            const listEl = document.getElementById('host-players-list');
            listEl.innerHTML = '';
            
            players.forEach(player => {
                const div = document.createElement('div');
                div.className = 'player-item' + (player.is_host ? ' host' : '');
                div.innerHTML = `
                    <span class="player-name">${player.player_name}</span>
                    ${player.is_host ? '<span class="player-badge">Tuan Rumah</span>' : ''}
                `;
                listEl.appendChild(div);
            });
            
            const startBtn = document.getElementById('btn-start-game');
            startBtn.disabled = playerCount < 3;
        } else {
            document.getElementById('player-player-count').textContent = playerCount;
            
            const hostPlayer = players.find(p => p.is_host);
            if (hostPlayer) {
                document.getElementById('player-host-name').textContent = hostPlayer.player_name;
            }
            
            const listEl = document.getElementById('player-players-list');
            listEl.innerHTML = '';
            
            players.forEach(player => {
                const div = document.createElement('div');
                div.className = 'player-item' + (player.is_host ? ' host' : '');
                div.innerHTML = `
                    <span class="player-name">${player.player_name}</span>
                    ${player.is_host ? '<span class="player-badge">Tuan Rumah</span>' : ''}
                `;
                listEl.appendChild(div);
            });
        }
    }
    
    leaveLobby() {
        if (confirm('Adakah anda pasti mahu keluar dari lobi?')) {
            this.stopPolling();
            this.stopHeartbeat();
            
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
    
    async saveSettings() {
        const settings = {
            categories: this.selectedCategories,
            enable_mr_white: document.getElementById('toggle-mrwhite').checked,
            imposter_awareness: document.getElementById('toggle-awareness').checked,
            custom_wordpack_id: this.customWordpackFile || null
        };
        
        try {
            await this.apiCall('api/polling.php', {
                action: 'update_settings',
                room_code: this.roomCode,
                settings: settings
            });
            
            this.hideModal('settings-modal');
        } catch (error) {
            alert('Gagal menyimpan tetapan: ' + error.message);
        }
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
        
        // Use Google Charts API for QR code (reliable and simple)
        const encodedLink = encodeURIComponent(link);
        qrEl.innerHTML = `<img src="https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl=${encodedLink}" alt="QR Code" style="max-width:100%;">`;
        
        this.showModal('qr-modal');
    }
    
    // Game Flow
    async startGame() {
        if (!this.isHost) return;
        
        this.showLoading();
        try {
            await this.apiCall('api/polling.php', {
                action: 'start_game',
                room_code: this.roomCode
            });
        } catch (error) {
            alert('Gagal memulakan permainan: ' + error.message);
        } finally {
            this.hideLoading();
        }
    }
    
    handleGameStartUpdate(room) {
        const gameState = room.game_state;
        const player = room.players.find(p => p.player_id === this.playerId);
        
        if (!player || !gameState) return;
        
        this.myRole = player.role;
        this.gameState = gameState;
        
        // Determine word and message
        let word, message;
        const settings = room.settings || {};
        
        if (player.role === 'majority') {
            word = gameState.majority_word;
            message = `Anda adalah pemain majoriti. Kata anda: ${word}`;
        } else if (player.role === 'imposter') {
            word = gameState.imposter_word;
            if (settings.imposter_awareness) {
                message = `Anda adalah imposter! Kata anda: ${word}`;
            } else {
                message = `Kata anda: ${word}`;
            }
        } else if (player.role === 'mrwhite') {
            word = null;
            message = 'Anda adalah Mr. White. Anda tidak menerima sebarang kata. Berlakon seolah-olah anda tahu!';
        }
        
        this.myWord = word;
        
        this.showPage('game-screen');
        document.getElementById('role-message').textContent = message;
        document.getElementById('word-text').textContent = word || '???';
        document.getElementById('game-round').textContent = gameState.round;
        document.getElementById('game-phase').textContent = 'Perbincangan';
        
        this.setupDiscussionPhase();
    }
    
    handleGameStateUpdate(gameState) {
        this.gameState = gameState;
        
        const phase = gameState.phase;
        
        if (phase === 'discussion') {
            this.updateCurrentSpeaker(gameState.current_speaker || gameState.speaking_order[gameState.current_speaker_index]);
        } else if (phase === 'voting') {
            this.setupVotingPhase();
        } else if (phase === 'revote') {
            this.setupRevotePhase();
        } else if (phase === 'elimination') {
            this.setupEliminationPhase();
        } else if (phase === 'game_over') {
            this.handleGameOver();
        } else if (phase === 'next_round') {
            setTimeout(() => {
                this.setupDiscussionPhase();
            }, 2000);
        }
    }
    
    setupDiscussionPhase() {
        document.getElementById('discussion-phase').style.display = 'block';
        document.getElementById('voting-phase').style.display = 'none';
        document.getElementById('revote-phase').style.display = 'none';
        document.getElementById('elimination-phase').style.display = 'none';
        document.getElementById('game-over').style.display = 'none';
        
        const listEl = document.getElementById('speaking-list');
        listEl.innerHTML = '';
        
        const players = this.currentRoom.players || [];
        this.gameState.speaking_order.forEach((playerId, index) => {
            const player = players.find(p => p.player_id === playerId);
            if (!player) return;
            
            const div = document.createElement('div');
            div.className = 'speaker-item';
            div.dataset.playerId = playerId;
            div.innerHTML = `
                <span class="speaker-number">${index + 1}</span>
                <span>${player.player_name}</span>
            `;
            listEl.appendChild(div);
        });
        
        const currentSpeakerId = this.gameState.speaking_order[this.gameState.current_speaker_index];
        this.updateCurrentSpeaker(currentSpeakerId);
    }
    
    updateCurrentSpeaker(speakerId) {
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
        
        const player = this.currentRoom.players.find(p => p.player_id === speakerId);
        if (player) {
            document.getElementById('current-speaker-name').textContent = player.player_name;
        }
        
        const nextBtn = document.getElementById('btn-next-turn');
        if (speakerId === this.playerId) {
            nextBtn.style.display = 'block';
        } else {
            nextBtn.style.display = 'none';
        }
    }
    
    async nextTurn() {
        try {
            await this.apiCall('api/polling.php', {
                action: 'next_turn',
                room_code: this.roomCode
            });
        } catch (error) {
            alert('Ralat: ' + error.message);
        }
    }
    
    setupVotingPhase() {
        document.getElementById('game-phase').textContent = 'Mengundi';
        document.getElementById('discussion-phase').style.display = 'none';
        document.getElementById('voting-phase').style.display = 'block';
        document.getElementById('revote-phase').style.display = 'none';
        
        this.hasVoted = false;
        
        const listEl = document.getElementById('voting-list');
        listEl.innerHTML = '';
        
        const alivePlayers = (this.currentRoom.players || []).filter(p => p.is_alive && p.player_id !== this.playerId);
        
        alivePlayers.forEach(player => {
            const div = document.createElement('div');
            div.className = 'vote-button';
            div.textContent = player.player_name;
            div.addEventListener('click', () => {
                this.submitVote(player.player_id, div);
            });
            listEl.appendChild(div);
        });
        
        document.getElementById('votes-count').textContent = '0';
        const aliveCount = (this.currentRoom.players || []).filter(p => p.is_alive).length;
        document.getElementById('total-voters').textContent = aliveCount;
    }
    
    setupRevotePhase() {
        document.getElementById('voting-phase').style.display = 'none';
        document.getElementById('revote-phase').style.display = 'block';
        
        this.hasVoted = false;
        
        const listEl = document.getElementById('revote-list');
        listEl.innerHTML = '';
        
        const candidates = this.gameState.revote_candidates || [];
        candidates.forEach(playerId => {
            const player = this.currentRoom.players.find(p => p.player_id === playerId);
            if (!player) return;
            
            const div = document.createElement('div');
            div.className = 'vote-button';
            div.textContent = player.player_name;
            div.addEventListener('click', () => {
                this.submitVote(playerId, div);
            });
            listEl.appendChild(div);
        });
    }
    
    async submitVote(targetId, buttonEl) {
        if (this.hasVoted) return;
        
        document.querySelectorAll('.vote-button').forEach(btn => {
            btn.classList.remove('selected');
        });
        buttonEl.classList.add('selected');
        
        this.hasVoted = true;
        
        try {
            const data = await this.apiCall('api/polling.php', {
                action: 'submit_vote',
                room_code: this.roomCode,
                voter_id: this.playerId,
                target_id: targetId
            });
            
            if (data.success) {
                document.getElementById('votes-count').textContent = data.votes_count;
            }
        } catch (error) {
            alert('Ralat: ' + error.message);
            this.hasVoted = false;
        }
    }
    
    setupEliminationPhase() {
        document.getElementById('voting-phase').style.display = 'none';
        document.getElementById('revote-phase').style.display = 'none';
        document.getElementById('elimination-phase').style.display = 'block';
        document.getElementById('game-phase').textContent = 'Penyingkiran';
        
        const eliminatedId = this.gameState.last_eliminated;
        const player = this.currentRoom.players.find(p => p.player_id === eliminatedId);
        
        if (player) {
            document.getElementById('eliminated-name').textContent = player.player_name;
        }
        
        document.getElementById('role-reveal').style.display = 'none';
    }
    
    async revealRole() {
        const eliminatedId = this.gameState.last_eliminated;
        
        try {
            const data = await this.apiCall('api/polling.php', {
                action: 'reveal_role',
                room_code: this.roomCode,
                player_id: eliminatedId
            });
            
            if (data.success) {
                const roleText = {
                    'majority': 'Majoriti',
                    'imposter': 'Imposter',
                    'mrwhite': 'Mr. White'
                };
                
                document.getElementById('revealed-role').textContent = roleText[data.role] || data.role;
                document.getElementById('role-reveal').style.display = 'block';
            }
        } catch (error) {
            alert('Ralat: ' + error.message);
        }
    }
    
    handleGameOver() {
        document.getElementById('discussion-phase').style.display = 'none';
        document.getElementById('voting-phase').style.display = 'none';
        document.getElementById('revote-phase').style.display = 'none';
        document.getElementById('elimination-phase').style.display = 'none';
        document.getElementById('game-over').style.display = 'block';
        document.getElementById('game-phase').textContent = 'Tamat';
        
        const winnerText = this.gameState.winner === 'majority' ? 'Majoriti' : 'Imposter';
        document.getElementById('winner-side').textContent = winnerText;
        
        document.getElementById('final-majority-word').textContent = this.gameState.majority_word;
        document.getElementById('final-imposter-word').textContent = this.gameState.imposter_word;
    }
    
    async playAgain() {
        if (!this.isHost) {
            alert('Hanya tuan rumah boleh memulakan permainan baru');
            return;
        }
        
        try {
            await this.apiCall('api/polling.php', {
                action: 'play_again',
                room_code: this.roomCode
            });
        } catch (error) {
            alert('Ralat: ' + error.message);
        }
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