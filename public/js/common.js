function resolveApiBase() {
    if (typeof window !== 'undefined' && typeof window.__API_BASE === 'string' && window.__API_BASE.trim() !== '') {
        return window.__API_BASE.trim().replace(/\/$/, '');
    }

    try {
        const stored = localStorage.getItem('qlsv_api_base');
        if (stored && stored.trim() !== '') {
            return stored.trim().replace(/\/$/, '');
        }
    } catch (error) {
    }

    const onProjectPath = window.location.pathname.includes('/QuanLySinhVien/');
    if (onProjectPath) {
        return `${window.location.origin}/QuanLySinhVien/api`;
    }

    return 'http://localhost/HeThongQLSV/api';
}

const API_BASE = resolveApiBase();

async function apiRequest(path, options = {}) {
    const config = {
        method: options.method || 'GET',
        credentials: 'include',
        headers: {
            Accept: 'application/json',
            ...(options.headers || {}),
        },
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

function setApiBase(baseUrl) {
    if (!baseUrl || typeof baseUrl !== 'string') {
        throw new Error('API base URL khong hop le.');
    }
    const normalized = baseUrl.trim().replace(/\/$/, '');
    localStorage.setItem('qlsv_api_base', normalized);
    return normalized;
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
window.setApiBase = setApiBase;
window.API_BASE = API_BASE;
