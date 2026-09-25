/* ============================================================
   VENDETTA — Live prijsberekening voor handmatige wapen aankoop
   ============================================================ */
(function () {

    const BULK_TIERS = [
        { min: 5000, pct: 0.40 },
        { min: 1000, pct: 0.30 },
        { min: 500,  pct: 0.20 },
        { min: 100,  pct: 0.10 },
    ];

    function formatNum(n) {
        return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function calculatePrice(quantity, unitPrice) {
        const subtotal = quantity * unitPrice;
        let pct = 0;
        for (const tier of BULK_TIERS) {
            if (quantity >= tier.min) { pct = tier.pct; break; }
        }
        const total = Math.ceil(subtotal * (1 - pct));
        return { subtotal, total, pct };
    }

    function attachToCard(card) {
        const input   = card.querySelector('.manual-quantity-input');
        const preview = card.querySelector('.manual-preview');
        const buyBtn  = card.querySelector('.manual-buy-btn');
        if (!input || !preview) return;

        const unitPrice = parseInt(card.dataset.unitPrice) || 0;
        const unitPower = parseInt(card.dataset.unitPower) || 0;
        const myClicks  = parseInt(card.dataset.myClicks) || 0;

        const costEl     = preview.querySelector('.mp-cost');
        const powerEl    = preview.querySelector('.mp-power');
        const discountRow = preview.querySelector('.mp-discount-row');
        const discountEl = preview.querySelector('.mp-discount');

        function update() {
            let qty = parseInt(input.value) || 0;
            if (qty < 1) qty = 1;

            const { total, pct } = calculatePrice(qty, unitPrice);

            // Update kosten
            costEl.textContent = formatNum(total) + ' clicks';

            // Update power
            powerEl.textContent = '+' + formatNum(qty * unitPower);

            // Update korting
            if (pct > 0) {
                discountRow.style.display = 'flex';
                discountEl.textContent = '-' + (pct * 100).toFixed(0) + '%';
            } else {
                discountRow.style.display = 'none';
            }

            // Kleur kosten: rood als te duur
            if (total > myClicks) {
                costEl.style.color = '#ff5c5c';
                if (buyBtn) buyBtn.disabled = true;
                preview.classList.add('too-expensive');
            } else {
                costEl.style.color = '#c9a44c';
                if (buyBtn) buyBtn.disabled = false;
                preview.classList.remove('too-expensive');
            }
        }

        input.addEventListener('input', update);
        input.addEventListener('change', update);

        // Quick-set knoppen (+1, +10, +100, +1000, max)
        card.querySelectorAll('[data-qty-multiply]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const mult = parseInt(btn.dataset.qtyMultiply) || 1;
                const current = parseInt(input.value) || 1;
                input.value = current * mult;
                update();
            });
        });

        card.querySelectorAll('[data-qty-set]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const val = btn.dataset.qtySet;
                if (val === 'max') {
                    input.value = card.dataset.maxAfford;
                } else {
                    input.value = val;
                }
                update();
            });
        });

        // Initial update
        update();
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.weapon-card.bulk').forEach(attachToCard);
    });

})();