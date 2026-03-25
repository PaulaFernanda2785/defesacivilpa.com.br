function abrirModalPerfil() {
    document.getElementById('modalPerfil').style.display = 'block';
}

function fecharModalPerfil() {
    document.getElementById('modalPerfil').style.display = 'none';
}

function limparFiltros() {
    const destino = new URL(window.location.href);
    destino.search = '';
    window.location.href = destino.pathname + destino.search;
}

function abrirModalCancelamento(motivo,data,usuario){
    document.getElementById('motivoCancelamento').innerText = motivo;
    document.getElementById('dataCancelamento').innerText = data;
    document.getElementById('usuarioCancelamento').innerText = usuario;
    document.getElementById('modalCancelamento').style.display='block';
}

function abrirModalCancelamentoBotao(button){
    abrirModalCancelamento(
        button.dataset.motivo || '',
        button.dataset.data || '',
        button.dataset.usuario || ''
    );
}

function fecharModalCancelamento(){
    document.getElementById('modalCancelamento').style.display='none';
}

function abrirModalCancelar(id){
 document.getElementById('cancelarId').value=id;
 document.getElementById('modalCancelar').style.display='block';
}
function fecharModalCancelar(){
 document.getElementById('modalCancelar').style.display='none';
}

function contarMotivo(el){
    document.getElementById('contadorMotivo').innerText =
        el.value.length + " / 500";
}

document.addEventListener('DOMContentLoaded', function () {
    const filtroVigentes = document.getElementById('vigentes');

    if (!filtroVigentes) {
        return;
    }

    filtroVigentes.addEventListener('change', function () {
        const form = filtroVigentes.closest('form');

        if (!form) {
            return;
        }

        const paginaAtual = form.querySelector('input[name="pagina"]');
        if (paginaAtual) {
            paginaAtual.value = '1';
        }

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }

        form.submit();
    });
});
