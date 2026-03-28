const form = document.getElementById('loginForm');

(async function initLogin() {
    try {
        const me = await apiRequest('/auth/me');
        redirectByRole(me.data.vai_tro);
    } catch (error) {
    }
})();

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const body = {
        ten_dang_nhap: document.getElementById('ten_dang_nhap').value.trim(),
        mat_khau: document.getElementById('mat_khau').value,
    };

    try {
        const result = await apiRequest('/auth/login', { method: 'POST', body });
        redirectByRole(result.data.vai_tro);
    } catch (error) {
        showAlert('loginAlert', error.message, 'error');
    }
});
