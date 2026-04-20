let myClasses = [];

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

    tabContents.forEach((tc, idx) => {
        tc.style.display = idx === 0 ? '' : 'none';
    });
}

async function loadMaterials() {
    const mats = (await apiRequest('/student/materials')).data;
    document.getElementById('materialTable').innerHTML = mats.map((m) => `
        <tr>
            <td>${m.id}</td>
            <td>${escapeHtml(m.tieu_de)}</td>
            <td>${escapeHtml(m.ten_lop || '')}</td>
            <td><a href="${escapeHtml(buildDownloadUrl('tai_lieu', m.duong_dan_file))}" target="_blank" class="btn btn-primary btn-sm">Tải về</a></td>
        </tr>
    `).join('');
}

(function bindMaterialTab() {
    const tab = document.querySelector('[data-tab="materials"]');
    if (tab) {
        tab.addEventListener('click', function() {
            loadMaterials();
        });
    }
})();

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

    if (myClasses.length > 0) {
        document.getElementById('detailClass').value = String(myClasses[0].id);
    }
}

async function loadAvailableClasses() {
    const rows = (await apiRequest('/student/available-classes')).data;
    document.getElementById('availableTable').innerHTML = rows.map((c) => `
        <tr>
            <td>${c.id}</td>
            <td>${escapeHtml(c.ten_lop)}</td>
            <td>${escapeHtml(c.ten_mon || '')}</td>
            <td>${escapeHtml(c.ten_giao_vien || '')}</td>
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
    if (!classId) {
        document.getElementById('detailMaterials').innerHTML = '<tr><td colspan="3" style="text-align:center;color:#888;">Bạn chưa đăng ký lớp nào.</td></tr>';
        document.getElementById('detailAssignments').innerHTML = '<tr><td colspan="5" style="text-align:center;color:#888;">Bạn chưa đăng ký lớp nào.</td></tr>';
        return;
    }

    const detail = (await apiRequest(`/student/classes/${classId}`)).data;

    const infoDiv = document.getElementById('classInfoBox');
    if (infoDiv) {
        let html = '';
        if (detail.lop) {
            html += `<div><strong>Giảng viên:</strong> ${escapeHtml(detail.lop.ten_giao_vien || 'Chưa có')} (${escapeHtml(detail.lop.email_giao_vien || '')})</div>`;
            html += `<div><strong>Học kỳ:</strong> ${escapeHtml(detail.lop.hoc_ky || '')}</div>`;
        }
        if (detail.thoi_khoa_bieu && detail.thoi_khoa_bieu.length > 0) {
            html += '<div><strong>Thời khóa biểu:</strong><ul>';
            for (const tkb of detail.thoi_khoa_bieu) {
                html += `<li>Thứ ${tkb.thu}, Tiết ${tkb.tiet_bat_dau} - ${tkb.tiet_ket_thuc}, Phòng: ${escapeHtml(tkb.phong || '')}</li>`;
            }
            html += '</ul></div>';
        } else {
            html += '<div><strong>Thời khóa biểu:</strong> Chưa cập nhật</div>';
        }
        infoDiv.innerHTML = html;
    }

    const materials = Array.isArray(detail.tai_lieu) ? detail.tai_lieu : [];
    document.getElementById('detailMaterials').innerHTML = materials
        .map((t) => {
            let fileName = t.duong_dan_file;

            let fileUrl = buildDownloadUrl('tai_lieu', fileName);
            return `
                <tr>
                    <td>${escapeHtml(t.tieu_de)}</td>
                    <td>${escapeHtml(fileName)}</td>
                    <td><a href="${escapeHtml(fileUrl)}" target="_blank" class="btn btn-primary btn-sm">Tải về</a></td>
                </tr>
            `;
        })
        .join('') || '<tr><td colspan="3" style="text-align:center;color:#888;">Chưa có tài liệu.</td></tr>';

    const detailAnnouncementsEl = document.getElementById('detailAnnouncements');
    if (detailAnnouncementsEl) {
        detailAnnouncementsEl.innerHTML = (detail.thong_bao || [])
            .map((t) => `<li><strong>${escapeHtml(t.tieu_de)}:</strong> ${escapeHtml(t.noi_dung)}</li>`)
            .join('');
    }

    const assignments = Array.isArray(detail.bai_tap) ? detail.bai_tap : [];
    document.getElementById('detailAssignments').innerHTML = assignments.map((a) => `
        <tr>
            <td>${escapeHtml(a.tieu_de)}</td>
            <td>${escapeHtml(a.han_nop || '')}</td>
            <td>
                ${a.file_de_bai ? `<a href="${escapeHtml(buildDownloadUrl('bai_tap', a.file_de_bai))}" target="_blank" class="btn btn-primary btn-sm">Tải đề</a>` : '<span style="color:#888;">Không có file</span>'}
            </td>
            <td>${a.da_nop ? 'Da nop' : 'Chua nop'}</td>
            <td>
                <input type="file" id="file-${a.id}" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar,.7z,.jpg,.jpeg,.png">
                <button class="btn btn-success btn-sm" onclick="submitAssignment(${a.id})">Nop</button>
            </td>
        </tr>
    `).join('') || '<tr><td colspan="5" style="text-align:center;color:#888;">Chưa có bài tập cho lớp này.</td></tr>';
}

async function submitAssignment(id) {
    const input = document.getElementById(`file-${id}`);
    if (!input.files[0]) {
        showAlert('globalAlert', 'Vui long chon file bai nop.', 'error');
        return;
    }

    const fd = new FormData();
    const file = input.files[0];
    const validation = validateUploadFile(file);
    if (!validation.ok) {
        showAlert('globalAlert', validation.message, 'error');
        return;
    }
    fd.append('file', file);
    try {
        await apiRequest(`/student/assignments/${id}/submit`, { method: 'POST', body: fd });
        showAlert('globalAlert', 'Nop bai thanh cong.');
        await loadClassDetail();
    } catch (error) {
        showAlert('globalAlert', error.message || 'Nop bai that bai.', 'error');
    }
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

document.getElementById('detailClass').addEventListener('change', loadClassDetail);
document.querySelector('[data-tab="class-detail"]')?.addEventListener('click', function() {
    if (document.getElementById('detailClass').value) {
        loadClassDetail();
    }
});

