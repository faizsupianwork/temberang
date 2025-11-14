-- Temberang Database Schema
-- Import file ini ke database temberang_db

CREATE DATABASE IF NOT EXISTS temberang_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE temberang_db;

-- Jadual Categories
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    name_ms VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jadual Word Pairs
CREATE TABLE word_pairs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    majority_word VARCHAR(100) NOT NULL,
    imposter_word VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jadual Rooms
CREATE TABLE rooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_code VARCHAR(6) UNIQUE NOT NULL,
    host_id VARCHAR(50) NOT NULL,
    status ENUM('lobby', 'playing', 'ended') DEFAULT 'lobby',
    settings JSON,
    game_state JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (room_code),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jadual Players
CREATE TABLE players (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id VARCHAR(50) UNIQUE NOT NULL,
    room_id INT NOT NULL,
    player_name VARCHAR(50) NOT NULL,
    role ENUM('majority', 'imposter', 'mrwhite') DEFAULT NULL,
    is_alive BOOLEAN DEFAULT TRUE,
    is_host BOOLEAN DEFAULT FALSE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    INDEX idx_room (room_id),
    INDEX idx_player (player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Categories
INSERT INTO categories (name, name_ms) VALUES
('basic_words', 'Perkataan Asas'),
('animal_kingdoms', 'Kerajaan Haiwan'),
('colours', 'Warna'),
('entertainment', 'Hiburan'),
('famous_people', 'Orang Terkenal'),
('food', 'Makanan'),
('geography', 'Geografi'),
('literature', 'Kesusasteraan'),
('music', 'Muzik'),
('science', 'Sains'),
('sports', 'Sukan'),
('technology', 'Teknologi');

-- Insert Word Pairs - Perkataan Asas (20 pairs)
INSERT INTO word_pairs (category_id, majority_word, imposter_word) VALUES
(1, 'Matahari', 'Bulan'),
(1, 'Panas', 'Sejuk'),
(1, 'Tinggi', 'Rendah'),
(1, 'Besar', 'Kecil'),
(1, 'Cepat', 'Perlahan'),
(1, 'Terang', 'Gelap'),
(1, 'Berat', 'Ringan'),
(1, 'Keras', 'Lembut'),
(1, 'Basah', 'Kering'),
(1, 'Panjang', 'Pendek'),
(1, 'Dalam', 'Cetek'),
(1, 'Jauh', 'Dekat'),
(1, 'Lama', 'Baru'),
(1, 'Mahal', 'Murah'),
(1, 'Bising', 'Senyap'),
(1, 'Penuh', 'Kosong'),
(1, 'Kuat', 'Lemah'),
(1, 'Cantik', 'Hodoh'),
(1, 'Benar', 'Palsu'),
(1, 'Hidup', 'Mati');

-- Insert Word Pairs - Kerajaan Haiwan (20 pairs)
INSERT INTO word_pairs (category_id, majority_word, imposter_word) VALUES
(2, 'Kucing', 'Anjing'),
(2, 'Harimau', 'Singa'),
(2, 'Gajah', 'Badak Sumbu'),
(2, 'Burung', 'Kelawar'),
(2, 'Ikan', 'Lumba-lumba'),
(2, 'Ular', 'Cicak'),
(2, 'Monyet', 'Beruk'),
(2, 'Kuda', 'Keldai'),
(2, 'Lembu', 'Kerbau'),
(2, 'Kambing', 'Biri-biri'),
(2, 'Arnab', 'Tikus'),
(2, 'Helang', 'Burung Hantu'),
(2, 'Penyu', 'Kura-kura'),
(2, 'Buaya', 'Biawak'),
(2, 'Lebah', 'Tebuan'),
(2, 'Rama-rama', 'Gegat'),
(2, 'Lipan', 'Gonggok'),
(2, 'Jerapah', 'Unta'),
(2, 'Beruang', 'Panda'),
(2, 'Serigala', 'Rubah');

-- Insert Word Pairs - Warna (20 pairs)
INSERT INTO word_pairs (category_id, majority_word, imposter_word) VALUES
(3, 'Merah', 'Biru'),
(3, 'Hitam', 'Putih'),
(3, 'Hijau', 'Kuning'),
(3, 'Jingga', 'Ungu'),
(3, 'Coklat', 'Kelabu'),
(3, 'Merah Jambu', 'Merah Tua'),
(3, 'Biru Muda', 'Biru Tua'),
(3, 'Hijau Muda', 'Hijau Tua'),
(3, 'Perak', 'Emas'),
(3, 'Merah Maroon', 'Merah Anggur'),
(3, 'Turquoise', 'Cyan'),
(3, 'Lavender', 'Lila'),
(3, 'Krem', 'Ivory'),
(3, 'Salmon', 'Coral'),
(3, 'Mint', 'Hijau Pastel'),
(3, 'Burgundy', 'Merah Wain'),
(3, 'Navy', 'Biru Laut'),
(3, 'Zaitun', 'Hijau Tentera'),
(3, 'Magenta', 'Fuchsia'),
(3, 'Indigo', 'Violet');

-- Insert Word Pairs - Hiburan (20 pairs)
INSERT INTO word_pairs (category_id, majority_word, imposter_word) VALUES
(4, 'Filem', 'Drama'),
(4, 'Pelakon', 'Pelakon Pembantu'),
(4, 'Penyanyi', 'Penari'),
(4, 'Pawagam', 'Teater'),
(4, 'Komedi', 'Jenaka'),
(4, 'Seram', 'Misteri'),
(4, 'Aksi', 'Pengembaraan'),
(4, 'Romantik', 'Cinta'),
(4, 'Animasi', 'Kartun'),
(4, 'Dokumentari', 'Realiti'),
(4, 'Sitkom', 'Komedi Situasi'),
(4, 'Pelakon Lelaki', 'Pelakon Wanita'),
(4, 'Pengarah', 'Penerbit'),
(4, 'Skrip', 'Dialog'),
(4, 'Pentas', 'Layar'),
(4, 'Konsert', 'Persembahan'),
(4, 'Muzik Video', 'Filem Pendek'),
(4, 'Siri', 'Musim'),
(4, 'Episod', 'Bab'),
(4, 'Tayangan Perdana', 'Tayangan Akhir');

-- Insert Word Pairs - Orang Terkenal (20 pairs)
INSERT INTO word_pairs (category_id, majority_word, imposter_word) VALUES
(5, 'Siti Nurhaliza', 'Dato Seri Vida'),
(5, 'P. Ramlee', 'Tan Sri Jins Shamsuddin'),
(5, 'Tunku Abdul Rahman', 'Tun Dr Mahathir'),
(5, 'Yuna', 'Misha Omar'),
(5, 'Nora Danish', 'Neelofa'),
(5, 'Aiman Hakim', 'Alif Satar'),
(5, 'Fazura', 'Lisa Surihani'),
(5, 'Zizan Razak', 'Zul Ariffin'),
(5, 'Fauziah Latiff', 'Jamal Abdillah'),
(5, 'Sheila Majid', 'Amy Search'),
(5, 'Lee Chong Wei', 'Nicol David'),
(5, 'Anwar Ibrahim', 'Najib Razak'),
(5, 'Syed Saddiq', 'Khairy Jamaluddin'),
(5, 'Che Ta', 'Datuk Aliff Syukri'),
(5, 'Aeril Zafrel', 'Remy Ishak'),
(5, 'Sharnaaz Ahmad', 'Fattah Amin'),
(5, 'Hanis Zalikha', 'Nur Fazura'),
(5, 'Eira Syazira', 'Elfira Loy'),
(5, 'Adibah Noor', 'Afdlin Shauki'),
(5, 'Mizz Nina', 'Elizabeth Tan');

-- Insert Word Pairs - Makanan (20 pairs)
INSERT INTO word_pairs (category_id, majority_word, imposter_word) VALUES
(6, 'Nasi Lemak', 'Nasi Goreng'),
(6, 'Roti Canai', 'Roti Telur'),
(6, 'Satay', 'Rendang'),
(6, 'Laksa', 'Mee Rebus'),
(6, 'Cendol', 'Ais Kacang'),
(6, 'Curry Mee', 'Curry Laksa'),
(6, 'Char Kuey Teow', 'Mee Goreng'),
(6, 'Nasi Ayam', 'Nasi Kandar'),
(6, 'Rojak', 'Pasembur'),
(6, 'Kuih Lapis', 'Kuih Talam'),
(6, 'Onde-Onde', 'Kuih Koci'),
(6, 'Apam Balik', 'Kuih Cara'),
(6, 'Lemang', 'Ketupat'),
(6, 'Nasi Kerabu', 'Nasi Dagang'),
(6, 'Assam Pedas', 'Gulai Ikan'),
(6, 'Murtabak', 'Martabak'),
(6, 'Nasi Briyani', 'Nasi Tomato'),
(6, 'Soto', 'Sup Tulang'),
(6, 'Otak-Otak', 'Keropok Lekor'),
(6, 'Kuih Bahulu', 'Kuih Bangkit');

-- Insert Word Pairs - Geografi (20 pairs)
INSERT INTO word_pairs (category_id, majority_word, imposter_word) VALUES
(7, 'Kuala Lumpur', 'Putrajaya'),
(7, 'Pulau Pinang', 'Pulau Langkawi'),
(7, 'Melaka', 'Johor Bahru'),
(7, 'Sabah', 'Sarawak'),
(7, 'Gunung Kinabalu', 'Gunung Tahan'),
(7, 'Sungai Pahang', 'Sungai Rajang'),
(7, 'Laut China Selatan', 'Selat Melaka'),
(7, 'Tasik Kenyir', 'Tasik Chini'),
(7, 'Gua Kelam', 'Gua Tempurung'),
(7, 'Taman Negara', 'Endau Rompin'),
(7, 'Bukit Bintang', 'Bukit Nanas'),
(7, 'Cameron Highlands', 'Genting Highlands'),
(7, 'Terengganu', 'Kelantan'),
(7, 'Kedah', 'Perlis'),
(7, 'Negeri Sembilan', 'Selangor'),
(7, 'Perak', 'Pahang'),
(7, 'Pantai Cenang', 'Pantai Tengah'),
(7, 'Kota Kinabalu', 'Kuching'),
(7, 'Miri', 'Sibu'),
(7, 'Labuan', 'Tawau');

-- Insert Word Pairs - Kesusasteraan (20 pairs)
INSERT INTO word_pairs (category_id, majority_word, imposter_word) VALUES
(8, 'Puisi', 'Sajak'),
(8, 'Novel', 'Cerpen'),
(8, 'Pantun', 'Syair'),
(8, 'Drama', 'Teater'),
(8, 'Pengarang', 'Penulis'),
(8, 'Penyair', 'Novelis'),
(8, 'Buku', 'Manuskrip'),
(8, 'Bab', 'Episod'),
(8, 'Watak', 'Karakter'),
(8, 'Plot', 'Jalan Cerita'),
(8, 'Dialog', 'Monolog'),
(8, 'Prosa', 'Puisi'),
(8, 'Autobiografi', 'Biografi'),
(8, 'Memoir', 'Diari'),
(8, 'Fiksyen', 'Bukan Fiksyen'),
(8, 'Fantasi', 'Sains Fiksyen'),
(8, 'Misteri', 'Thriller'),
(8, 'Romantis', 'Drama'),
(8, 'Antologi', 'Kompilasi'),
(8, 'Nukilan', 'Karya');

-- Insert Word Pairs - Muzik (20 pairs)
INSERT INTO word_pairs (category_id, majority_word, imposter_word) VALUES
(9, 'Gitar', 'Bass'),
(9, 'Piano', 'Keyboard'),
(9, 'Drum', 'Perkusi'),
(9, 'Biola', 'Cello'),
(9, 'Seruling', 'Flute'),
(9, 'Trompet', 'Trombon'),
(9, 'Saxophone', 'Klarinet'),
(9, 'Kompang', 'Rebana'),
(9, 'Gamelan', 'Angklung'),
(9, 'Rock', 'Metal'),
(9, 'Pop', 'Jazz'),
(9, 'Hip Hop', 'Rap'),
(9, 'Ballad', 'Lagu Slow'),
(9, 'Nasyid', 'Zikir'),
(9, 'Keroncong', 'Dangdut'),
(9, 'Penyanyi', 'Vokalis'),
(9, 'Pemuzik', 'Instrumentalis'),
(9, 'Album', 'EP'),
(9, 'Konsert', 'Showcase'),
(9, 'Studio', 'Rakaman');

-- Insert Word Pairs - Sains (20 pairs)
INSERT INTO word_pairs (category_id, majority_word, imposter_word) VALUES
(10, 'Fizik', 'Kimia'),
(10, 'Biologi', 'Botani'),
(10, 'Atom', 'Molekul'),
(10, 'Elektron', 'Proton'),
(10, 'Sel', 'Tisu'),
(10, 'DNA', 'RNA'),
(10, 'Graviti', 'Magnet'),
(10, 'Tenaga', 'Kuasa'),
(10, 'Cahaya', 'Bunyi'),
(10, 'Gas', 'Cecair'),
(10, 'Asid', 'Alkali'),
(10, 'Eksperimen', 'Ujikaji'),
(10, 'Mikroskop', 'Teleskop'),
(10, 'Bakteria', 'Virus'),
(10, 'Protein', 'Karbohidrat'),
(10, 'Oksigen', 'Karbon Dioksida'),
(10, 'Fotosintesis', 'Respirasi'),
(10, 'Ekosistem', 'Habitat'),
(10, 'Evolusi', 'Mutasi'),
(10, 'Teori', 'Hipotesis');

-- Insert Word Pairs - Sukan (20 pairs)
INSERT INTO word_pairs (category_id, majority_word, imposter_word) VALUES
(11, 'Bola Sepak', 'Futsal'),
(11, 'Badminton', 'Skuasy'),
(11, 'Bola Keranjang', 'Bola Baling'),
(11, 'Bola Tampar', 'Bola Sepak Takraw'),
(11, 'Renang', 'Terjun'),
(11, 'Lari', 'Jogging'),
(11, 'Lumba Basikal', 'Basikal Gunung'),
(11, 'Gimnastik', 'Akrobatik'),
(11, 'Silat', 'Taekwondo'),
(11, 'Judo', 'Karate'),
(11, 'Tinju', 'Muay Thai'),
(11, 'Golf', 'Mini Golf'),
(11, 'Tenis', 'Tenis Meja'),
(11, 'Boling', 'Boling Sepuluh Pin'),
(11, 'Hoki', 'Hoki Ais'),
(11, 'Ragbi', 'Ragbi Liga'),
(11, 'Kriket', 'Baseball'),
(11, 'Memanah', 'Menembak'),
(11, 'Angkat Berat', 'Bina Badan'),
(11, 'Maraton', 'Lari Halangan');

-- Insert Word Pairs - Teknologi (20 pairs)
INSERT INTO word_pairs (category_id, majority_word, imposter_word) VALUES
(12, 'Telefon', 'Tablet'),
(12, 'Komputer', 'Laptop'),
(12, 'Internet', 'Wi-Fi'),
(12, 'Email', 'Mesej'),
(12, 'Laman Web', 'Blog'),
(12, 'Aplikasi', 'Software'),
(12, 'Android', 'iOS'),
(12, 'Windows', 'Mac'),
(12, 'Google', 'Bing'),
(12, 'Facebook', 'Instagram'),
(12, 'YouTube', 'TikTok'),
(12, 'WhatsApp', 'Telegram'),
(12, 'Kamera', 'Webcam'),
(12, 'TV', 'Monitor'),
(12, 'Penggodam', 'Pembangun'),
(12, 'Kod', 'Skrip'),
(12, 'Artificial Intelligence', 'Machine Learning'),
(12, 'Cloud', 'Server'),
(12, 'Database', 'Spreadsheet'),
(12, 'Virtual Reality', 'Augmented Reality');