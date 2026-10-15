const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

function updateCartBadge() {
    fetch('actions/compteur_panier.php', {
        method: 'GET',
        headers: {
            'Accept': 'application/json'
        }
    })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Impossible de récupérer le compteur du panier.');
            }
            return response.json();
        })
        .then(function(data) {
            const counter = document.getElementById('cart-count');
            if (counter) {
                counter.textContent = data.count || 0;
            }
        })
        .catch(function() {
            const counter = document.getElementById('cart-count');
            if (counter) {
                counter.textContent = '0';
            }
        });
}

function showFlash(message, type) {
    const messageBox = document.getElementById('flash-message');
    if (!messageBox) {
        if (typeof window !== 'undefined') {
            window.alert(message);
        }
        return;
    }

    messageBox.className = 'flash-message ' + type;
    messageBox.textContent = message;
    messageBox.style.display = 'block';
}

function addProductToCart(productId, quantity) {
    if (!productId || quantity <= 0) {
        return;
    }

    const formData = new URLSearchParams();
    formData.append('product_id', String(productId));
    formData.append('quantity', String(quantity));
    formData.append('csrf_token', csrfToken);

    fetch('actions/ajouter_panier.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-CSRF-Token': csrfToken
        },
        body: formData.toString()
    })
        .then(function(response) {
            return response.json().then(function(data) {
                return { status: response.status, data: data };
            });
        })
        .then(function(result) {
            if (result.status >= 400 || !result.data.success) {
                throw new Error(result.data.message || 'Une erreur est survenue.');
            }

            showFlash(result.data.message, 'success');
            updateCartBadge();
        })
        .catch(function(error) {
            showFlash(error.message || 'Une erreur est survenue.', 'error');
        });
}

function updateCartItem(productId, action) {
    const formData = new URLSearchParams();
    formData.append('product_id', String(productId));
    formData.append('action', action);
    formData.append('csrf_token', csrfToken);

    fetch('actions/modifier_panier.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-CSRF-Token': csrfToken
        },
        body: formData.toString()
    })
        .then(function(response) {
            return response.json().then(function(data) {
                return { status: response.status, data: data };
            });
        })
        .then(function(result) {
            if (result.status >= 400 || !result.data.success) {
                throw new Error(result.data.message || 'La modification du panier a échoué.');
            }

            showFlash(result.data.message, 'success');
            window.location.reload();
        })
        .catch(function(error) {
            showFlash(error.message || 'La modification du panier a échoué.', 'error');
        });
}

function deleteCartItem(productId) {
    const formData = new URLSearchParams();
    formData.append('product_id', String(productId));
    formData.append('csrf_token', csrfToken);

    fetch('actions/supprimer_panier.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-CSRF-Token': csrfToken
        },
        body: formData.toString()
    })
        .then(function(response) {
            return response.json().then(function(data) {
                return { status: response.status, data: data };
            });
        })
        .then(function(result) {
            if (result.status >= 400 || !result.data.success) {
                throw new Error(result.data.message || 'La suppression a échoué.');
            }

            showFlash(result.data.message, 'success');
            window.location.reload();
        })
        .catch(function(error) {
            showFlash(error.message || 'La suppression a échoué.', 'error');
        });
}

document.addEventListener('DOMContentLoaded', function() {
    updateCartBadge();

    document.querySelectorAll('[data-add-to-cart]').forEach(function(button) {
        button.addEventListener('click', function() {
            const productId = Number(button.dataset.productId);
            const quantity = Number(button.dataset.quantity || 1);
            addProductToCart(productId, quantity);
        });
    });

    const detailAddButton = document.getElementById('detail-add-to-cart');
    if (detailAddButton) {
        detailAddButton.addEventListener('click', function() {
            const productId = Number(detailAddButton.dataset.productId);
            const quantityInput = document.getElementById('product-quantity');
            const quantity = Number(quantityInput ? quantityInput.value : 1);
            addProductToCart(productId, quantity);
        });
    }

    document.querySelectorAll('[data-cart-action]').forEach(function(button) {
        button.addEventListener('click', function() {
            const productId = Number(button.dataset.productId);
            const action = button.dataset.cartAction;
            if (action === 'delete') {
                deleteCartItem(productId);
                return;
            }
            updateCartItem(productId, action);
        });
    });

    const orderForm = document.getElementById('order-form');
    if (orderForm) {
        orderForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(orderForm);
            fetch(orderForm.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': csrfToken
                },
                body: formData
            })
                .then(function(response) {
                    return response.json().then(function(data) {
                        return { status: response.status, data: data };
                    });
                })
                .then(function(result) {
                    if (result.status >= 400 || !result.data.success) {
                        throw new Error(result.data.message || 'La commande a échoué.');
                    }

                    showFlash(result.data.message, 'success');
                    setTimeout(function() {
                        window.location.reload();
                    }, 900);
                })
                .catch(function(error) {
                    showFlash(error.message || 'La commande a échoué.', 'error');
                });
        });
    }
});
