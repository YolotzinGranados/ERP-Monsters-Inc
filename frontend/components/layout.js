async function loadComponent(id, file) {

    const response = await fetch(file);

    const html = await response.text();

    document.getElementById(id).innerHTML = html;

}

async function initLayout(pageTitle) {

    await loadComponent(
        "sidebar-container",
        "../components/sidebar.html"
    );

    await loadComponent(
        "header-container",
        "../components/header.html"
    );

    document.getElementById("pageTitle").textContent = pageTitle;

    initializeSidebar();

    initializeDarkMode();

}

function initializeSidebar() {

    const sidebar = document.getElementById("sidebar");

    const toggleBtn = document.getElementById("toggleSidebar");

    toggleBtn.addEventListener("click", () => {

        sidebar.classList.toggle("collapsed");

    });

}

function initializeDarkMode() {

    const button = document.getElementById("darkModeBtn");

    button.addEventListener("click", () => {

        document.body.classList.toggle("dark");

    });

}