(function () {
    if (!window.AlertaFormPage || typeof window.AlertaFormPage.init !== 'function') {
        return;
    }

    window.AlertaFormPage.init({
        formId: 'alertaForm',
        mapId: 'mapa',
        geojsonFieldId: 'area_geojson',
        areaOrigemFieldId: 'area_origem',
        areaOrigemBadgeId: 'areaOrigemBadge',
        inicioFieldId: 'inicio_alerta',
        fimFieldId: 'fim_alerta',
        imageInputId: 'informacoesInput',
        imageDropzoneId: 'informacoesDropzone',
        imagePreviewId: 'informacoesPreview',
        imageTitleId: 'informacoesTitle',
        imageDetailsId: 'informacoesDetails',
        imageClearButtonId: 'informacoesClear',
        currentImageUrl: '',
        currentImageName: '',
        kmlInputId: 'kmlInput',
        kmlDropzoneId: 'kmlDropzone',
        kmlTitleId: 'kmlTitle',
        kmlDetailsId: 'kmlDetails',
        kmlPillsId: 'kmlPills',
        kmlClearButtonId: 'kmlClear',
        currentKmlName: '',
        initialGeojson: null,
        initialAreaOrigem: 'DESENHO',
        territorioPreviewUrl: '/pages/alertas/territorio_preview.php'
    });
})();
