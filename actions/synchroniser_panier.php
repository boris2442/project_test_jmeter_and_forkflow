<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
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

$requestedCart = json_decode((string) ($_POST['cart'] ?? ''), true);
if (!is_array($requestedCart)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Le contenu du panier est invalide.']);
    exit;
}

$validatedCart = [];
$productsById = [];
$productStatement = $pdo->prepare('SELECT id, nom, prix, stock, image, actif FROM produits WHERE id = :id LIMIT 1');

foreach ($requestedCart as $productId => $item) {
    $productId = filter_var($productId, FILTER_VALIDATE_INT);
    $quantity = is_array($item) ? filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT) : false;

    if ($productId === false || $productId <= 0 || $quantity === false || $quantity <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Un article du panier est invalide.']);
        exit;
    }

    $productStatement->execute([':id' => $productId]);
    $product = $productStatement->fetch();

    if (!$product || (int) $product['actif'] !== 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Un produit du panier n\'est plus disponible.']);
        exit;
    }

    if ($quantity > (int) $product['stock']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'La quantité demandée dépasse le stock disponible pour un produit.']);
        exit;
    }

    $validatedCart[$productId] = $quantity;
    $productsById[$productId] = $product;
}

$_SESSION['cart'] = $validatedCart;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (empty($_SESSION['order_nonce'])) {
    $_SESSION['order_nonce'] = bin2hex(random_bytes(16));
}

$cart = [];
foreach ($validatedCart as $productId => $quantity) {
    $product = $productsById[$productId];
    $cart[(string) $productId] = [
        'id' => (int) $product['id'],
        'name' => $product['nom'],
        'price' => (float) $product['prix'],
        'image' => $product['image'],
        'stock' => (int) $product['stock'],
        'quantity' => $quantity,
    ];
}

echo json_encode([
    'success' => true,
    'message' => 'Panier synchronisé.',
    'cart' => $cart,
    'csrf_token' => $_SESSION['csrf_token'],
    'order_token' => $_SESSION['order_nonce'],
]);
