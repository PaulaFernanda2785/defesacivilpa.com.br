(function () {
    const form = document.getElementById('importarInmetForm');
    const input = document.getElementById('inmet_url');
    const clearButton = document.getElementById('limparInmetUrl');
    const loading = document.getElementById('loadingImportacao');
    const hint = document.getElementById('loadingImportacaoHint');

    const idleMessage = 'Cole a URL oficial do INMET para abrir a prévia de confirmação.';
    const loadingMessages = [
        'Validando a URL oficial do INMET...',
        'Consultando o XML oficial do alerta...',
        'Interpretando a geometria e os dados do aviso...',
        'Preparando a prévia para confirmação...'
    ];

    let loadingTimer = null;

    function stopLoadingCycle() {
        if (loadingTimer) {
            window.clearInterval(loadingTimer);
            loadingTimer = null;
        }
    }

    function resetIdleState() {
        stopLoadingCycle();

        if (loading) {
            loading.hidden = true;
            loading.textContent = 'Aguardando o início da importação.';
        }

        if (hint) {
            hint.textContent = idleMessage;
        }
    }

    function startLoadingCycle() {
        if (loading) {
            loading.hidden = false;
        }

        let index = 0;
        const renderMessage = function () {
            const message = loadingMessages[index] || loadingMessages[0];

            if (loading) {
                loading.textContent = message;
            }

            if (hint) {
                hint.textContent = message;
            }

            index = (index + 1) % loadingMessages.length;
        };

        renderMessage();
        stopLoadingCycle();
        loadingTimer = window.setInterval(renderMessage, 1400);
    }

    window.validarURLInmet = function validarURLInmet() {
        const url = input ? input.value.trim() : '';

        if (!url.includes('inmet.gov.br')) {
            alert('URL inválida. Informe um alerta oficial do INMET.');
            return false;
        }

        startLoadingCycle();
        return true;
    };

    if (clearButton && input) {
        clearButton.addEventListener('click', function () {
            input.value = '';
            input.focus();
            resetIdleState();
        });
    }

    if (input) {
        input.addEventListener('input', function () {
            if (input.value.trim() === '') {
                resetIdleState();
                return;
            }

            if (hint) {
                hint.textContent = 'URL preenchida. Quando quiser, clique em Importar alerta para consultar a prévia oficial.';
            }
        });
    }

    if (form) {
        form.addEventListener('reset', resetIdleState);
    }

    resetIdleState();
})();
