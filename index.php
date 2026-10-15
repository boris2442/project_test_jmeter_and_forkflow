<?php
session_start();
require_once __DIR__ . '/config/database.php';

if ($pdo === null) {
    http_response_code(503);
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>MiniShop</title></head><body><div style="padding: 40px; font-family: sans-serif;"><h1>Base de données indisponible</h1><p>Importez le fichier <strong>database.sql</strong> dans phpMyAdmin, puis vérifiez les paramètres de connexion dans <strong>config/database.php</strong>.</p></div></body></html>';
    exit;
}

$pageTitle = 'Accueil - MiniShop';
$searchTerm = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$params = [];
$sql = 'SELECT id, nom, description, prix, stock, image, actif FROM produits WHERE actif = 1';

if ($searchTerm !== '') {
    $sql .= ' AND nom LIKE :search';
    $params[':search'] = '%' . $searchTerm . '%';
}

$sql .= ' ORDER BY created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
// var_dump($products);

include __DIR__ . '/includes/header.php';
?>

<div class="container">
    <?php if (!empty($_SESSION['flash_message'])) : ?>
        <div class="flash-message success"><?php echo htmlspecialchars($_SESSION['flash_message'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <section class="page-header">
        <h1 class="page-heading">Boutique MiniShop</h1>
        <p class="page-subtitle">Des produits utiles, modernes et faciles à commander.</p>
    </section>

    <?php if ($searchTerm !== '' && empty($products)) : ?>
        <div class="empty-state" aria-live="polite">
            <h2>Aucun produit trouvé</h2>
            <p>Aucun résultat pour <strong><?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?></strong>. Essayez un autre mot-clé.</p>
        </div>
    <?php endif; ?>

    <div class="products-grid" aria-live="polite">
        <?php foreach ($products as $product) : ?>
            <article class="product-card">
                <a  href="details.php?id=<?php echo (int) $product['id']; ?>">
                <img class="product-image" src="<?php echo htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['nom'], ENT_QUOTES, 'UTF-8'); ?>">
</a>
                <div class="product-body">
                    <div class="product-info">
                       
                             <h2 class="product-name">  <a  href="details.php?id=<?php echo (int) $product['id']; ?>"><?php echo htmlspecialchars($product['nom'], ENT_QUOTES, 'UTF-8'); ?>
                                  </a></h2>
                      
                        <span class="price"><?php echo number_format((float) $product['prix'], 0, ',', ' '); ?> FCFA</span>
                    </div>

                    <p class="product-description"><?php echo nl2br(htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8')); ?></p>

                    <div class="product-meta">
                        <span class="stock-tag <?php echo ((int) $product['stock'] > 0) ? 'in-stock' : 'out-of-stock'; ?>">
                            <?php echo ((int) $product['stock'] > 0) ? 'En stock' : 'Rupture'; ?>
                        </span>
                        <span><?php echo (int) $product['stock']; ?> disponible(s)</span>
                    </div>

                    <div class="product-actions">
                        <a class="secondary-btn" href="details.php?id=<?php echo (int) $product['id']; ?>">Voir les détails</a>
                        <button class="primary-btn" type="button" data-add-to-cart data-product-id="<?php echo (int) $product['id']; ?>" data-quantity="1">
                            Ajouter au panier
                        </button>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
