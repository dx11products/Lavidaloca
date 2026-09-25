/* ============================================================
   VENDETTA — Main init
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {

    // Fade-in op hero
    const hero = document.querySelector('.hero-content');
    if (hero) {
        hero.style.opacity = 0;
        hero.style.transform = 'translateY(20px)';
        hero.style.transition = 'opacity .8s ease, transform .8s ease';
        requestAnimationFrame(() => {
            hero.style.opacity = 1;
            hero.style.transform = 'translateY(0)';
        });
    }

    // Auto-fade alerts na 6 seconden
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity .5s, transform .5s';
            alert.style.opacity = 0;
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 500);
        }, 6000);
    });

    // Hospital timer live aftellen
    const hospitalTimer = document.querySelector('[data-hospital-timer]');
    if (hospitalTimer) {
        let seconds = parseInt(hospitalTimer.dataset.hospitalTimer) || 0;
        const update = () => {
            if (seconds <= 0) {
                hospitalTimer.textContent = 'Klaar!';
                setTimeout(() => location.reload(), 1000);
                return;
            }
            const m = Math.floor(seconds / 60);
            const s = seconds % 60;
            hospitalTimer.textContent = m + 'm ' + s + 's';
            seconds--;
            setTimeout(update, 1000);
        };
        update();
    }
});