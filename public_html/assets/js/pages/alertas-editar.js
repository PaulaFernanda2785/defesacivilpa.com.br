(function () {
    if (!window.AlertaFormPage || typeof window.AlertaFormPage.init !== 'function') {
        return;
    }

    const dataNode = document.getElementById('alerta-editar-data');
    let pageData = {};

    if (dataNode) {
        try {
            pageData = JSON.parse(dataNode.textContent || '{}');
        } catch (error) {
            pageData = {};
        }
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
        currentImageUrl: pageData.currentImageUrl || '',
        currentImageName: pageData.currentImageName || '',
        kmlInputId: 'kmlInput',
        kmlDropzoneId: 'kmlDropzone',
        kmlTitleId: 'kmlTitle',
        kmlDetailsId: 'kmlDetails',
        kmlPillsId: 'kmlPills',
        kmlClearButtonId: 'kmlClear',
        kmlBrowseButtonId: 'kmlBrowse',
        currentKmlName: pageData.currentKmlName || '',
        initialGeojson: pageData.initialGeojson || null,
        initialAreaOrigem: pageData.initialAreaOrigem || 'DESENHO',
        territorioPreviewUrl: '/pages/alertas/territorio_preview.php'
    });
})();
