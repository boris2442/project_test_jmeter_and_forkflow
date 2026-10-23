# MiniShop

MiniShop est une boutique e-commerce pédagogique développée en PHP natif, JavaScript vanilla et MySQL. Elle permet de consulter les produits, ajouter des articles au panier, modifier leur quantité, puis passer une commande sans créer de compte.

## Structure du projet

- `index.php` : page d'accueil et recherche.
- `details.php` : fiche produit.
- `panier.php` : panier et confirmation de commande.
- `config/database.php` : paramètres de connexion MySQL (PDO).
- `actions/*.php` : endpoints du panier et de commande.
- `includes/header.php` : en-tête partagé.
- `includes/footer.php` : pied de page partagé.
- `assets/css/style.css` : design responsif.
- `assets/js/app.js` : interactions JavaScript.
- `sw.js` et `offline.html` : cache applicatif et page de secours hors ligne.
- `database.sql` : schéma MySQL et données de démonstration.

## Prérequis

- XAMPP, WAMP ou Laragon avec Apache + MySQL.
- PHP 8.1+
- MySQL 5.7+ ou MariaDB.

## Installation sous Laragon

1. Ouvrez Laragon.
2. Placez ce projet dans le dossier `www` de Laragon, par exemple :
   `C:\laragon\www\minishop`
3. Démarrez Apache et MySQL dans Laragon.
4. Ouvrez phpMyAdmin.
5. Importez le fichier `database.sql` pour créer la base `minishop` et les produits de démonstration.
6. Vérifiez que les paramètres de connexion dans `config/database.php` correspondent bien à votre environnement local :
   - hôte : `127.0.0.1`
   - port : `3306`
   - base : `minishop`
   - utilisateur : `root`
   - mot de passe : vide
7. Dans le navigateur, ouvrez :
   `http://localhost/minishop/`

## Vérification d'une commande

1. Ajoutez des produits au panier.
2. Ouvrez la page `panier.php`.
3. Renseignez un nom et un numéro de téléphone.
4. Cliquez sur « Confirmer la commande ».
5. Vérifiez dans phpMyAdmin que la commande est bien enregistrée dans `commandes` et les lignes dans `lignes_commande`.

## Points de sécurité

- Les requêtes SQL utilisent PDO avec préparations.
- Les données du panier sont conservées côté serveur dans la session PHP.
- Les prix et stocks sont revalidés depuis la base de données avant chaque commande.
- Les transactions MySQL protègent la cohérence des commandes.
- Les actions modifiant le panier ou les commandes nécessitent un token CSRF.

## Utilisation hors ligne

Ouvrez le site sous `http://localhost/...` ou en HTTPS : les Service Workers ne fonctionnent pas depuis une URL `file://`. Après un premier chargement connecté, l'application met en cache ses ressources, les images du catalogue et les pages d'accueil et du panier. Si une fiche produit n'a pas été mise en cache, son ouverture hors ligne renvoie vers le catalogue disponible hors ligne.

Les ajouts, changements de quantité et suppressions sont conservés dans le navigateur sans connexion. Quand le réseau revient, le panier complet est envoyé au serveur puis validé avec les stocks et les produits disponibles. Une erreur de synchronisation est affichée et le panier local est conservé pour permettre une nouvelle tentative.

La création de commande nécessite toujours une connexion : le prix et le stock sont revérifiés côté serveur avant validation. Les données hors ligne sont propres au navigateur et à son stockage local ; elles ne doivent pas être considérées comme une commande confirmée.

Si l'application a déjà été ouverte avant l'installation de cette version, rechargez une fois la page avec une connexion afin d'activer le nouveau Service Worker et de mettre à jour les ressources en cache. Une première visite sans réseau ne peut pas installer l'application ni télécharger le catalogue.
# project_test_jmeter_and_forkflow
