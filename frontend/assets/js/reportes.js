/* ============================================
   MONSTER ERP — REPORTES JS
   layout.js ya maneja: logout, dark mode, sidebar
============================================ */

function downloadReport(type) {
    const inicio = document.getElementById('fechaInicio')?.value || '';
    const fin    = document.getElementById('fechaFin')?.value    || '';
    const canal  = document.getElementById('canalFilter')?.value || '';
    const region = document.getElementById('regionFilter')?.value || '';

    const params = new URLSearchParams({ action: type });
    if (inicio) params.set('fecha_inicio', inicio);
    if (fin)    params.set('fecha_fin',    fin);
    if (canal)  params.set('canal',        canal);
    if (region) params.set('region',       region);

    const url = `http://localhost:3000/routes/reportes.php?${params}`;
    showToast(`Descargando reporte de ${type}...`, 'info');
    window.open(url, '_blank');
}
