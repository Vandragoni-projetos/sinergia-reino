/**
 * PWA Push - Registro de inscrição para notificações push
 * A permissão deve ser pedida por gesto do usuário (clique); este script expõe PwaPush.requestPermission() e dispara evento quando pode mostrar o botão.
 */
(function() {
    'use strict';

    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        return;
    }

    var API_BASE = '/api/api.php';

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = window.atob(base64);
        var outputArray = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    function sendSubscriptionToServer(subscription) {
        var subJson = subscription.toJSON ? subscription.toJSON() : {
            endpoint: subscription.endpoint,
            keys: {
                p256dh: subscription.getKey ? btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('p256dh')))) : (subscription.keys && subscription.keys.p256dh),
                auth: subscription.getKey ? btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('auth')))) : (subscription.keys && subscription.keys.auth)
            }
        };
        return fetch(API_BASE, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'register_pwa_push', subscription: subJson })
        }).then(function(res) { return res.json(); });
    }

    function doSubscribe(registration, vapidKey) {
        return registration.pushManager.getSubscription().then(function(sub) {
            if (sub) return sub;
            return registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: vapidKey
            });
        });
    }

    window.PwaPush = {
        isRequestable: false,
        isSubscribed: false,
        isDenied: false,
        requestPermission: function() {
            var self = this;
            if (!('Notification' in window)) return Promise.resolve(false);
            if (Notification.permission === 'granted') {
                return this._tryRegisterAndSend();
            }
            if (Notification.permission === 'denied') {
                return Promise.resolve(false);
            }
            return Notification.requestPermission().then(function(perm) {
                if (perm !== 'granted') {
                    self.isDenied = true;
                    try { window.dispatchEvent(new CustomEvent('pwa-push-state', { detail: { denied: true } })); } catch (e) {}
                    return false;
                }
                return self._tryRegisterAndSend();
            });
        },
        _vapidKey: null,
        _registration: null,
        _tryRegisterAndSend: function() {
            var self = this;
            if (this._vapidKey && this._registration) {
                return doSubscribe(this._registration, this._vapidKey).then(function(sub) {
                    if (!sub) return false;
                    return sendSubscriptionToServer(sub).then(function(result) {
                        if (result && result.success) {
                            self.isSubscribed = true;
                            self.isRequestable = false;
                            try { window.dispatchEvent(new CustomEvent('pwa-push-state', { detail: { subscribed: true } })); } catch (e) {}
                            return true;
                        }
                        return false;
                    });
                }).catch(function() { return false; });
            }
            return fetch(API_BASE + '?action=get_pwa_vapid_public', { credentials: 'include' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success || !data.publicKey) return false;
                    self._vapidKey = urlBase64ToUint8Array(data.publicKey);
                    return navigator.serviceWorker.register('/pwa/sw.js').then(function(reg) { return reg; }).catch(function() {
                        return navigator.serviceWorker.getRegistration().then(function(reg) { return reg || Promise.reject(new Error('No SW')); });
                    }).then(function(registration) {
                        self._registration = registration;
                        return doSubscribe(registration, self._vapidKey).then(function(sub) {
                            if (!sub) return false;
                            return sendSubscriptionToServer(sub).then(function(result) {
                                if (result && result.success) {
                                    self.isSubscribed = true;
                                    self.isRequestable = false;
                                    try { window.dispatchEvent(new CustomEvent('pwa-push-state', { detail: { subscribed: true } })); } catch (e) {}
                                    return true;
                                }
                                return false;
                            });
                        });
                    });
                }).catch(function() { return false; });
        }
    };

    function registerAndSubscribe() {
        fetch(API_BASE + '?action=get_pwa_vapid_public', { credentials: 'include' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success || !data.publicKey) {
                    return;
                }
                var vapidKey = urlBase64ToUint8Array(data.publicKey);
                window.PwaPush._vapidKey = vapidKey;
                return navigator.serviceWorker.register('/pwa/sw.js').then(function(reg) {
                    return reg;
                }).catch(function() {
                    return navigator.serviceWorker.getRegistration().then(function(reg) {
                        return reg || Promise.reject(new Error('No SW'));
                    });
                }).then(function(registration) {
                    window.PwaPush._registration = registration;
                    return registration.pushManager.getSubscription().then(function(sub) {
                        if (sub) {
                            return sendSubscriptionToServer(sub).then(function(result) {
                                if (result && result.success) {
                                    window.PwaPush.isSubscribed = true;
                                    console.log('PWA Push: inscrição registrada com sucesso.');
                                }
                            }).catch(function(err) {
                                console.warn('PWA Push: falha ao registrar inscrição.', err);
                            });
                        }
                        if (Notification.permission === 'denied') {
                            window.PwaPush.isDenied = true;
                            try { window.dispatchEvent(new CustomEvent('pwa-push-state', { detail: { denied: true } })); } catch (e) {}
                            return;
                        }
                        if (Notification.permission === 'granted') {
                            return doSubscribe(registration, vapidKey).then(function(sub) {
                                if (!sub) return;
                                return sendSubscriptionToServer(sub).then(function(result) {
                                    if (result && result.success) {
                                        window.PwaPush.isSubscribed = true;
                                        console.log('PWA Push: inscrição registrada com sucesso.');
                                    }
                                }).catch(function(err) { console.warn('PWA Push: falha ao registrar.', err); });
                            });
                        }
                        if (Notification.permission === 'default') {
                            window.PwaPush.isRequestable = true;
                            try { window.dispatchEvent(new CustomEvent('pwa-push-state', { detail: { requestable: true } })); } catch (e) {}
                        }
                    });
                });
            })
            .catch(function() {});
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            window.setTimeout(registerAndSubscribe, 800);
        });
    } else {
        window.setTimeout(registerAndSubscribe, 800);
    }
})();
