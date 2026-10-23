let csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
let orderToken = '';
let cartReady = Promise.resolve();
let syncInProgress = null;

const CART_STORAGE_KEY = 'minishop_cart';
const CART_PENDING_KEY = 'minishop_cart_pending';

function showFlash(message, type) {
    const messageBox = document.getElementById('flash-message');

    if (!messageBox) {
        window.alert(message);
        return;
    }

    messageBox.className = 'flash-message ' + type;
    messageBox.textContent = message;
    messageBox.style.display = 'block';
}

function getLocalCart() {
    try {
        const storedCart = JSON.parse(localStorage.getItem(CART_STORAGE_KEY) || '{}');
        const cart = {};

        if (!storedCart || typeof storedCart !== 'object' || Array.isArray(storedCart)) {
            return cart;
        }

        Object.keys(storedCart).forEach(function(key) {
            const item = storedCart[key];
            const quantity = Number(item && typeof item === 'object' ? item.quantity : item);
            const id = Number(item && typeof item === 'object' ? item.id : key);

            if (!Number.isInteger(id) || id <= 0 || !Number.isInteger(quantity) || quantity <= 0) {
                return;
            }

            cart[String(id)] = {
                id: id,
                name: item && typeof item === 'object' ? String(item.name || 'Produit') : 'Produit #' + id,
                price: item && typeof item === 'object' ? Number(item.price) || 0 : 0,
                image: item && typeof item === 'object' ? String(item.image || '') : '',
                stock: item && typeof item === 'object' ? Number(item.stock) || 0 : 0,
                quantity: quantity
            };
        });

        return cart;
    } catch (error) {
        console.error('Lecture du panier local impossible.', error);
        return {};
    }
}

function saveLocalCart(cart) {
    localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(cart));
}

function isCartPending() {
    return localStorage.getItem(CART_PENDING_KEY) === 'true';
}

function setCartPending(pending) {
    if (pending) {
        localStorage.setItem(CART_PENDING_KEY, 'true');
    } else {
        localStorage.removeItem(CART_PENDING_KEY);
    }
}

function isNetworkError(error) {
    return error instanceof TypeError;
}

function cartItemCount(cart) {
    return Object.values(cart).reduce(function(total, item) {
        return total + Number(item.quantity || 0);
    }, 0);
}

function updateCartBadge(cart) {
    const counter = document.getElementById('cart-count');

    if (counter) {
        counter.textContent = String(cartItemCount(cart || getLocalCart()));
    }
}

async function readJsonResponse(response) {
    let data;

    try {
        data = await response.json();
    } catch (error) {
        throw new Error('Le serveur a renvoyé une réponse illisible.');
    }

    if (!response.ok || !data.success) {
        throw new Error(data.message || 'La requête a échoué.');
    }

    return data;
}

function applySecurityState(data) {
    if (data.csrf_token) {
        csrfToken = data.csrf_token;
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        if (tokenMeta) {
            tokenMeta.setAttribute('content', csrfToken);
        }
    }

    if (data.order_token) {
        orderToken = data.order_token;
    }
}

function normalizeServerCart(cart) {
    const normalized = {};

    Object.keys(cart || {}).forEach(function(key) {
        const item = cart[key];
        const id = Number(item.id || key);
        const quantity = Number(item.quantity);

        if (!Number.isInteger(id) || id <= 0 || !Number.isInteger(quantity) || quantity <= 0) {
            return;
        }

        normalized[String(id)] = {
            id: id,
            name: String(item.name || 'Produit'),
            price: Number(item.price) || 0,
            image: String(item.image || ''),
            stock: Number(item.stock) || 0,
            quantity: quantity
        };
    });

    return normalized;
}

async function fetchServerCart() {
    const data = await fetchServerState();

    const cart = normalizeServerCart(data.cart);
    saveLocalCart(cart);
    setCartPending(false);
    return cart;
}

async function fetchServerState() {
    const response = await fetch('actions/etat_panier.php', {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
        cache: 'no-store'
    });
    const data = await readJsonResponse(response);

    applySecurityState(data);
    return data;
}

