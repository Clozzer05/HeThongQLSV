let classes = [];
let activeAssignmentId = null;

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
    const me = await requireRole('gv');
    if (!me) return;

    setupTabs();
    document.getElementById('btnLogout').addEventListener('click', (e) => {
        e.preventDefault();
        doLogout();
    });

    bindEvents();
    await loadClasses();
    await loadMaterials();
    await loadAnnouncements();
})();

function bindEvents() {
    document.getElementById('btnLoadAtt').addEventListener('click', function(e) {
        e.preventDefault();
        loadAttendanceStudents();
    });
    document.getElementById('btnSaveAtt').addEventListener('click', function(e) {
        e.preventDefault();
        saveAttendance();
    });
    document.getElementById('assignmentForm').addEventListener('submit', saveAssignment);
    document.getElementById('btnLoadGrades').addEventListener('click', function(e) {
        e.preventDefault();
        loadGradeStudents();
    });
    document.getElementById('btnSaveGrades').addEventListener('click', saveGrades);
    document.getElementById('materialForm').addEventListener('submit', saveMaterial);
    document.getElementById('annForm').addEventListener('submit', saveAnnouncement);
    document.getElementById('btnShowAttHistory').addEventListener('click', showAttendanceHistoryModal);
    document.getElementById('closeAttHistory').addEventListener('click', closeAttendanceHistoryModal);

    document.getElementById('attHistoryModal').addEventListener('click', function(e) {
        if (e.target === this) closeAttendanceHistoryModal();
    });
}

async function showAttendanceHistoryModal() {
    const classId = document.getElementById('attClass').value;
    if (!classId) return;

    const res = await apiRequest(`/teacher/attendance/${classId}`);
    const data = res.data || [];

    document.getElementById('attHistoryTable').innerHTML = data.map(row => `
        <tr>
            <td>${escapeHtml(row.ngay_diem_danh)}</td>
            <td>${escapeHtml(row.ho_ten)}</td>
            <td>${escapeHtml(row.email || '')}</td>
            <td>${renderTrangThai(row.trang_thai)}</td>
        </tr>
    `).join('');
    document.getElementById('attHistoryModal').style.display = 'block';
}

function closeAttendanceHistoryModal() {
    document.getElementById('attHistoryModal').style.display = 'none';
}

function renderTrangThai(tt) {
    if (tt === 'co_mat') return 'Có mặt';
    if (tt === 'vang_co_phep') return 'Vắng có phép';
    if (tt === 'vang_khong_phep') return 'Vắng không phép';
    return tt;
}

async function loadClasses() {
    classes = (await apiRequest('/teacher/classes')).data;

    document.getElementById('classTable').innerHTML = classes.map((c) => `
        <tr><td>${c.id}</td><td>${escapeHtml(c.ten_lop)}</td><td>${escapeHtml(c.ten_mon || '')}</td><td>${escapeHtml(c.hoc_ky || '')}</td></tr>
    `).join('');

    const options = classes.map((c) => `<option value="${c.id}">${escapeHtml(c.ten_lop)} - ${escapeHtml(c.ten_mon || '')}</option>`).join('');
    ['attClass', 'asClass', 'gradeClass', 'mClass', 'annClass'].forEach((id) => {
        document.getElementById(id).innerHTML = options;
    });

    await loadAssignments();
}

async function loadAttendanceStudents() {
    const classId = document.getElementById('attClass').value;
    document.getElementById('attTable').innerHTML = '';
    if (!classId) return;
    const students = (await apiRequest(`/teacher/classes/${classId}/students`)).data;
    if (!students || students.length === 0) {
        document.getElementById('attTable').innerHTML = '<tr><td colspan="3" style="text-align:center;color:#888;">Lớp này chưa có sinh viên đăng ký</td></tr>';
        return;
    }
    document.getElementById('attTable').innerHTML = students.map((s) => `
        <tr>
            <td>${escapeHtml(s.ho_ten)}</td>
            <td>${escapeHtml(s.email || '')}</td>
            <td>
                <select data-svid="${s.id}">
                    <option value="co_mat">Có mặt</option>
                    <option value="vang_co_phep">Vắng có phép</option>
                    <option value="vang_khong_phep">Vắng không phép</option>
                </select>
            </td>
        </tr>
    `).join('');
}

