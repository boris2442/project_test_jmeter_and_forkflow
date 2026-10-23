<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

if ($pdo === null) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'La base de données n\'est pas disponible pour le moment.']);
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (empty($_SESSION['order_nonce'])) {
    $_SESSION['order_nonce'] = bin2hex(random_bytes(16));
}

$cart = [];
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $statement = $pdo->prepare('SELECT id, nom, prix, stock, image FROM produits WHERE id = :id AND actif = 1 LIMIT 1');

    foreach ($_SESSION['cart'] as $productId => $quantity) {
        $productId = filter_var($productId, FILTER_VALIDATE_INT);
        $quantity = filter_var($quantity, FILTER_VALIDATE_INT);

        if ($productId === false || $productId <= 0 || $quantity === false || $quantity <= 0) {
            continue;
        }

        $statement->execute([':id' => $productId]);
        $product = $statement->fetch();

        if (!$product) {
            continue;
        }

        $cart[(string) $productId] = [
            'id' => $productId,
            'name' => $product['nom'],
            'price' => (float) $product['prix'],
            'image' => $product['image'],
            'stock' => (int) $product['stock'],
            'quantity' => $quantity,
        ];
    }
}

echo json_encode([
    'success' => true,
    'cart' => $cart,
    'csrf_token' => $_SESSION['csrf_token'],
    'order_token' => $_SESSION['order_nonce'],
]);
