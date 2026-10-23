const CACHE_NAME = 'minishop-offline-v4';
const APP_SHELL = [
    'offline.html',
    'assets/css/style.css',
    'assets/js/app.js?v=4'
];
const CACHEABLE_PAGES = ['index.php', 'panier.php'];

self.addEventListener('install', function(event) {
    event.waitUntil((async function() {
        const cache = await caches.open(CACHE_NAME);
        await cache.addAll(APP_SHELL.map(function(path) {
            return new URL(path, self.registration.scope).href;
        }));

        await Promise.all(CACHEABLE_PAGES.map(async function(path) {
            const url = new URL(path, self.registration.scope);
            try {
                const response = await fetch(url.href, { credentials: 'same-origin' });
                if (response.ok) {
                    const page = await response.clone().text();
                    await cache.put(url.href, response);

                    if (path === 'index.php') {
                        const imagePaths = [];
                        const imagePattern = /<img\b[^>]*\bsrc=["']([^"']+)["']/gi;
                        let match;

                        while ((match = imagePattern.exec(page)) !== null) {
                            const imageUrl = new URL(match[1], url);
                            if (imageUrl.origin === self.location.origin) {
                                imagePaths.push(imageUrl.href);
                            }
                        }

                        await Promise.all(imagePaths.map(async function(imageUrl) {
                            try {
                                const imageResponse = await fetch(imageUrl, { credentials: 'same-origin' });
                                if (imageResponse.ok) {
                                    await cache.put(imageUrl, imageResponse);
                                }
                            } catch (error) {
                                console.warn('Mise en cache hors ligne ignorée pour une image.', error);
                            }
                        }));
                    }
                }
            } catch (error) {
                console.warn('Mise en cache hors ligne ignorée pour ' + path + '.', error);
            }
        }));

        await self.skipWaiting();
    })());
});

self.addEventListener('activate', function(event) {
    event.waitUntil((async function() {
        const cacheNames = await caches.keys();
        await Promise.all(cacheNames.map(function(cacheName) {
            if (cacheName !== CACHE_NAME && cacheName.indexOf('minishop-offline-') === 0) {
                return caches.delete(cacheName);
            }
            return Promise.resolve();
        }));
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', function(event) {
    const request = event.request;
    const requestUrl = new URL(request.url);

    if (request.method !== 'GET' || requestUrl.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith((async function() {
            const cache = await caches.open(CACHE_NAME);

            try {
                const response = await fetch(request);
                if (response.ok) {
                    await cache.put(request, response.clone());
                    if (CACHEABLE_PAGES.some(function(path) {
                        return requestUrl.pathname === new URL(path, self.registration.scope).pathname;
                    })) {
                        const canonicalUrl = new URL(requestUrl.pathname, self.registration.scope);
                        await cache.put(canonicalUrl.href, response.clone());
                    }
                    return response;
                }

                if (response.status >= 500) {
                    const cached = await findCachedPage(cache, request, requestUrl);
                    if (cached) {
                        return cached;
                    }
                }

                return response;
            } catch (error) {
                const cached = await findCachedPage(cache, request, requestUrl);
                if (cached) {
                    return cached;
                }

                const fallback = await cache.match(new URL('offline.html', self.registration.scope).href);
                if (fallback) {
                    return fallback;
                }

                throw error;
            }
        })());
        return;
    }

    if (request.destination === 'script' || request.destination === 'style') {
        event.respondWith((async function() {
            const cache = await caches.open(CACHE_NAME);

            try {
                const response = await fetch(request);
                if (response.ok) {
                    await cache.put(request, response.clone());
                }
                return response;
            } catch (error) {
                const cached = await cache.match(request);
                if (cached) {
                    return cached;
                }
                throw error;
            }
        })());
        return;
    }

    if (requestUrl.pathname.indexOf('/assets/') !== -1 || request.destination === 'image') {
        event.respondWith((async function() {
            const cache = await caches.open(CACHE_NAME);
            const cached = await cache.match(request);

            if (cached) {
                return cached;
            }

            const response = await fetch(request);
            if (response.ok) {
                await cache.put(request, response.clone());
            }
            return response;
        })());
    }
});

async function findCachedPage(cache, request, requestUrl) {
    const exactMatch = await cache.match(request);
    if (exactMatch) {
        return exactMatch;
    }

    const indexUrl = new URL('index.php', self.registration.scope);
    const cartUrl = new URL('panier.php', self.registration.scope);

    if (requestUrl.pathname === indexUrl.pathname || requestUrl.pathname === cartUrl.pathname) {
        return cache.match(new URL(requestUrl.pathname, self.registration.scope).href);
    }

    const detailsUrl = new URL('details.php', self.registration.scope);
    if (requestUrl.pathname === detailsUrl.pathname) {
        return cache.match(indexUrl.href);
    }

    return null;
}
