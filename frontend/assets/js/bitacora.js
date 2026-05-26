async function apiRequest(action, options = {}) {
    const url = `../../backend/api/erp.php?action=${action}`;
    const response = await fetch(url, {
        headers: { 'Content-Type': 'application/json' },
        ...options
    });
    const data = await response.json();
    if (!response.ok || data.error) {
        throw new Error(data.error || 'No fue posible completar la operación.');
    }
    return data;
}

document.addEventListener('DOMContentLoaded', () => {
    const logsTable = document.getElementById('logsTable');

    async function fetchLogs() {
        try {
            const logs = await apiRequest('get_logs');
            renderLogs(logs);
        } catch (error) {
            logsTable.innerHTML = `<tr><td colspan="3" class="alert alert-danger">${error.message}</td></tr>`;
        }
    }

    function renderLogs(logs) {
        if (!logs.length) {
            logsTable.innerHTML = `<tr><td colspan="3" class="empty-state">No hay registros en la bitácora.</td></tr>`;
            return;
        }
        logsTable.innerHTML = logs.map(log => `<tr><td>${log.fecha}</td><td>${log.empleado}</td><td>${log.accion}</td></tr>`).join('');
    }

    fetchLogs();
});