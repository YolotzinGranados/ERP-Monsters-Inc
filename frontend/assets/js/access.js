// Helper para peticiones a la API
async function apiRequest(action, options = {}) {
    const url = `../../api/erp.php?action=${action}`;
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

// Helper para obtener la sesión
const getSession = () => JSON.parse(sessionStorage.getItem('monster_erp_session') || 'null');

document.addEventListener('DOMContentLoaded', () => {
    const state = {
        employees: [],
        roles: [],
        permissions: [],
        branches: [],
        selectedRoleId: null,
    };

    const employeeTable = document.getElementById('employeeRows');
    const roleEditorSelect = document.getElementById('roleEditor');
    const permissionChecklist = document.getElementById('permissionChecklist');
    const savePermissionsBtn = document.getElementById('saveRoleChanges');
    const createEmployeeBtn = document.getElementById('createEmployee');

    async function fetchData() {
        try {
            const data = await apiRequest('initial_data');
            state.employees = data.employees;
            state.roles = data.roles;
            state.branches = data.branches;
            state.allPermissions = [...new Set(data.rolePermissions.map(p => p.nombre_permiso))];
            render();
        } catch (error) {
            showToast('Error al cargar datos de acceso: ' + error.message, 'error');
        }
    }

    function render() {
        renderEmployees();
        renderRoleEditor();
    }

    function renderEmployees() {
        if (!employeeTable) return;
        employeeTable.innerHTML = state.employees.map(emp => `
            <tr>
                <td>${escapeHtml(emp.nombre)}</td>
                <td style="font-size: 13px;">${escapeHtml(emp.correo)}</td>
                <td><span class="badge badge-purple">${escapeHtml(emp.rol)}</span></td>
                <td>${escapeHtml(emp.sucursal || 'N/A')}</td>
                <td>
                    <span class="badge ${emp.activo ? 'badge-green' : 'badge-red'}">
                        ${emp.activo ? 'Activo' : 'Inactivo'}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-ghost" onclick="toggleEmployee(${emp.id}, ${emp.activo})">${emp.activo ? 'Desactivar' : 'Activar'}</button>
                </td>
            </tr>
        `).join('');
    }

    function renderRoleEditor() {
        if (!roleEditorSelect) return;
        const employeeRoleSelect = document.getElementById('employeeRole');
        const options = state.roles.map(role =>
            `<option value="${role.id}">${escapeHtml(role.nombre)}</option>`
        ).join('');

        roleEditorSelect.innerHTML = options;
        if (employeeRoleSelect) employeeRoleSelect.innerHTML = options;

        if (state.selectedRoleId) {
            roleEditorSelect.value = state.selectedRoleId;
        } else if (state.roles.length > 0) {
            state.selectedRoleId = state.roles[0].id;
            roleEditorSelect.value = state.selectedRoleId;
        }
        
        if (state.selectedRoleId) {
            loadPermissionsForRole(state.selectedRoleId);
        }
    }

    async function loadPermissionsForRole(roleId) {
        if (!permissionChecklist) return;
        permissionChecklist.innerHTML = '<div class="spinner"></div>';
        try {
            const permissions = await apiRequest(`get_role_permissions&role_id=${roleId}`);
            state.permissions = permissions;
            renderPermissions();
        } catch (error) {
            showToast('Error al cargar permisos: ' + error.message, 'error');
            permissionChecklist.innerHTML = `<p class="alert alert-danger">${error.message}</p>`;
        }
    }

    function renderPermissions() {
        if (!permissionChecklist) return;
        permissionChecklist.innerHTML = state.permissions.map(p => `
            <label class="checkbox-item">
                <input type="checkbox" value="${p.id_permiso}" ${p.asignado ? 'checked' : ''}>
                <span>${escapeHtml(p.nombre_permiso)}</span>
            </label>
        `).join('');
    }

    roleEditorSelect?.addEventListener('change', (e) => {
        state.selectedRoleId = e.target.value;
        loadPermissionsForRole(state.selectedRoleId);
    });

    savePermissionsBtn?.addEventListener('click', async () => {
        const selectedPermissions = [...permissionChecklist.querySelectorAll('input:checked')].map(input => input.value);
        
        try {
            await apiRequest('save_role_permissions', {
                method: 'POST',
                body: JSON.stringify({
                    rol_id: state.selectedRoleId,
                    permisos: selectedPermissions,
                    empleado: getSession()?.id
                })
            });
            showToast('Permisos guardados con éxito', 'success');
            fetchData(); // Recargar para reflejar cambios
        } catch (error) {
            showToast('Error al guardar permisos: ' + error.message, 'error');
        }
    });

    createEmployeeBtn?.addEventListener('click', async () => {
        const nombre = document.getElementById('employeeName').value;
        const correo = document.getElementById('employeeEmail').value;
        const rol_id = document.getElementById('employeeRole').value;
        const sucursal_id = document.getElementById('employeeBranch').value;

        try {
            await apiRequest('create_employee', {
                method: 'POST',
                body: JSON.stringify({
                    nombre,
                    correo,
                    rol_id,
                    sucursal_id,
                    empleado: getSession()?.id
                })
            });
            showToast('Empleado creado con éxito', 'success');
            fetchData(); // Recargar
        } catch (error) {
            showToast('Error al crear empleado: ' + error.message, 'error');
        }
    });

    // Inicializar
    fetchData();
});

// Funciones globales para botones en la tabla (si es necesario)
async function toggleEmployee(id, isActive) {
    const action = isActive ? 'desactivar' : 'reactivar';
    if (!confirm(`¿Estás seguro de que quieres ${action} a este empleado?`)) return;

    try {
        await apiRequest('toggle_employee', {
            method: 'POST',
            body: JSON.stringify({
                id: id,
                empleado: getSession()?.id
            })
        });
        showToast(`Empleado ${action}do.`, 'success');
        location.reload(); // Recargar para ver el cambio
    } catch (error) { showToast(error.message, 'error'); }
}