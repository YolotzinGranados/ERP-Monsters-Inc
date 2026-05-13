// ==========================================
// ESTADO GLOBAL DEL SISTEMA
// ==========================================
let productos = [
  { id: 1, nombre: 'Puerta Estándar', sku: 'PRD-001', precio: 1500, categoria: 'Puertas' },
  { id: 2, nombre: 'Puerta Premium Peluda', sku: 'PRD-002', precio: 3200, categoria: 'Puertas' },
];
let clientes = [
  { nombre: 'Boo S.A. de C.V.', rfc: 'BSC020101AAA', tipo: 'Moral', correo: 'boo@monsters.com', telefono: '55 1234 5678' },
  { nombre: 'Mike Wazowski', rfc: 'WAMI790304AAB', tipo: 'Física', correo: 'mike@monsters.com', telefono: '55 9876 5432' },
];
let carrito = [];
let contadorIdProducto = 3;

// ==========================================
// LOGIN / SESIÓN
// ==========================================
function iniciarSesion(event) {
  event.preventDefault();
  const correo = document.getElementById('login-correo').value;
  const pass = document.getElementById('login-pass').value;
  if (correo !== '' && pass !== '') {
    document.getElementById('pantalla-login').style.display = 'none';
    document.getElementById('app-dashboard').style.display = 'flex';
    renderizarTablaClientes();
  }
}

function cerrarSesion() {
  document.getElementById('app-dashboard').style.display = 'none';
  document.getElementById('pantalla-login').style.display = 'flex';
  document.getElementById('login-correo').value = '';
  document.getElementById('login-pass').value = '';
}

// ==========================================
// NAVEGACIÓN
// ==========================================
function mostrarSeccion(idSeccion, botonPresionado) {
  document.querySelectorAll('.seccion').forEach(sec => sec.classList.remove('activa'));
  document.getElementById(idSeccion).classList.add('activa');
  document.querySelectorAll('.menu-btn').forEach(btn => btn.classList.remove('activo'));
  botonPresionado.classList.add('activo');
}

// ==========================================
// TABS (Personal y Roles)
// ==========================================
function cambiarTab(idTab, botonPresionado) {
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('activa'));
  document.getElementById(idTab).classList.add('activa');
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('tab-activo'));
  botonPresionado.classList.add('tab-activo');
}

// ==========================================
// PUNTO DE VENTA
// ==========================================
function agregarProductoVenta() {
  const sku = document.getElementById('skuBusqueda').value.trim().toUpperCase();
  if (!sku) { alert('Ingresa un código SKU.'); return; }

  const producto = productos.find(p => p.sku.toUpperCase() === sku);
  if (!producto) { alert(`No se encontró el producto con SKU: ${sku}`); return; }

  const existente = carrito.find(item => item.sku === producto.sku);
  if (existente) {
    existente.cantidad++;
  } else {
    carrito.push({ ...producto, cantidad: 1 });
  }

  document.getElementById('skuBusqueda').value = '';
  renderizarCarrito();
}

function renderizarCarrito() {
  const tbody = document.getElementById('tablaCarrito');
  if (carrito.length === 0) {
    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:#94a3b8; padding:20px;">Sin productos agregados</td></tr>';
    document.getElementById('resumenSubtotal').textContent = '$0.00';
    document.getElementById('resumenTotal').textContent = '$0.00';
    return;
  }

  tbody.innerHTML = carrito.map((item, idx) => `
    <tr>
      <td>${item.nombre}</td>
      <td>
        <div style="display:flex; align-items:center; gap:6px;">
          <button onclick="cambiarCantidad(${idx}, -1)" style="width:24px; height:24px; padding:0; font-size:14px; background:#f1f5f9; color:#334155; border-radius:4px;">−</button>
          ${item.cantidad}
          <button onclick="cambiarCantidad(${idx}, 1)" style="width:24px; height:24px; padding:0; font-size:14px; background:#f1f5f9; color:#334155; border-radius:4px;">+</button>
        </div>
      </td>
      <td>$${item.precio.toFixed(2)}</td>
      <td>$${(item.precio * item.cantidad).toFixed(2)}</td>
    </tr>
  `).join('');

  const subtotal = carrito.reduce((acc, item) => acc + item.precio * item.cantidad, 0);
  document.getElementById('resumenSubtotal').textContent = `$${subtotal.toFixed(2)}`;
  document.getElementById('resumenDescuento').textContent = `-$0.00`;
  document.getElementById('resumenTotal').textContent = `$${subtotal.toFixed(2)}`;
}

function cambiarCantidad(idx, delta) {
  carrito[idx].cantidad += delta;
  if (carrito[idx].cantidad <= 0) carrito.splice(idx, 1);
  renderizarCarrito();
}

function procesarVenta() {
  if (carrito.length === 0) { alert('Agrega al menos un producto.'); return; }
  const total = carrito.reduce((acc, item) => acc + item.precio * item.cantidad, 0);
  alert(`✅ Venta procesada exitosamente.\nTotal cobrado: $${total.toFixed(2)}`);
  carrito = [];
  renderizarCarrito();
}

// ==========================================
// PRODUCTOS
// ==========================================
function registrarProducto(event) {
  event.preventDefault();
  const nombre = document.getElementById('nombreProducto').value;
  const sku = document.getElementById('skuProducto').value.toUpperCase();
  const precio = parseFloat(document.getElementById('precioProducto').value);
  const categoria = document.getElementById('categoriaProducto').value;

  if (productos.find(p => p.sku === sku)) {
    alert('Ya existe un producto con ese SKU.');
    return;
  }

  productos.push({ id: contadorIdProducto++, nombre, sku, precio, categoria });
  renderizarTablaProductos();
  event.target.reset();
  alert('✅ Producto registrado con éxito.');
}

