async function loadFacturas() {
    const response = await fetch(
        'http://localhost:3000/routes/facturas.php',
        { credentials: 'include' }
    );

    const result = await response.json();
    const table = document.getElementById('facturasTable');
    table.innerHTML = '';

    result.data.forEach(factura => {
        table.innerHTML += `
            <tr>
                <td>${factura.sello_digital}</td>
                <td>${factura.id_factura}</td>
                <td>${factura.cliente}</td>
                <td>$${factura.total}</td>
                <td>${factura.uso_cfdi}</td>
                <td>${factura.fecha_emision}</td>
            </tr>
        `;
    });
}

async function generateFactura() {
    const idVenta = parseInt(document.getElementById('ventaInput').value);

    const response = await fetch(
        'http://localhost:3000/routes/facturas.php',
        {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_venta: idVenta })
        }
    );

    const result = await response.json();
    showToast(result.message, result.success ? 'success' : 'error');
    loadFacturas();
}

async function logout() {
    await fetch(
        'http://localhost:3000/routes/auth.php?action=logout',
        { credentials: 'include' }
    );
    localStorage.removeItem('user');
    window.location.href = '../auth/login.html';
}

document.getElementById('generateBtn').addEventListener('click', generateFactura);
document.getElementById('logoutBtn').addEventListener('click', logout);

loadFacturas();