<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $quantity) {
        $cartCount += (int) $quantity;
    }
}

$searchValue = '';
if (!empty($_GET['q'])) {
    $searchValue = trim((string) $_GET['q']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') : 'MiniShop'; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <a href="index.php" class="brand" aria-label="Accueil MiniShop">MiniShop</a>

            <nav class="main-nav" aria-label="Navigation principale">
                <a href="index.php">Accueil</a>
                <a href="panier.php">Panier</a>
            </nav>

            <form class="search-form" method="get" action="index.php" aria-label="Recherche de produits">
                <input type="search" name="q" value="<?php echo htmlspecialchars($searchValue, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Rechercher un produit" aria-label="Rechercher un produit">
                <button type="submit">Rechercher</button>
            </form>

            <a href="panier.php" class="cart-link" aria-label="Voir le panier">
                <span class="cart-icon" aria-hidden="true">🛒</span>
                <span>Panier</span>
                <span class="cart-badge" id="cart-count"><?php echo (int) $cartCount; ?></span>
            </a>
        </div>
    </header>

    <div id="flash-message" class="flash-message" aria-live="polite" style="display: none;"></div>

    <main class="page-shell">
