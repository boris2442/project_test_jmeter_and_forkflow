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
$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

if ($productId === false || $productId <= 0 || $quantity === false || $quantity <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Les informations du produit sont invalides.']);
    exit;
}

if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$stmt = $pdo->prepare('SELECT id, nom, prix, stock, actif FROM produits WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $productId]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Produit introuvable.']);
    exit;
}

if ((int) $product['actif'] !== 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ce produit n\'est plus disponible.']);
    exit;
}

$currentQuantity = isset($_SESSION['cart'][$productId]) ? (int) $_SESSION['cart'][$productId] : 0;
$newTotal = $currentQuantity + $quantity;

if ($newTotal > (int) $product['stock']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La quantité demandée dépasse le stock disponible.']);
    exit;
}

$_SESSION['cart'][$productId] = $newTotal;

$totalItems = 0;
foreach ($_SESSION['cart'] as $itemQuantity) {
    $totalItems += (int) $itemQuantity;
}

echo json_encode([
    'success' => true,
    'message' => 'Produit ajouté au panier avec succès.',
    'count' => $totalItems,
]);
