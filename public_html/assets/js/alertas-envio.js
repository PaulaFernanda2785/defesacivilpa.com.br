/* =====================================================
   ALERTAS — ENVIO MANUAL PARA COMPDEC (ANTI-DUPLICIDADE)
===================================================== */

document.addEventListener('click', async e => {

    const btn = e.target.closest('.btn-enviar-alerta');
    if (!btn) return;

    if (!confirm('Confirmar envio do alerta para as Defesas Civis Municipais?')) {
        return;
    }

    const alertaId = btn.dataset.alertaId;
    btn.disabled = true;
    btn.innerHTML = 'Enviando...';

    let offset = 0;
    let totalEnviados = 0;

    while (true) {

        const response = await fetch('/api/alertas/enviar_alerta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${encodeURIComponent(alertaId)}&offset=${encodeURIComponent(offset)}&csrf_token=${encodeURIComponent(window.APP_CSRF_TOKEN || '')}`
        });

        const res = await response.json();

        if (!res.ok) {
            alert(res.msg || 'Erro no envio');
            btn.disabled = false;
            return;
        }

        totalEnviados += 30;

        if (res.data.finalizado) break;

        offset = res.data.proximo_offset;
    }

    const agora = new Date();
    const dataLocal = agora.toLocaleDateString('pt-BR') + ' ' +
                      agora.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

    btn.outerHTML = `
        <span class="tag tag-sucesso">
            Enviado
            <br>
            <small>${dataLocal}</small>
        </span>
    `;
});


