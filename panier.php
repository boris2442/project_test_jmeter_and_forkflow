<?php
session_start();
require_once __DIR__ . '/config/database.php';

if ($pdo === null) {
    http_response_code(503);
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>MiniShop</title></head><body><div style="padding: 40px; font-family: sans-serif;"><h1>Base de données indisponible</h1><p>Importez le fichier <strong>database.sql</strong> dans phpMyAdmin, puis vérifiez les paramètres de connexion dans <strong>config/database.php</strong>.</p></div></body></html>';
    exit;
}

$pageTitle = 'Panier - MiniShop';

if (empty($_SESSION['order_nonce'])) {
    $_SESSION['order_nonce'] = bin2hex(random_bytes(16));
}

$cartItems = [];
$totalAmount = 0.0;
$totalArticles = 0;

if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $productId => $quantity) {
        $productId = (int) $productId;
        $quantity = (int) $quantity;

        if ($productId <= 0 || $quantity <= 0) {
            continue;
        }

        $stmt = $pdo->prepare('SELECT id, nom, prix, stock, image, actif FROM produits WHERE id = :id AND actif = 1 LIMIT 1');
        $stmt->execute([':id' => $productId]);
        $product = $stmt->fetch();

        if (!$product) {
            continue;
        }

        $unitPrice = (float) $product['prix'];
        $lineTotal = $unitPrice * $quantity;
        $totalAmount += $lineTotal;
        $totalArticles += $quantity;

        $cartItems[] = [
            'id' => $productId,
            'nom' => $product['nom'],
            'image' => $product['image'],
            'prix_unitaire' => $unitPrice,
            'quantite' => $quantity,
            'sous_total' => $lineTotal,
            'stock' => (int) $product['stock'],
        ];
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="container">
    <?php if (!empty($_SESSION['flash_message'])) : ?>
        <div class="flash-message success"><?php echo htmlspecialchars($_SESSION['flash_message'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <section class="page-header">
        <h1 class="page-heading">Mon panier</h1>
    </section>

    <?php if (empty($cartItems)) : ?>
        <div class="empty-state" aria-live="polite">
            <h2>Votre panier est vide</h2>
            <p>Ajoutez des produits pour commencer votre commande.</p>
            <a class="primary-btn" href="index.php">Continuer mes achats</a>
        </div>
    <?php else : ?>
        <div class="cart-layout">
            <div class="cart-items">
                <?php foreach ($cartItems as $item) : ?>
                    <article class="cart-item">
                        <img src="<?php echo htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['nom'], ENT_QUOTES, 'UTF-8'); ?>">

                        <div>
                            <h3><?php echo htmlspecialchars($item['nom'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <div class="price-small">Prix unitaire : <?php echo number_format($item['prix_unitaire'], 0, ',', ' '); ?> FCFA</div>

                            <div class="cart-controls">
                                <button class="cart-qty-button" type="button" data-cart-action="decrease" data-product-id="<?php echo (int) $item['id']; ?>" aria-label="Diminuer la quantité">−</button>
                                <span class="qty-value"><?php echo (int) $item['quantite']; ?></span>
                                <button class="cart-qty-button" type="button" data-cart-action="increase" data-product-id="<?php echo (int) $item['id']; ?>" aria-label="Augmenter la quantité">+</button>
                            </div>
                        </div>

                        <div>
                            <div class="price"><?php echo number_format($item['sous_total'], 0, ',', ' '); ?> FCFA</div>
                            <button class="delete-btn" type="button" data-cart-action="delete" data-product-id="<?php echo (int) $item['id']; ?>">Supprimer</button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <aside class="cart-summary">
                <h2>Résumé</h2>

                <div class="summary-row">
                    <span>Nombre total d'articles</span>
                    <strong><?php echo (int) $totalArticles; ?></strong>
                </div>

                <div class="summary-row total">
                    <span>Total</span>
                    <span><?php echo number_format($totalAmount, 0, ',', ' '); ?> FCFA</span>
                </div>

                <a class="secondary-btn" href="index.php">Continuer mes achats</a>

                <form class="order-form" id="order-form" action="actions/commander.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="order_token" value="<?php echo htmlspecialchars($_SESSION['order_nonce'], ENT_QUOTES, 'UTF-8'); ?>">

                    <label>
                        Nom du client
                        <input type="text" name="nom_client" placeholder="Votre nom complet" required>
                    </label>

                    <label>
                        Numéro de téléphone
                        <input type="tel" name="telephone_client" placeholder="Ex. 690123456" required>
                    </label>

                    <button class="primary-btn" type="submit">Confirmer la commande</button>
                </form>
            </aside>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