async function saveAttendance() {
    const classId = document.getElementById('attClass').value;
    const date = document.getElementById('attDate').value;
    const rows = Array.from(document.querySelectorAll('#attTable select')).map((el) => ({
        id_sinh_vien: Number(el.dataset.svid),
        trang_thai: el.value,
    }));

    await apiRequest('/teacher/attendance', {
        method: 'POST',
        body: { id_lop: Number(classId), ngay_diem_danh: date, danh_sach: rows },
    });
    showAlert('globalAlert', 'Đã lưu điểm danh');
}

async function loadAssignments() {
    const classId = document.getElementById('asClass').value;
    if (!classId) return;
    const assignments = (await apiRequest(`/teacher/assignments/${classId}`)).data;

    document.getElementById('assignmentTable').innerHTML = assignments.map((a) => `
        <tr>
            <td>${a.id}</td>
            <td>${escapeHtml(a.tieu_de)}</td>
            <td>${escapeHtml(a.han_nop || '')}</td>
            <td><button class="btn btn-primary btn-sm" onclick="loadSubmissions(${a.id})">Xem bài nộp</button></td>
            <td><button class="btn btn-danger btn-sm" onclick="deleteAssignment(${a.id})">Xóa</button></td>
        </tr>
    `).join('');
}

async function saveAssignment(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('id_lop', document.getElementById('asClass').value);
    fd.append('tieu_de', document.getElementById('asTitle').value);
    fd.append('mo_ta', document.getElementById('asDesc').value);
    fd.append('han_nop', document.getElementById('asDeadline').value);
    const file = document.getElementById('asFile').files[0];
    if (!file) {
        showAlert('globalAlert', 'Vui long dinh kem file de bai de sinh vien tai ve.', 'error');
        return;
    }

    const validation = validateUploadFile(file);
    if (!validation.ok) {
        showAlert('globalAlert', validation.message, 'error');
        return;
    }
    fd.append('file_de_bai', file);

    try {
        await apiRequest('/teacher/assignments', { method: 'POST', body: fd });
        e.target.reset();
        await loadAssignments();
        showAlert('globalAlert', 'Đã tạo bài tập');
    } catch (error) {
        showAlert('globalAlert', error.message || 'Upload thất bại', 'error');
    }
}

async function loadSubmissions(assignmentId) {
    activeAssignmentId = assignmentId;
    const subs = (await apiRequest(`/teacher/assignments/${assignmentId}/submissions`)).data;

    document.getElementById('submissionTable').innerHTML = subs.map((s, idx) => {
        let fileNameCell = '';
        let fileDownloadCell = '';
        if (s.file_bai_lam) {
            let fileUrl = buildDownloadUrl('bai_nop', s.file_bai_lam);
            fileNameCell = `<span style='font-size:13px;'>${escapeHtml(s.file_bai_lam)}</span>`;
            fileDownloadCell = `<a href="${escapeHtml(fileUrl)}" target="_blank" class="btn btn-primary btn-sm">Tải về</a>`;
        }
        return `
            <tr style="background:${idx%2===0?'#f9fbff':'#fff'};">
                <td style="text-align:center;">${escapeHtml(s.ho_ten)}</td>
                <td style="text-align:center;">${fileNameCell}</td>
                <td style="text-align:center;">${fileDownloadCell}</td>
                <td style="text-align:center;"><input id="score-${s.id}" type="number" step="0.1" value="${s.diem ?? ''}" style="width:60px;padding:4px 8px;border-radius:6px;border:1px solid #ccc;text-align:center;"></td>
                <td style="text-align:center;"><input id="fb-${s.id}" value="${escapeHtml(s.nhan_xet || '')}" style="width:120px;padding:4px 8px;border-radius:6px;border:1px solid #ccc;"></td>
                <td style="text-align:center;"><button class="btn btn-success btn-sm" style="padding:4px 12px;min-width:48px;" onclick="saveSubmissionScore(${s.id})">Lưu</button></td>
            </tr>
        `;
    }).join('');
}

async function saveSubmissionScore(id) {
    const diem = document.getElementById(`score-${id}`).value;
    const nhan_xet = document.getElementById(`fb-${id}`).value;
    await apiRequest(`/teacher/submissions/${id}/grade`, { method: 'POST', body: { diem, nhan_xet } });
    showAlert('globalAlert', 'Đã lưu điểm và nhận xét');
    if (activeAssignmentId) await loadSubmissions(activeAssignmentId);
}

async function deleteAssignment(id) {
    await apiRequest(`/teacher/assignments/${id}`, { method: 'DELETE' });
    await loadAssignments();
    showAlert('globalAlert', 'Đã xóa bài tập');
}

