let PRODUCTS = [];
let cart = [];
let wishlist = new Set();

const fmt = value => '$' + Number(value || 0).toLocaleString('es-MX', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2
});
const $ = id => document.getElementById(id);
const qs = selector => document.querySelector(selector);
const qsa = selector => [...document.querySelectorAll(selector)];

function cartFooterElement() {
  const footer = $('cart-footer') || qs('.cart-footer');
  if (footer && !footer.id) {
    footer.id = 'cart-footer';
  }
  return footer;
}

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function showToast(message) {
  const toast = $('toast');
  const text = $('toast-msg');
  if (!toast || !text) return;

  text.textContent = message;
  toast.classList.add('show');
  clearTimeout(toast._timer);
  toast._timer = setTimeout(() => toast.classList.remove('show'), 3000);
}

async function apiRequest(action, options = {}) {
  const response = await fetch(`../../backend/api/tienda.php?action=${action}`, {
    headers: { 'Content-Type': 'application/json' },
    ...options
  });
  const data = await response.json();

  if (!response.ok || data.error) {
    throw new Error(data.error || 'No fue posible completar la operacion.');
  }

  return data;
}

async function loadProductsFromDB() {
  try {
    PRODUCTS = await apiRequest('get_products');
    populateSearchCategories();
    renderProducts();
    initWishlistButtons();
  } catch (error) {
    console.error(error);
    showToast('No se pudo cargar el catalogo desde sistema_ventas');
  }
}

function populateSearchCategories() {
  const select = qs('.search-bar select');
  if (!select) return;

  const categories = [...new Set(PRODUCTS.map(product => product.cat || 'General'))];
  select.innerHTML = ['Todo', ...categories]
    .map(category => `<option>${escapeHtml(category)}</option>`)
    .join('');
}

function productCard(product, compact = false) {
  const disabled = product.stock <= 0;
  const stockLabel = disabled ? 'Sin inventario' : `${product.stock} disponibles`;
  const category = product.cat || 'General';

  return `
    <div class="product-card" data-product-id="${product.id}">
      <div class="product-img" style="background:${compact ? '#f7fff5' : '#f0f8ff'}">
        <div class="product-badges">
          <span class="tag ${disabled ? 'tag-red' : 'tag-dark'}">${escapeHtml(stockLabel)}</span>
        </div>
        <div class="product-wishlist">
          <svg viewBox="0 0 24 24" stroke-width="2">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
          </svg>
        </div>
        <span class="p-icon">${escapeHtml(product.icon || 'MI')}</span>
      </div>
      <div class="product-info">
        <div class="product-brand">${escapeHtml(product.brand || 'Monster Inc.')} - ${escapeHtml(category)}</div>
        <div class="product-name">${escapeHtml(product.name)}</div>
        <div class="product-stars">
          <span class="stars">★★★★★</span>
          <span class="star-count">SKU ${escapeHtml(product.sku || product.id)}</span>
        </div>
        <div class="product-price">
          <span class="price-now">${fmt(product.price)}</span>
          <span class="price-save">Canal Linea</span>
        </div>
        <button class="product-add" ${disabled ? 'disabled style="opacity:.5;cursor:not-allowed"' : ''} onclick="addToCart(${product.id})">
          ${disabled ? 'Sin inventario' : 'Agregar al carrito'}
        </button>
      </div>
    </div>`;
}

function renderProducts(list = PRODUCTS) {
  const grids = qsa('.products-grid');
  if (grids.length === 0) return;

  if (list.length === 0) {
    grids[0].innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--muted)">No hay productos disponibles.</div>';
    return;
  }

  grids[0].innerHTML = list.map(product => productCard(product)).join('');

  if (grids[1]) {
    grids[1].innerHTML = list.slice(0, 3).map(product => productCard(product, true)).join('');
  }
}

function openCart() {
  $('cartOverlay')?.classList.add('open');
  $('cartPanel')?.classList.add('open');
  renderCart();
}

function closeCart() {
  $('cartOverlay')?.classList.remove('open');
  $('cartPanel')?.classList.remove('open');
}

