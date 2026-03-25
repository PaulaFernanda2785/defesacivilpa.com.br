(function () {
    const form = document.querySelector('form[action="salvar_senha.php"]');
    const senhaInput = document.getElementById('senha');
    const confirmacaoInput = document.getElementById('confirmacao');
    const modal = document.getElementById('modalSenhaErro');
    const modalBody = document.getElementById('modalSenhaErroBody');

    function abrirModalSenhaErro(mensagem) {
        if (!modal || !modalBody) {
            return;
        }

        modalBody.textContent = mensagem;
        modal.classList.add('ativo');
        modal.style.display = 'block';
    }

    function fecharModalSenhaErro() {
        if (!modal) {
            return;
        }

        modal.classList.remove('ativo');
        modal.style.display = 'none';
    }

    function limparEstadoErro() {
        [senhaInput, confirmacaoInput].forEach(function (input) {
            if (input) {
                input.classList.remove('is-invalid');
            }
        });
    }

    function validarConfirmacao() {
        if (!senhaInput || !confirmacaoInput) {
            return true;
        }

        limparEstadoErro();

        if (confirmacaoInput.value === '' || senhaInput.value === confirmacaoInput.value) {
            return true;
        }

        senhaInput.classList.add('is-invalid');
        confirmacaoInput.classList.add('is-invalid');
        abrirModalSenhaErro('A confirmacao da nova senha nao confere com a senha digitada. Revise os dois campos e tente novamente.');
        confirmacaoInput.focus();
        confirmacaoInput.select();
        return false;
    }

    if (modal && modal.dataset.autoOpen === '1' && modal.dataset.errorMessage) {
        abrirModalSenhaErro(modal.dataset.errorMessage);
    }

    if (confirmacaoInput) {
        confirmacaoInput.addEventListener('blur', function () {
            if (confirmacaoInput.value !== '' && senhaInput && senhaInput.value !== '') {
                validarConfirmacao();
            }
        });

        confirmacaoInput.addEventListener('input', limparEstadoErro);
    }

    if (senhaInput) {
        senhaInput.addEventListener('input', limparEstadoErro);
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            if (!validarConfirmacao()) {
                event.preventDefault();
            }
        });
    }

    window.fecharModalSenhaErro = fecharModalSenhaErro;
})();
