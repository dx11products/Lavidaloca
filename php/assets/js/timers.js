/* ============================================================
   VENDETTA — Live cooldown timers (seconden)
   ============================================================ */
(function () {

    function formatSec(sec) {
        sec = Math.max(0, Math.floor(sec));
        if (sec >= 60) {
            const m = Math.floor(sec / 60);
            const s = sec % 60;
            return m + 'm ' + s + 's';
        }
        return sec + 's';
    }

    function startCrimeTimer(card) {
        const btn = card.querySelector('.crime-btn');
        if (!btn) return;

        let remaining = parseInt(card.dataset.cooldownRemaining) || 0;

        if (remaining > 0) {
            btn.disabled = true;
            btn.innerHTML = '🚔 ' + formatSec(remaining);
        }

        const tick = () => {
            if (remaining <= 0) {
                btn.disabled = false;
                btn.innerHTML = 'Uitvoeren';
                return;
            }
            remaining--;
            btn.innerHTML = '🚔 ' + formatSec(remaining);
            setTimeout(tick, 1000);
        };

        if (remaining > 0) tick();

        card._startCooldown = (seconds) => {
            remaining = seconds;
            tick();
        };
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.crime-card[data-cooldown]').forEach(startCrimeTimer);
    });

    window.VendettaTimers = { formatSec, startCrimeTimer };
})();