function addToCart(productIdOrName) {
  const product = typeof productIdOrName === 'number'
    ? PRODUCTS.find(item => item.id === productIdOrName)
    : PRODUCTS.find(item => item.name.includes(productIdOrName) || String(productIdOrName).includes(item.brand || ''));

  if (!product) {
    showToast('Este producto no esta registrado en sistema_ventas');
    return;
  }

  if (product.stock <= 0) {
    showToast('Producto sin inventario disponible');
    return;
  }

  const existing = cart.find(item => item.product.id === product.id);
  if (existing) {
    if (existing.qty >= product.stock) {
      showToast('No hay mas inventario disponible');
      return;
    }
    existing.qty++;
  } else {
    cart.push({ product, qty: 1 });
  }

  updateCartBadge();
  renderCart();
  showToast(`${product.name.split(' ').slice(0, 3).join(' ')} agregado`);
}

function changeQty(productId, delta) {
  const item = cart.find(entry => entry.product.id === productId);
  if (!item) return;

  if (delta > 0 && item.qty >= item.product.stock) {
    showToast('No hay mas inventario disponible');
    return;
  }

  item.qty += delta;
  if (item.qty <= 0) {
    cart = cart.filter(entry => entry.product.id !== productId);
  }

  updateCartBadge();
  renderCart();
}

function removeFromCart(productId) {
  cart = cart.filter(entry => entry.product.id !== productId);
  updateCartBadge();
  renderCart();
  showToast('Producto eliminado del carrito');
}

function clearCart() {
  cart = [];
  updateCartBadge();
  renderCart();
  showToast('Carrito vaciado');
}

function updateCartBadge() {
  const badge = $('cart-count');
  if (badge) {
    badge.textContent = cart.reduce((total, item) => total + item.qty, 0);
  }
}

function renderCart() {
  const container = $('cart-items');
  const footer = cartFooterElement();
  if (!container || !footer) return;

  if (cart.length === 0) {
    container.innerHTML = `
      <div style="text-align:center;padding:60px 20px;color:var(--muted)">
        <div style="font-size:44px;margin-bottom:16px">MI</div>
        <div style="font-size:15px;font-weight:600;margin-bottom:6px">Tu carrito esta vacio</div>
        <div style="font-size:13px">Agrega productos del catalogo conectado al ERP.</div>
        <button class="btn btn-primary" style="margin-top:20px" onclick="closeCart()">Explorar productos</button>
      </div>`;
    footer.style.display = 'none';
    return;
  }

  footer.style.display = '';
  let subtotal = 0;

  container.innerHTML = cart.map(({ product, qty }) => {
    const line = product.price * qty;
    subtotal += line;
    const shortName = product.name.length > 38 ? `${product.name.slice(0, 38)}...` : product.name;

    return `
      <div class="cart-item">
        <div class="cart-item-img">${escapeHtml(product.icon || 'MI')}</div>
        <div class="cart-item-info">
          <div class="cart-item-name">${escapeHtml(product.brand || 'Monster Inc.')} - ${escapeHtml(shortName)}</div>
          <div class="cart-item-price">${fmt(product.price)}</div>
          <div class="cart-item-qty">
            <button class="qty-btn" onclick="changeQty(${product.id}, -1)">-</button>
            <span class="qty-val">${qty}</span>
            <button class="qty-btn" onclick="changeQty(${product.id}, 1)">+</button>
            <button onclick="removeFromCart(${product.id})" style="margin-left:auto;background:none;border:none;cursor:pointer;color:var(--muted);font-size:18px" title="Eliminar">x</button>
          </div>
          <div style="font-size:11px;color:var(--accent);font-weight:600;margin-top:4px">Total linea: ${fmt(line)}</div>
        </div>
      </div>`;
  }).join('');

  const freeShipping = subtotal >= 599;
  $('cart-subtotal').textContent = fmt(subtotal);
  $('cart-shipping-label').textContent = freeShipping ? 'Envio gratis' : 'Envio';
  $('cart-shipping-msg').textContent = freeShipping ? 'Aplica' : `Faltan ${fmt(599 - subtotal)}`;
  $('cart-total-amount').textContent = fmt(subtotal);
}

