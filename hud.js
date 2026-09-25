/* ============================================================
   VENDETTA — Live HUD updater
   ============================================================ */
(function () {
    let lastData = null;
    let intervalId = null;

    function updateEl(selector, value) {
        const el = document.querySelector(selector);
        if (!el) return;
        el.textContent = value;
    }

    function updateMoney(oldVal, newVal) {
        const el = document.querySelector('.hud-value.gold');
        if (!el) return;
        if (oldVal !== null && oldVal !== newVal) {
            Vendetta.animateNumber(el, oldVal, newVal);
            el.classList.add('hud-flash');
            setTimeout(() => el.classList.remove('hud-flash'), 800);
        } else {
            el.textContent = Vendetta.formatMoney(newVal);
        }
    }

    function updateBell(count) {
        const icon = document.querySelector('.hud-icon');
        if (!icon) return;

        let badge = icon.querySelector('.badge');
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'badge';
                icon.appendChild(badge);
            }
            badge.textContent = count > 9 ? '9+' : count;
        } else if (badge) {
            badge.remove();
        }
    }

    async function fetchUser() {
        const data = await Vendetta.get('api/get_user.php');
        if (!data || data.error) return;

        if (lastData) {
            // Alleen updaten als iets veranderd is
            if (lastData.money !== data.money) updateMoney(lastData.money, data.money);
            if (lastData.energy !== data.energy) {
                const els = document.querySelectorAll('[data-hud="energy"]');
                els.forEach(el => el.textContent = data.energy);
            }
            if (lastData.unread !== data.unread) updateBell(data.unread);
        } else {
            updateMoney(null, data.money);
            updateBell(data.unread);
        }

        // Custom event voor andere modules
        document.dispatchEvent(new CustomEvent('vendetta:hud-update', { detail: data }));

        lastData = data;
    }

    function start() {
        fetchUser();
        intervalId = setInterval(fetchUser, 15000);
    }

    function stop() {
        if (intervalId) clearInterval(intervalId);
    }

    document.addEventListener('DOMContentLoaded', start);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) stop(); else start();
    });

    // Expose voor andere modules
    window.VendettaHUD = { fetch: fetchUser, getData: () => lastData };
})();