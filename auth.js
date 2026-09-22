/**
 * 枪王 - 用户认证 & 下载管理
 */

function showLoginModal() {
  document.getElementById('loginModal').classList.remove('hidden');
  document.getElementById('loginError').classList.add('hidden');
}
function showRegisterModal() {
  document.getElementById('registerModal').classList.remove('hidden');
  document.getElementById('registerError').classList.add('hidden');
}
function showPurchaseModal() {
  document.getElementById('purchaseModal').classList.remove('hidden');
}
function closeModalById(id) {
  document.getElementById(id).classList.add('hidden');
}

async function authApi(action, data) {
  try {
    var opts = { method: data ? 'POST' : 'GET', headers: { 'Content-Type': 'application/json' } };
    if (data) opts.body = JSON.stringify(data);
    var res = await fetch('api.php?action=' + action, opts);
    return await res.json();
  } catch(e) { return { ok: false, error: '网络错误' }; }
}

async function doLogin() {
  var login = document.getElementById('loginUsername').value.trim();
  var password = document.getElementById('loginPassword').value.trim();
  if (!login || !password) {
    document.getElementById('loginError').textContent = '请填写用户名/邮箱和密码';
    document.getElementById('loginError').classList.remove('hidden');
    return;
  }
  var r = await authApi('user_login', { login: login, password: password });
  if (r.ok) {
    closeModalById('loginModal');
    refreshUserStatus();
  } else {
    document.getElementById('loginError').textContent = r.error || '登录失败';
    document.getElementById('loginError').classList.remove('hidden');
  }
}

async function doRegister() {
  var username = document.getElementById('regUsername').value.trim();
  var email = document.getElementById('regEmail').value.trim();
  var password = document.getElementById('regPassword').value.trim();
  if (!username || !email || !password) {
    document.getElementById('registerError').textContent = '请填写所有字段';
    document.getElementById('registerError').classList.remove('hidden');
    return;
  }
  if (password.length < 6) {
    document.getElementById('registerError').textContent = '密码长度至少6位';
    document.getElementById('registerError').classList.remove('hidden');
    return;
  }
  var r = await authApi('register', { username: username, email: email, password: password });
  if (r.ok) {
    closeModalById('registerModal');
    refreshUserStatus();
  } else {
    document.getElementById('registerError').textContent = r.error || '注册失败';
    document.getElementById('registerError').classList.remove('hidden');
  }
}

async function doLogout() {
  await authApi('logout');
  refreshUserStatus();
}

async function doPurchase() {
  var credits = parseInt(document.getElementById('purchaseCredits').value) || 1;
  var r = await authApi('purchase_credits', { credits: credits, payment_method: 'simulated' });
  if (r.ok) {
    closeModalById('purchaseModal');
    await refreshUserStatus();
    if (r.anonymous) {
      alert('模拟购买成功！已添加 ' + credits + ' 次下载。请注册或登录后使用。');
    } else {
      alert('模拟购买成功！已添加 ' + credits + ' 次下载。剩余: ' + (r.remaining_credits || '?') + ' 次');
    }
  } else {
    alert('购买失败: ' + (r.error || '未知错误'));
  }
}

async function refreshUserStatus() {
  var r = await authApi('user_info');
  var guestEl = document.getElementById('userGuest');
  var loggedInEl = document.getElementById('userLoggedIn');
  var creditBadge = document.getElementById('creditBadge');
  if (r.ok && r.user) {
    guestEl.classList.add('hidden');
    loggedInEl.classList.remove('hidden');
    if (r.user.is_admin) {
      document.getElementById('creditCount').textContent = '无限';
      creditBadge.className = 'text-xs bg-purple-50 text-purple-700 px-2 py-1 rounded-full font-medium';
    } else {
      document.getElementById('creditCount').textContent = r.user.download_credits;
      creditBadge.className = 'text-xs bg-blue-50 text-blue-700 px-2 py-1 rounded-full font-medium';
    }
    document.getElementById('displayUsername').textContent = r.user.username;
    document.getElementById('purchaseGuestNote').textContent = '当前用户: ' + r.user.username;
  } else {
    guestEl.classList.remove('hidden');
    loggedInEl.classList.add('hidden');
    document.getElementById('purchaseGuestNote').textContent = '未登录也可购买，购买后请注册/登录使用。';
  }
}

async function checkDownloadCredits() {
  return await authApi('check_download');
}
