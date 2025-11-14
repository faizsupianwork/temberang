<?php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

try {
    $db = getDBConnection();
    
    $stmt = $db->prepare("SELECT id, name, name_ms FROM categories ORDER BY id");
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'categories' => $categories
    ]);
    
} catch (PDOException $e) {
    jsonResponse(['error' => 'Gagal mendapatkan kategori: ' . $e->getMessage()], 500);
}
?>