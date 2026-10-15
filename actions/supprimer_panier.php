<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisé.']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

if ($pdo === null) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'La base de données n\'est pas disponible pour le moment.']);
    exit;
}

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string) $token)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Le jeton de sécurité est invalide.']);
    exit;
}

$productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

if ($productId === false || $productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Identifiant produit invalide.']);
    exit;
}

if (!empty($_SESSION['cart']) && is_array($_SESSION['cart']) && isset($_SESSION['cart'][$productId])) {
    unset($_SESSION['cart'][$productId]);
    echo json_encode(['success' => true, 'message' => 'Produit supprimé du panier.']);
    exit;
}

http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Produit absent du panier.']);
