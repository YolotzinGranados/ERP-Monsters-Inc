/* ============================================
   MONSTER ERP — LAYOUT SYSTEM
   Etapa 15: Sidebar, Dark Mode, Toast, Modal
============================================ */

/* ===== SIDEBAR ===== */
function initSidebar() {
    const sidebar  = document.getElementById('sidebar');
    const wrapper  = document.getElementById('mainWrapper');
    const toggleBtn = document.getElementById('toggleSidebar');

    if (!sidebar || !toggleBtn) return;

    // Restore collapsed state
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed) {
        sidebar.classList.add('collapsed');
        wrapper?.classList.add('expanded');
    }

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        wrapper?.classList.toggle('expanded');
        localStorage.setItem(
            'sidebarCollapsed',
            sidebar.classList.contains('collapsed')
        );
    });

    // Mark active link
    const currentPage = window.location.pathname.split('/').pop();
    document.querySelectorAll('.sidebar-nav a').forEach(link => {
        const href = link.getAttribute('href')?.split('/').pop();
        if (href === currentPage) {
            link.classList.add('active');
        }
    });

    // Mobile overlay close
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 768
            && !sidebar.contains(e.target)
            && !e.target.closest('#mobileMenuBtn')) {
            sidebar.classList.remove('mobile-open');
        }
    });
}

/* ===== DARK MODE ===== */
function initDarkMode() {
    const btn = document.getElementById('darkModeBtn');
    if (!btn) return;

    const saved = localStorage.getItem('darkMode') === 'true';
    if (saved) {
        document.body.classList.add('dark');
        btn.textContent = '☀️';
    }

    btn.addEventListener('click', () => {
        document.body.classList.toggle('dark');
        const isDark = document.body.classList.contains('dark');
        localStorage.setItem('darkMode', isDark);
        btn.textContent = isDark ? '☀️' : '🌙';
    });
}

/* ===== TOAST SYSTEM ===== */
function showToast(message, type = 'success', duration = 3500) {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        document.body.appendChild(container);
    }

    const icons = {
        success: '✅',
        error:   '❌',
        warning: '⚠️',
        info:    'ℹ️'
    };

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<span>${icons[type] || '💬'}</span> ${message}`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.transition = 'opacity 0.3s, transform 0.3s';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(110%)';
        setTimeout(() => toast.remove(), 350);
    }, duration);
}

/* ===== MODAL SYSTEM ===== */
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.add('active');
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.remove('active');
}

// Close on overlay click
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
    }
    if (e.target.classList.contains('modal-close')) {
        e.target.closest('.modal-overlay')?.classList.remove('active');
    }
});

// Close on ESC
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => {
            m.classList.remove('active');
        });
    }
});

/* ===== LOADING STATE ===== */
function showLoading(containerId) {
    const el = document.getElementById(containerId);
    if (el) el.innerHTML = `<div class="loading-state"><div class="spinner"></div><p>Cargando...</p></div>`;
}

function showEmpty(containerId, icon = '📋', message = 'No hay datos disponibles') {
    const el = document.getElementById(containerId);
    if (el) el.innerHTML = `
        <div class="empty-state">
            <div class="empty-icon">${icon}</div>
            <p>${message}</p>
        </div>`;
}

/* ===== LOGOUT ===== */
function initLogout() {
    const btn = document.getElementById('logoutBtn');
    if (!btn) return;

    btn.addEventListener('click', async () => {
        try {
            await fetch(
                'http://localhost:3000/routes/auth.php?action=logout',
                { credentials: 'include' }
            );
        } catch (e) {}
        localStorage.removeItem('user');
        window.location.href = '../auth/login.html';
    });
}

/* ===== INIT ALL ===== */
document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initDarkMode();
    initLogout();
});

/* ===== EXPOSE GLOBALLY ===== */
window.showToast  = showToast;
window.openModal  = openModal;
window.closeModal = closeModal;
window.showLoading = showLoading;
window.showEmpty   = showEmpty;
