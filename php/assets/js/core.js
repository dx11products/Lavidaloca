/* ============================================================
   VENDETTA — Core helpers
   Toast, Modal, Fetch, Ticker
   ============================================================ */
window.Vendetta = (function () {

    // CSRF token uit meta tag
    function csrf() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    // Formatteer geld
    function formatMoney(n) {
        n = Math.round(n);
        return '€' + n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    // Formatteer tijd (seconden)
    function formatTime(sec) {
        sec = Math.max(0, Math.floor(sec));
        const m = Math.floor(sec / 60);
        const s = sec % 60;
        if (m > 0) return m + 'm ' + s + 's';
        return s + 's';
    }

    // ============================================================
    // TOAST
    // ============================================================
    function ensureToastContainer() {
        let c = document.getElementById('toast-container');
        if (!c) {
            c = document.createElement('div');
            c.id = 'toast-container';
            c.className = 'toast-container';
            document.body.appendChild(c);
        }
        return c;
    }

    function toast(type, message, duration) {
        duration = duration || 4000;
        const c = ensureToastContainer();
        const el = document.createElement('div');
        el.className = 'toast toast-' + type;

        const icons = { success: '✅', error: '❌', info: 'ℹ️', warning: '⚠️', gold: '💰' };
        el.innerHTML = '<span class="toast-icon">' + (icons[type] || 'ℹ️') + '</span>' +
                       '<span class="toast-message">' + message + '</span>' +
                       '<button class="toast-close" type="button">×</button>';

        c.appendChild(el);

        requestAnimationFrame(() => el.classList.add('show'));

        const remove = () => {
            el.classList.remove('show');
            el.classList.add('hide');
            setTimeout(() => el.remove(), 300);
        };

        const t = setTimeout(remove, duration);
        el.querySelector('.toast-close').addEventListener('click', () => {
            clearTimeout(t);
            remove();
        });
    }

    // ============================================================
    // CONFIRM MODAL
    // ============================================================
    function confirmModal(message, options) {
        options = options || {};
        const okText = options.okText || 'Ja';
        const cancelText = options.cancelText || 'Annuleren';
        const title = options.title || 'Bevestigen';

        return new Promise(resolve => {
            const overlay = document.createElement('div');
            overlay.className = 'modal-overlay';
            overlay.innerHTML =
                '<div class="modal-box">' +
                    '<h3 class="modal-title">' + title + '</h3>' +
                    '<p class="modal-message">' + message + '</p>' +
                    '<div class="modal-actions">' +
                        '<button class="btn btn-outline modal-cancel" type="button">' + cancelText + '</button>' +
                        '<button class="btn btn-gold modal-ok" type="button">' + okText + '</button>' +
                    '</div>' +
                '</div>';

            document.body.appendChild(overlay);
            requestAnimationFrame(() => overlay.classList.add('show'));

            const close = (val) => {
                overlay.classList.remove('show');
                setTimeout(() => overlay.remove(), 250);
                resolve(val);
            };

            overlay.querySelector('.modal-cancel').addEventListener('click', () => close(false));
            overlay.querySelector('.modal-ok').addEventListener('click', () => close(true));
            overlay.addEventListener('click', (e) => { if (e.target === overlay) close(false); });
        });
    }

    // ============================================================
    // FETCH
    // ============================================================
    async function post(url, data) {
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(Object.assign({ csrf: csrf() }, data)),
            });
            return await res.json();
        } catch (e) {
            return { success: false, error: 'Netwerkfout' };
        }
    }

    async function get(url) {
        try {
            const res = await fetch(url);
            return await res.json();
        } catch (e) {
            return { error: 'Netwerkfout' };
        }
    }

    // ============================================================
    // SPINNER (op knop)
    // ============================================================
    function spinner(btn, text) {
        if (!btn) return null;
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="btn-spinner"></span>' + (text || 'Bezig...');
        return () => {
            btn.disabled = false;
            btn.innerHTML = original;
        };
    }

    // ============================================================
    // ANIMATE NUMBER (ticker)
    // ============================================================
    function animateNumber(el, from, to, duration) {
        if (!el) return;
        duration = duration || 700;
        const start = performance.now();
        const diff = to - from;

        function tick(now) {
            const t = Math.min(1, (now - start) / duration);
            const ease = 1 - Math.pow(1 - t, 3); // easeOutCubic
            const val = Math.round(from + diff * ease);
            el.textContent = formatMoney(val);
            if (t < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    }

    return { csrf, formatMoney, formatTime, toast, confirmModal, post, get, spinner, animateNumber };
})();