const form = document.getElementById('loginForm');
const message = document.getElementById('message');

form.addEventListener('submit', async (e) => {

    e.preventDefault();

    const correo = document.getElementById('correo').value;
    const password = document.getElementById('password').value;

    try {

        const response = await fetch(
            'http://localhost:3000/routes/auth.php?action=login',
            {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ correo, password })
            }
        );

        const result = await response.json();

        if (!result.success) {
            message.innerText = result.message;
            return;
        }

        localStorage.setItem('user', JSON.stringify(result.data.user));
        window.location.href = '../erp/dashboard.html';

    } catch (error) {
        console.error(error);
        message.innerText = 'Error de conexión';
    }
});