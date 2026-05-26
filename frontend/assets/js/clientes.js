/* ============================================
   MONSTER ERP — CLIENTES JS
   Etapa 15: usa layout.js (showToast, openModal, closeModal)
============================================ */

const API = 'http://localhost:3000/routes/clientes.php';
let clientes    = [];
let editingId   = null;
let deletingId  = null;
let activeFilter = 'todos';

/* ===== LOAD ===== */
async function loadClientes() {
    const tbody = document.getElementById('clientesTable');
    tbody.innerHTML = `<tr><td colspan="6"><div class="loading-state"><div class="spinner"></div></div></td></tr>`;

    try {
        const res = await fetch(API, { credentials: 'include' });
        const result = await res.json();
        clientes = result.data || [];
        applyFilter(activeFilter);
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--accent-red);">❌ Error al cargar clientes</td></tr>`;
    }
}

/* ===== FILTER ===== */
function filterClientes(tipo) {
    activeFilter = tipo;
    applyFilter(tipo);
}

function applyFilter(tipo) {
    const filtered = tipo === 'todos'
        ? clientes
        : clientes.filter(c => c.tipo_cliente === tipo);
    renderTable(filtered);
}

/* ===== RENDER ===== */
function renderTable(data) {
    const tbody = document.getElementById('clientesTable');
    const count = document.getElementById('clienteCount');
    count.textContent = `${data.length} cliente${data.length !== 1 ? 's' : ''}`;

    if (!data.length) {
        tbody.innerHTML = `
            <tr><td colspan="6">
                <div class="empty-state">
                    <div class="empty-icon">👥</div>
                    <p>No hay clientes que coincidan</p>
                </div>
            </td></tr>`;
        return;
    }

    tbody.innerHTML = data.map(c => `
        <tr>
            <td>
                <span class="badge ${c.tipo_cliente === 'Corporativo' ? 'badge-purple' : 'badge-blue'}">
                    ${c.tipo_cliente}
                </span>
            </td>
            <td><strong>${c.nombre_razon_social}</strong></td>
            <td style="font-family:monospace;font-size:13px;">${c.rfc || '—'}</td>
            <td>${c.correo || '—'}</td>
            <td>${c.telefono || '—'}</td>
            <td>
                <button class="btn btn-warning btn-sm btn-icon" onclick='startEdit(${JSON.stringify(c)})' title="Editar">✏️</button>
                <button class="btn btn-danger btn-sm btn-icon" onclick='startDelete(${c.id_cliente})' title="Eliminar">🗑️</button>
            </td>
        </tr>
    `).join('');
}

/* ===== SEARCH ===== */
document.getElementById('searchInput').addEventListener('input', (e) => {
    const q = e.target.value.toLowerCase();
    const filtered = clientes.filter(c =>
        c.nombre_razon_social?.toLowerCase().includes(q) ||
        c.rfc?.toLowerCase().includes(q) ||
        c.correo?.toLowerCase().includes(q)
    );
    renderTable(filtered);
});

/* ===== SAVE (CREATE / UPDATE) ===== */
document.getElementById('saveBtn').addEventListener('click', async () => {
    const nombre = document.getElementById('nombre').value.trim();
    if (!nombre) {
        showToast('El nombre es obligatorio', 'warning');
        return;
    }

    const body = {
        tipo_cliente:        document.getElementById('tipoCliente').value,
        nombre_razon_social: nombre,
        rfc:                 document.getElementById('rfc').value.trim(),
        correo:              document.getElementById('correo').value.trim(),
        telefono:            document.getElementById('telefono').value.trim()
    };

    const method = editingId ? 'PUT' : 'POST';
    const url    = editingId ? `${API}?id=${editingId}` : API;

    try {
        const res    = await fetch(url, {
            method,
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const result = await res.json();

        closeModal('clienteModal');
        showToast(result.message || 'Cliente guardado', 'success');
        resetForm();
        loadClientes();
    } catch (e) {
        showToast('Error al guardar cliente', 'error');
    }
});

/* ===== EDIT ===== */
function startEdit(cliente) {
    editingId = cliente.id_cliente;
    document.getElementById('modalTitle').textContent = 'Editar cliente';
    document.getElementById('tipoCliente').value = cliente.tipo_cliente;
    document.getElementById('nombre').value      = cliente.nombre_razon_social;
    document.getElementById('rfc').value         = cliente.rfc || '';
    document.getElementById('correo').value      = cliente.correo || '';
    document.getElementById('telefono').value    = cliente.telefono || '';
    openModal('clienteModal');
}

/* ===== DELETE ===== */
function startDelete(id) {
    deletingId = id;
    openModal('deleteModal');
}

document.getElementById('confirmDeleteBtn').addEventListener('click', async () => {
    if (!deletingId) return;

    try {
        const res    = await fetch(`${API}?id=${deletingId}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        const result = await res.json();

        closeModal('deleteModal');
        showToast(result.message || 'Cliente eliminado', 'success');
        deletingId = null;
        loadClientes();
    } catch (e) {
        showToast('Error al eliminar cliente', 'error');
    }
});

/* ===== RESET FORM ===== */
function resetForm() {
    editingId = null;
    document.getElementById('modalTitle').textContent = 'Nuevo cliente';
    document.getElementById('tipoCliente').value = 'Individual';
    document.getElementById('nombre').value      = '';
    document.getElementById('rfc').value         = '';
    document.getElementById('correo').value      = '';
    document.getElementById('telefono').value    = '';
}

// Reset form when modal closes
document.getElementById('clienteModal').addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay') || e.target.classList.contains('modal-close')) {
        resetForm();
    }
});

// Init
loadClientes();
