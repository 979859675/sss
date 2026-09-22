<?php
/**
 * 枪王 - 专属博主后台
 */
declare(strict_types=1);
require_once __DIR__ . '/config.php';
session_name('QIANGWANG_BLOGGER');
session_start();

$isLoggedIn = isset($_SESSION['blogger_id']) && $_SESSION['blogger_id'] > 0;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>博主专属后台 - 枪王福利发放中心</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
  <script>tailwind.config={theme:{extend:{colors:{primary:'#1976d2'}}}}</script>
</head>
<body class="bg-gray-50 min-h-screen">

<?php if (!$isLoggedIn): ?>
<!-- 登录界面 -->
<div class="flex items-center justify-center min-h-screen">
  <div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-sm">
    <div class="text-center mb-6">
      <div class="w-16 h-16 bg-blue-100 text-primary rounded-full flex items-center justify-center mx-auto mb-3"><i class="fas fa-star text-2xl"></i></div>
      <h2 class="text-xl font-bold">认证博主后台</h2>
      <p class="text-xs text-gray-500 mt-1">为粉丝生成专属免费福利码</p>
    </div>
    <div id="loginErr" class="hidden mb-3 text-sm text-red-600 bg-red-50 p-2 rounded"></div>
    <div class="space-y-4">
      <input type="text" id="bLoginUser" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-primary" placeholder="博主账号"/>
      <input type="password" id="bLoginPwd" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-primary" placeholder="登录密码"/>
      <button onclick="bloggerLogin()" class="w-full py-3 bg-primary text-white rounded-lg font-bold hover:bg-blue-700 transition">登录专属后台</button>
    </div>
  </div>
</div>
<?php else: ?>
<!-- 博主控制台 -->
<nav class="bg-white shadow-sm sticky top-0">
  <div class="max-w-5xl mx-auto px-4 py-3 flex justify-between items-center">
    <div class="font-bold text-lg text-gray-800"><i class="fas fa-star text-primary mr-2"></i>博主控制台</div>
    <div class="text-sm">
      <span class="mr-4 text-gray-600">欢迎, <?=htmlspecialchars($_SESSION['blogger_username'])?></span>
      <button onclick="bloggerLogout()" class="text-red-500 hover:underline"><i class="fas fa-sign-out-alt"></i> 退出</button>
    </div>
  </div>
</nav>

<div class="max-w-5xl mx-auto px-4 py-8">
  <div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold">我的专属下载码</h1>
    <button onclick="showCodeModal()" class="px-4 py-2 bg-primary text-white rounded-lg font-medium"><i class="fas fa-plus mr-1"></i> 生成新福利码</button>
  </div>

  <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <table class="w-full text-left">
      <thead class="bg-gray-50 border-b">
        <tr>
          <th class="p-4 font-medium text-gray-600">福利码</th>
          <th class="p-4 font-medium text-gray-600">单人获取次数</th>
          <th class="p-4 font-medium text-gray-600">总名额 / 已兑换</th>
          <th class="p-4 font-medium text-gray-600">状态</th>
          <th class="p-4 font-medium text-gray-600">操作</th>
        </tr>
      </thead>
      <tbody id="codesTable" class="divide-y text-sm">
        <tr><td colspan="5" class="p-8 text-center text-gray-400">加载中...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- 生成码弹窗 -->
<div id="codeModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white p-6 rounded-xl w-full max-w-sm">
    <h3 class="text-lg font-bold mb-4">生成专属下载码</h3>
    <div class="space-y-3">
      <div><label class="block text-xs mb-1 text-gray-600">自定义兑换码 (如 VIP888)</label><input id="newCode" type="text" class="w-full px-3 py-2 border rounded uppercase"/></div>
      <div><label class="block text-xs mb-1 text-gray-600">每个粉丝获得下载次数</label><input id="newCredits" type="number" value="3" class="w-full px-3 py-2 border rounded"/></div>
      <div><label class="block text-xs mb-1 text-gray-600">最多允许多少人兑换 (防泛滥)</label><input id="newMax" type="number" value="100" class="w-full px-3 py-2 border rounded"/></div>
    </div>
    <div class="mt-5 flex justify-end gap-2">
      <button onclick="hideCodeModal()" class="px-4 py-2 border rounded">取消</button>
      <button onclick="createCode()" class="px-4 py-2 bg-primary text-white rounded">生成</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
async function api(act, data=null) {
    const opts = {method: data ? 'POST' : 'GET', headers: {'Content-Type': 'application/json'}};
    if(data) opts.body = JSON.stringify(data);
    const res = await fetch('api.php?action=' + act, opts);
    return await res.json();
}

function showCodeModal() {
    const m = document.getElementById('codeModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function hideCodeModal() {
    const m = document.getElementById('codeModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

<?php if (!$isLoggedIn): ?>
async function bloggerLogin() {
    const r = await api('blogger_login', {
        u: document.getElementById('bLoginUser').value,
        p: document.getElementById('bLoginPwd').value
    });
    if(r.ok) location.reload();
    else { document.getElementById('loginErr').textContent = r.error; document.getElementById('loginErr').classList.remove('hidden'); }
}
<?php else: ?>
async function bloggerLogout() { await api('blogger_logout'); location.reload(); }
async function loadCodes() {
    const r = await api('blogger_codes');
    if(!r.ok) return;
    document.getElementById('codesTable').innerHTML = r.data.length === 0 ? '<tr><td colspan="5" class="p-8 text-center text-gray-400">尚未生成兑换码</td></tr>' : r.data.map(c => `
        <tr>
            <td class="p-4 font-bold text-primary">${c.code}</td>
            <td class="p-4">${c.credits_per_user} 次</td>
            <td class="p-4"><span class="font-bold ${c.used_users >= c.max_users ? 'text-red-500' : 'text-green-600'}">${c.used_users}</span> / ${c.max_users} 人</td>
            <td class="p-4">${c.is_active == 1 ? '<span class="text-green-500">正常</span>' : '<span class="text-red-500">已停用</span>'}</td>
            <td class="p-4">
                <button onclick="toggleCode(${c.id}, ${c.is_active})" class="text-xs px-2 py-1 border rounded hover:bg-gray-50">${c.is_active == 1 ? '停用' : '启用'}</button>
            </td>
        </tr>
    `).join('');
}
async function createCode() {
    const r = await api('blogger_create_code', {
        code: document.getElementById('newCode').value,
        credits: document.getElementById('newCredits').value,
        max: document.getElementById('newMax').value
    });
    if(r.ok) { hideCodeModal(); loadCodes(); }
    else alert(r.error);
}
async function toggleCode(id, currentStatus) {
    await api('blogger_toggle_code', {id: id, status: currentStatus == 1 ? 0 : 1});
    loadCodes();
}
document.addEventListener('DOMContentLoaded', loadCodes);
<?php endif; ?>
</script>
</body>
</html>