async function syncLocalCart() {
    if (syncInProgress) {
        return syncInProgress;
    }

    syncInProgress = (async function() {
        await fetchServerState();

        const formData = new URLSearchParams();
        formData.set('csrf_token', csrfToken);
        formData.set('cart', JSON.stringify(getLocalCart()));

        const response = await fetch('actions/synchroniser_panier.php', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-CSRF-Token': csrfToken
            },
            body: formData.toString()
        });
        const data = await readJsonResponse(response);
        const cart = normalizeServerCart(data.cart);

        applySecurityState(data);
        saveLocalCart(cart);
        setCartPending(false);
        updateCartBadge(cart);
        renderCart(cart);
        return cart;
    })();

    try {
        return await syncInProgress;
    } finally {
        syncInProgress = null;
    }
}

async function initializeCart() {
    const cart = getLocalCart();
    updateCartBadge(cart);
    renderCart(cart);

    if (!navigator.onLine) {
        return cart;
    }

    try {
        const currentCart = isCartPending()
            ? await syncLocalCart()
            : await fetchServerCart();
        renderCart(currentCart);
        return currentCart;
    } catch (error) {
        if (isCartPending()) {
            if (isNetworkError(error)) {
                showFlash('Le serveur est injoignable. Votre panier reste enregistré sur cet appareil et sera synchronisé dès que possible.', 'success');
            } else {
                console.error('Initialisation du panier en ligne impossible.', error);
                showFlash('La synchronisation du panier a échoué : ' + error.message, 'error');
            }
        }
        return getLocalCart();
    }
}

function formatPrice(price) {
    return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(price) + ' FCFA';
}

function createElement(tagName, className, text) {
    const element = document.createElement(tagName);

    if (className) {
        element.className = className;
    }

    if (text !== undefined) {
        element.textContent = text;
    }

    return element;
}

function createCartAction(label, action, productId, className, ariaLabel) {
    const button = createElement('button', className, label);
    button.type = 'button';
    button.dataset.cartAction = action;
    button.dataset.productId = String(productId);

    if (ariaLabel) {
        button.setAttribute('aria-label', ariaLabel);
    }

    return button;
}

