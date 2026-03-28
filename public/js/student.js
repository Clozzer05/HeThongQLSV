let myClasses = [];

(async function init() {
    const me = await requireRole('sv');
    if (!me) return;

    setupTabs();
    document.getElementById('btnLogout').addEventListener('click', (e) => {
        e.preventDefault();
        doLogout();
    });

    document.getElementById('btnLoadDetail').addEventListener('click', loadClassDetail);

    await loadMyClasses();
    await loadAvailableClasses();
    await loadAnnouncements();
})();

async function loadMyClasses() {
    myClasses = (await apiRequest('/student/my-classes')).data;
    document.getElementById('myClassTable').innerHTML = myClasses.map((c) => `
        <tr>
            <td>${c.id}</td>
            <td>${escapeHtml(c.ten_lop)}</td>
            <td>${escapeHtml(c.ten_mon || '')}</td>
            <td>${c.diem_giua_ky ?? ''}</td>
            <td>${c.diem_cuoi_ky ?? ''}</td>
            <td><button class="btn btn-primary btn-sm" onclick="setDetailClass(${c.id})">Xem</button></td>
        </tr>
    `).join('');

    const options = myClasses.map((c) => `<option value="${c.id}">${escapeHtml(c.ten_lop)} - ${escapeHtml(c.ten_mon || '')}</option>`).join('');
    document.getElementById('detailClass').innerHTML = options;
}

async function loadAvailableClasses() {
    const rows = (await apiRequest('/student/available-classes')).data;
    document.getElementById('availableTable').innerHTML = rows.map((c) => `
        <tr>
            <td>${c.id}</td>
            <td>${escapeHtml(c.ten_lop)}</td>
            <td>${escapeHtml(c.ten_mon || '')}</td>
            <td>${c.si_so_hien_tai}/${c.si_so_toi_da}</td>
            <td><button class="btn btn-success btn-sm" onclick="enrollClass(${c.id})">Dang ky</button></td>
        </tr>
    `).join('');
}

async function enrollClass(id) {
    await apiRequest('/student/enroll', { method: 'POST', body: { id_lop: id } });
    showAlert('globalAlert', 'Dang ky lop thanh cong.');
    await loadMyClasses();
    await loadAvailableClasses();
}

function setDetailClass(id) {
    document.querySelector('[data-tab="class-detail"]').click();
    document.getElementById('detailClass').value = id;
    loadClassDetail();
}

async function loadClassDetail() {
    const classId = document.getElementById('detailClass').value;
    if (!classId) return;

    const detail = (await apiRequest(`/student/classes/${classId}`)).data;

    document.getElementById('detailMaterials').innerHTML = detail.tai_lieu
        .map((t) => `<li>${escapeHtml(t.tieu_de)} - ${escapeHtml(t.duong_dan_file)}</li>`)
        .join('');

    document.getElementById('detailAnnouncements').innerHTML = detail.thong_bao
        .map((t) => `<li><strong>${escapeHtml(t.tieu_de)}:</strong> ${escapeHtml(t.noi_dung)}</li>`)
        .join('');

    document.getElementById('detailAssignments').innerHTML = detail.bai_tap.map((a) => `
        <tr>
            <td>${escapeHtml(a.tieu_de)}</td>
            <td>${escapeHtml(a.han_nop || '')}</td>
            <td>${a.da_nop ? 'Da nop' : 'Chua nop'}</td>
            <td>
                <input type="file" id="file-${a.id}">
                <button class="btn btn-success btn-sm" onclick="submitAssignment(${a.id})">Nop</button>
            </td>
        </tr>
    `).join('');
}

async function submitAssignment(id) {
    const input = document.getElementById(`file-${id}`);
    if (!input.files[0]) {
        showAlert('globalAlert', 'Vui long chon file bai nop.', 'error');
        return;
    }

    const fd = new FormData();
    fd.append('file', input.files[0]);
    await apiRequest(`/student/assignments/${id}/submit`, { method: 'POST', body: fd });
    showAlert('globalAlert', 'Nop bai thanh cong.');
    await loadClassDetail();
}

async function loadAnnouncements() {
    const anns = (await apiRequest('/student/announcements')).data;
    document.getElementById('annTable').innerHTML = anns.map((a) => `
        <tr>
            <td>${a.id}</td>
            <td>${escapeHtml(a.tieu_de)}</td>
            <td>${escapeHtml(a.ten_lop || 'Thong bao chung')}</td>
            <td>${escapeHtml(a.noi_dung)}</td>
        </tr>
    `).join('');
}

window.enrollClass = enrollClass;
window.setDetailClass = setDetailClass;
window.submitAssignment = submitAssignment;
