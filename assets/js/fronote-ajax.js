/**
 * FronoteAjax — Standardized AJAX utility for Fronote modules.
 *
 * Handles CSRF tokens, error handling, toasts, and common patterns.
 *
 * Usage:
 *   FronoteAjax.post('/api/endpoint', { key: 'value' }).then(data => { ... });
 *   FronoteAjax.get('/api/endpoint', { page: 1 }).then(data => { ... });
 *   FronoteAjax.submitForm(document.getElementById('myForm')).then(data => { ... });
 *   FronoteAjax.confirmDelete('/api/endpoint', { id: 123 }, 'Supprimer cet element ?');
 */
var FronoteAjax = (function() {
    'use strict';

    /**
     * Get CSRF token from meta tag or hidden input.
     */
    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.getAttribute('content');
        var input = document.querySelector('input[name="csrf_token"], input[name="_token"]');
        if (input) return input.value;
        return '';
    }

    /**
     * Build default headers for AJAX requests.
     */
    function defaultHeaders(extra) {
        var headers = {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCsrfToken()
        };
        if (extra) {
            for (var k in extra) {
                if (extra.hasOwnProperty(k)) headers[k] = extra[k];
            }
        }
        return headers;
    }

    /**
     * Handle the response: parse JSON, show toast on error.
     */
    function handleResponse(response) {
        if (!response.ok) {
            return response.json().catch(function() {
                return { success: false, error: 'Erreur serveur (' + response.status + ')' };
            }).then(function(data) {
                showError(data.error || data.message || 'Erreur ' + response.status);
                return Promise.reject(data);
            });
        }
        return response.json().then(function(data) {
            if (data.success === false) {
                showError(data.error || data.message || 'Une erreur est survenue.');
                return Promise.reject(data);
            }
            if (data.redirect) {
                window.location.href = data.redirect;
                return data;
            }
            return data;
        });
    }

    /**
     * Show an error toast.
     */
    function showError(msg) {
        if (typeof FronoteToast !== 'undefined') {
            FronoteToast.error(msg);
        } else {
            console.error('[FronoteAjax]', msg);
        }
    }

    /**
     * Show a success toast.
     */
    function showSuccess(msg) {
        if (typeof FronoteToast !== 'undefined') {
            FronoteToast.success(msg);
        }
    }

    /**
     * POST request with FormData or object body.
     */
    function post(url, data, options) {
        options = options || {};
        var body;

        if (data instanceof FormData) {
            body = data;
            if (!data.has('_token')) {
                body.append('_token', getCsrfToken());
            }
        } else {
            body = new FormData();
            body.append('_token', getCsrfToken());
            if (data && typeof data === 'object') {
                for (var k in data) {
                    if (data.hasOwnProperty(k)) {
                        body.append(k, data[k]);
                    }
                }
            }
        }

        return fetch(url, {
            method: 'POST',
            body: body,
            headers: defaultHeaders()
        })
        .then(handleResponse)
        .then(function(result) {
            if (options.successMessage) showSuccess(options.successMessage);
            return result;
        })
        .catch(function(err) {
            if (options.onError) options.onError(err);
            return Promise.reject(err);
        });
    }

    /**
     * GET request with query parameters.
     */
    function get(url, params, options) {
        options = options || {};
        if (params && typeof params === 'object') {
            var qs = Object.keys(params).map(function(k) {
                return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
            }).join('&');
            if (qs) url += (url.indexOf('?') === -1 ? '?' : '&') + qs;
        }

        return fetch(url, {
            method: 'GET',
            headers: defaultHeaders()
        })
        .then(handleResponse)
        .catch(function(err) {
            if (options.onError) options.onError(err);
            return Promise.reject(err);
        });
    }

    /**
     * DELETE request.
     */
    function del(url, data, options) {
        data = data || {};
        data._method = 'DELETE';
        return post(url, data, options);
    }

    /**
     * Submit a form via AJAX.
     * Serializes the form into FormData, posts to form.action.
     */
    function submitForm(form, options) {
        options = options || {};
        var url = options.url || form.action || window.location.href;
        var fd = new FormData(form);

        // Disable submit button during request
        var submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.dataset.originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Chargement...';
        }

        return post(url, fd, options)
        .then(function(data) {
            if (options.resetOnSuccess !== false && data.success !== false) {
                form.reset();
            }
            return data;
        })
        .finally(function() {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtn.dataset.originalText || 'Envoyer';
            }
        });
    }

    /**
     * Show a confirmation modal, then delete on confirm.
     */
    function confirmDelete(url, data, message, options) {
        message = message || 'Etes-vous sur de vouloir supprimer cet element ?';
        options = options || {};

        if (!confirm(message)) {
            return Promise.resolve(null);
        }

        return post(url, Object.assign({ _method: 'DELETE' }, data || {}), {
            successMessage: options.successMessage || 'Supprime avec succes.'
        }).then(function(result) {
            if (options.onSuccess) options.onSuccess(result);
            if (options.removeElement) {
                var el = typeof options.removeElement === 'string'
                    ? document.querySelector(options.removeElement)
                    : options.removeElement;
                if (el) {
                    el.style.transition = 'opacity 0.3s, transform 0.3s';
                    el.style.opacity = '0';
                    el.style.transform = 'translateX(-20px)';
                    setTimeout(function() { el.remove(); }, 300);
                }
            }
            return result;
        });
    }

    /**
     * Upload a file with progress tracking.
     */
    function upload(url, file, fieldName, extraData, onProgress) {
        return new Promise(function(resolve, reject) {
            var fd = new FormData();
            fd.append(fieldName || 'file', file);
            fd.append('_token', getCsrfToken());
            if (extraData) {
                for (var k in extraData) {
                    if (extraData.hasOwnProperty(k)) fd.append(k, extraData[k]);
                }
            }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('X-CSRF-TOKEN', getCsrfToken());

            if (onProgress) {
                xhr.upload.onprogress = function(e) {
                    if (e.lengthComputable) {
                        onProgress(Math.round(e.loaded / e.total * 100));
                    }
                };
            }

            xhr.onload = function() {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (xhr.status >= 200 && xhr.status < 300 && data.success !== false) {
                        resolve(data);
                    } else {
                        showError(data.error || 'Erreur upload');
                        reject(data);
                    }
                } catch (e) {
                    showError('Erreur de traitement de la reponse');
                    reject({ success: false, error: 'Parse error' });
                }
            };

            xhr.onerror = function() {
                showError('Erreur reseau');
                reject({ success: false, error: 'Network error' });
            };

            xhr.send(fd);
        });
    }

    // Public API
    return {
        post: post,
        get: get,
        delete: del,
        submitForm: submitForm,
        confirmDelete: confirmDelete,
        upload: upload,
        getCsrfToken: getCsrfToken
    };
})();
