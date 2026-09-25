/* ============================================================
   VENDETTA — Privéberichten (live chat)
   ============================================================ */
(function () {

    const chat = document.getElementById('pm-chat');
    if (!chat) return;

    const conversationId = parseInt(chat.dataset.conversation);
    const myId           = parseInt(chat.dataset.myId);
    let lastId           = parseInt(chat.dataset.lastId) || 0;
    const messagesEl     = document.getElementById('pm-messages');
    const form           = document.getElementById('pm-form');
    const input          = document.getElementById('pm-input');

    function escapeHtml(s) {
        return s.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }

    function appendMessage(msg, animate) {
        const isMine = msg.sender_id === myId;
        const el = document.createElement('div');
        el.className = 'pm-message ' + (isMine ? 'mine' : 'theirs');
        el.dataset.id = msg.id;
        if (animate) el.classList.add('new');

        const time = new Date(msg.created_at.replace(' ', 'T'))
            .toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' });

        el.innerHTML = `
            <div class="pmm-body">${escapeHtml(msg.body).replace(/\n/g, '<br>')}</div>
            <div class="pmm-meta">
                <span>${time}</span>
                ${isMine ? '<span class="pmm-read">✓</span>' : ''}
            </div>
        `;
        messagesEl.appendChild(el);
    }

    function scrollBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    // Initieel naar beneden
    scrollBottom();

    // Poll voor nieuwe berichten
    async function poll() {
        try {
            const res = await fetch(`api/get_messages.php?conversation=${conversationId}&after=${lastId}`);
            const data = await res.json();
            if (data.success && data.messages.length > 0) {
                data.messages.forEach(m => {
                    appendMessage(m, true);
                    lastId = Math.max(lastId, m.id);
                });
                scrollBottom();
            }
        } catch (e) {}
    }

    setInterval(poll, 4000);

    // Verstuur bericht via AJAX
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const body = input.value.trim();
            if (!body) return;

            const sendBtn = document.getElementById('pm-send');
            sendBtn.disabled = true;

            try {
                const res = await fetch('api/send_message.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        conversation_id: conversationId,
                        body: body
                    })
                });
                const data = await res.json();

                if (data.error) {
                    if (window.Vendetta) Vendetta.toast('error', data.error);
                    sendBtn.disabled = false;
                    return;
                }

                appendMessage({
                    id: data.id,
                    sender_id: myId,
                    body: data.body,
                    created_at: data.created_at
                }, true);
                lastId = Math.max(lastId, data.id);
                input.value = '';
                scrollBottom();
            } catch (e) {
                if (window.Vendetta) Vendetta.toast('error', 'Verzenden mislukt');
            }
            sendBtn.disabled = false;
            input.focus();
        });

        // Enter = verzenden (Shift+Enter = nieuwe regel)
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                form.dispatchEvent(new Event('submit'));
            }
        });
    }

})();