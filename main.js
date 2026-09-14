// Kleine UX verbereringen — kan later uitgebreid worden
document.addEventListener('DOMContentLoaded', () => {
    // Fade-in animatie op hero
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

    // Alert auto-fade
    const alert = document.querySelector('.alert');
    if (alert) {
        setTimeout(() => {
            alert.style.transition = 'opacity .5s';
            alert.style.opacity = 0;
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    }
});