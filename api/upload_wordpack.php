<?php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

if (!isset($_FILES['wordpack'])) {
    jsonResponse(['error' => 'Fail tidak dijumpai'], 400);
}

$file = $_FILES['wordpack'];

// Validate file
if ($file['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['error' => 'Gagal memuat naik fail'], 400);
}

if ($file['size'] > MAX_UPLOAD_SIZE) {
    jsonResponse(['error' => 'Saiz fail terlalu besar (maksimum 5MB)'], 400);
}

$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($fileExtension, ALLOWED_EXTENSIONS)) {
    jsonResponse(['error' => 'Hanya fail CSV dibenarkan'], 400);
}

try {
    // Read CSV file
    $handle = fopen($file['tmp_name'], 'r');
    
    // Read header
    $header = fgetcsv($handle);
    if (!$header || count($header) < 2) {
        jsonResponse(['error' => 'Format CSV tidak sah. Header diperlukan: majoriti,imposter'], 400);
    }
    
    // Normalize headers
    $header = array_map('strtolower', array_map('trim', $header));
    $majoritiIndex = array_search('majoriti', $header);
    $imposterIndex = array_search('imposter', $header);
    
    if ($majoritiIndex === false || $imposterIndex === false) {
        jsonResponse(['error' => 'Header CSV mesti mengandungi "majoriti" dan "imposter"'], 400);
    }
    
    // Read word pairs
    $wordPairs = [];
    $lineNumber = 1;
    while (($row = fgetcsv($handle)) !== false) {
        $lineNumber++;
        
        if (count($row) < 2) {
            continue; // Skip empty lines
        }
        
        $majoritiWord = trim($row[$majoritiIndex]);
        $imposterWord = trim($row[$imposterIndex]);
        
        if (empty($majoritiWord) || empty($imposterWord)) {
            continue; // Skip invalid pairs
        }
        
        $wordPairs[] = [
            'majoriti' => $majoritiWord,
            'imposter' => $imposterWord
        ];
    }
    
    fclose($handle);
    
    if (count($wordPairs) === 0) {
        jsonResponse(['error' => 'Tiada pasangan kata yang sah dijumpai dalam fail'], 400);
    }
    
    // Create unique filename
    $filename = uniqid('wordpack_') . '.json';
    $filepath = UPLOAD_DIR . $filename;
    
    // Ensure upload directory exists
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    
    // Save as JSON
    file_put_contents($filepath, json_encode($wordPairs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    jsonResponse([
        'success' => true,
        'filename' => $filename,
        'word_count' => count($wordPairs),
        'message' => count($wordPairs) . ' pasangan kata berjaya dimuat naik'
    ]);
    
} catch (Exception $e) {
    jsonResponse(['error' => 'Gagal memproses fail: ' . $e->getMessage()], 500);
}
?>