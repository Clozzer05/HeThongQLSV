const form = document.getElementById("loginForm");
const btn = form.querySelector('button[type="submit"]');
const alertDiv = document.getElementById("loginAlert");
const inputUser = document.getElementById("ten_dang_nhap");
const inputPass = document.getElementById("mat_khau");

window.addEventListener("DOMContentLoaded", () => {
  inputUser.focus();
});

(async function initLogin() {
  try {
    const me = await apiRequest("/auth/me");
    redirectByRole(me.data.vai_tro);
  } catch (error) {}
})();

inputUser.addEventListener("input", () => alertDiv.classList.add("hidden"));
inputPass.addEventListener("input", () => alertDiv.classList.add("hidden"));

form.addEventListener("submit", async (event) => {
  event.preventDefault();
  alertDiv.classList.add("hidden");
  btn.disabled = true;
  btn.innerHTML = "Đang đăng nhập...";
  const body = {
    ten_dang_nhap: inputUser.value.trim(),
    mat_khau: inputPass.value,
  };
  try {
    const result = await apiRequest("/auth/login", { method: "POST", body });
    redirectByRole(result.data.vai_tro);
  } catch (error) {
    showAlert("loginAlert", error.message, "error");
  } finally {
    btn.disabled = false;
    btn.innerHTML = 'Đăng nhập <i class="fas fa-sign-in-alt"></i>';
  }
});
