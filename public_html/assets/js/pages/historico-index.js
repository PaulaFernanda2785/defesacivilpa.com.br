(function () {
    const modal = document.getElementById('modalHistorico');
    const modalBody = document.getElementById('historico-modal-body');

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function asText(value, fallback) {
        const normalized = String(value ?? '').trim();
        return normalized !== '' ? normalized : fallback;
    }

    function asHtmlBlock(value, fallback) {
        return escapeHtml(asText(value, fallback)).replace(/\r?\n/g, '<br>');
    }

    function abrirModalHistorico(registro) {
        if (!modal || !modalBody || !registro || typeof registro !== 'object') {
            return;
        }

        const usuario = asText(registro.usuario_nome, 'Usuario nao identificado');
        const acaoLabel = asText(registro.acao_label || registro.acao_descricao, 'Acao nao informada');
        const acaoCodigo = asText(registro.acao_codigo, 'SEM_CODIGO');
        const descricao = asHtmlBlock(registro.acao_descricao, 'Sem descricao complementar.');
        const referencia = asHtmlBlock(registro.referencia, 'Sem referencia complementar.');
        const dataHora = asText(registro.data_hora_local, 'Sem data/hora registrada');
        const ipUsuario = asText(registro.ip_usuario, 'Nao informado');
        const navegador = asHtmlBlock(registro.user_agent, 'Nao informado');

        modalBody.innerHTML = `
            <div class="historico-modal-grid">
                <div class="historico-modal-item">
                    <strong>Usuario</strong>
                    <p>${escapeHtml(usuario)}</p>
                </div>

                <div class="historico-modal-item">
                    <strong>Acao registrada</strong>
                    <p>${escapeHtml(acaoLabel)}</p>
                    <span class="historico-modal-code">${escapeHtml(acaoCodigo)}</span>
                </div>

                <div class="historico-modal-item">
                    <strong>Descricao operacional</strong>
                    <p>${descricao}</p>
                </div>

                <div class="historico-modal-item">
                    <strong>Referencia</strong>
                    <p>${referencia}</p>
                </div>

                <div class="historico-modal-item">
                    <strong>Data e hora</strong>
                    <p>${escapeHtml(dataHora)}</p>
                </div>

                <div class="historico-modal-item">
                    <strong>IP de origem</strong>
                    <p>${escapeHtml(ipUsuario)}</p>
                </div>

                <div class="historico-modal-item">
                    <strong>Navegador</strong>
                    <p>${navegador}</p>
                </div>
            </div>
        `;

        modal.classList.add('ativo');
        modal.style.display = 'block';
    }

    function fecharModalHistorico() {
        if (!modal) {
            return;
        }

        modal.classList.remove('ativo');
        modal.style.display = 'none';
    }

    function limparFiltrosHistorico() {
        const url = new URL(window.location.href);

        ['usuario_nome', 'acao_codigo', 'data_inicio', 'data_fim', 'page'].forEach(function (parametro) {
            url.searchParams.delete(parametro);
        });

        const queryString = url.searchParams.toString();
        window.location.href = queryString ? `${url.pathname}?${queryString}` : url.pathname;
    }

    window.abrirModalHistorico = abrirModalHistorico;
    window.fecharModalHistorico = fecharModalHistorico;
    window.limparFiltrosHistorico = limparFiltrosHistorico;
})();
