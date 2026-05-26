let inventory = [];

async function loadInventory()
{
    try {

        const response = await fetch(
            'http://localhost:3000/routes/inventario.php?action=list',
            {
                credentials: 'include'
            }
        );

        const result = await response.json();

        if (!result.success) {

            showToast(result.message, result.success ? 'success' : 'error');

            return;
        }

        inventory = result.data;

        renderTable(inventory);

    } catch (error) {

        console.error(error);
    }
}

function renderTable(data)
{
    const table =
        document.getElementById('inventoryTable');

    table.innerHTML = '';

    data.forEach(item => {

        const estado =
            item.cantidad_disponible <= 5
            ? 'Crítico'
            : 'Disponible';

        const clase =
            item.cantidad_disponible <= 5
            ? 'low-stock'
            : 'good-stock';

        table.innerHTML += `
        
            <tr>

                <td>${item.sucursal}</td>

                <td>${item.sku}</td>

                <td>${item.producto}</td>

                <td class="${clase}">
                    ${item.cantidad_disponible}
                </td>

                <td class="${clase}">
                    ${estado}
                </td>

                <td>

                    <input
                        type="number"
                        value="${item.cantidad_disponible}"
                        id="stock-${item.id_sucursal}-${item.id_producto}"
                        class="stock-input"
                    >

                    <button
                        onclick="
                            updateStock(
                                ${item.id_sucursal},
                                ${item.id_producto}
                            )
                        "
                    >
                        Guardar
                    </button>

                </td>

            </tr>
        `;
    });
}

async function updateStock(idSucursal, idProducto)
{
    const input = document.getElementById(
        `stock-${idSucursal}-${idProducto}`
    );

    const cantidad =
        parseInt(input.value);

    try {

        const response = await fetch(
            'http://localhost:3000/backend/routes/inventario.php?action=update-stock',
            {
                method: 'PUT',

                credentials: 'include',

                headers: {
                    'Content-Type': 'application/json'
                },

                body: JSON.stringify({
                    id_sucursal: idSucursal,
                    id_producto: idProducto,
                    cantidad_disponible: cantidad
                })
            }
        );

        const result = await response.json();

        showToast(result.message, result.success ? 'success' : 'error');

        loadInventory();

    } catch (error) {

        console.error(error);
    }
}

document
    .getElementById('searchInput')
    .addEventListener('input', (e) => {

        const text =
            e.target.value.toLowerCase();

        const filtered = inventory.filter(item =>

            item.producto
                .toLowerCase()
                .includes(text)

            ||

            item.sku
                .toLowerCase()
                .includes(text)
        );

        renderTable(filtered);
    });

async function logout()
{
    await fetch(
        'http://localhost:3000/backend/routes/auth.php?action=logout',
        {
            credentials: 'include'
        }
    );

    localStorage.removeItem('user');

    window.location.href =
        '../auth/login.html';
}

document
    .getElementById('logoutBtn')
    .addEventListener('click', logout);

loadInventory();