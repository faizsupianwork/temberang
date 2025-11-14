<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Temberang - Permainan Imposter Kata Terhebat</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div id="app">
        <!-- Landing Page -->
        <div id="landing-page" class="page active">
            <div class="container">
                <h1 class="title">🎭 Temberang</h1>
                <p class="subtitle">Permainan Imposter Kata Terhebat!</p>
                
                <div class="info-box">
                    <p>👥 3-10 pemain</p>
                    <p>⏱️ 5-15 minit</p>
                    <p>🎯 Strategi & keseronokan!</p>
                </div>
                
                <div class="name-input-section">
                    <label for="player-name">Apa yang patut kami panggil anda?</label>
                    <input type="text" id="player-name" placeholder="Masukkan nama anda" maxlength="20">
                    <button id="btn-continue" class="btn btn-primary">Teruskan</button>
                </div>
            </div>
        </div>

        <!-- Main Menu -->
        <div id="main-menu" class="page">
            <div class="container">
                <h2 class="welcome-text">Selamat datang, <span id="display-name"></span>!</h2>
                
                <div class="menu-buttons">
                    <button id="btn-new-game" class="btn btn-large btn-primary">
                        <span class="btn-icon">🎮</span>
                        Permainan Baru
                    </button>
                    <button id="btn-join-game" class="btn btn-large btn-secondary">
                        <span class="btn-icon">🚪</span>
                        Sertai Permainan
                    </button>
                </div>
                
                <div class="game-modes">
                    <h3>Mod Permainan</h3>
                    <div class="mode-card active">
                        <h4>🗣️ Main Setempat</h4>
                        <p>Bercakap secara peribadi, bilik yang sama</p>
                    </div>
                    <div class="mode-card disabled">
                        <h4>📱 Lepas & Main</h4>
                        <p>Hantar telefon sekitar</p>
                        <span class="badge">Akan Datang</span>
                    </div>
                    <div class="mode-card disabled">
                        <h4>🌐 Main Jarak Jauh</h4>
                        <p>Main dalam talian dengan rakan</p>
                        <span class="badge">Akan Datang</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Join Game Modal -->
        <div id="join-modal" class="modal">
            <div class="modal-content">
                <h3>Sertai Permainan</h3>
                <input type="text" id="room-code-input" placeholder="Masukkan kod bilik (cth: ABC123)" maxlength="6">
                <div class="modal-buttons">
                    <button id="btn-join-confirm" class="btn btn-primary">Sertai</button>
                    <button id="btn-join-cancel" class="btn btn-secondary">Batal</button>
                </div>
                <div id="join-error" class="error-message"></div>
            </div>
        </div>

        <!-- Host Lobby -->
        <div id="host-lobby" class="page">
            <div class="container">
                <h2>Lobi Tuan Rumah</h2>
                
                <div class="room-info">
                    <div class="room-code-display">
                        <span>Kod Bilik:</span>
                        <strong id="host-room-code">------</strong>
                    </div>
                    
                    <div class="invite-options">
                        <button id="btn-copy-code" class="btn btn-small">
                            📋 Salin Kod
                        </button>
                        <button id="btn-copy-link" class="btn btn-small">
                            🔗 Salin Pautan
                        </button>
                        <button id="btn-show-qr" class="btn btn-small">
                            📱 Tunjuk QR
                        </button>
                    </div>
                </div>
                
                <div class="players-section">
                    <h3>Pemain (<span id="host-player-count">1</span>)</h3>
                    <div id="host-players-list" class="players-list"></div>
                </div>
                
                <div class="host-actions">
                    <button id="btn-game-settings" class="btn btn-secondary">
                        ⚙️ Tetapan Permainan
                    </button>
                    <button id="btn-start-game" class="btn btn-primary btn-large" disabled>
                        🎮 Mula Permainan
                    </button>
                </div>
            </div>
        </div>

        <!-- Player Lobby -->
        <div id="player-lobby" class="page">
            <div class="container">
                <h2>Lobi Pemain</h2>
                
                <div class="room-info">
                    <p>Kod Bilik: <strong id="player-room-code">------</strong></p>
                    <p>Tuan Rumah: <strong id="player-host-name">---</strong></p>
                </div>
                
                <div class="players-section">
                    <h3>Pemain (<span id="player-player-count">0</span>)</h3>
                    <div id="player-players-list" class="players-list"></div>
                </div>
                
                <div class="waiting-message">
                    <p>⏳ Menunggu tuan rumah untuk memulakan permainan...</p>
                </div>
                
                <button id="btn-leave-lobby" class="btn btn-secondary">
                    ← Keluar dari Lobi
                </button>
            </div>
        </div>

        <!-- Game Settings Modal -->
        <div id="settings-modal" class="modal">
            <div class="modal-content modal-large">
                <h3>Tetapan Permainan</h3>
                
                <div class="settings-section">
                    <h4>Kategori Kata</h4>
                    <div id="categories-list" class="categories-grid"></div>
                </div>
                
                <div class="settings-section">
                    <h4>Pakej Kata Khas</h4>
                    <div class="custom-wordpack">
                        <input type="file" id="wordpack-file" accept=".csv" style="display:none">
                        <button id="btn-upload-wordpack" class="btn btn-secondary">
                            📤 Muat Naik CSV
                        </button>
                        <span id="wordpack-status"></span>
                    </div>
                    <p class="help-text">Format CSV: majoriti,imposter</p>
                </div>
                
                <div class="settings-section">
                    <h4>Peraturan Tambahan</h4>
                    <div class="toggle-option">
                        <label>
                            <input type="checkbox" id="toggle-mrwhite">
                            <span>Aktifkan Mr. White</span>
                        </label>
                        <p class="help-text">Peranan khas yang tidak menerima sebarang kata</p>
                    </div>
                    
                    <div class="toggle-option">
                        <label>
                            <input type="checkbox" id="toggle-awareness" checked>
                            <span>Kesedaran Imposter</span>
                        </label>
                        <p class="help-text">Imposter tahu mereka adalah imposter</p>
                    </div>
                </div>
                
                <div class="modal-buttons">
                    <button id="btn-save-settings" class="btn btn-primary">Simpan</button>
                    <button id="btn-cancel-settings" class="btn btn-secondary">Batal</button>
                </div>
            </div>
        </div>

        <!-- Game Screen -->
        <div id="game-screen" class="page">
            <div class="container">
                <div class="game-header">
                    <div class="game-info">
                        <span>Pusingan: <strong id="game-round">1</strong></span>
                        <span>Fasa: <strong id="game-phase">---</strong></span>
                    </div>
                </div>
                
                <!-- Role & Word Display -->
                <div id="word-display" class="word-card">
                    <h3 id="role-message">---</h3>
                    <div id="word-text" class="word-text">---</div>
                </div>
                
                <!-- Discussion Phase -->
                <div id="discussion-phase" class="game-phase">
                    <h3>Fasa Perbincangan</h3>
                    <div class="speaking-order">
                        <h4>Giliran Bercakap:</h4>
                        <div id="speaking-list" class="speaking-list"></div>
                    </div>
                    
                    <div class="current-speaker">
                        <p>Giliran sekarang: <strong id="current-speaker-name">---</strong></p>
                        <button id="btn-next-turn" class="btn btn-primary" style="display:none">
                            Saya Dah Siap ✓
                        </button>
                    </div>
                </div>
                
                <!-- Voting Phase -->
                <div id="voting-phase" class="game-phase" style="display:none">
                    <h3>Fasa Mengundi</h3>
                    <p class="instruction">Pilih seorang pemain yang anda syaki sebagai imposter:</p>
                    <div id="voting-list" class="voting-list"></div>
                    <div class="vote-status">
                        <p>Undi: <span id="votes-count">0</span> / <span id="total-voters">0</span></p>
                    </div>
                </div>
                
                <!-- Revote Phase -->
                <div id="revote-phase" class="game-phase" style="display:none">
                    <h3>Undi Semula</h3>
                    <p class="instruction">Terdapat seri! Undi semula untuk salah seorang calon:</p>
                    <div id="revote-list" class="voting-list"></div>
                </div>
                
                <!-- Elimination Phase -->
                <div id="elimination-phase" class="game-phase" style="display:none">
                    <h3>Pemain Disingkirkan</h3>
                    <div class="eliminated-info">
                        <p><strong id="eliminated-name">---</strong> telah disingkirkan!</p>
                        <button id="btn-reveal-role" class="btn btn-primary">
                            👁️ Dedah Peranan
                        </button>
                    </div>
                    <div id="role-reveal" style="display:none">
                        <p>Peranan: <strong id="revealed-role">---</strong></p>
                    </div>
                </div>
                
                <!-- Game Over -->
                <div id="game-over" class="game-phase" style="display:none">
                    <h2>Permainan Tamat!</h2>
                    <div class="winner-announce">
                        <p class="winner-text">🎉 <span id="winner-side">---</span> Menang!</p>
                    </div>
                    <div class="word-reveal">
                        <p>Kata Majoriti: <strong id="final-majority-word">---</strong></p>
                        <p>Kata Imposter: <strong id="final-imposter-word">---</strong></p>
                    </div>
                    <button id="btn-play-again" class="btn btn-primary btn-large">
                        🔄 Main Semula
                    </button>
                </div>
            </div>
        </div>

        <!-- QR Code Modal -->
        <div id="qr-modal" class="modal">
            <div class="modal-content">
                <h3>Sertai dengan QR Code</h3>
                <div id="qr-code" class="qr-display"></div>
                <button id="btn-close-qr" class="btn btn-primary">Tutup</button>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div id="loading" class="loading-overlay" style="display:none">
            <div class="spinner"></div>
            <p>Memuat....</p>
        </div>
    </div>

    <script src="js/game_polling.js"></script>
</body>
</html>