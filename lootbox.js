/* ============================================================
   VENDETTA — Lootbox opening animatie (extreme editie)
   ============================================================ */
(function () {

    const overlay = document.getElementById('lootbox-overlay');
    const reveal  = document.getElementById('lootbox-reveal');
    const spinner = document.getElementById('lr-spinner');
    const result  = document.getElementById('lr-result');
    const dropsEl = document.getElementById('lr-drops');

    if (!overlay) return;

    function showOverlay() { overlay.classList.add('show'); }
    function hideOverlay() {
        overlay.classList.remove('show');
        result.style.display = 'none';
        spinner.style.display = 'block';
        dropsEl.innerHTML = '';
    }

    async function openBox(boxKey, method) {
        showOverlay();
        spinner.style.display = 'block';
        result.style.display = 'none';

        const res = await fetch('api/open_lootbox.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                box: boxKey,
                method: method
            })
        });

        const data = await res.json();

        setTimeout(() => {
            if (data.error) {
                hideOverlay();
                if (window.Vendetta) Vendetta.toast('error', data.error);
                return;
            }

            updateHUD(data.user);

            spinner.style.display = 'none';
            result.style.display = 'flex';
            reveal.setAttribute('data-rarity', data.best_rarity);

            const rarityInfo = data.rarity_info;
            document.getElementById('lr-rarity').innerHTML =
                rarityInfo.icon + ' ' + rarityInfo.label.toUpperCase();
            document.getElementById('lr-rarity').style.color = rarityInfo.color;

            // Toon alle drops
            dropsEl.innerHTML = '';
            data.rewards.forEach((r, idx) => {
                const rInfo = window.LOOTBOX_RARITIES ? window.LOOTBOX_RARITIES[r.rarity] : rarityInfo;
                const item = document.createElement('div');
                item.className = 'lr-item';
                item.style.animationDelay = (idx * 150) + 'ms';
                item.style.borderColor = rInfo.color + '66';

                const icons = {
                    eur:    '💰',
                    btc:    '₿',
                    clicks: '🖱️',
                    weapon: '🔫'
                };

                item.innerHTML = `
                    <div class="lri-rarity" style="color:${rInfo.color};">${rInfo.icon} ${rInfo.label}</div>
                    <div class="lri-icon">${icons[r.type] || '🎁'}</div>
                    <div class="lri-value" style="color:${rInfo.color};">${r.display}</div>
                `;
                dropsEl.appendChild(item);
            });

            if (data.best_rarity === 'epic' || data.best_rarity === 'legendary' ||
                data.best_rarity === 'mythic' || data.best_rarity === 'divine') {
                spawnConfetti(rarityInfo.color, data.best_rarity === 'divine' ? 120 : (data.best_rarity === 'mythic' ? 80 : 50));
            }

            updateStatCards(data.user);
        }, 1800);
    }

    function updateHUD(user) {
        const moneyEl = document.querySelector('.hud-value.gold');
        if (moneyEl) moneyEl.textContent = '€' + formatBig(user.money);

        const btcEl = document.querySelector('.hud-value.btc');
        if (btcEl) btcEl.textContent = '₿ ' + (parseFloat(user.btc).toFixed(8).replace(/\.?0+$/, '') || '0');

        const clicksEl = document.querySelector('.hud-value.clicks');
        if (clicksEl) clicksEl.textContent = '🖱️ ' + formatBig(user.clicks);
    }

    function updateStatCards(user) {
        const m = document.getElementById('lb-money');
        if (m) m.textContent = '€' + formatBig(user.money);
        const b = document.getElementById('lb-btc');
        if (b) b.textContent = parseFloat(user.btc).toFixed(8).replace(/\.?0+$/, '') || '0';
        const c = document.getElementById('lb-clicks');
        if (c) c.textContent = formatBig(user.clicks);
    }

    function formatBig(n) {
        n = Math.round(n);
        if (n >= 1e15) return (n / 1e15).toFixed(2) + ' Q';
        if (n >= 1e12) return (n / 1e12).toFixed(2) + ' T';
        if (n >= 1e9)  return (n / 1e9).toFixed(2) + ' mld';
        if (n >= 1e6)  return (n / 1e6).toFixed(2) + ' M';
        return n.toLocaleString('nl-NL');
    }

    function spawnConfetti(color, count) {
        count = count || 40;
        for (let i = 0; i < count; i++) {
            const c = document.createElement('div');
            c.className = 'confetti';
            c.style.left = '50%';
            c.style.top = '50%';
            c.style.background = color;
            c.style.width = (6 + Math.random() * 8) + 'px';
            c.style.height = c.style.width;

            const angle = Math.random() * Math.PI * 2;
            const dist = 150 + Math.random() * 400;
            const tx = Math.cos(angle) * dist;
            const ty = Math.sin(angle) * dist;

            c.style.setProperty('--tx', tx + 'px');
            c.style.setProperty('--ty', ty + 'px');

            overlay.appendChild(c);
            setTimeout(() => c.remove(), 2200);
        }
    }

    document.querySelectorAll('.btn-buy-lootbox, .clicks-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            openBox(btn.dataset.box, btn.dataset.method);
        });
    });

    document.getElementById('lr-close').addEventListener('click', () => {
        hideOverlay();
        setTimeout(() => location.reload(), 200);
    });

    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) hideOverlay();
    });

})();