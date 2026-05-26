/* ============================================
   MONSTER ERP — DASHBOARD JS
   Etapa 15: Dashboard con layout.js
============================================ */

const API = 'http://localhost:3000/routes/dashboard.php';

async function loadStats() {
    try {
        const res = await fetch(`${API}?action=stats`, { credentials: 'include' });
        const result = await res.json();
        if (!result.success) return;

        const s = result.data;
        document.getElementById('ventasTotales').textContent   = s.ventas_totales;
        document.getElementById('ingresosTotales').textContent = '$' + Number(s.ingresos_totales).toLocaleString();
        document.getElementById('clientesTotales').textContent = s.clientes_totales;
        document.getElementById('inventarioCritico').textContent = s.inventario_critico;
    } catch (e) {
        showToast('Error cargando estadísticas', 'error');
    }
}

async function loadSalesByChannel() {
    try {
        const res = await fetch(`${API}?action=sales-by-channel`, { credentials: 'include' });
        const result = await res.json();
        const labels = result.data.map(i => i.canal_venta);
        const data   = result.data.map(i => i.ingresos);

        new Chart(document.getElementById('salesChannelChart'), {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Ingresos por canal',
                    data,
                    backgroundColor: ['#2563eb','#10b981','#f59e0b','#ef4444','#8b5cf6'],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    } catch (e) {}
}

async function loadSalesByRegion() {
    try {
        const res = await fetch(`${API}?action=sales-by-region`, { credentials: 'include' });
        const result = await res.json();
        const labels = result.data.map(i => i.nombre_region);
        const data   = result.data.map(i => i.ingresos);

        new Chart(document.getElementById('salesRegionChart'), {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data,
                    backgroundColor: ['#2563eb','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4'],
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    } catch (e) {}
}

async function loadTopProducts() {
    const tbody = document.getElementById('topProductsTable');
    try {
        const res = await fetch(`${API}?action=top-products`, { credentials: 'include' });
        const result = await res.json();

        if (!result.data?.length) {
            tbody.innerHTML = `<tr><td colspan="3"><div class="empty-state"><div class="empty-icon">📦</div><p>Sin datos de productos</p></div></td></tr>`;
            return;
        }

        tbody.innerHTML = result.data.map((p, i) => `
            <tr>
                <td><span class="badge badge-blue">${i + 1}</span></td>
                <td>${p.nombre}</td>
                <td><strong>${p.total_vendido}</strong></td>
            </tr>
        `).join('');
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="3" style="color:var(--accent-red); padding:20px;">Error cargando datos</td></tr>`;
    }
}

// Init
loadStats();
loadSalesByChannel();
loadSalesByRegion();
loadTopProducts();