function ensureCheckoutModal() {
  if ($('checkout-modal')) return;

  const modal = document.createElement('div');
  modal.id = 'checkout-modal';
  modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1200;display:none;align-items:center;justify-content:center;padding:20px';
  modal.innerHTML = `
    <div style="width:min(460px,100%);background:#fff;border-radius:8px;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,.25)">
      <div style="display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:18px">
        <h2 style="margin:0;font-size:22px">Confirmar compra</h2>
        <button type="button" onclick="closeCheckout()" style="border:none;background:#f1f5f9;width:34px;height:34px;border-radius:50%;cursor:pointer">x</button>
      </div>
      <div style="display:grid;gap:12px">
        <label style="display:grid;gap:6px;font-size:13px;font-weight:600">
          Nombre del cliente
          <input id="checkout-name" type="text" value="Cliente Tienda Online" style="padding:12px;border:1px solid #d8dee8;border-radius:6px;font:inherit">
        </label>
        <label style="display:grid;gap:6px;font-size:13px;font-weight:600">
          Correo
          <input id="checkout-email" type="email" value="cliente.online@monsters.com" style="padding:12px;border:1px solid #d8dee8;border-radius:6px;font:inherit">
        </label>
        <label style="display:grid;gap:6px;font-size:13px;font-weight:600">
          Metodo de pago
          <select id="checkout-payment" style="padding:12px;border:1px solid #d8dee8;border-radius:6px;font:inherit">
            <option>Tarjeta</option>
            <option>PayPal</option>
            <option>SPEI</option>
            <option>OXXO Pay</option>
          </select>
        </label>
      </div>
      <div style="margin-top:18px;border-top:1px solid #e5e7eb;padding-top:16px;display:flex;justify-content:space-between;font-weight:700">
        <span>Total</span>
        <span id="checkout-total">$0.00</span>
      </div>
      <button type="button" onclick="confirmCheckout()" style="width:100%;margin-top:18px;padding:14px;border:none;border-radius:6px;background:var(--primary);color:#fff;font-weight:700;cursor:pointer">
        Pagar y registrar venta
      </button>
    </div>`;

  modal.addEventListener('click', event => {
    if (event.target === modal) closeCheckout();
  });

  document.body.appendChild(modal);
}

function openCheckout() {
  if (cart.length === 0) {
    showToast('Tu carrito esta vacio');
    return;
  }

  ensureCheckoutModal();
  const total = cart.reduce((sum, item) => sum + item.product.price * item.qty, 0);
  $('checkout-total').textContent = fmt(total);
  $('checkout-modal').style.display = 'flex';
}

function closeCheckout() {
  const modal = $('checkout-modal');
  if (modal) modal.style.display = 'none';
}

async function confirmCheckout() {
  const name = $('checkout-name')?.value.trim();
  const email = $('checkout-email')?.value.trim();
  const payment = $('checkout-payment')?.value || 'Tarjeta';

  if (!name || !email || !email.includes('@')) {
    showToast('Captura nombre y correo validos');
    return;
  }

  await checkout({ name, email, payment });
}

async function checkout(customer) {
  if (cart.length === 0) {
    showToast('Tu carrito esta vacio');
    return;
  }

  try {
    showToast('Registrando compra en sistema_ventas...');
    const data = await apiRequest('create_sale', {
      method: 'POST',
      body: JSON.stringify({
        cliente_nombre: customer.name,
        cliente_email: customer.email,
        metodo_pago: customer.payment,
        notas: 'Compra registrada desde tienda-de-Monsters-Inc.',
        items: cart.map(item => ({
          id: item.product.id,
          qty: item.qty
        }))
      })
    });

    cart = [];
    updateCartBadge();
    await loadProductsFromDB();
    renderCart();
    closeCheckout();
    closeCart();
    showToast(`¡Compra #${data.venta_id} exitosa! Inventario actualizado.`);
  } catch (error) {
    showToast(error.message);
  }
}