async function loadGradeStudents() {
    const classId = document.getElementById('gradeClass').value;
    const students = (await apiRequest(`/teacher/classes/${classId}/students`)).data;
    document.getElementById('gradeTable').innerHTML = students.map((s) => `
        <tr>
            <td>${escapeHtml(s.ho_ten)}</td>
            <td>${escapeHtml(s.email || '')}</td>
            <td><input id="gk-${s.id}" type="number" step="0.1" value="${s.diem_giua_ky ?? ''}"></td>
            <td><input id="ck-${s.id}" type="number" step="0.1" value="${s.diem_cuoi_ky ?? ''}"></td>
        </tr>
    `).join('');
}

async function saveGrades() {
    const classId = document.getElementById('gradeClass').value;
    const rows = Array.from(document.querySelectorAll('#gradeTable tr')).map((tr) => {
        const gk = tr.querySelector('[id^="gk-"]');
        const ck = tr.querySelector('[id^="ck-"]');
        if (!gk || !ck) return null;
        const id = Number(gk.id.replace('gk-', ''));
        return {
            id_sinh_vien: id,
            diem_giua_ky: gk.value,
            diem_cuoi_ky: ck.value,
        };
    }).filter(Boolean);

    await apiRequest(`/teacher/grades/${classId}`, { method: 'PUT', body: { danh_sach: rows } });
    showAlert('globalAlert', 'Đã cập nhật điểm tổng kết');
}

async function loadMaterials() {
    const mats = (await apiRequest('/teacher/materials')).data;
    document.getElementById('materialTable').innerHTML = mats.map((m) => `
        <tr>
            <td>${m.id}</td>
            <td>${escapeHtml(m.tieu_de)}</td>
            <td>${escapeHtml(m.ten_lop || '')}</td>
            <td>
                ${m.duong_dan_file ? `<a href="${escapeHtml(buildDownloadUrl('tai_lieu', m.duong_dan_file))}" target="_blank" class="btn btn-primary btn-sm">ải về</a>` : ''}
                <button class="btn btn-danger btn-sm" onclick="deleteMaterial(${m.id})">Xóa</button>
            </td>
        </tr>
    `).join('');
}

async function saveMaterial(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('id_lop', document.getElementById('mClass').value);
    fd.append('tieu_de', document.getElementById('mTitle').value);
    const file = document.getElementById('mFile').files[0];
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
        await apiRequest('/teacher/materials', { method: 'POST', body: fd });
        e.target.reset();
        await loadMaterials();
        showAlert('globalAlert', 'Đã thêm tài liệu');
    } catch (error) {
        showAlert('globalAlert', error.message || 'Upload tài liệu thất bại', 'error');
    }
}

async function deleteMaterial(id) {
    await apiRequest(`/teacher/materials/${id}`, { method: 'DELETE' });
    await loadMaterials();
    showAlert('globalAlert', 'Đã xóa tài liệu');
}

async function loadAnnouncements() {
    const anns = (await apiRequest('/teacher/announcements')).data;
    document.getElementById('annTable').innerHTML = anns.map((a) => `
        <tr>
            <td>${a.id}</td>
            <td>${escapeHtml(a.tieu_de)}</td>
            <td>${escapeHtml(a.noi_dung || '')}</td>
            <td>${escapeHtml(a.ten_lop || '')}</td>
            <td><button class="btn btn-danger btn-sm" onclick="deleteAnnouncement(${a.id})">Xóa</button></td>
        </tr>
    `).join('');
}

async function saveAnnouncement(e) {
    e.preventDefault();
    const id = document.getElementById('annId').value;
    const body = {
        id_lop: document.getElementById('annClass').value,
        tieu_de: document.getElementById('annTitle').value,
        noi_dung: document.getElementById('annContent').value,
    };
    await apiRequest(id ? `/teacher/announcements/${id}` : '/teacher/announcements', { method: id ? 'PUT' : 'POST', body });
    e.target.reset();
    document.getElementById('annId').value = '';
    await loadAnnouncements();
    showAlert('globalAlert', 'Đã lưu thông báo');
}

async function deleteAnnouncement(id) {
    await apiRequest(`/teacher/announcements/${id}`, { method: 'DELETE' });
    await loadAnnouncements();
    showAlert('globalAlert', 'Đã xóa thông báo');
}

document.getElementById('asClass').addEventListener('change', loadAssignments);

window.loadSubmissions = loadSubmissions;
window.deleteAssignment = deleteAssignment;
window.saveSubmissionScore = saveSubmissionScore;
window.deleteMaterial = deleteMaterial;
window.deleteAnnouncement = deleteAnnouncement;
