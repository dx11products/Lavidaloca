/* ============================================================
   VENDETTA — AJAX acties (crimes, attacks)
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

    // ============================================================
    // CRIMES
    // ============================================================
    function bindCrimeButtons() {
        document.querySelectorAll('[data-action="crime"]').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const key = btn.dataset.crime;
                const restore = Vendetta.spinner(btn, 'Bezig...');

                const data = await Vendetta.post('api/do_crime.php', { crime: key });
                restore();

                if (!data.success) {
                    Vendetta.toast('error', data.error || 'Onbekende fout');
                    if (data.cooldown && data.cooldown > 0) {
                        startCooldown(btn, data.cooldown);
                    }
                    return;
                }

                if (data.type === 'win') {
                    Vendetta.toast('success',
                        data.crime + ' geslaagd! +' + Vendetta.formatMoney(data.reward) +
                        (data.multiplier > 1 ? ' (×' + data.multiplier.toFixed(2) + ' chain)' : '')
                    );
                    if (data.no_cooldown) {
                        setTimeout(() => {
                            Vendetta.toast('info', '⚡ Geen cooldown — je kunt direct verder!', 2500);
                        }, 600);
                    }
                } else if (data.type === 'busted') {
                    Vendetta.toast('warning',
                        data.crime + ' geslaagd maar je werd gepakt! Boete: ' + Vendetta.formatMoney(data.fine),
                        6000
                    );
                    if (data.cooldown > 0) {
                        setTimeout(() => {
                            Vendetta.toast('error',
                                '🚔 Cooldown: ' + formatSec(data.cooldown),
                                5000
                            );
                        }, 800);
                    }
                } else if (data.type === 'fail') {
                    Vendetta.toast('error',
                        data.crime + ' mislukt. Boete: ' + Vendetta.formatMoney(data.fine)
                    );
                    if (data.cooldown > 0) {
                        setTimeout(() => {
                            Vendetta.toast('error',
                                '🚔 Cooldown: ' + formatSec(data.cooldown),
                                5000
                            );
                        }, 800);
                    }
                }

                if (data.vault_drop) {
                    setTimeout(() => {
                        Vendetta.toast('gold',
                            '🔐 Kluiscijfer! ' + data.vault_drop.vault + ' — cijfer ' + data.vault_drop.value,
                            6000
                        );
                    }, 1000);
                }

                if (data.user) applyUserUpdate(data.user);

                if (data.cooldown && data.cooldown > 0) {
                    startCooldown(btn, data.cooldown);
                }
            });
        });
    }

    // ============================================================
    // ATTACKS
    // ============================================================
    function bindAttackButtons() {
        document.querySelectorAll('[data-action="attack"]').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const targetId = btn.dataset.target;

                const confirmed = await Vendetta.confirmModal(
                    'Weet je zeker dat je deze speler wil aanvallen?',
                    { title: 'Aanval', okText: 'Aanvallen' }
                );
                if (!confirmed) return;

                const restore = Vendetta.spinner(btn, 'Bezig...');
                const data = await Vendetta.post('api/do_attack.php', { target_id: targetId });
                restore();

                if (!data.success) {
                    Vendetta.toast('error', data.error || 'Onbekende fout');
                    return;
                }

                if (data.type === 'win') {
                    Vendetta.toast('success',
                        'Je versloeg ' + data.target + '! Buit: ' + Vendetta.formatMoney(data.loot) +
                        (data.used_ammo ? ' (+munitie)' : '')
                    );
                    if (data.hospitalized) {
                        Vendetta.toast('info', data.target + ' ligt nu in het ziekenhuis');
                    }
                    const card = btn.closest('.target-card');
                    if (card) card.remove();
                } else {
                    Vendetta.toast('error',
                        'Aanval mislukt! ' + (data.hospitalized ? 'Je ligt nu in het ziekenhuis.' : '')
                    );
                    if (data.hospitalized) {
                        setTimeout(() => location.reload(), 1500);
                    }
                }

                if (data.user) applyUserUpdate(data.user);
            });
        });
    }

    // ============================================================
    // HULPFUNCTIES
    // ============================================================
    function applyUserUpdate(u) {
        const moneyEl = document.querySelector('.hud-value.gold');
        if (moneyEl) {
            const current = parseInt(moneyEl.textContent.replace(/\D/g, '')) || 0;
            Vendetta.animateNumber(moneyEl, current, u.money);
            moneyEl.classList.add('hud-flash');
            setTimeout(() => moneyEl.classList.remove('hud-flash'), 800);
        }

        const rankEls = document.querySelectorAll('.hud-value:not(.gold):not(.clicks):not(.btc)');
        if (rankEls.length && u.rank_title) rankEls[0].textContent = u.rank_title;

        if (u.clicks !== undefined) {
            const clicksEl = document.querySelector('.hud-value.clicks');
            if (clicksEl) {
                clicksEl.textContent = '🖱️ ' + u.clicks.toLocaleString('nl-NL');
                clicksEl.classList.add('hud-flash');
                setTimeout(() => clicksEl.classList.remove('hud-flash'), 800);
            }
        }

        document.dispatchEvent(new CustomEvent('vendetta:user-update', { detail: u }));
    }

    function startCooldown(btn, seconds) {
        let remaining = seconds;
        const original = btn.innerHTML;
        btn.disabled = true;

        const update = () => {
            if (remaining <= 0) {
                btn.disabled = false;
                btn.innerHTML = original;
                return;
            }
            btn.innerHTML = '🚔 ' + formatSec(remaining);
            remaining--;
            setTimeout(update, 1000);
        };
        update();
    }

    document.addEventListener('DOMContentLoaded', () => {
        bindCrimeButtons();
        bindAttackButtons();
    });

})();