let state = {
    users: [],
    subjects: [],
    classes: [],
    teachers: [],
    materials: [],
    announcements: [],
};

function setupTabs() {
    const tabLinks = document.querySelectorAll('.tab-link');
    const tabContents = document.querySelectorAll('.tab-content');
    tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            tabLinks.forEach(l => l.classList.remove('active'));
            this.classList.add('active');
            tabContents.forEach(tc => tc.style.display = 'none');
            const tab = this.getAttribute('data-tab');
            const content = document.getElementById('tab-' + tab);
            if (content) content.style.display = '';
        });
    });

    const firstTab = document.querySelector('.tab-link');
    if (firstTab) {
        const firstContent = document.getElementById('tab-' + firstTab.getAttribute('data-tab'));
        if (firstContent) firstContent.style.display = '';
    }
}

(async function init() {
    const me = await requireRole('admin');
    if (!me) return;

    setupTabs();
    document.getElementById('btnLogout').addEventListener('click', (e) => {
        e.preventDefault();
        doLogout();
    });

    bindForms();
    await Promise.all([loadStats(), loadUsers(), loadSubjects(), loadClasses(), loadMaterials(), loadAnnouncements()]);
})();

function bindForms() {
    document.getElementById('userForm').addEventListener('submit', saveUser);
    document.getElementById('subjectForm').addEventListener('submit', saveSubject);
    document.getElementById('classForm').addEventListener('submit', saveClass);
    document.getElementById('materialForm').addEventListener('submit', saveMaterial);
    document.getElementById('annForm').addEventListener('submit', saveAnnouncement);
}

async function loadStats() {
    const res = await apiRequest('/admin/stats');
    document.getElementById('stSv').textContent = res.data.soHocSinh;
    document.getElementById('stGv').textContent = res.data.soGiaoVien;
    document.getElementById('stLop').textContent = res.data.soLopHoc;
}

async function loadUsers() {
    state.users = (await apiRequest('/admin/users')).data;
    state.teachers = state.users.filter((u) => u.vai_tro === 'gv');
    renderUsers();
}

async function loadSubjects() {
    state.subjects = (await apiRequest('/admin/subjects')).data;
    renderSubjects();
}

async function loadClasses() {
    state.classes = (await apiRequest('/admin/classes')).data;
    renderClasses();
}

async function loadMaterials() {
    state.materials = (await apiRequest('/admin/materials')).data;
    renderMaterials();
}

async function loadAnnouncements() {
    state.announcements = (await apiRequest('/admin/announcements')).data;
    renderAnnouncements();
}

function renderUsers() {
    const tb = document.getElementById('userTable');
    tb.innerHTML = state.users.map((u) => `
        <tr>
            <td>${u.id}</td>
            <td>${escapeHtml(u.ten_dang_nhap)}</td>
            <td>${escapeHtml(u.ho_ten)}</td>
            <td>${escapeHtml(u.vai_tro)}</td>
            <td class="actions">
                <button class="btn btn-primary btn-sm" onclick='editUser(${JSON.stringify(u)})'>Sửa</button>
                <button class="btn btn-danger btn-sm" onclick='deleteUser(${u.id})'>Xóa</button>
            </td>
        </tr>
    `).join('');

    const teacherOptions = ['<option value="">Chọn giảng viên</option>']
        .concat(state.teachers.map((t) => `<option value="${t.id}">${escapeHtml(t.ho_ten)}</option>`));
    document.getElementById('c_teacher').innerHTML = teacherOptions.join('');
}

function renderSubjects() {
    const tb = document.getElementById('subjectTable');
    tb.innerHTML = state.subjects.map((s) => `
        <tr>
            <td>${s.id}</td>
            <td>${escapeHtml(s.ten_mon)}</td>
            <td>${s.so_tin_chi}</td>
            <td class="actions">
                <button class="btn btn-primary btn-sm" onclick='editSubject(${JSON.stringify(s)})'>Sửa</button>
                <button class="btn btn-danger btn-sm" onclick='deleteSubject(${s.id})'>Xóa</button>
            </td>
        </tr>
    `).join('');

    const options = state.subjects.map((s) => `<option value="${s.id}">${escapeHtml(s.ten_mon)}</option>`).join('');
    document.getElementById('c_subject').innerHTML = options;
}

