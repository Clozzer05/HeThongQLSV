const API_BASE = '/QuanLySinhVien/api';

async function apiRequest(path, options = {}) {
    const config = {
        method: options.method || 'GET',
        credentials: 'include',
        headers: options.headers || {},
    };

    if (options.body instanceof FormData) {
        config.body = options.body;
    } else if (options.body !== undefined) {
        config.headers['Content-Type'] = 'application/json';
        config.body = JSON.stringify(options.body);
    }

    const response = await fetch(API_BASE + path, config);
    let data = null;
    try {
        data = await response.json();
    } catch (error) {
        data = { success: false, message: 'Response khong hop le.' };
    }

    if (!response.ok || !data.success) {
        throw new Error(data.message || 'Yeu cau that bai.');
    }

    return data;
}

function showAlert(elementId, message, type = 'success') {
    const el = document.getElementById(elementId);
    if (!el) {
        return;
    }
    el.textContent = message;
    el.className = `alert alert-${type}`;
    setTimeout(() => {
        el.className = 'alert hidden';
    }, 3500);
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

async function requireRole(expectedRole) {
    try {
        const me = await apiRequest('/auth/me');
        const role = me.data.vai_tro;
        if (role !== expectedRole) {
            redirectByRole(role);
            return null;
        }
        return me.data;
    } catch (error) {
        window.location.href = 'login.html';
        return null;
    }
}

function redirectByRole(role) {
    if (role === 'admin') {
        window.location.href = 'admin.html';
        return;
    }
    if (role === 'gv') {
        window.location.href = 'teacher.html';
        return;
    }
    window.location.href = 'student.html';
}

async function doLogout() {
    try {
        await apiRequest('/auth/logout', { method: 'POST' });
    } catch (error) {
    }
    window.location.href = 'login.html';
}

function setupTabs() {
    const links = document.querySelectorAll('.tab-link');
    links.forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            const tab = link.dataset.tab;
            if (!tab) {
                return;
            }

            document.querySelectorAll('.tab-link').forEach((i) => i.classList.remove('active'));
            link.classList.add('active');

            document.querySelectorAll('.tab-content').forEach((section) => section.classList.add('hidden'));
            const target = document.getElementById(`tab-${tab}`);
            if (target) {
                target.classList.remove('hidden');
            }
        });
    });
}

window.apiRequest = apiRequest;
window.showAlert = showAlert;
window.escapeHtml = escapeHtml;
window.requireRole = requireRole;
window.redirectByRole = redirectByRole;
window.doLogout = doLogout;
window.setupTabs = setupTabs;