function renderCart(cart) {
    const cartPage = document.querySelector('[data-cart-page]');
    const content = document.getElementById('cart-page-content');

    updateCartBadge(cart);

    if (!cartPage || !content) {
        return;
    }

    if (orderToken) {
        cartPage.dataset.orderToken = orderToken;
    }

    content.replaceChildren();
    const items = Object.values(cart);

    if (items.length === 0) {
        const empty = createElement('div', 'empty-state');
        empty.setAttribute('aria-live', 'polite');
        empty.append(
            createElement('h2', '', 'Votre panier est vide'),
            createElement('p', '', 'Ajoutez des produits pour commencer votre commande.')
        );
        const continueLink = createElement('a', 'primary-btn', 'Continuer mes achats');
        continueLink.href = 'index.php';
        empty.append(continueLink);
        content.append(empty);
        return;
    }

    const layout = createElement('div', 'cart-layout');
    const cartItems = createElement('div', 'cart-items');
    let totalQuantity = 0;
    let totalAmount = 0;

    items.forEach(function(item) {
        totalQuantity += item.quantity;
        totalAmount += item.price * item.quantity;

        const article = createElement('article', 'cart-item');
        const image = createElement('img');
        image.src = item.image;
        image.alt = item.name;
        article.append(image);

        const details = createElement('div');
        details.append(
            createElement('h3', '', item.name),
            createElement('div', 'price-small', 'Prix unitaire : ' + formatPrice(item.price))
        );
        const controls = createElement('div', 'cart-controls');
        controls.append(
            createCartAction('−', 'decrease', item.id, 'cart-qty-button', 'Diminuer la quantité'),
            createElement('span', 'qty-value', String(item.quantity)),
            createCartAction('+', 'increase', item.id, 'cart-qty-button', 'Augmenter la quantité')
        );
        const increaseButton = controls.querySelector('[data-cart-action="increase"]');
        if (item.quantity >= item.stock) {
            increaseButton.disabled = true;
        }
        details.append(controls);
        article.append(details);

        const itemTotal = createElement('div');
        itemTotal.append(
            createElement('div', 'price', formatPrice(item.price * item.quantity)),
            createCartAction('Supprimer', 'delete', item.id, 'delete-btn')
        );
        article.append(itemTotal);
        cartItems.append(article);
    });

    const summary = createElement('aside', 'cart-summary');
    summary.append(createElement('h2', '', 'Résumé'));

    const quantityRow = createElement('div', 'summary-row');
    quantityRow.append(
        createElement('span', '', "Nombre total d'articles"),
        createElement('strong', '', String(totalQuantity))
    );

    const totalRow = createElement('div', 'summary-row total');
    totalRow.append(
        createElement('span', '', 'Total'),
        createElement('span', '', formatPrice(totalAmount))
    );

    const continueLink = createElement('a', 'secondary-btn', 'Continuer mes achats');
    continueLink.href = 'index.php';
    summary.append(quantityRow, totalRow, continueLink);

    if (navigator.onLine && orderToken) {
        const form = createElement('form', 'order-form');
        form.id = 'order-form';
        form.action = 'actions/commander.php';
        form.method = 'post';

        const csrfInput = createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = csrfToken;

        const orderInput = createElement('input');
        orderInput.type = 'hidden';
        orderInput.name = 'order_token';
        orderInput.value = orderToken;

        const nameLabel = createElement('label', '', 'Nom du client');
        const nameInput = createElement('input');
        nameInput.type = 'text';
        nameInput.name = 'nom_client';
        nameInput.placeholder = 'Votre nom complet';
        nameInput.required = true;
        nameLabel.append(nameInput);

        const phoneLabel = createElement('label', '', 'Numéro de téléphone');
        const phoneInput = createElement('input');
        phoneInput.type = 'tel';
        phoneInput.name = 'telephone_client';
        phoneInput.placeholder = 'Ex. 690123456';
        phoneInput.required = true;
        phoneLabel.append(phoneInput);

        const submit = createElement('button', 'primary-btn', 'Confirmer la commande');
        submit.type = 'submit';
        form.append(csrfInput, orderInput, nameLabel, phoneLabel, submit);
        summary.append(form);
    } else {
        summary.append(createElement('p', 'price-small', 'La commande sera disponible avec une connexion Internet.'));
    }

    layout.append(cartItems, summary);
    content.append(layout);
}

function getProductData(button) {
    return {
        id: Number(button.dataset.productId),
        name: button.dataset.productName || 'Produit',
        price: Number(button.dataset.productPrice || 0),
        image: button.dataset.productImage || '',
        stock: Number(button.dataset.productStock || 0)
    };
}

async function saveCartMutation(cart, message) {
    saveLocalCart(cart);
    setCartPending(true);
    renderCart(cart);

    if (!navigator.onLine) {
        showFlash(message + ' Enregistré sur cet appareil et synchronisé au retour du réseau.', 'success');
        return;
    }

    try {
        await syncLocalCart();
        showFlash(message, 'success');
    } catch (error) {
        if (isNetworkError(error)) {
            showFlash(message + ' Enregistré sur cet appareil ; synchronisation dès le retour du réseau.', 'success');
            return;
        }

        console.error('Synchronisation du panier impossible.', error);
        showFlash('Panier enregistré sur cet appareil. Synchronisation impossible : ' + error.message, 'error');
    }
}

