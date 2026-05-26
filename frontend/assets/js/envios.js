async function loadEnvios() {
    const response = await fetch(
        'http://localhost:3000/routes/envios.php',
        { credentials: 'include' }
    );

    const result = await response.json();
    const table = document.getElementById('enviosTable');
    table.innerHTML = '';

    result.data.forEach(envio => {
        table.innerHTML += `
            <tr>
                <td>${envio.id_envio}</td>
                <td>${envio.id_venta}</td>
                <td>${envio.cliente}</td>
                <td>${envio.fecha_estimada_entrega}</td>
                <td>${envio.nombre}</td>
                <td>${envio.numero_guia}</td>
                <td>
                    <select onchange="updateEstado(${envio.id_envio}, this.value)">
                        <option value="1">Pendiente</option>
                        <option value="2">En tránsito</option>
                        <option value="3">Entregado</option>
                        <option value="4">Cancelado</option>
                    </select>
                </td>
            </tr>
        `;
    });
}

async function createEnvio() {
    const idVenta = parseInt(document.getElementById('ventaInput').value);

    const response = await fetch(
        'http://localhost:3000/routes/envios.php',
        {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_venta: idVenta })
        }
    );

    const result = await response.json();
    showToast(result.message, result.success ? 'success' : 'error');
    loadEnvios();
}

async function updateEstado(id, estado) {
    const response = await fetch(
        `http://localhost:3000/routes/envios.php?id=${id}`,
        {
            method: 'PUT',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_estado_envio: estado })
        }
    );

    const result = await response.json();
    showToast(result.message, result.success ? 'success' : 'error');
    loadEnvios();
}

async function logout() {
    await fetch(
        'http://localhost:3000/routes/auth.php?action=logout',
        { credentials: 'include' }
    );
    localStorage.removeItem('user');
    window.location.href = '../auth/login.html';
}

document.getElementById('createBtn').addEventListener('click', createEnvio);
document.getElementById('logoutBtn').addEventListener('click', logout);

loadEnvios();