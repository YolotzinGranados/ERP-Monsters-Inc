const getSession = () => JSON.parse(localStorage.getItem('user') || 'null');

document.addEventListener('DOMContentLoaded', () => {
    const state = {
        employees: [],
        roles: [],
        permissions: [],
        branches: [],
        selectedRoleId: null,
    };

    const employeeTable       = document.getElementById('employeeRows');
    const roleEditorSelect    = document.getElementById('roleEditor');
    const permissionChecklist = document.getElementById('permissionChecklist');
    const savePermissionsBtn  = document.getElementById('saveRoleChanges');
    const createEmployeeBtn   = document.getElementById('createEmployee');

    async function fetchData() {
        try {
            // Cargar empleados
            const empRes = await fetch('http://localhost:3000/routes/empleados.php?action=list', { credentials: 'include' });
            const empData = await empRes.json();
            state.employees = empData.data || [];

            // Cargar roles
            const rolRes = await fetch('http://localhost:3000/routes/roles.php?action=list', { credentials: 'include' });
            const rolData = await rolRes.json();
            state.roles = rolData.data || [];

            // Cargar sucursales
            const sucRes = await fetch('http://localhost:3000/routes/clientes.php?action=sucursales', { credentials: 'include' });
            const sucData = await sucRes.json();
            state.branches = sucData.data || [];

            render();
        } catch (error) {
            showToast('Error al cargar datos: ' + error.message, 'error');
        }
    }

    function render() {
        renderEmployees();
        renderRoleEditor();
        renderBranches();
    }

    function renderEmployees() {
        if (!employeeTable) return;
        if (!state.employees.length) {
            employeeTable.innerHTML = '<tr><td colspan="6"><div class="empty-state"><div class="empty-icon">👤</div><p>Sin empleados registrados</p></div></td></tr>';
            return;
        }
        employeeTable.innerHTML = state.employees.map(emp => `
            <tr>
                <td>${escapeHtml(emp.nombre)}</td>
                <td style="font-size:13px;">${escapeHtml(emp.correo)}</td>
                <td><span class="badge badge-purple">${escapeHtml(emp.rol || 'Sin rol')}</span></td>
                <td>${escapeHtml(emp.sucursal || 'N/A')}</td>
                <td>
                    <span class="badge ${emp.activo ? 'badge-green' : 'badge-red'}">
                        ${emp.activo ? 'Activo' : 'Inactivo'}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-ghost" onclick="toggleEmployee(${emp.id_empleado}, ${emp.activo ? 1 : 0})">
                        ${emp.activo ? 'Desactivar' : 'Activar'}
                    </button>
                </td>
            </tr>
        `).join('');
    }

    function renderRoleEditor() {
        if (!roleEditorSelect) return;
        const employeeRoleSelect = document.getElementById('employeeRole');
        const options = state.roles.map(role =>
            `<option value="${role.id_rol}">${escapeHtml(role.nombre_rol)}</option>`
        ).join('');

        roleEditorSelect.innerHTML = '<option value="">— Seleccionar rol —</option>' + options;
        if (employeeRoleSelect) employeeRoleSelect.innerHTML = '<option value="">— Seleccionar rol —</option>' + options;

        if (state.roles.length > 0) {
            state.selectedRoleId = state.roles[0].id_rol;
            roleEditorSelect.value = state.selectedRoleId;
            loadPermissionsForRole(state.selectedRoleId);
        }
    }

    function renderBranches() {
        const branchSelect = document.getElementById('employeeBranch');
        if (!branchSelect) return;
        branchSelect.innerHTML = '<option value="">— Sucursal (opcional) —</option>' +
            state.branches.map(b => `<option value="${b.id_sucursal}">${escapeHtml(b.nombre)}</option>`).join('');
    }

    async function loadPermissionsForRole(roleId) {
        if (!permissionChecklist || !roleId) return;
        permissionChecklist.innerHTML = '<div class="spinner"></div>';
        try {
            const response = await fetch(`http://localhost:3000/routes/roles.php?action=get_permissions&role_id=${roleId}`, {
                credentials: 'include'
            });
            const permissions = await response.json();
            state.permissions = Array.isArray(permissions) ? permissions : [];
            renderPermissions();
        } catch (error) {
            permissionChecklist.innerHTML = `<p style="color:var(--accent-red);padding:12px;">${error.message}</p>`;
        }
    }

    function renderPermissions() {
        if (!permissionChecklist) return;
        if (!state.permissions.length) {
            permissionChecklist.innerHTML = '<p style="color:var(--text-muted);padding:12px;">Sin permisos disponibles</p>';
            return;
        }
        permissionChecklist.innerHTML = state.permissions.map(p => `
            <label class="checkbox-item">
                <input type="checkbox" value="${p.id_permiso}" ${parseInt(p.asignado) ? 'checked' : ''}>
                <span>${escapeHtml(p.nombre_permiso)}</span>
            </label>
        `).join('');
    }

    roleEditorSelect?.addEventListener('change', (e) => {
        state.selectedRoleId = e.target.value;
        loadPermissionsForRole(state.selectedRoleId);
    });

    savePermissionsBtn?.addEventListener('click', async () => {
        if (!state.selectedRoleId) {
            showToast('Selecciona un rol primero', 'warning');
            return;
        }
        const selectedPermissions = [...permissionChecklist.querySelectorAll('input:checked')].map(i => i.value);
        try {
            const response = await fetch('http://localhost:3000/routes/roles.php?action=save_permissions', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    rol_id: state.selectedRoleId,
                    permisos: selectedPermissions,
                    empleado: getSession()?.id_empleado ?? 1
                })
            });
            const result = await response.json();
            showToast(result.success ? 'Permisos guardados' : result.message, result.success ? 'success' : 'error');
        } catch (error) {
            showToast('Error: ' + error.message, 'error');
        }
    });

    createEmployeeBtn?.addEventListener('click', async () => {
        const nombre      = document.getElementById('employeeName').value.trim();
        const correo      = document.getElementById('employeeEmail').value.trim();
        const rol_id      = document.getElementById('employeeRole').value;
        const sucursal_id = document.getElementById('employeeBranch').value;

        if (!nombre || !correo || !rol_id) {
            showToast('Nombre, correo y rol son obligatorios', 'warning');
            return;
        }

        try {
            const response = await fetch('http://localhost:3000/routes/empleados.php?action=create', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    nombre,
                    correo,
                    rol_id,
                    sucursal_id: sucursal_id || null,
                    empleado: getSession()?.id_empleado ?? 1
                })
            });
            const result = await response.json();
            if (result.success) {
                showToast('Empleado creado. Contraseña: Monster123*', 'success');
                document.getElementById('employeeName').value  = '';
                document.getElementById('employeeEmail').value = '';
                fetchData();
            } else {
                showToast(result.message || 'Error al crear empleado', 'error');
            }
        } catch (error) {
            showToast('Error: ' + error.message, 'error');
        }
    });

    fetchData();
});

async function toggleEmployee(id, isActive) {
    const accion = isActive ? 'desactivar' : 'activar';
    if (!confirm(`¿Seguro que quieres ${accion} a este empleado?`)) return;
    try {
        const response = await fetch('http://localhost:3000/routes/empleados.php?action=toggle', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_empleado: id,
                empleado: JSON.parse(localStorage.getItem('user') || '{}')?.id_empleado ?? 1
            })
        });
        const result = await response.json();
        showToast(result.success ? `Empleado ${accion}do` : result.message, result.success ? 'success' : 'error');
        location.reload();
    } catch (error) {
        showToast(error.message, 'error');
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}