async function addProductToCart(product, quantity) {
    if (!Number.isInteger(product.id) || product.id <= 0 || !Number.isInteger(quantity) || quantity <= 0) {
        showFlash('Les informations du produit sont invalides.', 'error');
        return;
    }

    if (product.stock <= 0) {
        showFlash('Ce produit est actuellement en rupture de stock.', 'error');
        return;
    }

    await cartReady;
    const cart = getLocalCart();
    const current = cart[String(product.id)];
    const newQuantity = (current ? current.quantity : 0) + quantity;

    if (newQuantity > product.stock) {
        showFlash('La quantité demandée dépasse le stock disponible.', 'error');
        return;
    }

    cart[String(product.id)] = {
        id: product.id,
        name: product.name,
        price: product.price,
        image: product.image,
        stock: product.stock,
        quantity: newQuantity
    };

    await saveCartMutation(cart, 'Produit ajouté au panier.');
}

async function changeCartItem(productId, action) {
    await cartReady;
    const cart = getLocalCart();
    const key = String(productId);
    const item = cart[key];

    if (!item) {
        showFlash('Produit introuvable dans le panier.', 'error');
        return;
    }

    if (action === 'delete') {
        delete cart[key];
        await saveCartMutation(cart, 'Produit supprimé du panier.');
        return;
    }

    if (action === 'increase') {
        if (item.quantity >= item.stock) {
            showFlash('Stock insuffisant pour cette quantité.', 'error');
            return;
        }
        item.quantity += 1;
    } else if (action === 'decrease') {
        item.quantity -= 1;
        if (item.quantity <= 0) {
            delete cart[key];
        }
    } else {
        return;
    }

    await saveCartMutation(cart, 'Quantité du panier mise à jour.');
}

async function submitOrder(form) {
    if (!navigator.onLine) {
        showFlash('La commande ne peut pas être confirmée hors ligne. Votre panier est conservé sur cet appareil.', 'error');
        return;
    }

    try {
        if (isCartPending()) {
            await syncLocalCart();
        }

        const response = await fetch(form.action, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            body: new FormData(form)
        });
        const data = await readJsonResponse(response);
        saveLocalCart({});
        setCartPending(false);
        showFlash(data.message, 'success');
        window.setTimeout(function() {
            window.location.reload();
        }, 900);
    } catch (error) {
        showFlash(error.message || 'La commande a échoué.', 'error');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const cartPage = document.querySelector('[data-cart-page]');
    if (cartPage) {
        orderToken = cartPage.dataset.orderToken || '';
    }

    cartReady = initializeCart();

    document.querySelectorAll('[data-add-to-cart], #detail-add-to-cart').forEach(function(button) {
        button.addEventListener('click', function() {
            const product = getProductData(button);
            const quantityInput = document.getElementById('product-quantity');
            const quantity = quantityInput
                ? Number(quantityInput.value)
                : Number(button.dataset.quantity || 1);
            addProductToCart(product, quantity).catch(function(error) {
                console.error('Ajout au panier impossible.', error);
                showFlash('Impossible d’enregistrer cet article sur cet appareil.', 'error');
            });
        });
    });

    const cartContent = document.getElementById('cart-page-content');
    if (cartContent) {
        cartContent.addEventListener('click', function(event) {
            const button = event.target.closest('[data-cart-action]');
            if (!button) {
                return;
            }

            changeCartItem(Number(button.dataset.productId), button.dataset.cartAction).catch(function(error) {
                console.error('Modification du panier impossible.', error);
                showFlash('Impossible de modifier le panier sur cet appareil.', 'error');
            });
        });

        cartContent.addEventListener('submit', function(event) {
            if (event.target.id !== 'order-form') {
                return;
            }

            event.preventDefault();
            submitOrder(event.target);
        });
    }

    window.addEventListener('online', function() {
        if (isCartPending()) {
            syncLocalCart().then(function() {
                showFlash('Le panier a été synchronisé.', 'success');
            }).catch(function(error) {
                if (isNetworkError(error)) {
                    showFlash('Le serveur est injoignable. Votre panier reste enregistré sur cet appareil.', 'success');
                    return;
                }

                console.error('Synchronisation après reconnexion impossible.', error);
                showFlash('La connexion est revenue, mais le panier n’a pas pu être synchronisé : ' + error.message, 'error');
            });
            return;
        }

        fetchServerCart().then(renderCart).catch(function(error) {
            console.error('Actualisation du panier impossible.', error);
        });
    });
});