function ensureCartFooterIds() {
  const footer = cartFooterElement();
  if (!footer) return;

  footer.innerHTML = `
    <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-bottom:8px">
      <span>Subtotal</span><span id="cart-subtotal">$0.00</span>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:12px;color:#16a34a;margin-bottom:12px">
      <span id="cart-shipping-label">Envio</span>
      <span id="cart-shipping-msg">Gratis desde $599</span>
    </div>
    <div class="cart-total">
      <span>Total</span>
      <strong id="cart-total-amount">$0.00</strong>
    </div>
    <button class="btn btn-primary" style="width:100%;justify-content:center;font-size:15px;padding:16px;margin-bottom:8px" onclick="openCheckout()">Proceder al pago</button>
    <div style="display:flex;gap:8px">
      <button class="btn btn-outline" style="flex:1;justify-content:center;font-size:13px" onclick="closeCart()">Seguir comprando</button>
      <button class="btn btn-outline" style="padding:14px 16px;font-size:13px;color:var(--muted)" onclick="clearCart()" title="Vaciar carrito">Vaciar</button>
    </div>`;
  footer.style.display = 'none';
}

function toggleWishlist(productId, button) {
  const svg = button.querySelector('svg');
  if (wishlist.has(productId)) {
    wishlist.delete(productId);
    if (svg) {
      svg.style.fill = 'none';
      svg.style.stroke = 'var(--muted)';
    }
    showToast('Eliminado de favoritos');
    return;
  }

  wishlist.add(productId);
  if (svg) {
    svg.style.fill = 'var(--accent)';
    svg.style.stroke = 'var(--accent)';
  }
  showToast('Agregado a favoritos');
}

function initWishlistButtons() {
  qsa('.product-card').forEach(card => {
    const productId = Number(card.dataset.productId);
    const button = card.querySelector('.product-wishlist');
    if (!button || !productId) return;

    button.onclick = event => {
      event.stopPropagation();
      toggleWishlist(productId, button);
    };
    button.title = 'Agregar a favoritos';
  });
}

function openWishlist() {
  if (wishlist.size === 0) {
    showToast('Tu lista de favoritos esta vacia');
    return;
  }
  showToast(`${wishlist.size} producto(s) en favoritos`);
}