function renderClasses() {
    const tb = document.getElementById('classTable');
    tb.innerHTML = state.classes.map((c) => `
        <tr>
            <td>${c.id}</td>
            <td>${escapeHtml(c.ten_lop)}</td>
            <td>${escapeHtml(c.ten_mon || '')}</td>
            <td>${escapeHtml(c.ten_giao_vien || '')}</td>
            <td class="actions">
                <button class="btn btn-primary btn-sm" onclick='editClass(${JSON.stringify(c)})'>Sửa</button>
                <button class="btn btn-danger btn-sm" onclick='deleteClass(${c.id})'>Xóa</button>
            </td>
        </tr>
    `).join('');

    const classOptions = ['<option value="">Tài liệu chung</option>']
        .concat(state.classes.map((c) => `<option value="${c.id}">${escapeHtml(c.ten_lop)}</option>`));
    document.getElementById('m_class').innerHTML = classOptions.join('');
    document.getElementById('a_class').innerHTML = classOptions.join('');
}

function renderMaterials() {
    const tb = document.getElementById('materialTable');
    tb.innerHTML = state.materials.map((m) => `
        <tr>
            <td>${m.id}</td>
            <td>${escapeHtml(m.tieu_de)}</td>
            <td>${escapeHtml(m.ten_lop || 'Tất cả')}</td>
            <td>${escapeHtml(m.duong_dan_file || '')}</td>
            <td>
                ${m.duong_dan_file ? `<a href="${escapeHtml(buildDownloadUrl('tai_lieu', m.duong_dan_file))}" target="_blank" class="btn btn-primary btn-sm">ải về</a>` : ''}
                <button class="btn btn-danger btn-sm" onclick='deleteMaterial(${m.id})'>Xóa</button>
            </td>
        </tr>
    `).join('');
}

function renderAnnouncements() {
    const tb = document.getElementById('annTable');
    tb.innerHTML = state.announcements.map((a) => `
        <tr>
            <td>${a.id}</td>
            <td>${escapeHtml(a.tieu_de)}</td>
            <td>${escapeHtml(a.ten_lop || 'Tất cả')}</td>
            <td class="actions">
                <button class="btn btn-primary btn-sm" onclick='editAnn(${JSON.stringify(a)})'>Sửa</button>
                <button class="btn btn-danger btn-sm" onclick='deleteAnn(${a.id})'>Xóa</button>
            </td>
        </tr>
    `).join('');
}

async function saveUser(e) {
    e.preventDefault();
    const id = document.getElementById('userId').value;
    const body = {
        ten_dang_nhap: document.getElementById('u_username').value,
        mat_khau: document.getElementById('u_password').value,
        ho_ten: document.getElementById('u_name').value,
        email: document.getElementById('u_email').value,
        vai_tro: document.getElementById('u_role').value,
    };
    await apiRequest(id ? `/admin/users/${id}` : '/admin/users', { method: id ? 'PUT' : 'POST', body });
    e.target.reset();
    document.getElementById('userId').value = '';
    await loadUsers();
    showAlert('globalAlert', 'Đã lưu người dùng');
}

async function saveSubject(e) {
    e.preventDefault();
    const id = document.getElementById('subjectId').value;
    const body = {
        ten_mon: document.getElementById('s_name').value,
        so_tin_chi: document.getElementById('s_credit').value,
        mo_ta: document.getElementById('s_desc').value,
    };
    await apiRequest(id ? `/admin/subjects/${id}` : '/admin/subjects', { method: id ? 'PUT' : 'POST', body });
    e.target.reset();
    document.getElementById('subjectId').value = '';
    await loadSubjects();
    await loadClasses();
    showAlert('globalAlert', 'Đã lưu môn học');
}

async function saveClass(e) {
    e.preventDefault();
    const id = document.getElementById('classId').value;
    const body = {
        id_mon_hoc: document.getElementById('c_subject').value,
        id_giao_vien: document.getElementById('c_teacher').value,
        ten_lop: document.getElementById('c_name').value,
        hoc_ky: document.getElementById('c_semester').value,
        si_so_toi_da: document.getElementById('c_limit').value,
    };
    await apiRequest(id ? `/admin/classes/${id}` : '/admin/classes', { method: id ? 'PUT' : 'POST', body });
    e.target.reset();
    document.getElementById('classId').value = '';
    await loadClasses();
    showAlert('globalAlert', 'Đã lưu lớp học');
}

