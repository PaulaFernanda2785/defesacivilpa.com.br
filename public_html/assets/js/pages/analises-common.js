(function () {
    function submitForm(form) {
        if (!form) {
            return;
        }

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }

        form.submit();
    }

    document.querySelectorAll('[data-auto-submit]').forEach(function (field) {
        field.addEventListener('change', function () {
            submitForm(field.form);
        });
    });

    document.querySelectorAll('[data-clear-filtros]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (typeof window.limparFiltros === 'function') {
                window.limparFiltros();
            }
        });
    });

    document.querySelectorAll('[data-open-metodologia]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (typeof window.abrirModal === 'function') {
                window.abrirModal();
            }
        });
    });

    document.querySelectorAll('[data-close-metodologia]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (typeof window.fecharModal === 'function') {
                window.fecharModal();
            }
        });
    });
})();
