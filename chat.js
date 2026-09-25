/* ============================================================
   VENDETTA — Chat (real-time met polling)
   ============================================================ */
(function () {

    const chatBox = document.getElementById('chat-box');
    if (!chatBox) return;

    const channel    = chatBox.dataset.channel;
    const familyId   = parseInt(chatBox.dataset.family) || 0;
    const myId       = parseInt(chatBox.dataset.myId);
    const isAdmin    = chatBox.dataset.isAdmin === '1';
    const messagesEl = document.getElementById('chat-messages');
    const form       = document.getElementById('chat-form');
    const input      = document.getElementById('chat-input');
    const statusEl   = document.getElementById('chat-status');
    const onlineEl   = document.getElementById('chat-online-list');

    let lastId = parseInt(chatBox.dataset.lastId) || 0;
    let isAtBottom = true;

    // ============================================================
    // Helpers
    // ============================================================
    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;',
            '"': '&quot;', "'": '&#39;'
        }[m]));
    }

    function rankColor(xp) {
        // Simpele benadering — backend stuurt al xp
        if (xp >= 50000) return '#ffb040';
        if (xp >= 25000) return '#e8c877';
        if (xp >= 12000) return '#c9a44c';
        if (xp >= 6000)  return '#b06aff';
        if (xp >= 3000)  return '#4a9dff';
        if (xp >= 1500)  return '#58e08c';
        if (xp >= 700)   return '#8ac9ff';
        return '#a08d75';
    }

    function timeAgo(dateStr) {
        const d = new Date(dateStr.replace(' ', 'T'));
        const diff = Math.floor((Date.now() - d.getTime()) / 1000);
        if (diff < 60) return 'nu';
        if (diff < 3600) return Math.floor(diff / 60) + 'm';
        return d.getHours().toString().padStart(2, '0') + ':' + d.getMinutes().toString().padStart(2, '0');
    }

    function scrollBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    // ============================================================
    // Rendering
    // ============================================================
    function renderMessage(msg) {
        const color = rankColor(msg.xp);
        const isMine = msg.is_mine || msg.user_id === myId;
        const canDelete = isMine || isAdmin;

        const div = document.createElement('div');
        div.className = 'chat-msg' + (isMine ? ' mine' : '');
        div.dataset.id = msg.id;

        div.innerHTML = `
            <div class="cm-avatar" style="border-color:${color};">
                ${escapeHtml(msg.username.charAt(0).toUpperCase())}
            </div>
            <div class="cm-body">
                <div class="cm-head">
                    <strong style="color:${color};">${escapeHtml(msg.username)}</strong>
                    <small class="cm-rank">${escapeHtml(msg.rank_title || '')}</small>
                    <time>${timeAgo(msg.created_at)}</time>
                    ${canDelete ? `<button type="button" class="cm-delete" data-id="${msg.id}" title="Verwijderen">×</button>` : ''}
                </div>
                <div class="cm-text">${escapeHtml(msg.body).replace(/\n/g, '<br>')}</div>
            </div>
        `;
        return div;
    }

    function appendMessage(msg, animate) {
        const empty = messagesEl.querySelector('.chat-empty');
        if (empty) empty.remove();

        const el = renderMessage(msg);
        if (animate) el.classList.add('new');
        messagesEl.appendChild(el);
    }

    function clearMessages() {
        messagesEl.innerHTML = '';
    }

    // ============================================================
    // Polling
    // ============================================================
    async function poll() {
        if (document.hidden) return;

        try {
            const res = await fetch(`api/chat_messages.php?channel=${channel}&after=${lastId}`);
            const data = await res.json();

            if (!data.success) return;

            if (data.messages.length > 0) {
                if (data.initial_load) {
                    clearMessages();
                    data.messages.forEach(m => {
                        appendMessage(m, false);
                        lastId = Math.max(lastId, m.id);
                    });
                    scrollBottom();
                } else {
                    data.messages.forEach(m => {
                        appendMessage(m, true);
                        lastId = Math.max(lastId, m.id);
                    });
                    if (isAtBottom) scrollBottom();
                }
            }
        } catch (e) {
            statusEl.textContent = '⚠️ Verbinding verbroken';
            statusEl.style.color = '#ff5c5c';
        }
    }

    // ============================================================
    // Online users
    // ============================================================
    async function pollOnline() {
        try {
            const res = await fetch('api/chat_online.php');
            const data = await res.json();
            if (!data.success) return;

            // Update count in tab
            const countEl = document.querySelector('.chat-tab.active .chat-tab-count');
            if (countEl) countEl.textContent = data.count + ' online';

            // Update list
            if (onlineEl) {
                onlineEl.innerHTML = data.online.map(u => `
                    <li data-user="${u.id}">
                        <span class="cou-dot" style="background: ${rankColor(u.xp)};"></span>
                        <span class="cou-name" style="color: ${rankColor(u.xp)};">${escapeHtml(u.username)}</span>
                    </li>
                `).join('');
            }

            // Update sidebar header
            const header = document.querySelector('.chat-sidebar h3');
            if (header) header.textContent = `🟢 Online (${data.count})`;
        } catch (e) {}
    }

    // ============================================================
    // Form: bericht sturen
    // ============================================================
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = input.value.trim();
        if (!body) return;

        const sendBtn = document.getElementById('chat-send');
        sendBtn.disabled = true;
        statusEl.textContent = '';
        statusEl.style.color = '';

        try {
            const res = await fetch('api/chat_send.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    channel: channel,
                    body: body
                })
            });
            const data = await res.json();

            if (data.error) {
                statusEl.textContent = '❌ ' + data.error;
                statusEl.style.color = '#ff5c5c';
                setTimeout(() => { statusEl.textContent = ''; }, 3000);
            } else {
                appendMessage(data.message, true);
                lastId = Math.max(lastId, data.message.id);
                input.value = '';
                scrollBottom();
                statusEl.textContent = '✅ Verzonden';
                statusEl.style.color = '#58e08c';
                setTimeout(() => { statusEl.textContent = ''; }, 1500);
            }
        } catch (e) {
            statusEl.textContent = '❌ Verzenden mislukt';
            statusEl.style.color = '#ff5c5c';
        }

        sendBtn.disabled = false;
        input.focus();
    });

    // Enter om te verzenden
    input?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.dispatchEvent(new Event('submit'));
        }
    });

    // ============================================================
    // Verwijderen
    // ============================================================
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.cm-delete');
        if (!btn) return;

        const id = parseInt(btn.dataset.id);
        if (!confirm('Bericht verwijderen?')) return;

        try {
            const res = await fetch('api/chat_delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    id: id
                })
            });
            const data = await res.json();

            if (data.success) {
                const msgEl = document.querySelector(`.chat-msg[data-id="${id}"]`);
                if (msgEl) {
                    msgEl.style.transition = 'opacity .3s, transform .3s';
                    msgEl.style.opacity = '0';
                    msgEl.style.transform = 'translateX(-30px)';
                    setTimeout(() => msgEl.remove(), 300);
                }
            } else {
                if (window.Vendetta) Vendetta.toast('error', data.error || 'Verwijderen mislukt');
            }
        } catch (err) {}
    });

    // ============================================================
    // Scroll detectie
    // ============================================================
    messagesEl.addEventListener('scroll', () => {
        const threshold = 50;
        isAtBottom = (messagesEl.scrollHeight - messagesEl.scrollTop - messagesEl.clientHeight) < threshold;
    });

    // ============================================================
    // Init
    // ============================================================
    scrollBottom();

    // Polling: berichten elke 3 sec, online elke 15 sec
    setInterval(poll, 3000);
    setInterval(pollOnline, 15000);

    // Refresh als pagina weer zichtbaar wordt
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            poll();
            pollOnline();
        }
    });

    // Focus op input
    input?.focus();

})();