async function saveMaterial(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('tieu_de', document.getElementById('m_title').value);
    fd.append('id_lop', document.getElementById('m_class').value);
    const file = document.getElementById('m_file').files[0];
    if (!file) {
        showAlert('globalAlert', 'Vui lòng chọn file tài liệu', 'error');
        return;
    }
    const validation = validateUploadFile(file);
    if (!validation.ok) {
        showAlert('globalAlert', validation.message, 'error');
        return;
    }
    fd.append('file_upload', file);

    try {
        await apiRequest('/admin/materials', { method: 'POST', body: fd });
        e.target.reset();
        await loadMaterials();
        showAlert('globalAlert', 'Đã thêm tài liệu');
    } catch (error) {
        showAlert('globalAlert', error.message || 'Upload tài liệu thất bại', 'error');
    }
}

async function saveAnnouncement(e) {
    e.preventDefault();
    const id = document.getElementById('annId').value;
    const body = {
        tieu_de: document.getElementById('a_title').value,
        noi_dung: document.getElementById('a_content').value,
        id_lop: document.getElementById('a_class').value,
    };
    await apiRequest(id ? `/admin/announcements/${id}` : '/admin/announcements', { method: id ? 'PUT' : 'POST', body });
    e.target.reset();
    document.getElementById('annId').value = '';
    await loadAnnouncements();
    showAlert('globalAlert', 'Đã lưu thông báo');
}

function editUser(u) {
    document.getElementById('userId').value = u.id;
    document.getElementById('u_username').value = u.ten_dang_nhap;
    document.getElementById('u_name').value = u.ho_ten;
    document.getElementById('u_email').value = u.email || '';
    document.getElementById('u_role').value = u.vai_tro;
}

function editSubject(s) {
    document.getElementById('subjectId').value = s.id;
    document.getElementById('s_name').value = s.ten_mon;
    document.getElementById('s_credit').value = s.so_tin_chi;
    document.getElementById('s_desc').value = s.mo_ta || '';
}

function editClass(c) {
    document.getElementById('classId').value = c.id;
    document.getElementById('c_subject').value = c.id_mon_hoc;
    document.getElementById('c_teacher').value = c.id_giao_vien || '';
    document.getElementById('c_name').value = c.ten_lop;
    document.getElementById('c_semester').value = c.hoc_ky || '';
    document.getElementById('c_limit').value = c.si_so_toi_da || 50;
}

function editAnn(a) {
    document.getElementById('annId').value = a.id;
    document.getElementById('a_title').value = a.tieu_de;
    document.getElementById('a_content').value = a.noi_dung;
    document.getElementById('a_class').value = a.id_lop || '';
}

async function deleteUser(id) { await apiRequest(`/admin/users/${id}`, { method: 'DELETE' }); await loadUsers(); showAlert('globalAlert', 'Đã xóa người dùng'); }
async function deleteSubject(id) { await apiRequest(`/admin/subjects/${id}`, { method: 'DELETE' }); await loadSubjects(); await loadClasses(); showAlert('globalAlert', 'Đã xóa môn học'); }
async function deleteClass(id) { await apiRequest(`/admin/classes/${id}`, { method: 'DELETE' }); await loadClasses(); showAlert('globalAlert', 'Đã xóa lớp học.'); }
async function deleteMaterial(id) { await apiRequest(`/admin/materials/${id}`, { method: 'DELETE' }); await loadMaterials(); showAlert('globalAlert', 'Đã xóa tài liệu'); }
async function deleteAnn(id) { await apiRequest(`/admin/announcements/${id}`, { method: 'DELETE' }); await loadAnnouncements(); showAlert('globalAlert', 'Đã xóa thông báo'); }

window.editUser = editUser;
window.editSubject = editSubject;
window.editClass = editClass;
window.editAnn = editAnn;
window.deleteUser = deleteUser;
window.deleteSubject = deleteSubject;
window.deleteClass = deleteClass;
window.deleteMaterial = deleteMaterial;
window.deleteAnn = deleteAnn;
