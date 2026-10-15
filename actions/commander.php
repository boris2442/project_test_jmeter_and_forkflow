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

$orderToken = trim((string) ($_POST['order_token'] ?? ''));
if ($orderToken === '' || empty($_SESSION['order_nonce']) || !hash_equals($_SESSION['order_nonce'], $orderToken)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'La commande a déjà été traitée ou est invalide.']);
    exit;
}

if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Votre panier est vide.']);
    exit;
}

$nomClient = trim((string) ($_POST['nom_client'] ?? ''));
$telephoneClient = trim((string) ($_POST['telephone_client'] ?? ''));

if ($nomClient === '' || !preg_match('/^[A-Za-zÀ-ÖØ-öø-ÿ\s\'-]{2,80}$/u', $nomClient)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Le nom du client est invalide.']);
    exit;
}

if ($telephoneClient === '' || !preg_match('/^[0-9+\s().-]{8,20}$/', $telephoneClient)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Le numéro de téléphone est invalide.']);
    exit;
}

$cartItems = [];
$total = 0.0;

try {
    $pdo->beginTransaction();

    foreach ($_SESSION['cart'] as $productId => $quantity) {
        $productId = (int) $productId;
        $quantity = (int) $quantity;

        if ($productId <= 0 || $quantity <= 0) {
            continue;
        }

        $stmt = $pdo->prepare('SELECT id, nom, prix, stock, actif FROM produits WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute([':id' => $productId]);
        $product = $stmt->fetch();

        if (!$product || (int) $product['actif'] !== 1) {
            throw new Exception('Un produit du panier n\'est plus disponible.');
        }

        if ($quantity > (int) $product['stock']) {
            throw new Exception('La quantité demandée dépasse le stock disponible pour un produit.');
        }

        $unitPrice = (float) $product['prix'];
        $lineTotal = $unitPrice * $quantity;
        $total += $lineTotal;

        $cartItems[] = [
            'id' => $productId,
            'nom' => $product['nom'],
            'prix' => $unitPrice,
            'quantite' => $quantity,
            'sous_total' => $lineTotal,
        ];
    }

    if (empty($cartItems)) {
        throw new Exception('Votre panier est vide.');
    }

    $reference = 'MS-' . strtoupper(bin2hex(random_bytes(4))) . '-' . date('ymdHis');

    $insertOrder = $pdo->prepare('INSERT INTO commandes (reference, nom_client, telephone_client, total, statut, created_at) VALUES (:reference, :nom_client, :telephone_client, :total, :statut, NOW())');
    $insertOrder->execute([
        ':reference' => $reference,
        ':nom_client' => $nomClient,
        ':telephone_client' => $telephoneClient,
        ':total' => number_format($total, 2, '.', ''),
        ':statut' => 'en_attente',
    ]);

    $commandId = (int) $pdo->lastInsertId();

    foreach ($cartItems as $item) {
        $insertLine = $pdo->prepare('INSERT INTO lignes_commande (commande_id, produit_id, nom_produit, prix_unitaire, quantite, sous_total) VALUES (:commande_id, :produit_id, :nom_produit, :prix_unitaire, :quantite, :sous_total)');
        $insertLine->execute([
            ':commande_id' => $commandId,
            ':produit_id' => $item['id'],
            ':nom_produit' => $item['nom'],
            ':prix_unitaire' => number_format($item['prix'], 2, '.', ''),
            ':quantite' => $item['quantite'],
            ':sous_total' => number_format($item['sous_total'], 2, '.', ''),
        ]);

        $updateStock = $pdo->prepare('UPDATE produits SET stock = stock - :quantite WHERE id = :id');
        $updateStock->execute([
            ':quantite' => $item['quantite'],
            ':id' => $item['id'],
        ]);
    }

    $pdo->commit();
    unset($_SESSION['cart']);
    unset($_SESSION['order_nonce']);

    echo json_encode([
        'success' => true,
        'message' => 'Commande enregistrée avec succès.',
        'reference' => $reference,
    ]);
    exit;
} catch (Exception $exception) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
    exit;
}