function renderizarTablaProductos() {
  const tbody = document.getElementById('tablaProductos');
  if (!tbody) return;
  tbody.innerHTML = productos.map(p => `
    <tr>
      <td><span class="badge-sku">${p.sku}</span></td>
      <td>${p.nombre}</td>
      <td>$${p.precio.toFixed(2)}</td>
      <td><span class="tag">${p.categoria}</span></td>
      <td><button class="btn-sm btn-gray">Editar</button></td>
    </tr>
  `).join('');
}

function filtrarProductos() {
  const q = document.getElementById('buscarProducto').value.toLowerCase();
  const tbody = document.getElementById('tablaProductos');
  const filtrados = productos.filter(p =>
    p.nombre.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q)
  );
  tbody.innerHTML = filtrados.map(p => `
    <tr>
      <td><span class="badge-sku">${p.sku}</span></td>
      <td>${p.nombre}</td>
      <td>$${p.precio.toFixed(2)}</td>
      <td><span class="tag">${p.categoria}</span></td>
      <td><button class="btn-sm btn-gray">Editar</button></td>
    </tr>
  `).join('');
}

// ==========================================
// CLIENTES
// ==========================================
function registrarCliente(event) {
  event.preventDefault();
  const nombre = document.getElementById('nombreCliente').value;
  const rfc = document.getElementById('rfcCliente').value.toUpperCase();
  const correo = document.getElementById('correoCliente').value;
  const telefono = document.getElementById('telefonoCliente').value;
  const tipo = document.getElementById('tipoCliente').value === 'persona_moral' ? 'Moral' : 'Física';

  clientes.push({ nombre, rfc, correo, telefono, tipo });
  renderizarTablaClientes();
  event.target.reset();
  alert('✅ Cliente registrado con éxito.');
}

function renderizarTablaClientes() {
  const tbody = document.getElementById('tablaClientes');
  if (!tbody) return;
  tbody.innerHTML = clientes.map(c => `
    <tr>
      <td><strong>${c.nombre}</strong></td>
      <td><span class="mono">${c.rfc || '—'}</span></td>
      <td><span class="tag ${c.tipo === 'Moral' ? 'tag-purple' : 'tag-blue'}">${c.tipo}</span></td>
      <td>${c.correo}</td>
      <td>
        <button class="btn-sm btn-gray">Ver</button>
        <button class="btn-sm btn-gray">Editar</button>
      </td>
    </tr>
  `).join('');
}

function filtrarClientes(q) {
  const tbody = document.getElementById('tablaClientes');
  const filtrados = clientes.filter(c =>
    c.nombre.toLowerCase().includes(q.toLowerCase()) ||
    (c.rfc && c.rfc.toLowerCase().includes(q.toLowerCase())) ||
    c.correo.toLowerCase().includes(q.toLowerCase())
  );
  tbody.innerHTML = filtrados.map(c => `
    <tr>
      <td><strong>${c.nombre}</strong></td>
      <td><span class="mono">${c.rfc || '—'}</span></td>
      <td><span class="tag ${c.tipo === 'Moral' ? 'tag-purple' : 'tag-blue'}">${c.tipo}</span></td>
      <td>${c.correo}</td>
      <td>
        <button class="btn-sm btn-gray">Ver</button>
        <button class="btn-sm btn-gray">Editar</button>
      </td>
    </tr>
  `).join('');
}

// ==========================================
// INVENTARIO
// ==========================================
function abrirModalAjusteStock() {
  document.getElementById('modalAjusteStock').style.display = 'flex';
}

// ==========================================
// ENVÍOS
// ==========================================
function abrirModalEnvio(id) {
  document.getElementById('modalEnvioId').textContent = `#${id}`;
  document.getElementById('modalDetalleEnvio').style.display = 'flex';
}

// ==========================================
// FACTURACIÓN
// ==========================================
function generarCFDI() {
  const rfc = document.getElementById('rfcFactura').value;
  if (!rfc) { alert('Ingresa el RFC del cliente.'); return; }
  const folio = 'FAC-00' + (Math.floor(Math.random() * 90) + 10);
  alert(`✅ CFDI timbrado exitosamente.\nFolio: ${folio}\nRFC: ${rfc.toUpperCase()}`);
  const tbody = document.getElementById('tablaFacturas');
  const hoy = new Date().toISOString().split('T')[0];
  const newRow = document.createElement('tr');
  newRow.innerHTML = `
    <td><span class="mono">${folio}</span></td>
    <td><span class="mono">${rfc.toUpperCase()}</span></td>
    <td>${hoy}</td>
    <td>$1,500.00</td>
    <td><span class="status-badge status-ok">✅ Timbrada</span></td>
    <td><button class="btn-sm btn-gray">📄 Ver</button></td>
  `;
  tbody.prepend(newRow);
}

// ==========================================
// CERRAR MODALES
// ==========================================
function cerrarModal(idModal) {
  document.getElementById(idModal).style.display = 'none';
}

// Cerrar modal al hacer clic fuera
document.addEventListener('click', function(e) {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.style.display = 'none';
  }
});

// ==========================================
// INICIALIZACIÓN
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
  renderizarTablaProductos();
  renderizarTablaClientes();
});