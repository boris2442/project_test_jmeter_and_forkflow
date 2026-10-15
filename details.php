<?php
session_start();
require_once __DIR__ . '/config/database.php';

if ($pdo === null) {
    http_response_code(503);
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>MiniShop</title></head><body><div style="padding: 40px; font-family: sans-serif;"><h1>Base de données indisponible</h1><p>Importez le fichier <strong>database.sql</strong> dans phpMyAdmin, puis vérifiez les paramètres de connexion dans <strong>config/database.php</strong>.</p></div></body></html>';
    exit;
}

$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($productId === false || $productId <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, nom, description, prix, stock, image, actif FROM produits WHERE id = :id AND actif = 1 LIMIT 1');
$stmt->execute([':id' => $productId]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Détails - ' . $product['nom'];
include __DIR__ . '/includes/header.php';
?>

<div class="container">
    <section class="page-header">
        <h1 class="page-heading">Détails du produit</h1>
    </section>

    <div class="details-layout">
        <div class="product-visual">
            <img src="<?php echo htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['nom'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="product-detail-card">
            <div class="detail-row">
                <span class="label">Produit</span>
                <span class="stock-tag <?php echo ((int) $product['stock'] > 0) ? 'in-stock' : 'out-of-stock'; ?>">
                    <?php echo ((int) $product['stock'] > 0) ? 'En stock' : 'Rupture'; ?>
                </span>
            </div>

            <h2><?php echo htmlspecialchars($product['nom'], ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="detail-price"><?php echo number_format((float) $product['prix'], 0, ',', ' '); ?> FCFA</div>

            <p class="detail-description"><?php echo nl2br(htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8')); ?></p>

            <div class="detail-row">
                <span class="label">Stock disponible</span>
                <strong><?php echo (int) $product['stock']; ?></strong>
            </div>

            <div class="detail-quantity">
                <label for="product-quantity" class="label">Quantité</label>
                <input id="product-quantity" type="number" min="1" max="<?php echo (int) $product['stock']; ?>" value="1">
            </div>

            <div class="product-actions">
                <button id="detail-add-to-cart" class="primary-btn" type="button" data-product-id="<?php echo (int) $product['id']; ?>">Ajouter au panier</button>
                <a href="index.php" class="secondary-btn">Retour à la boutique</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
