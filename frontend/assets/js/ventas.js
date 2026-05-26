const money = value => '$' + Number(value || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const getSession = () => JSON.parse(localStorage.getItem('user') || 'null');

async function apiRequest(action, options = {}) {
    const url = `http://localhost:3000/api/erp.php?action=${action}`;
    const response = await fetch(url, {
        credentials: 'include',
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
    const state = {
        products: [],
        clients: [],
        cart: []
    };

    const clienteSelect   = document.getElementById('clienteSelect');
    const sucursalSelect  = document.getElementById('sucursalSelect');
    const canalSelect     = document.getElementById('canalSelect');
    const productoSelect  = document.getElementById('productoSelect');
    const cantidadInput   = document.getElementById('cantidadInput');
    const addProductBtn   = document.getElementById('addProductBtn');
    const cartTable       = document.getElementById('cartTable');
    const totalText       = document.getElementById('totalText');
    const createSaleBtn   = document.getElementById('createSaleBtn');

    async function initialize() {
        try {
            const data = await apiRequest('initial_data');
            state.products = data.products;
            state.clients  = data.clients;

            populateSelect(clienteSelect,  data.clients.map(c  => ({ value: c.id,  text: c.nombre })));
            populateSelect(productoSelect, data.products.map(p => ({ value: p.id,  text: `${p.sku} - ${p.nombre}` })));

            addProductBtn.addEventListener('click', addToCart);
            createSaleBtn.addEventListener('click', processSale);

        } catch (error) {
            showToast('Error al cargar datos iniciales: ' + error.message, 'error');
        }
    }

    function populateSelect(select, options) {
        select.innerHTML = options.map(opt => `<option value="${opt.value}">${opt.text}</option>`).join('');
    }

    function getPriceForChannel(product, channel) {
        const channelMap = { 'Fisica': 'Fisica', 'Linea': 'Linea', 'Corporativo': 'Corporativo' };
        const dbChannel = channelMap[channel];
        return product.precios ? (product.precios[dbChannel] || 0) : 0;
    }

    function addToCart() {
        const productId = parseInt(productoSelect.value);
        const quantity  = parseInt(cantidadInput.value) || 1;

        if (!productId || quantity <= 0) {
            showToast('Selecciona un producto y una cantidad válida.', 'warning');
            return;
        }

        const existingItem = state.cart.find(item => item.productId === productId);
        if (existingItem) {
            existingItem.quantity += quantity;
        } else {
            state.cart.push({ productId, quantity });
        }
        renderCart();
        showToast('Producto agregado al carrito.', 'success');
    }

    function renderCart() {
        if (state.cart.length === 0) {
            cartTable.innerHTML = '<tr><td colspan="5"><div class="empty-state"><div class="empty-icon">🛒</div><p>Sin productos en el carrito</p></div></td></tr>';
            totalText.textContent = 'Total: 0 productos';
            return;
        }

        let totalAmount = 0;
        const currentChannel = canalSelect.value;

        cartTable.innerHTML = state.cart.map((item, index) => {
            const product  = state.products.find(p => p.id === item.productId);
            const price    = getPriceForChannel(product, currentChannel);
            const subtotal = price * item.quantity;
            totalAmount   += subtotal;

            return `
                <tr>
                    <td>${product.nombre}</td>
                    <td>${item.quantity}</td>
                    <td>${money(price)}</td>
                    <td>${money(subtotal)}</td>
                    <td><button class="btn btn-sm btn-danger" onclick="window.removeCartItem(${index})">Quitar</button></td>
                </tr>
            `;
        }).join('');

        totalText.textContent = `Total: ${money(totalAmount)}`;
    }

    window.removeCartItem = (index) => {
        state.cart.splice(index, 1);
        renderCart();
    };

    async function processSale() {
        if (state.cart.length === 0) {
            showToast('El carrito está vacío.', 'warning');
            return;
        }

        const session = getSession();

        const payload = {
            cliente:      parseInt(clienteSelect.value),
            canal:        canalSelect.value,
            id_sucursal:  parseInt(sucursalSelect.value),
            items:        state.cart.map(item => ({ id: item.productId, cantidad: item.quantity })),
            empleado:     session?.id_empleado ?? 1
        };

        try {
            createSaleBtn.disabled     = true;
            createSaleBtn.textContent  = 'Procesando...';

            const result = await apiRequest('create_sale', {
                method: 'POST',
                body: JSON.stringify(payload)
            });

            showToast(`Venta #${result.venta_id} creada con éxito.`, 'success');
            state.cart = [];
            renderCart();

        } catch (error) {
            showToast('Error al crear la venta: ' + error.message, 'error');
        } finally {
            createSaleBtn.disabled    = false;
            createSaleBtn.textContent = 'Crear Venta';
        }
    }

    initialize();
});