function initSearch() {
  const input = qs('.search-bar input');
  const select = qs('.search-bar select');
  const button = qs('.search-bar button');
  if (!input || !select || !button) return;

  function doSearch() {
    const query = input.value.trim().toLowerCase();
    const category = select.value;
    const results = PRODUCTS.filter(product => {
      const matchesCategory = category === 'Todo' || product.cat === category;
      const matchesQuery = !query ||
        product.name.toLowerCase().includes(query) ||
        (product.brand || '').toLowerCase().includes(query) ||
        (product.sku || '').toLowerCase().includes(query);

      return matchesCategory && matchesQuery;
    });

    renderProducts(results);
    initWishlistButtons();
    showToast(`${results.length} resultado(s)`);
    qs('.products-grid')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  button.addEventListener('click', doSearch);
  input.addEventListener('keydown', event => {
    if (event.key === 'Enter') doSearch();
  });
}

function initNavCats() {
  qsa('.nav-cat').forEach(category => {
    category.addEventListener('click', event => {
      event.preventDefault();
      qsa('.nav-cat').forEach(item => item.classList.remove('active'));
      category.classList.add('active');
      showToast(category.textContent.trim());
    });
  });
}

function initProductFilters() {
  qsa('.section-header .btn').forEach(button => {
    button.addEventListener('click', () => {
      showToast(`Ordenando: ${button.textContent.trim()}`);
    });
  });
}

function initCatCards() {
  qsa('.cat-card').forEach(card => {
    card.addEventListener('click', () => {
      const name = card.querySelector('.cat-name')?.textContent || 'Categoria';
      showToast(`Filtrando: ${name}`);
      qs('.products-grid')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });
}

function initTimer() {
  let h = 8;
  let m = 47;
  let s = 33;
  setInterval(() => {
    s--;
    if (s < 0) {
      s = 59;
      m--;
    }
    if (m < 0) {
      m = 59;
      h = Math.max(0, h - 1);
    }
    if ($('t-h')) $('t-h').textContent = String(h).padStart(2, '0');
    if ($('t-m')) $('t-m').textContent = String(m).padStart(2, '0');
    if ($('t-s')) $('t-s').textContent = String(s).padStart(2, '0');
  }, 1000);
}

function initNewsletter() {
  const button = qs('.newsletter-btn');
  const input = qs('.newsletter-input');
  if (!button || !input) return;

  const submit = () => {
    const email = input.value.trim();
    if (!email || !email.includes('@')) {
      showToast('Ingresa un correo valido');
      return;
    }
    showToast(`Suscripcion registrada para ${email}`);
    input.value = '';
  };

  button.addEventListener('click', submit);
  input.addEventListener('keydown', event => {
    if (event.key === 'Enter') submit();
  });
}

function initBrands() {
  qsa('.brand-item').forEach(brand => {
    brand.addEventListener('click', () => showToast(brand.textContent.trim()));
  });
}

function initSocial() {
  qsa('.social-btn').forEach(button => {
    button.addEventListener('click', () => showToast('Red social disponible'));
  });
}

function initPaymentIcons() {
  qsa('.payment-icon').forEach(icon => {
    icon.style.cursor = 'pointer';
    icon.addEventListener('click', () => showToast(`${icon.textContent.trim()} disponible`));
  });
}

function initReviews() {
  qsa('.review-card').forEach(card => {
    const button = document.createElement('button');
    button.textContent = 'Util';
    button.style.cssText = 'margin-top:12px;background:none;border:1px solid var(--border);border-radius:20px;padding:4px 14px;font-size:12px;cursor:pointer;color:var(--muted);font-family:var(--font-body)';
    button.addEventListener('click', () => {
      button.textContent = 'Gracias';
      button.style.color = 'var(--accent)';
      button.style.borderColor = 'var(--accent)';
    });
    card.appendChild(button);
  });
}

function initStickyHeader() {
  const header = qs('header');
  if (!header) return;

  window.addEventListener('scroll', () => {
    header.style.boxShadow = window.scrollY > 10 ? '0 4px 24px rgba(0,0,0,.12)' : 'none';
  }, { passive: true });
}

function initAnnouncementBar() {
  const bar = qs('.announcement-bar');
  if (!bar) return;

  const messages = [
    'Envio gratis en compras mayores a $599',
    'Precios conectados al canal Linea',
    'Inventario sincronizado con ERP',
    'Ventas registradas en sistema_ventas'
  ];
  let index = 0;

  setInterval(() => {
    index = (index + 1) % messages.length;
    const span = bar.querySelector('span');
    if (span) span.textContent = messages[index];
  }, 4000);
}

function initBackToTop() {
  const button = document.createElement('button');
  button.textContent = '^';
  button.title = 'Volver arriba';
  button.style.cssText = 'position:fixed;bottom:80px;right:24px;width:44px;height:44px;background:var(--primary);color:#fff;border:none;border-radius:50%;font-size:18px;font-weight:700;cursor:pointer;z-index:999;opacity:0;pointer-events:none;transition:opacity .3s;box-shadow:0 4px 16px rgba(0,0,0,.25)';
  document.body.appendChild(button);

  window.addEventListener('scroll', () => {
    const show = window.scrollY > 400;
    button.style.opacity = show ? '1' : '0';
    button.style.pointerEvents = show ? 'all' : 'none';
  }, { passive: true });

  button.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
}

function initProgressBar() {
  const fill = qs('.progress-fill');
  if (fill) fill.style.width = '67%';
}

function initProductAnimations() {
  qsa('.product-card, .cat-card, .review-card, .hero-card, .promo-card').forEach(card => {
    card.style.transition = 'opacity .45s ease, transform .45s ease, box-shadow .2s';
  });
}

document.addEventListener('DOMContentLoaded', async () => {
  ensureCartFooterIds();
  updateCartBadge();
  await loadProductsFromDB();
  initTimer();
  initSearch();
  initNavCats();
  initProductFilters();
  initCatCards();
  initNewsletter();
  initBrands();
  initSocial();
  initPaymentIcons();
  initReviews();
  initStickyHeader();
  initAnnouncementBar();
  initBackToTop();
  initProgressBar();
  initProductAnimations();
  qsa('a[href="#"]').forEach(link => {
    link.addEventListener('click', event => {
      event.preventDefault();
      renderProducts(PRODUCTS);
      initWishlistButtons();
      qs('.products-grid')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      showToast('Catalogo actualizado desde sistema_ventas');
    });
  });
});
