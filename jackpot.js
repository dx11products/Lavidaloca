/* ============================================================
   VENDETTA — Live jackpot ticker
   ============================================================ */
(function () {

    function formatJackpot(n) {
        n = Math.round(n);
        if (n >= 1000000000) return '€' + (n / 1000000000).toFixed(2).replace('.', ',') + ' mld';
        if (n >= 1000000)    return '€' + (n / 1000000).toFixed(2).replace('.', ',') + ' M';
        if (n >= 1000)       return '€' + Math.round(n / 1000).toLocaleString('nl-NL') + 'K';
        return '€' + n.toLocaleString('nl-NL');
    }

    function animateNumber(el, from, to) {
        const duration = 700;
        const start = performance.now();
        const diff = to - from;

        function tick(now) {
            const t = Math.min(1, (now - start) / duration);
            const ease = 1 - Math.pow(1 - t, 3);
            const val = Math.round(from + diff * ease);
            el.textContent = formatJackpot(val);
            if (t < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    }

    function updateDisplay(jackpots) {
        jackpots.forEach(j => {
            const el = document.querySelector(`[data-jackpot="${j.key}"] .jp-amount`);
            if (el) {
                const current = parseInt(el.dataset.amount) || 0;
                if (current !== j.amount) {
                    animateNumber(el, current, j.amount);
                    el.dataset.amount = j.amount;
                }
            }
        });
    }

    async function poll() {
        try {
            const res = await fetch('api/jackpot_status.php');
            const data = await res.json();
            if (data.jackpots) updateDisplay(data.jackpots);
        } catch (e) {}
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-jackpot] .jp-amount').forEach(el => {
            el.dataset.amount = parseInt(el.textContent.replace(/\D/g, '')) || 0;
        });
    });

    setInterval(poll, 5000);

})();