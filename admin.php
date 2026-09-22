<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
startSession();
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'login') { $ok = doLogin($_POST['username'] ?? '', $_POST['password'] ?? ''); if (!$ok) $loginError = '用户名或密码错误'; }
    elseif ($_POST['action'] === 'logout') { doLogout(); header('Location: admin.php'); exit; }
}
$isLoggedIn = isLoggedIn();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>枪王 - 后台管理</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/dracula.min.css"/>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
  <script>tailwind.config={theme:{extend:{colors:{primary:'#1976d2','primary-dark':'#1565c0'}}}}</script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@300;400;500;700&display=swap');
    *{font-family:'Noto Sans SC',sans-serif;}body{background:#f0f2f5;}
    .tab-btn.active{background:#1976d2;color:#fff;}
    .tab-panel{display:none;}.tab-panel.active{display:block;}
    .form-input{width:100%;padding:8px 12px;border:1px solid #ccc;border-radius:6px;font-size:14px;}
    .form-input:focus{outline:none;border-color:#1976d2;box-shadow:0 0 0 3px rgba(25,118,210,0.1);}
    .CodeMirror{height:100%!important;font-size:13px!important;border:none;border-radius:0;}
    .toast{position:fixed;top:20px;right:20px;padding:12px 20px;border-radius:8px;color:#fff;font-size:14px;z-index:9999;animation:slideIn .3s ease;}
    .toast-ok{background:#43a047;}.toast-err{background:#e53935;}
    @keyframes slideIn{from{transform:translateX(100%);opacity:0;}to{transform:translateX(0);opacity:1;}}
    table.at{width:100%;border-collapse:collapse;}
    table.at th,table.at td{padding:10px 12px;border-bottom:1px solid #e0e0e0;text-align:left;font-size:13px;}
    table.at th{background:#f5f5f5;font-weight:600;color:#555;}table.at tr:hover{background:#fafafa);}
    .editor-split{display:flex;gap:0;height:calc(100vh - 280px);min-height:500px;border:1px solid #ddd;border-radius:8px;overflow:hidden;}
    .editor-left{flex:1;display:flex;flex-direction:column;min-width:0;border-right:1px solid #ddd;}
    .editor-right{flex:1;display:flex;flex-direction:column;min-width:0;background:#fff;}
    .editor-tab{padding:6px 14px;font-size:12px;font-weight:600;cursor:pointer;border-bottom:2px solid transparent;color:#888;transition:all .2s;}
    .editor-tab.active{color:#1976d2;border-bottom-color:#1976d2;background:#f8faff;}
    .editor-tab:hover:not(.active){color:#555;background:#f5f5f5;}
    .editor-body{flex:1;overflow:hidden;position:relative;}
    .editor-body .CodeMirror-wrap{position:absolute;inset:0;}
    .preview-header{padding:8px 14px;background:#f8f8f8;border-bottom:1px solid #eee;font-size:12px;font-weight:600;color:#555;display:flex;align-items:center;gap:6px;}
    .preview-body{flex:1;overflow:auto;padding:12px;background:#f0f2f5;}
    .preview-body .preview-paper{background:#fff;box-shadow:0 2px 12px rgba(0,0,0,.1);border-radius:4px;min-height:400px;width:max-content;min-width:100%;}
    .tpl-card{background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:14px 16px;transition:all .2s;}
    .tpl-card:hover{border-color:#1976d2;box-shadow:0 2px 12px rgba(25,118,210,.12);}
    .ph-tag{display:inline-flex;align-items:center;gap:3px;background:#fff;padding:2px 8px;border-radius:4px;border:1px solid #bbdefb;font-size:11px;color:#1565c0;cursor:pointer;transition:all .15s;white-space:nowrap;}
    .ph-tag:hover{background:#e3f2fd;border-color:#90caf9;}
    .ph-tag .ph-label{color:#888;font-size:9px;}
    #dropZone{transition:all .2s;}
    #dropZone.drag-active{border-color:#f59e0b;background:rgba(245,158,11,.08);transform:scale(1.01);}
  </style>
</head>
<body class="min-h-screen">

<?php if (!$isLoggedIn): ?>
<div class="min-h-screen flex items-center justify-center">
  <div class="bg-white rounded-2xl shadow-lg p-8 w-full max-w-md">
    <div class="text-center mb-6">
      <div class="w-16 h-16 rounded-xl bg-primary flex items-center justify-center mx-auto mb-3"><i class="fas fa-lock text-white text-2xl"></i></div>
      <h1 class="text-2xl font-bold text-gray-800">枪王 后台</h1>
      <p class="text-sm text-gray-500 mt-1">管理员登录</p>
    </div>
    <?php if (isset($loginError)): ?><div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm"><i class="fas fa-exclamation-circle mr-1"></i><?=htmlspecialchars($loginError)?></div><?php endif; ?>
    <form method="POST"><input type="hidden" name="action" value="login"/>
      <div class="mb-4"><label class="block text-sm font-medium text-gray-700 mb-1">用户名</label><input name="username" type="text" class="form-input" placeholder="admin" required/></div>
      <div class="mb-6"><label class="block text-sm font-medium text-gray-700 mb-1">密码</label><input name="password" type="password" class="form-input" placeholder="admin123" required/></div>
      <button type="submit" class="w-full py-3 bg-primary hover:bg-primary-dark text-white rounded-xl font-semibold transition"><i class="fas fa-sign-in-alt mr-2"></i>登录</button>
    </form>
    <div class="mt-4 text-center"><a href="index.php" class="text-sm text-primary hover:underline">← 返回前台</a></div>
  </div>
</div>
<?php else: ?>
<div class="flex min-h-screen">
  <aside class="w-56 bg-white border-r flex-shrink-0 flex flex-col">
    <div class="p-4 border-b"><div class="flex items-center gap-2"><div class="w-8 h-8 rounded-lg bg-primary flex items-center justify-center"><i class="fas fa-file-invoice text-white"></i></div><span class="font-bold text-gray-800">枪王</span></div><div class="text-xs text-gray-400 mt-1">后台管理 v4.0</div></div>
    <nav class="p-3 space-y-1 flex-1">
      <button onclick="switchTab('regions')" class="tab-btn active w-full text-left px-3 py-2.5 rounded-lg text-sm font-medium transition flex items-center gap-2 hover:bg-gray-100" data-tab="regions"><i class="fas fa-globe-asia w-5"></i>地区管理</button>
      <button onclick="switchTab('billtypes')" class="tab-btn w-full text-left px-3 py-2.5 rounded-lg text-sm font-medium transition flex items-center gap-2 hover:bg-gray-100" data-tab="billtypes"><i class="fas fa-file-alt w-5"></i>账单类型</button>
      <button onclick="switchTab('templates')" class="tab-btn w-full text-left px-3 py-2.5 rounded-lg text-sm font-medium transition flex items-center gap-2 hover:bg-gray-100" data-tab="templates"><i class="fas fa-code w-5"></i>模板管理</button>
      <button onclick="switchTab('announcement')" class="tab-btn w-full text-left px-3 py-2.5 rounded-lg text-sm font-medium transition flex items-center gap-2 hover:bg-gray-100" data-tab="announcement"><i class="fas fa-bullhorn w-5"></i>公告管理</button>
      <button onclick="switchTab('users')" class="tab-btn w-full text-left px-3 py-2.5 rounded-lg text-sm font-medium transition flex items-center gap-2 hover:bg-gray-100" data-tab="users"><i class="fas fa-users w-5"></i>用户管理</button>
      <button onclick="switchTab('bloggers')" class="tab-btn w-full text-left px-3 py-2.5 rounded-lg text-sm font-medium transition flex items-center gap-2 hover:bg-gray-100" data-tab="bloggers"><i class="fas fa-star w-5 text-yellow-500"></i>博主申请审批</button>
    </nav>
    <div class="p-3 border-t">
      <form method="POST"><input type="hidden" name="action" value="logout"/><button type="submit" class="w-full text-left px-3 py-2.5 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50 transition flex items-center gap-2"><i class="fas fa-sign-out-alt w-5"></i>退出登录</button></form>
      <button onclick="showAdminPwdModal()" class="w-full text-left px-3 py-2.5 rounded-lg text-sm font-medium text-orange-600 hover:bg-orange-50 transition mt-1 flex items-center gap-2"><i class="fas fa-key w-5"></i>修改密码</button>
      <a href="index.php" class="block px-3 py-2.5 rounded-lg text-sm font-medium text-primary hover:bg-blue-50 transition mt-1"><i class="fas fa-external-link-alt w-5"></i>查看前台</a>
    </div>
  </aside>

  <main class="flex-1 p-6 overflow-auto">
    <!-- ════ 地区管理 ════ -->
    <div id="panel-regions" class="tab-panel active">
      <div class="flex items-center justify-between mb-4"><h2 class="text-xl font-bold text-gray-800"><i class="fas fa-globe-asia text-primary mr-2"></i>地区管理</h2><button onclick="showRegionModal()" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition"><i class="fas fa-plus mr-1"></i>添加地区</button></div>
      <div class="bg-white rounded-xl shadow-sm border overflow-hidden"><table class="at"><thead><tr><th>ID</th><th>旗帜</th><th>代码</th><th>名称</th><th>排序</th><th>状态</th><th>操作</th></tr></thead><tbody id="regionsTable"><tr><td colspan="7" class="text-center py-8 text-gray-400">加载中...</td></tr></tbody></table></div>
    </div>

    <!-- ════ 账单类型管理 ════ -->
    <div id="panel-billtypes" class="tab-panel">
      <div class="flex items-center justify-between mb-4"><h2 class="text-xl font-bold text-gray-800"><i class="fas fa-file-alt text-primary mr-2"></i>账单类型管理</h2><button onclick="showBillTypeModal()" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition"><i class="fas fa-plus mr-1"></i>添加账单类型</button></div>
      <div class="bg-white rounded-xl shadow-sm border overflow-hidden"><table class="at"><thead><tr><th>ID</th><th>地区</th><th>名称</th><th>类别</th><th>图标</th><th>单位</th><th>货币</th><th>状态</th><th>操作</th></tr></thead><tbody id="billTypesTable"><tr><td colspan="9" class="text-center py-8 text-gray-400">加载中...</td></tr></tbody></table></div>
    </div>

    <!-- ════ 模板管理 ════ -->
    <div id="panel-templates" class="tab-panel">
      <!-- 子视图：模板列表 -->
      <div id="tplListView">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-xl font-bold text-gray-800"><i class="fas fa-code text-primary mr-2"></i>模板管理</h2>
          <div class="flex gap-2">
            <button onclick="showUploadHtmlModal()" class="px-4 py-2 bg-amber-500 text-white rounded-lg text-sm font-medium hover:bg-amber-600 transition"><i class="fas fa-upload mr-1"></i>上传HTML转模板</button>
            <button onclick="showAddTemplateModal()" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition"><i class="fas fa-plus mr-1"></i>添加模板</button>
          </div>
        </div>
        <div id="tplCards" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4"></div>
      </div>

      <!-- 子视图：模板编辑器（边写边预览） -->
      <div id="tplEditorView" class="hidden">
        <div class="flex items-center justify-between mb-3">
          <div class="flex items-center gap-3">
            <button onclick="backToList()" class="px-3 py-1.5 border rounded-lg text-sm hover:bg-gray-50 transition"><i class="fas fa-arrow-left mr-1"></i>返回列表</button>
            <h2 class="text-lg font-bold text-gray-800" id="tplEditorTitle">编辑模板</h2>
            <span id="tplEditorBadge" class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full"></span>
          </div>
          <div class="flex gap-2">
            <button onclick="resetTemplate()" class="px-3 py-1.5 border border-orange-400 text-orange-600 rounded-lg text-sm font-medium hover:bg-orange-50 transition"><i class="fas fa-undo mr-1"></i>恢复默认</button>
            <button onclick="deleteTemplate()" class="px-3 py-1.5 border border-red-400 text-red-600 rounded-lg text-sm font-medium hover:bg-red-50 transition"><i class="fas fa-trash mr-1"></i>删除</button>
            <button onclick="saveTemplate()" class="px-3 py-1.5 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition"><i class="fas fa-save mr-1"></i>保存</button>
          </div>
        </div>

        <!-- 占位符快捷插入（带中文标签） -->
        <div class="mb-3 p-3 bg-blue-50 rounded-lg border border-blue-200">
          <div class="flex items-center gap-2 mb-2">
            <i class="fas fa-magic text-primary text-xs"></i>
            <span class="text-xs font-semibold text-primary">点击插入占位符</span>
            <select id="sampleDataType" onchange="updatePreview()" class="text-xs border rounded px-2 py-0.5 ml-auto">
              <option value="utility">💧 水电费示例</option>
              <option value="bank">🏦 银行流水示例</option>
              <option value="credit_card">💳 信用卡示例</option>
              <option value="wise">🏦 Wise银行示例</option>
            </select>
          </div>
          <div class="flex flex-wrap gap-1.5" id="placeholderRef"></div>
        </div>

        <!-- 分屏编辑器 -->
        <div class="editor-split">
          <div class="editor-left">
            <div class="flex border-b bg-gray-50" id="editorTabs">
              <div class="editor-tab active" data-editor="html" onclick="switchEditorTab('html')"><i class="fab fa-html5 mr-1"></i>HTML</div>
              <div class="editor-tab" data-editor="css" onclick="switchEditorTab('css')"><i class="fab fa-css3-alt mr-1"></i>CSS</div>
              <div class="editor-tab" data-editor="js" onclick="switchEditorTab('js')"><i class="fab fa-js mr-1"></i>JS</div>
              <div class="ml-auto flex items-center pr-2 gap-2">
                <label class="flex items-center gap-1 text-xs text-gray-500 cursor-pointer"><input type="checkbox" id="livePreviewToggle" checked onchange="toggleLivePreview()" class="rounded"/>实时预览</label>
              </div>
            </div>
            <div class="editor-body" id="editorContainerHtml"><textarea id="tplHtml"></textarea></div>
            <div class="editor-body hidden" id="editorContainerCss"><textarea id="tplCss"></textarea></div>
            <div class="editor-body hidden" id="editorContainerJs"><textarea id="tplJs"></textarea></div>
          </div>
          <div class="editor-right">
            <div class="preview-header"><i class="fas fa-eye text-primary"></i>实时预览<span id="previewStatus" class="ml-auto text-green-600 text-xs"><i class="fas fa-circle text-xs mr-1" style="font-size:6px;"></i>已同步</span></div>
            <div class="preview-body">
              <div class="preview-paper" id="tplPreview"><div class="flex items-center justify-center py-20 text-gray-300"><i class="fas fa-file-invoice text-4xl mr-3"></i><span>输入模板内容，预览将自动更新</span></div></div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- ════ 公告管理 ════ -->
    <div id="panel-announcement" class="tab-panel">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-bold text-gray-800"><i class="fas fa-bullhorn text-primary mr-2"></i>公告管理</h2>
        <button onclick="saveAnnouncement()" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition"><i class="fas fa-save mr-1"></i>保存公告</button>
      </div>
      <div class="mb-3 p-3 bg-amber-50 rounded-lg border border-amber-200 text-xs text-amber-700">
        <i class="fas fa-info-circle mr-1"></i>此处编辑前台首页 Hero 区域的公告内容。标题为纯文本，公告内容支持 HTML 格式。
      </div>

      <!-- 标题编辑 -->
      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-1"><i class="fas fa-heading mr-1"></i>公告标题</label>
        <input id="heroTitle" type="text" class="form-input" placeholder="枪王账单学习模版" oninput="updateAnnouncementPreview()" style="font-size:15px;"/>
      </div>

      <!-- 分屏编辑+预览 -->
      <div class="editor-split">
        <div class="editor-left">
          <div class="flex border-b bg-gray-50">
            <div class="editor-tab active"><i class="fab fa-html5 mr-1"></i>HTML 内容</div>
          </div>
          <div class="editor-body" id="announcementEditorContainer"><textarea id="announcementHtml"></textarea></div>
        </div>
        <div class="editor-right">
          <div class="preview-header"><i class="fas fa-eye text-primary"></i>实时预览</div>
          <div class="preview-body">
            <div class="bg-white border-b" style="padding:0;">
              <div class="max-w-7xl mx-auto px-4 py-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-3" id="announcementPreviewTitle">枪王账单学习模版</h1>
                <div id="announcementPreviewContent" class="text-gray-600 max-w-2xl leading-relaxed"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- ════ 用户管理 ════ -->
    <div id="panel-users" class="tab-panel">
      <!-- 统计卡片 -->
      <div class="grid grid-cols-4 gap-4 mb-5" id="userStatsRow">
        <div class="bg-white rounded-xl border p-4"><div class="text-2xl font-bold text-primary" id="statUsers">0</div><div class="text-xs text-gray-500 mt-1">总用户数</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-2xl font-bold text-amber-600" id="statTopupUsdc">0</div><div class="text-xs text-gray-500 mt-1">累计充值 USDC</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-2xl font-bold text-amber-600" id="statTopupUsdt">0</div><div class="text-xs text-gray-500 mt-1">累计充值 USDT</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-2xl font-bold text-green-600" id="statDownloads">0</div><div class="text-xs text-gray-500 mt-1">累计下载次数</div></div>
        <div class="bg-white rounded-xl border p-4"><div class="text-2xl font-bold text-purple-600" id="statRevenue">¥0.00</div><div class="text-xs text-gray-500 mt-1">模拟收入总额</div></div>
      </div>

      <!-- 用户列表 / 用户详情 -->
      <div id="userListView">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-xl font-bold text-gray-800"><i class="fas fa-users text-primary mr-2"></i>用户列表</h2>
          <button onclick="refreshUsers()" class="px-3 py-1.5 border rounded-lg text-sm hover:bg-gray-50 transition"><i class="fas fa-sync-alt mr-1"></i>刷新</button>
        </div>
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
          <table class="at">
            <thead><tr><th>ID</th><th>用户名</th><th>邮箱</th><th>余额</th><th>充值/下载</th><th>注册时间</th><th>操作</th></tr></thead>
            <tbody id="usersTable"><tr><td colspan="8" class="text-center py-8 text-gray-400"><i class="fas fa-spinner fa-spin mr-2"></i>加载中...</td></tr></tbody>
          </table>
        </div>
      </div>

      <!-- 用户详情视图 -->
      <div id="userDetailView" class="hidden">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-3">
            <button onclick="backToUserList()" class="px-3 py-1.5 border rounded-lg text-sm hover:bg-gray-50 transition"><i class="fas fa-arrow-left mr-1"></i>返回列表</button>
            <h2 class="text-lg font-bold text-gray-800" id="userDetailTitle">用户详情</h2>
          </div>
          <div class="flex gap-2" id="userDetailActions"></div>
        </div>
        <!-- 用户信息卡片 -->
        <div class="bg-white rounded-xl border p-5 mb-4" id="userDetailCard"></div>
        <!-- Tabs: 订单 / 下载记录 -->
        <div class="flex gap-1 mb-3">
          <button onclick="switchUserSubTab('orders')" class="user-sub-tab px-3 py-1.5 rounded-lg text-sm font-medium bg-primary text-white" data-subtab="orders">充值订单</button>
          <button onclick="switchUserSubTab('downloads')" class="user-sub-tab px-3 py-1.5 rounded-lg text-sm font-medium bg-gray-100 text-gray-600" data-subtab="downloads">下载记录</button>
        </div>
        <div id="userSubOrders" class="bg-white rounded-xl border overflow-hidden"><table class="at" id="ordersTable"></table></div>
        <div id="userSubDownloads" class="bg-white rounded-xl border overflow-hidden hidden"><table class="at" id="downloadsTable"></table></div>
      </div>
    </div>

    <!-- ════ 博主管理面板 ════ -->
    <div id="panel-bloggers" class="tab-panel">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-bold text-gray-800"><i class="fas fa-star text-yellow-500 mr-2"></i>博主申请审批</h2>
      </div>
      <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="at">
          <thead><tr><th>ID</th><th>登录账号</th><th>主页/频道链接</th><th>联系方式</th><th>申请时间</th><th>状态</th><th>操作</th></tr></thead>
          <tbody id="bloggersTable"></tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<!-- Modal: 修改管理员密码 -->
<div id="adminPwdModal" class="fixed inset-0 bg-black/50 z-[100] flex items-center justify-center hidden">
  <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm mx-4">
    <h3 class="text-lg font-bold mb-4"><i class="fas fa-key mr-2 text-primary"></i>修改管理员密码</h3>
    <div class="space-y-3">
      <div><label class="block text-xs font-medium text-gray-600 mb-1">旧密码</label><input id="oldAdminPwd" type="password" class="form-input"/></div>
      <div><label class="block text-xs font-medium text-gray-600 mb-1">新密码</label><input id="newAdminPwd" type="password" class="form-input" placeholder="至少6位"/></div>
    </div>
    <div class="flex justify-end gap-2 mt-4">
      <button onclick="closeModal('adminPwdModal')" class="px-4 py-2 border rounded-lg text-sm">取消</button>
      <button onclick="saveAdminPwd()" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium">保存修改</button>
    </div>
  </div>
</div>

<!-- Modal: 地区 -->
<div id="regionModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden">
  <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md">
    <h3 class="text-lg font-bold mb-4" id="regionModalTitle">添加地区</h3>
    <input type="hidden" id="regionId"/>
    <div class="space-y-3">
      <div><label class="block text-sm font-medium text-gray-700 mb-1">地区代码</label><input id="regionCode" type="text" class="form-input" placeholder="cn"/></div>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">地区名称</label><input id="regionName" type="text" class="form-input" placeholder="中国"/></div>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">旗帜 Emoji</label><input id="regionFlag" type="text" class="form-input" placeholder="🇨🇳"/></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">排序</label><input id="regionSort" type="number" class="form-input" value="0"/></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">启用</label><select id="regionActive" class="form-input"><option value="1">启用</option><option value="0">禁用</option></select></div>
      </div>
    </div>
    <div class="flex justify-end gap-2 mt-4"><button onclick="closeModal('regionModal')" class="px-4 py-2 border rounded-lg text-sm">取消</button><button onclick="saveRegion()" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium">保存</button></div>
  </div>
</div>

<!-- Modal: 账单类型 -->
<div id="billTypeModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden">
  <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-lg">
    <h3 class="text-lg font-bold mb-4" id="btModalTitle">添加账单类型</h3>
    <input type="hidden" id="btId"/>
    <div class="space-y-3">
      <div><label class="block text-sm font-medium text-gray-700 mb-1">所属地区</label><select id="btRegion" class="form-input"></select></div>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">账单名称</label><input id="btName" type="text" class="form-input" placeholder="自来水公司水费单"/></div>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">图标</label>
        <div class="flex gap-2">
          <input id="btIcon" type="text" class="form-input flex-1" placeholder="fa-tint" value="fa-tint"/>
          <span id="btIconPreview" class="w-10 h-10 flex items-center justify-center border rounded-lg text-primary"><i class="fas fa-tint"></i></span>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">计量单位</label><input id="btUnit" type="text" class="form-input" placeholder="m³" value="m³"/></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">货币符号</label><input id="btCurrency" type="text" class="form-input" placeholder="¥" value="¥"/></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-tag text-primary mr-1"></i>账单类别</label>
          <select id="btCategory" class="form-input">
            <option value="utility">💧 水电账单 (Utility)</option>
            <option value="bank">🏦 银行账单 (Bank)</option>
            <option value="credit_card">💳 信用卡 (Credit Card)</option>
            <option value="crypto">🪙 加密货币 (Crypto)</option>
          </select>
        </div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">启用</label><select id="btActive" class="form-input"><option value="1">启用</option><option value="0">禁用</option></select></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">排序</label><input id="btSort" type="number" class="form-input" value="0"/></div>
        <div></div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-copy text-primary mr-1"></i>复制模板</label>
        <select id="btCopyFrom" class="form-input"><option value="0">不复制（创建空白模板）</option></select>
      </div>
    </div>
    <div class="flex justify-end gap-2 mt-4"><button onclick="closeModal('billTypeModal')" class="px-4 py-2 border rounded-lg text-sm">取消</button><button onclick="saveBillType()" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium">保存</button></div>
  </div>
</div>

<!-- Modal: 添加模板（可自定义名字） -->
<div id="addTemplateModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden">
  <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md">
    <h3 class="text-lg font-bold mb-4"><i class="fas fa-plus text-primary mr-2"></i>添加模板</h3>
    <div class="space-y-3">
      <div><label class="block text-sm font-medium text-gray-700 mb-1">选择账单类型</label>
        <select id="addTplBillType" class="form-input"></select>
      </div>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">模板名称 <span class="text-gray-400">(自定义)</span></label>
        <input id="addTplName" type="text" class="form-input" placeholder="如：自定义信用卡模板" value=""/>
        <p class="text-xs text-gray-400 mt-1">留空则使用"默认模板"作为名称</p>
      </div>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">复制自</label>
        <select id="addTplCopyFrom" class="form-input"><option value="0">空白模板</option></select>
      </div>
    </div>
    <div class="flex justify-end gap-2 mt-4"><button onclick="closeModal('addTemplateModal')" class="px-4 py-2 border rounded-lg text-sm">取消</button><button onclick="createAndEditTemplate()" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium"><i class="fas fa-plus mr-1"></i>创建并编辑</button></div>
  </div>
</div>

<!-- Modal: 上传HTML转模板 -->
<div id="uploadModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-6xl max-h-[92vh] flex flex-col">
    <div class="flex items-center justify-between px-6 py-4 border-b flex-shrink-0">
      <h3 class="text-lg font-bold"><i class="fas fa-upload text-amber-500 mr-2"></i>上传HTML转模板</h3>
      <button onclick="closeUploadModal()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition"><i class="fas fa-times"></i></button>
    </div>

    <div class="flex-1 overflow-auto px-6 py-5">
      <!-- ════ Step 1: 上传 ════ -->
      <div id="uploadStep1">
        <div id="dropZone" class="border-2 border-dashed border-gray-300 rounded-xl p-12 text-center hover:border-amber-400 hover:bg-amber-50/20 transition cursor-pointer mb-4"
             onclick="document.getElementById('htmlFileInput').click()"
             ondragover="event.preventDefault();this.classList.add('border-amber-400','bg-amber-50/30')"
             ondragleave="this.classList.remove('border-amber-400','bg-amber-50/30')"
             ondrop="event.preventDefault();this.classList.remove('border-amber-400','bg-amber-50/30');handleHtmlFile(event.dataTransfer.files[0])">
          <i class="fas fa-cloud-upload-alt text-5xl text-gray-300 mb-4"></i>
          <div class="text-gray-500 text-base">拖拽 HTML 文件到此处，或 <span class="text-amber-500 font-semibold">点击选择文件</span></div>
          <div class="text-xs text-gray-400 mt-2">支持 .html / .htm 文件，自动提取CSS和检测占位符</div>
          <div id="uploadFileName" class="text-amber-600 font-semibold mt-3"></div>
        </div>
        <input type="file" id="htmlFileInput" accept=".html,.htm" class="hidden" onchange="handleHtmlFile(this.files[0])"/>

        <div class="flex items-center gap-3 my-4">
          <div class="flex-1 border-t"></div>
          <span class="text-gray-400 text-sm">或直接粘贴HTML代码</span>
          <div class="flex-1 border-t"></div>
        </div>

        <textarea id="uploadHtmlInput" class="form-input font-mono text-xs" rows="8" placeholder="粘贴完整的 HTML 代码到此处，包括 <style> 标签..."></textarea>

        <div class="flex justify-center gap-3 mt-5">
          <button onclick="closeUploadModal()" class="px-5 py-2.5 border rounded-lg text-sm hover:bg-gray-50 transition">取消</button>
          <button onclick="processPastedHtml()" class="px-8 py-2.5 bg-amber-500 text-white rounded-lg font-medium hover:bg-amber-600 transition text-base">
            <i class="fas fa-magic mr-2"></i>分析并转换
          </button>
        </div>
      </div>

      <!-- ════ Step 2: 检测 & 映射 ════ -->
      <div id="uploadStep2" class="hidden">
        <div class="flex items-center justify-between mb-3">
          <div class="flex items-center gap-3">
            <button onclick="uploadGoBack()" class="px-3 py-1.5 border rounded-lg text-sm hover:bg-gray-50 transition"><i class="fas fa-arrow-left mr-1"></i>返回上传</button>
            <span class="text-sm text-gray-500">已检测到 <strong id="detectedCount" class="text-amber-600">0</strong> 个可替换项</span>
          </div>
          <div class="flex items-center gap-2">
            <button onclick="addCustomMapping()" class="px-3 py-1.5 text-xs font-medium text-amber-600 border border-amber-300 rounded-lg hover:bg-amber-50 transition"><i class="fas fa-plus mr-1"></i>手动添加替换</button>
            <button onclick="skipToEditor()" class="px-3 py-1.5 text-xs font-medium text-gray-600 border rounded-lg hover:bg-gray-50 transition"><i class="fas fa-forward mr-1"></i>跳过检测，直接编辑</button>
          </div>
        </div>

        <div class="grid grid-cols-5 gap-4" style="height: calc(92vh - 300px); min-height: 420px;">
          <!-- 左侧: 检测列表 -->
          <div class="col-span-2 flex flex-col min-h-0">
            <div class="flex-1 overflow-auto border rounded-lg">
              <table class="at" style="font-size:12px;">
                <thead><tr><th style="width:35%;">原文</th><th style="width:15%;">类型</th><th style="width:35%;">替换为</th><th style="width:15%;">启用</th></tr></thead>
                <tbody id="uploadDetectedTable"><tr><td colspan="4" class="text-center py-8 text-gray-400">加载中...</td></tr></tbody>
              </table>
            </div>
            <!-- 手动添加行 -->
            <div id="customMappingRow" class="hidden mt-2 p-2.5 bg-amber-50 rounded-lg border border-amber-200 flex gap-2 items-center">
              <input id="customFromText" type="text" class="form-input text-xs flex-1" placeholder="输入要替换的原文..."/>
              <select id="customToPlaceholder" class="form-input text-xs flex-1"></select>
              <button onclick="confirmCustomMapping()" class="w-8 h-8 bg-green-500 text-white rounded-lg text-xs flex items-center justify-center hover:bg-green-600"><i class="fas fa-check"></i></button>
              <button onclick="cancelCustomMapping()" class="w-8 h-8 bg-gray-300 text-gray-600 rounded-lg text-xs flex items-center justify-center hover:bg-gray-400"><i class="fas fa-times"></i></button>
            </div>
          </div>

          <!-- 右侧: 实时预览 -->
          <div class="col-span-3 flex flex-col min-h-0">
            <div class="flex items-center justify-between mb-1">
              <span class="text-xs font-semibold text-gray-600"><i class="fas fa-eye text-primary mr-1"></i>预览</span>
              <span class="text-xs text-gray-400"><span style="background:#ffe0b2;padding:0 6px;border-radius:2px;font-size:9px;color:#e65100;">橙色</span> = 将被替换为占位符</span>
            </div>
            <div class="flex-1 overflow-auto border rounded-lg bg-gray-100 p-3">
              <div id="uploadPreview" class="bg-white shadow rounded min-h-[300px] p-3"></div>
            </div>
          </div>
        </div>

        <!-- 保存控件 -->
        <div class="flex items-end gap-4 mt-4 p-4 bg-gray-50 rounded-lg border">
          <div class="flex-1">
            <label class="block text-xs font-medium text-gray-500 mb-1">模板名称</label>
            <input id="uploadTplName" type="text" class="form-input text-sm" placeholder="如：招商银行信用卡对账单" value=""/>
          </div>
          <div class="flex-1">
            <label class="block text-xs font-medium text-gray-500 mb-1">账单类型</label>
            <select id="uploadBillType" class="form-input text-sm"></select>
          </div>
          <div class="flex gap-2">
            <button onclick="closeUploadModal()" class="px-5 py-2 border rounded-lg text-sm hover:bg-gray-50 transition">取消</button>
            <button onclick="saveUploadedTemplate()" class="px-6 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition"><i class="fas fa-save mr-1"></i>创建模板</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// ──── 工具函数 ────
function switchTab(tab) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.getElementById('panel-' + tab).classList.add('active');
  if (tab === 'regions') loadRegions();
  if (tab === 'billtypes') loadBillTypes();
  if (tab === 'templates') initTemplates();
  if (tab === 'announcement') loadAnnouncement();
  if (tab === 'users') loadUsers();
  if (tab === 'bloggers') loadBloggers();
}
function toast(msg, ok=true) { const el = document.createElement('div'); el.className = 'toast ' + (ok ? 'toast-ok' : 'toast-err'); el.innerHTML = '<i class="fas '+(ok?'fa-check-circle':'fa-times-circle')+' mr-2"></i>' + msg; document.body.appendChild(el); setTimeout(() => el.remove(), 3000); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

async function api(action, data=null) {
  const opts = {method:'GET', headers:{'Content-Type':'application/json'}, credentials:'same-origin'};
  if (data) { opts.method = 'POST'; opts.body = JSON.stringify(data); }
  const r = await fetch('api.php?action='+action, opts);
  return await r.json();
}

// ════ 占位符中文标签映射 ════
const PH_LABELS = {
  customer_name:'客户姓名', full_address:'完整地址', address_unit:'单元地址', address_street:'街道地址', address_district:'区域地址',
  bill_number:'账单编号', account_number:'账号/户号', period_start:'起始日期', period_end:'截止日期', issue_date:'发出日期',
  consumption:'用量', meter_prev:'上次读数', meter_curr:'本次读数', meter_number:'表编号', days:'天数', daily_litres:'日均公升',
  currency:'货币符号', unit:'计量单位', bill_type_name:'账单类型名',
  t1:'第一阶梯量', t2:'第二阶梯量', t3:'第三阶梯量', t4:'第四阶梯量',
  c1:'第一阶梯费', c2:'第二阶梯费', c3:'第三阶梯费', c4:'第四阶梯费',
  water_charge:'水费', sewage_charge:'排污费', total_due:'应缴总额',
  last_pay_date:'上次缴款日', last_pay_amt:'上次缴款额', deposit:'按金',
  surcharge_date:'附加费日期', after_surcharge:'附加费后金额', slip_ref:'缴款单编号', long_ref:'长参考号', crc_code:'CRC码',
  bar_chart_svg:'柱状图SVG', qr_code_svg:'二维码SVG', barcode_svg:'条码SVG', bottom_barcode_svg:'底部条码SVG',
  bank_name:'银行名称(中)', bank_name_en:'银行名称(英)', account_type:'账户类型', statement_period:'流水期间',
  opening_balance:'期初余额', closing_balance:'期末余额', total_credits:'收入合计', total_debits:'支出合计',
  transactions:'交易明细(HTML)',
  card_number:'信用卡号', credit_limit:'信用额度', available_credit:'可用额度',
  min_payment:'最低还款额', payment_due_date:'还款到期日',
  new_charges:'本期消费', new_payments:'本期还款', interest_charge:'循环利息',
  points_balance:'积分余额', previous_balance:'上期余额',
  postal_code:'邮政编码',
  sort_code:'Sort Code', bic:'BIC代码', iban:'IBAN',
  balance_pots:'Pots余额', total_outgoings:'总支出', total_deposits:'总存款',
  adjustment:'本期调整金额', card_last4:'卡号末四位',
  wise_currency:'Wise货币', wise_period_start:'Wise起始日', wise_period_end:'Wise截止日',
  wise_generated_date:'Wise生成日', wise_balance_date:'Wise余额日', wise_balance:'Wise余额',
  wise_iban:'Wise IBAN', wise_bic:'Wise BIC', wise_city:'Wise城市',
  wise_state:'Wise省州', wise_postcode:'Wise邮编', wise_country:'Wise国家',
  wise_ref:'Wise Ref编号', wise_transactions:'Wise交易明细', wise_timezone:'Wise时区',
  kraken_public_id:'Kraken Public ID', statement_month:'对账月份', portfolio_table:'Portfolio表格', balance_date:'余额日期',
};

// ════ 地区管理 ════
let regionsData = [];
async function loadRegions() {
  const r = await api('regions');
  if (r.ok) { regionsData = r.data; document.getElementById('regionsTable').innerHTML = r.data.map(d => `<tr><td>${d.id}</td><td class="text-2xl">${d.flag_emoji}</td><td><code>${d.code}</code></td><td class="font-medium">${d.name}</td><td>${d.sort_order}</td><td><span class="px-2 py-0.5 rounded text-xs font-medium ${d.is_active?'bg-green-100 text-green-700':'bg-red-100 text-red-700'}">${d.is_active?'启用':'禁用'}</span></td><td><button onclick='editRegion(${JSON.stringify(d).replace(/'/g,"&#39;")})' class='text-primary hover:underline text-sm mr-2'><i class="fas fa-edit"></i></button><button onclick="deleteRegion(${d.id})" class="text-red-500 hover:underline text-sm"><i class="fas fa-trash"></i></button></td></tr>`).join(''); }
}
function showRegionModal(d=null) {
  document.getElementById('regionModalTitle').textContent = d ? '编辑地区' : '添加地区';
  document.getElementById('regionId').value = d ? d.id : '';
  document.getElementById('regionCode').value = d ? d.code : '';
  document.getElementById('regionName').value = d ? d.name : '';
  document.getElementById('regionFlag').value = d ? d.flag_emoji : '🏳️';
  document.getElementById('regionSort').value = d ? d.sort_order : 0;
  document.getElementById('regionActive').value = d ? d.is_active : 1;
  document.getElementById('regionModal').classList.remove('hidden');
}
function editRegion(d) { showRegionModal(d); }
async function saveRegion() {
  const id = document.getElementById('regionId').value;
  const data = {id, code:document.getElementById('regionCode').value, name:document.getElementById('regionName').value, flag_emoji:document.getElementById('regionFlag').value, sort_order:document.getElementById('regionSort').value, is_active:document.getElementById('regionActive').value};
  const r = await api(id ? 'region_update' : 'region_create', data);
  if (r.ok) { toast(id?'已更新':'已创建'); closeModal('regionModal'); loadRegions(); } else toast(r.error||'失败', false);
}
async function deleteRegion(id) { if (!confirm('确定删除？')) return; const r = await api('region_delete&id='+id); if (r.ok) { toast('已删除'); loadRegions(); } else toast('失败', false); }

// ════ 账单类型管理 ════
let allBillTypesData = [];
async function loadBillTypes() {
  const r = await api('bill_types');
  if (r.ok) {
    allBillTypesData = r.data;
    const catLabels = {utility:'💧 水电', bank:'🏦 银行', credit_card:'💳 信用卡', crypto:'🪙 加密'};
    document.getElementById('billTypesTable').innerHTML = r.data.map(d => `<tr><td>${d.id}</td><td>${d.region_name||''}</td><td class="font-medium"><i class="fas ${d.icon_class} text-primary mr-1"></i>${d.name}</td><td><span class="px-2 py-0.5 rounded text-xs font-medium ${d.category==='bank'?'bg-amber-100 text-amber-700':d.category==='credit_card'?'bg-purple-100 text-purple-700':d.category==='crypto'?'bg-violet-100 text-violet-700':'bg-blue-100 text-blue-700'}">${catLabels[d.category] || '💧 水电'}</span></td><td><i class="fas ${d.icon_class}"></i></td><td>${d.unit}</td><td>${d.currency}</td><td><span class="px-2 py-0.5 rounded text-xs font-medium ${d.is_active?'bg-green-100 text-green-700':'bg-red-100 text-red-700'}">${d.is_active?'启用':'禁用'}</span></td><td><button onclick='editBillType(${JSON.stringify(d).replace(/'/g,"&#39;")})' class='text-primary hover:underline text-sm mr-2'><i class="fas fa-edit"></i></button><button onclick="deleteBillType(${d.id})" class="text-red-500 hover:underline text-sm"><i class="fas fa-trash"></i></button></td></tr>`).join('');
  }
}

document.getElementById('btIcon')?.addEventListener('input', function() {
  document.getElementById('btIconPreview').innerHTML = '<i class="fas ' + this.value + '"></i>';
});

async function showBillTypeModal(d=null) {
  document.getElementById('btModalTitle').textContent = d ? '编辑账单类型' : '添加账单类型';
  document.getElementById('btId').value = d ? d.id : '';
  document.getElementById('btName').value = d ? d.name : '';
  document.getElementById('btIcon').value = d ? d.icon_class : 'fa-tint';
  document.getElementById('btIconPreview').innerHTML = '<i class="fas ' + (d ? d.icon_class : 'fa-tint') + '"></i>';
  document.getElementById('btUnit').value = d ? d.unit : 'm³';
  document.getElementById('btCurrency').value = d ? d.currency : '¥';
  document.getElementById('btSort').value = d ? d.sort_order : 0;
  document.getElementById('btActive').value = d ? d.is_active : 1;
  document.getElementById('btCategory').value = d ? (d.category || 'utility') : 'utility';
  const sel = document.getElementById('btRegion');
  sel.innerHTML = regionsData.map(r => `<option value="${r.id}" ${d && d.region_id==r.id?'selected':''}>${r.flag_emoji} ${r.name}</option>`).join('');
  const copySel = document.getElementById('btCopyFrom');
  if (!d) {
    const tr = await api('all_templates');
    if (tr.ok && tr.data.length > 0) {
      copySel.innerHTML = '<option value="0">不复制（创建空白模板）</option>' + tr.data.map(t => `<option value="${t.bill_type_id}">📋 ${t.region_name} - ${t.bt_name} (${t.name})</option>`).join('');
      copySel.parentElement.style.display = '';
    } else { copySel.innerHTML = '<option value="0">不复制（创建空白模板）</option>'; }
  } else { copySel.parentElement.style.display = 'none'; }
  document.getElementById('billTypeModal').classList.remove('hidden');
}
function editBillType(d) { showBillTypeModal(d); }
async function saveBillType() {
  const id = document.getElementById('btId').value;
  const name = document.getElementById('btName').value;
  const region = regionsData.find(r => r.id == document.getElementById('btRegion').value);
  const regionCode = region ? region.code : 'xx';
  const autoCode = regionCode + '-' + name.replace(/[^a-zA-Z0-9\u4e00-\u9fff]/g,'').substring(0,20).toLowerCase();
  const data = {
    id, region_id: document.getElementById('btRegion').value,
    code: autoCode, name: name,
    icon_class: document.getElementById('btIcon').value,
    unit: document.getElementById('btUnit').value, currency: document.getElementById('btCurrency').value,
    category: document.getElementById('btCategory').value,
    sort_order: document.getElementById('btSort').value, is_active: document.getElementById('btActive').value,
    copy_template_from: id ? 0 : document.getElementById('btCopyFrom').value,
  };
  const r = await api(id ? 'bill_type_update' : 'bill_type_create', data);
  if (r.ok) { toast(id?'已更新':'已创建'); closeModal('billTypeModal'); loadBillTypes(); } else toast(r.error||'失败', false);
}
async function deleteBillType(id) { if (!confirm('确定删除？关联模板也会被删除。')) return; const r = await api('bill_type_delete&id='+id); if (r.ok) { toast('已删除'); loadBillTypes(); } else toast('失败', false); }

// ════ 模板管理 ════
let editorHtml = null, editorCss = null, editorJs = null;
let currentTplBillTypeId = null;
let editorsInitialized = false;
let livePreviewEnabled = true;
let previewTimer = null;
let currentEditorTab = 'html';
let allTemplatesData = [];

async function initTemplates() {
  if (!editorsInitialized) {
    editorHtml = CodeMirror.fromTextArea(document.getElementById('tplHtml'), {mode:'htmlmixed', theme:'dracula', lineNumbers:true, lineWrapping:true});
    editorCss = CodeMirror.fromTextArea(document.getElementById('tplCss'), {mode:'css', theme:'dracula', lineNumbers:true, lineWrapping:true});
    editorJs = CodeMirror.fromTextArea(document.getElementById('tplJs'), {mode:'javascript', theme:'dracula', lineNumbers:true, lineWrapping:true});
    editorHtml.on('change', () => schedulePreview());
    editorCss.on('change', () => schedulePreview());
    editorJs.on('change', () => schedulePreview());
    editorsInitialized = true;
  }
  await loadTemplateList();
}

function schedulePreview() {
  if (!livePreviewEnabled) return;
  clearTimeout(previewTimer);
  document.getElementById('previewStatus').innerHTML = '<i class="fas fa-spinner fa-spin text-xs mr-1"></i>更新中...';
  previewTimer = setTimeout(() => updatePreview(), 300);
}
function toggleLivePreview() { livePreviewEnabled = document.getElementById('livePreviewToggle').checked; if (livePreviewEnabled) updatePreview(); }

function switchEditorTab(tab) {
  currentEditorTab = tab;
  document.querySelectorAll('#editorTabs .editor-tab').forEach(t => t.classList.toggle('active', t.dataset.editor === tab));
  document.getElementById('editorContainerHtml').classList.toggle('hidden', tab !== 'html');
  document.getElementById('editorContainerCss').classList.toggle('hidden', tab !== 'css');
  document.getElementById('editorContainerJs').classList.toggle('hidden', tab !== 'js');
  if (tab === 'html' && editorHtml) editorHtml.refresh();
  if (tab === 'css' && editorCss) editorCss.refresh();
  if (tab === 'js' && editorJs) editorJs.refresh();
}

// ──── 模板列表 ────
async function loadTemplateList() {
  const r = await api('all_templates');
  if (!r.ok) return;
  allTemplatesData = r.data;
  const container = document.getElementById('tplCards');
  if (allTemplatesData.length === 0) {
    container.innerHTML = '<div class="col-span-full text-center py-16 text-gray-400"><i class="fas fa-inbox text-4xl mb-3"></i><p>暂无模板，点击上方按钮添加</p></div>';
    return;
  }
  container.innerHTML = allTemplatesData.map(t => {
    const htmlSize = (t.html_template||'').length;
    const cssSize = (t.css_template||'').length;
    const hasContent = htmlSize > 0;
    const icon = t.bt_name||'';
    const cat = t.category || 'utility';
    let iconClass = 'fa-file-invoice', iconBg = 'bg-blue-50', iconColor = 'text-primary';
    if (cat === 'bank') { iconClass = 'fa-university'; iconBg = 'bg-amber-50'; iconColor = 'text-amber-600'; }
    if (cat === 'credit_card') { iconClass = 'fa-credit-card'; iconBg = 'bg-purple-50'; iconColor = 'text-purple-600'; }
    if (cat === 'crypto') { iconClass = 'fa-bitcoin-sign'; iconBg = 'bg-violet-50'; iconColor = 'text-violet-600'; }
    if (cat === 'utility' && (icon.includes('电') || icon.includes('Power') || icon.includes('Electric'))) { iconClass = 'fa-bolt'; }
    return `
    <div class="tpl-card">
      <div class="flex items-start gap-3">
        <div class="w-10 h-10 rounded-lg ${iconBg} flex items-center justify-center flex-shrink-0"><i class="fas ${iconClass} ${iconColor}"></i></div>
        <div class="flex-1 min-w-0">
          <div class="font-semibold text-gray-800 text-sm truncate">${t.region_name||''} · ${t.bt_name||''}</div>
          <div class="text-xs text-gray-500 mt-0.5">模板：${t.name||'默认'}</div>
          <div class="flex gap-3 mt-2 text-xs text-gray-400">
            <span><i class="fab fa-html5 mr-1"></i>${htmlSize} 字符</span>
            <span><i class="fab fa-css3-alt mr-1"></i>${cssSize} 字符</span>
            <span class="${hasContent?'text-green-500':'text-orange-500'}">${hasContent?'✓ 有内容':'⚠ 空白'}</span>
          </div>
        </div>
      </div>
      <div class="flex gap-2 mt-3 pt-3 border-t">
        <button onclick="editTemplate(${t.bill_type_id})" class="flex-1 py-1.5 text-xs font-medium text-primary border border-blue-200 rounded-lg hover:bg-blue-50 transition"><i class="fas fa-edit mr-1"></i>编辑</button>
        <button onclick="quickPreview(${t.bill_type_id})" class="flex-1 py-1.5 text-xs font-medium text-gray-600 border rounded-lg hover:bg-gray-50 transition"><i class="fas fa-eye mr-1"></i>预览</button>
        <button onclick="deleteTemplate(${t.bill_type_id})" class="py-1.5 px-3 text-xs font-medium text-red-500 border border-red-200 rounded-lg hover:bg-red-50 transition"><i class="fas fa-trash"></i></button>
      </div>
    </div>`;
  }).join('');
}

function backToList() {
  document.getElementById('tplEditorView').classList.add('hidden');
  document.getElementById('tplListView').classList.remove('hidden');
  currentTplBillTypeId = null;
  loadTemplateList();
}

// ──── 添加模板（可自定义名字） ────
async function showAddTemplateModal() {
  const r = await api('bill_types');
  if (!r.ok) return;
  const sel = document.getElementById('addTplBillType');
  sel.innerHTML = r.data.map(d => `<option value="${d.id}">${d.region_name||''} - ${d.name}</option>`).join('');
  const copySel = document.getElementById('addTplCopyFrom');
  if (allTemplatesData.length > 0) {
    copySel.innerHTML = '<option value="0">空白模板</option>' + allTemplatesData.map(t => `<option value="${t.bill_type_id}">📋 ${t.region_name} - ${t.bt_name} (${t.name})</option>`).join('');
  } else { copySel.innerHTML = '<option value="0">空白模板</option>'; }
  document.getElementById('addTplName').value = '';
  document.getElementById('addTemplateModal').classList.remove('hidden');
}

async function createAndEditTemplate() {
  const btId = document.getElementById('addTplBillType').value;
  const copyFrom = document.getElementById('addTplCopyFrom').value;
  const tplName = document.getElementById('addTplName').value.trim() || '默认模板';
  if (!btId) return toast('请选择账单类型', false);

  if (parseInt(copyFrom) > 0) {
    const src = await api('template&bill_type_id=' + copyFrom);
    if (src.ok && src.data) {
      await api('template_save', {bill_type_id: btId, name: tplName, html_template: src.data.html_template||'', css_template: src.data.css_template||'', js_template: src.data.js_template||''});
    }
  } else {
    await api('template_save', {bill_type_id: btId, name: tplName, html_template: '', css_template: '', js_template: ''});
  }
  closeModal('addTemplateModal');
  toast('模板已创建');
  await loadTemplateList();
  editTemplate(parseInt(btId));
}

// ──── 编辑模板 ────
async function editTemplate(btId) {
  currentTplBillTypeId = btId;
  const bt = allBillTypesData.find(b => b.id == btId);
  const btName = bt ? bt.name : '';
  const regionName = bt ? (bt.region_name||'') : '';
  const cat = bt ? (bt.category || 'utility') : 'utility';
  const catBadges = {bank:'🏦 银行', credit_card:'💳 信用卡', crypto:'🪙 加密货币', utility:'💧 公用事业'};

  document.getElementById('tplListView').classList.add('hidden');
  document.getElementById('tplEditorView').classList.remove('hidden');
  document.getElementById('tplEditorTitle').textContent = regionName + ' · ' + btName;
  document.getElementById('tplEditorBadge').textContent = catBadges[cat] || '💧 公用事业';
  document.getElementById('sampleDataType').value = cat === 'credit_card' ? 'credit_card' : (cat === 'bank' || cat === 'crypto' ? 'bank' : 'utility');

  const r = await api('template&bill_type_id=' + btId);
  if (r.ok && r.data) {
    if (editorHtml) editorHtml.setValue(r.data.html_template || '');
    if (editorCss) editorCss.setValue(r.data.css_template || '');
    if (editorJs) editorJs.setValue(r.data.js_template || '');
  } else {
    if (editorHtml) editorHtml.setValue('');
    if (editorCss) editorCss.setValue('');
    if (editorJs) editorJs.setValue('');
  }
  // ★ 始终用 PH_LABELS 渲染占位符（不依赖数据库 placeholder_map，避免保存后被覆盖为{}导致报错）
  renderPlaceholders(Object.keys(PH_LABELS));
  switchEditorTab('html');
  setTimeout(() => updatePreview(), 100);
}

// ★ 渲染占位符标签（带中文说明） ★
function renderPlaceholders(keys) {
  const container = document.getElementById('placeholderRef');
  container.innerHTML = keys.map(k => {
    const label = PH_LABELS[k] || k;
    return `<span class="ph-tag" onclick="insertPlaceholder('${k}')" title="${label}">{{${k}}}<span class="ph-label">${label}</span></span>`;
  }).join('');
}

function quickPreview(btId) { editTemplate(btId); }

async function deleteTemplate(btId) {
  if (!btId) btId = currentTplBillTypeId;
  if (!btId) return;
  if (!confirm('确定删除此模板？')) return;
  const r = await api('template_delete&bill_type_id=' + btId);
  if (r.ok) { toast('模板已删除'); if (currentTplBillTypeId == btId) backToList(); else loadTemplateList(); }
  else toast('删除失败', false);
}

async function saveTemplate() {
  if (!currentTplBillTypeId) return toast('请先选择模板', false);
  const data = {
    bill_type_id: currentTplBillTypeId, name: '自定义模板',
    html_template: editorHtml ? editorHtml.getValue() : '',
    css_template: editorCss ? editorCss.getValue() : '',
    js_template: editorJs ? editorJs.getValue() : '',
  };
  const r = await api('template_save', data);
  if (r.ok) toast('模板已保存'); else toast(r.error||'保存失败', false);
}

async function resetTemplate() {
  if (!currentTplBillTypeId) return;
  if (!confirm('确定恢复默认模板？当前修改将丢失。')) return;
  const r = await api('template_reset&bill_type_id='+currentTplBillTypeId);
  if (r.ok) { toast('已恢复默认'); editTemplate(currentTplBillTypeId); } else toast('失败', false);
}

function insertPlaceholder(name) {
  const editor = currentEditorTab === 'html' ? editorHtml : (currentEditorTab === 'css' ? editorCss : editorJs);
  if (editor) { editor.replaceSelection('{{' + name + '}}'); editor.focus(); }
}

// ──── 示例数据 ────
const SAMPLE_UTILITY = {
  customer_name:'张三', full_address:'12栋3单元501室，幸福路88号，天河区',
  address_unit:'12栋3单元501室', address_street:'幸福路88号', address_district:'天河区',
  bill_number:'WSD-12345678', account_number:'87654321',
  period_start:'15/11/2025', period_end:'10/03/2026', issue_date:'15/03/2026',
  consumption:'38.0', meter_prev:'5,432', meter_curr:'5,470', meter_number:'W123456',
  days:'116', daily_litres:'328',
  currency:'¥', unit:'m³', bill_type_name:'自来水公司水费单',
  t1:'12.0', t2:'26.0', t3:'0.0', t4:'0.0',
  c1:'33.60', c2:'109.20', c3:'0.00', c4:'19.00',
  water_charge:'142.80', sewage_charge:'57.00', total_due:'218.80',
  last_pay_date:'12/11/2025', last_pay_amt:'78.50', deposit:'389.00',
  surcharge_date:'29/03/2026', after_surcharge:'229.74',
  slip_ref:'1234567890', long_ref:'12345678 1234 5678 90', crc_code:'4567',
  bar_chart_svg:'<div style="padding:20px;text-align:center;color:#aaa;border:1px dashed #ccc;border-radius:4px;">[柱状图]</div>',
  qr_code_svg:'<div style="width:56px;height:56px;border:1px solid #ddd;display:flex;align-items:center;justify-content:center;font-size:8px;color:#aaa;">QR</div>',
  barcode_svg:'<div style="padding:10px;text-align:center;color:#aaa;font-size:9px;border:1px dashed #ccc;">[条码]</div>',
  bottom_barcode_svg:'<div style="padding:10px;text-align:center;color:#aaa;font-size:9px;border:1px dashed #ccc;">[条码]</div>',
  bank_name:'', bank_name_en:'', account_type:'', statement_period:'',
  opening_balance:'', closing_balance:'', total_credits:'', total_debits:'', transactions:'',
  card_number:'', credit_limit:'', available_credit:'', min_payment:'',
  payment_due_date:'', new_charges:'', new_payments:'', interest_charge:'',
  points_balance:'', previous_balance:'',
};

const SAMPLE_BANK = {
  customer_name:'张三', full_address:'12栋3单元501室，幸福路88号，天河区',
  address_unit:'12栋3单元501室', address_street:'幸福路88号', address_district:'天河区',
  bill_number:'STMT-20260315001', account_number:'6222 0200 0000 1234 567',
  period_start:'01/01/2026', period_end:'28/02/2026', issue_date:'15/03/2026',
  consumption:'0.0', meter_prev:'0', meter_curr:'0', meter_number:'',
  days:'59', daily_litres:'0',
  currency:'¥', unit:'笔', bill_type_name:'中国工商银行流水单',
  t1:'0.0', t2:'0.0', t3:'0.0', t4:'0.0',
  c1:'0.00', c2:'0.00', c3:'0.00', c4:'0.00',
  water_charge:'0.00', sewage_charge:'0.00', total_due:'53,500.20',
  last_pay_date:'', last_pay_amt:'0.00', deposit:'0.00',
  surcharge_date:'', after_surcharge:'0.00',
  slip_ref:'', long_ref:'', crc_code:'',
  bar_chart_svg:'', qr_code_svg:'', barcode_svg:'', bottom_barcode_svg:'',
  bank_name:'中国工商银行', bank_name_en:'ICBC',
  account_type:'储蓄账户', statement_period:'01/01/2026 — 28/02/2026',
  opening_balance:'50,000.00', closing_balance:'53,500.20',
  total_credits:'12,000.50', total_debits:'8,500.30',
  transactions: '<tr><td>2026-01-05</td><td>工资收入</td><td>6222000000</td><td class="debit"></td><td class="credit">¥8,500.00</td><td>¥58,500.00</td><td>REF001</td></tr>'
    +'<tr><td>2026-01-10</td><td>消费支出</td><td>6222000001</td><td class="debit">¥320.50</td><td class="credit"></td><td>¥58,179.50</td><td>REF002</td></tr>'
    +'<tr><td>2026-01-15</td><td>ATM取款</td><td></td><td class="debit">¥2,000.00</td><td class="credit"></td><td>¥56,179.50</td><td>REF003</td></tr>',
  card_number:'**** **** **** 4567', credit_limit:'', available_credit:'', min_payment:'',
  payment_due_date:'', new_charges:'', new_payments:'', interest_charge:'',
  points_balance:'', previous_balance:'',
};

const SAMPLE_CREDIT_CARD = {
  customer_name:'张三', full_address:'12栋3单元501室，幸福路88号，天河区',
  address_unit:'12栋3单元501室', address_street:'幸福路88号', address_district:'天河区',
  bill_number:'CC-20260615001', account_number:'6222 0200 0000 1234 567',
  period_start:'16/05/2026', period_end:'2026.06', issue_date:'2026年06月15日',
  consumption:'0.0', meter_prev:'0', meter_curr:'0', meter_number:'',
  days:'31', daily_litres:'0',
  currency:'¥', unit:'笔', bill_type_name:'信用卡对账单',
  t1:'0.0', t2:'0.0', t3:'0.0', t4:'0.0',
  c1:'0.00', c2:'0.00', c3:'0.00', c4:'0.00',
  water_charge:'0.00', sewage_charge:'0.00', total_due:'8,532.60',
  last_pay_date:'', last_pay_amt:'0.00', deposit:'0.00',
  surcharge_date:'', after_surcharge:'0.00',
  slip_ref:'', long_ref:'', crc_code:'',
  bar_chart_svg:'', qr_code_svg:'', barcode_svg:'', bottom_barcode_svg:'',
  bank_name:'中国工商银行', bank_name_en:'ICBC',
  account_type:'信用卡', statement_period:'16/05/2026 — 15/06/2026',
  opening_balance:'0.00', closing_balance:'8,532.60',
  total_credits:'0.00', total_debits:'8,532.60',
  transactions: '<tr><td colspan="6" class="row-category">还款</td></tr>'
    +'<tr class="row-highlight"><td></td><td>06/05</td><td class="text-left">掌上生活跨行还款</td><td class="text-right">-102.55</td><td>6277</td><td class="text-right">-102.55</td></tr>'
    +'<tr><td colspan="6" class="row-category">消费</td></tr>'
    +'<tr><td>05/18</td><td>05/19</td><td class="text-left">支付宝 拼多多商户</td><td class="text-right">21.52</td><td>6277</td><td class="text-right">21.52(CN)</td></tr>'
    +'<tr><td>06/03</td><td>06/04</td><td class="text-left">支付宝 拼多多商户</td><td class="text-right">65.10</td><td>6277</td><td class="text-right">65.10(CN)</td></tr>'
    +'<tr><td>06/08</td><td>06/09</td><td class="text-left">微信支付 美团外卖</td><td class="text-right">45.80</td><td>6277</td><td class="text-right">45.80(CN)</td></tr>'
    +'<tr><td>06/10</td><td>06/11</td><td class="text-left">POS消费 中国石化</td><td class="text-right">400.00</td><td>6277</td><td class="text-right">400.00(CN)</td></tr>'
    +'<tr><td>06/12</td><td>06/13</td><td class="text-left">支付宝 淘宝商户</td><td class="text-right">2,399.00</td><td>6277</td><td class="text-right">2,399.00(CN)</td></tr>',
  card_number:'**** **** **** 4567',
  credit_limit:'50,000.00',
  available_credit:'41,467.40',
  min_payment:'853.26',
  payment_due_date:'2026年07月02日',
  new_charges:'8,532.60',
  new_payments:'0.00',
  interest_charge:'0.00',
  points_balance:'8,532',
  previous_balance:'0.00',
  postal_code:'400000',
};

const SAMPLE_WISE = {
  customer_name:'', full_address:'3樓D室, SC, 644000, China',
  address_unit:'3樓D室', address_street:'', address_district:'宜宾',
  bill_number:'', account_number:'', period_start:'2026年6月10日', period_end:'2026年6月23日', issue_date:'2026年6月23日',
  currency:'€', unit:'txn', bill_type_name:'Wise EUR Statement',
  bank_name:'Wise', bank_name_en:'WISE',
  account_type:'EUR Account', statement_period:'2026年6月10日 - 2026年6月23日',
  total_due:'', wise_currency:'EUR',
  wise_period_start:'2026年6月10日', wise_period_end:'2026年6月23日',
  wise_generated_date:'2026年6月23日', wise_balance_date:'2026年6月23日',
  wise_balance:'1,250.00', wise_iban:'BE63 9058 6280 5408', wise_bic:'TRWIBEB1XXX',
  wise_city:'宜宾', wise_state:'SC', wise_postcode:'644000', wise_country:'China',
  wise_ref:'a1b2c3d4-e5f6-7890-abcd-ef1234567890', wise_timezone:'GMT+08:00',
  wise_transactions:'',
};

// ★ 实时预览 ★
function updatePreview() {
  const html = editorHtml ? editorHtml.getValue() : '';
  const css = editorCss ? editorCss.getValue() : '';
  if (!html.trim()) {
    document.getElementById('tplPreview').innerHTML = '<div class="flex items-center justify-center py-20 text-gray-300"><i class="fas fa-file-invoice text-4xl mr-3"></i><span>输入模板内容，预览将自动更新</span></div>';
    document.getElementById('previewStatus').innerHTML = '<i class="fas fa-circle text-xs mr-1" style="font-size:6px;"></i>等待输入';
    return;
  }
  const type = document.getElementById('sampleDataType').value;
  const sample = type === 'credit_card' ? SAMPLE_CREDIT_CARD : (type === 'wise' ? SAMPLE_WISE : (type === 'bank' ? SAMPLE_BANK : SAMPLE_UTILITY));
  let rendered = html;
  for (const [k, v] of Object.entries(sample)) rendered = rendered.replace(new RegExp('\\{\\{' + k + '\\}\\}', 'g'), v);
  rendered = rendered.replace(/\{\{\w+\}\}/g, '<span style="background:#ffe0b2;padding:0 4px;border-radius:2px;font-size:10px;color:#e65100;">$&</span>');
  document.getElementById('tplPreview').innerHTML = (css ? '<style>' + css + '</style>' : '') + rendered;
  document.getElementById('previewStatus').innerHTML = '<i class="fas fa-circle text-green-500 text-xs mr-1" style="font-size:6px;"></i>已同步';
}

// ══════════════════════════════════
// 上传HTML转模板
// ══════════════════════════════════
let uploadedRawHtml = '';
let uploadedCleanHtml = '';
let extractedUploadCss = '';
let detectedItems = [];

async function showUploadHtmlModal() {
    uploadedRawHtml = '';
    uploadedCleanHtml = '';
    extractedUploadCss = '';
    detectedItems = [];
    document.getElementById('uploadStep1').classList.remove('hidden');
    document.getElementById('uploadStep2').classList.add('hidden');
    document.getElementById('uploadPreview').innerHTML = '';
    document.getElementById('uploadDetectedTable').innerHTML = '';
    document.getElementById('uploadHtmlInput').value = '';
    document.getElementById('uploadFileName').textContent = '';
    document.getElementById('uploadTplName').value = '';
    document.getElementById('customMappingRow').classList.add('hidden');
    // Populate bill type dropdown
    const r = await api('bill_types');
    if (r.ok) {
        document.getElementById('uploadBillType').innerHTML = r.data.map(d =>
            `<option value="${d.id}">${d.region_name||''} - ${d.name}</option>`
        ).join('');
    }
    // Populate custom placeholder dropdown
    const phSel = document.getElementById('customToPlaceholder');
    phSel.innerHTML = '<option value="">选择占位符</option>' + Object.entries(PH_LABELS).map(([k,v]) =>
        `<option value="${k}">{{${k}}} — ${v}</option>`
    ).join('');
    document.getElementById('uploadModal').classList.remove('hidden');
}

function closeUploadModal() { document.getElementById('uploadModal').classList.add('hidden'); }
function uploadGoBack() { document.getElementById('uploadStep1').classList.remove('hidden'); document.getElementById('uploadStep2').classList.add('hidden'); }

function handleHtmlFile(file) {
    if (!file) return;
    if (!file.name.match(/\.html?$/i)) { toast('请选择 .html 或 .htm 文件', false); return; }
    document.getElementById('uploadFileName').innerHTML = '<i class="fas fa-file-code mr-1"></i>' + file.name;
    const reader = new FileReader();
    reader.onload = function(e) {
        const content = e.target.result;
        document.getElementById('uploadHtmlInput').value = content;
        processUploadedHtml(content);
    };
    reader.readAsText(file);
}

function processPastedHtml() {
    const content = document.getElementById('uploadHtmlInput').value.trim();
    if (!content) return toast('请上传文件或粘贴HTML代码', false);
    processUploadedHtml(content);
}

function processUploadedHtml(htmlStr) {
    uploadedRawHtml = htmlStr;

    // ① 提取 <style> 内容
    extractedUploadCss = '';
    const cssBlocks = [];
    const cssRegex = /<style[^>]*>([\s\S]*?)<\/style>/gi;
    let cssMatch;
    while ((cssMatch = cssRegex.exec(htmlStr)) !== null) {
        cssBlocks.push(cssMatch[1].trim());
    }
    extractedUploadCss = cssBlocks.join('\n');

    // ② 清理 HTML
    let clean = htmlStr;
    clean = clean.replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '');   // 移除 style
    clean = clean.replace(/<script[^>]*>[\s\S]*?<\/script>/gi, ''); // 移除 script
    clean = clean.replace(/<link[^>]*\/?>/gi, '');                  // 移除 link
    clean = clean.replace(/<meta[^>]*\/?>/gi, '');                  // 移除 meta
    clean = clean.replace(/<!DOCTYPE[^>]*>/gi, '');                 // 移除 doctype
    clean = clean.replace(/<\/?html[^>]*>/gi, '');                  // 移除 html 标签
    clean = clean.replace(/<head[\s\S]*?<\/head>/gi, '');           // 移除 head
    clean = clean.replace(/<\/?body[^>]*>/gi, '');                  // 移除 body
    clean = clean.trim();

    uploadedCleanHtml = clean;

    // ③ 自动检测占位符
    detectedItems = detectPlaceholders(clean);

    // ④ 切换到 Step 2
    document.getElementById('uploadStep1').classList.add('hidden');
    document.getElementById('uploadStep2').classList.remove('hidden');
    document.getElementById('detectedCount').textContent = detectedItems.length;

    renderDetectionTable();
    renderUploadPreview();
}

// ──── 智能检测规则 ────
const DETECT_RULES = [
    // 银行名称（中）
    { regex: /[\u4e00-\u9fff]*银行[\u4e00-\u9fff]*/g, ph: 'bank_name', label: '🏦银行名称', unique: true },
    // 银行英文名
    { regex: /\b(?:ICBC|HSBC|CommBank|DBS|Barclays|TD\s+Canada|ANZ|Westpac|BOC|CCB|ABC|BCM|CMB|SPDB|CITIC|CEB|CIB)\b/gi, ph: 'bank_name_en', label: '🏦银行(英)', unique: true },
    // 信用卡号（掩码）
    { regex: /\*{2,4}\s?\*{2,4}\s?\*{2,4}\s?\d{4}/g, ph: 'card_number', label: '💳信用卡号', unique: true },
    // 账号（分段数字 4-4-4-3/4）
    { regex: /\d{4}\s\d{4}\s\d{4}\s\d{3,4}/g, ph: 'account_number', label: '🔢账号', unique: true },
    // 中文日期
    { regex: /\d{4}年\d{1,2}月\d{1,2}日/g, ph: 'issue_date', label: '📅日期(中)', unique: false },
    // HK日期 DD/MM/YYYY
    { regex: /\d{1,2}\/\d{1,2}\/\d{4}/g, ph: 'period_start', label: '📅日期(HK)', unique: false },
    // ISO日期 YYYY-MM-DD
    { regex: /\d{4}-\d{2}-\d{2}/g, ph: 'period_start', label: '📅日期(ISO)', unique: false },
    // 账单周期 YYYY.MM
    { regex: /\b\d{4}\.\d{2}\b/g, ph: 'period_end', label: '📅周期', unique: false },
    // 货币金额（带符号）
    { regex: /[¥$£€]\s*[\d,]+\.?\d{0,2}/g, ph: 'total_due', label: '💰金额', unique: false },
    // 纯金额数字（在标签之间的独立小数）
    { regex: /(?<=>)\s*([\d,]+\.\d{2})\s*(?=<)/g, ph: 'amount', label: '💰金额(纯)', unique: false, group: 1 },
    // 邮编（6位纯数字在标签间）
    { regex: /(?<=>)\s*(\d{6})\s*(?=<)/g, ph: 'postal_code', label: '📮邮编', unique: true, group: 1 },
    // 中国姓名（2-4汉字独立标签间，排除常见非姓名词）
    { regex: /(?<=>)\s*([\u4e00-\u9fff]{2,4})\s*(?=<)/g, ph: 'customer_name', label: '👤姓名', unique: true, group: 1,
      exclude: new Set(['信用卡','账单日','还款日','交易日','记账日','人民币','消费','还款','摘要','金额','信用额度','交易摘要','银行','流水','对账单','账务','账单','本期','上期','通知','缴费','最低','通知单','应还','持卡人','客户','地址','户名','户号','账单编号']) },
    // 地址（必须同时含市/区/路/号/室/栋等地址关键字中的2个以上）
    { regex: /(?<=>)\s*([\u4e00-\u9fff\d][\u4e00-\u9fff\d\s]{4,}[\u4e00-\u9fff\d])\s*(?=<)/g, ph: 'full_address', label: '📍地址', unique: true, group: 1,
      filter: (t) => { const kw = ['市','区','路','街','号','室','栋','幢','单元','楼层','小区','花园','公寓','广场','大厦']; return kw.filter(k=>t.includes(k)).length >= 2; } },
    // 短地址片段（区/市名，独立在标签间）
    { regex: /(?<=>)\s*([\u4e00-\u9fff]{2,6}[市区县])\s*(?=<)/g, ph: 'address_district', label: '📍区域', unique: true, group: 1 },
    // 客服电话 400-xxx-xxxx
    { regex: /400-\d{3}-\d{4}/g, ph: 'phone', label: '📞客服电话', unique: true },
    // 银行客服 955xx
    { regex: /\b955\d{2}\b/g, ph: 'phone', label: '📞银行客服', unique: true },
    // 账单编号（含字母+数字组合）
    { regex: /\b[A-Z]{2,5}[-–]?\d{6,12}\b/g, ph: 'bill_number', label: '📋编号', unique: true },
];

function detectPlaceholders(htmlStr) {
    const items = [];
    const usedTexts = new Set();

    for (const rule of DETECT_RULES) {
        const regex = new RegExp(rule.regex.source, rule.regex.flags);
        let match;
        while ((match = regex.exec(htmlStr)) !== null) {
            const text = rule.group ? match[rule.group] : match[0];
            if (!text || text.trim().length < 2) continue;
            const key = text.trim();
            // 排除列表过滤
            if (rule.exclude && rule.exclude.has(key)) continue;
            // 自定义过滤函数
            if (rule.filter && !rule.filter(key)) continue;
            // 去重：同一原文只出现一次
            if (usedTexts.has(key)) continue;
            usedTexts.add(key);

            items.push({
                original: key,
                placeholder: rule.ph,
                label: rule.label,
                include: true,
            });

            if (rule.unique) break; // 唯一字段只取第一个匹配
        }
    }

    // 智能优化：如果有多个日期，依次分配不同占位符
    const dateItems = items.filter(i => ['issue_date','period_start','period_end'].includes(i.placeholder));
    if (dateItems.length >= 2) {
        dateItems[0].placeholder = 'period_start';
        dateItems[1].placeholder = 'period_end';
        if (dateItems.length >= 3) dateItems[2].placeholder = 'issue_date';
    }
    // 如果有多个金额，分配不同的
    const amtItems = items.filter(i => ['total_due','amount'].includes(i.placeholder));
    if (amtItems.length >= 2) {
        amtItems[0].placeholder = 'total_due';
        amtItems[1].placeholder = 'new_charges';
        if (amtItems.length >= 3) amtItems[2].placeholder = 'min_payment';
        if (amtItems.length >= 4) amtItems[3].placeholder = 'credit_limit';
    }

    return items;
}

function renderDetectionTable() {
    const tbody = document.getElementById('uploadDetectedTable');
    if (detectedItems.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-8 text-gray-400"><i class="fas fa-search text-2xl mb-2 block"></i>未自动检测到可替换内容<br/>请使用「手动添加替换」或跳过检测直接编辑</td></tr>';
        return;
    }

    const phOptions = Object.entries(PH_LABELS).map(([k, v]) =>
        `<option value="${k}">{{${k}}} — ${v}</option>`
    ).join('');

    tbody.innerHTML = detectedItems.map((item, i) => {
        const display = item.original.length > 30 ? item.original.substring(0, 30) + '…' : item.original;
        const phOptions = Object.entries(PH_LABELS).map(([k, v]) =>
            `<option value="${k}" ${k === item.placeholder ? 'selected' : ''}>{{${k}}} — ${v}</option>`
        ).join('');
        return `<tr>
            <td class="font-mono" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${item.original.replace(/"/g,'&quot;')}">${display}</td>
            <td><span class="text-xs">${item.label}</span></td>
            <td><select class="form-input text-xs py-1 px-1" onchange="detectedItems[${i}].placeholder=this.value;renderUploadPreview()">
                <option value="">— 不替换 —</option>${phOptions}
            </select></td>
            <td class="text-center"><input type="checkbox" ${item.include ? 'checked' : ''} onchange="detectedItems[${i}].include=this.checked;renderUploadPreview()"/></td>
        </tr>`;
    }).join('');

    document.getElementById('detectedCount').textContent = detectedItems.filter(i => i.include).length;
}

function renderUploadPreview() {
    let preview = uploadedCleanHtml;
    // 高亮将被替换的文本
    for (const item of detectedItems) {
        if (!item.include || !item.placeholder) continue;
        const escaped = item.original.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        try {
            preview = preview.replace(new RegExp(escaped, 'g'),
                `<span style="background:#ffe0b2;padding:0 4px;border-radius:2px;font-size:10px;color:#e65100;border:1px dashed #e65100;">{{${item.placeholder}}}</span>`);
        } catch(e) {}
    }
    document.getElementById('uploadPreview').innerHTML =
        (extractedUploadCss ? '<style>' + extractedUploadCss + '</style>' : '') + preview;
    document.getElementById('detectedCount').textContent = detectedItems.filter(i => i.include && i.placeholder).length;
}

// 手动添加替换映射
function addCustomMapping() {
    document.getElementById('customMappingRow').classList.remove('hidden');
    document.getElementById('customFromText').value = '';
    document.getElementById('customFromText').focus();
}
function cancelCustomMapping() { document.getElementById('customMappingRow').classList.add('hidden'); }
function confirmCustomMapping() {
    const from = document.getElementById('customFromText').value.trim();
    const to = document.getElementById('customToPlaceholder').value;
    if (!from) return toast('请输入要替换的原文', false);
    if (!to) return toast('请选择占位符', false);
    // 检查是否重复
    if (detectedItems.some(i => i.original === from)) return toast('该原文已存在', false);
    detectedItems.push({ original: from, placeholder: to, label: PH_LABELS[to] || '✏️自定义', include: true });
    renderDetectionTable();
    renderUploadPreview();
    cancelCustomMapping();
    toast('已添加替换项');
}

// 跳过检测，直接在编辑器中打开
async function skipToEditor() {
    const btId = document.getElementById('uploadBillType').value;
    const tplName = document.getElementById('uploadTplName').value.trim() || '上传模板';
    if (!btId) return toast('请先选择账单类型', false);

    const r = await api('template_save', {
        bill_type_id: btId, name: tplName,
        html_template: uploadedCleanHtml,
        css_template: extractedUploadCss,
        js_template: '',
    });
    if (r.ok) {
        closeUploadModal();
        toast('模板已创建，请编辑');
        await loadTemplateList();
        editTemplate(parseInt(btId));
    } else { toast(r.error || '创建失败', false); }
}

// 创建模板（应用所有替换）
async function saveUploadedTemplate() {
    const btId = document.getElementById('uploadBillType').value;
    const tplName = document.getElementById('uploadTplName').value.trim() || '上传模板';
    if (!btId) return toast('请选择账单类型', false);

    const activeItems = detectedItems.filter(i => i.include && i.placeholder);
    if (activeItems.length === 0) {
        if (!confirm('没有启用任何替换项，将以原始HTML创建模板。继续？')) return;
    }

    // 应用所有替换
    let template = uploadedCleanHtml;
    // 按原文长度降序排列，先替换长的（避免短文本替换干扰长文本）
    const sorted = [...activeItems].sort((a, b) => b.original.length - a.original.length);
    for (const item of sorted) {
        const escaped = item.original.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        try {
            template = template.replace(new RegExp(escaped, 'g'), '{{' + item.placeholder + '}}');
        } catch(e) { console.warn('替换失败:', item.original); }
    }

    const r = await api('template_save', {
        bill_type_id: btId, name: tplName,
        html_template: template,
        css_template: extractedUploadCss,
        js_template: '',
    });
    if (r.ok) {
        closeUploadModal();
        toast('模板已创建，可在编辑器中继续调整');
        await loadTemplateList();
        editTemplate(parseInt(btId));
    } else { toast(r.error || '创建失败', false); }
}

// ══════════════════════════════════
// 用户管理 JS
// ══════════════════════════════════
let allUsersData = [];
let currentUserDetailId = null;
let currentUserSubTab = 'orders';

async function loadUsers() {
  const r = await api('admin_users');
  if (!r.ok) return;
  allUsersData = r.users || [];
  renderStats(r.stats);
  renderUsersTable(allUsersData);
}

function renderStats(stats) {
  if (!stats) return;
  document.getElementById('statUsers').textContent = stats.total_users || 0;
  document.getElementById('statTopupUsdc').textContent = stats.total_topup_usdc || 0;
  document.getElementById('statTopupUsdt').textContent = stats.total_topup_usdt || 0;
  document.getElementById('statDownloads').textContent = stats.total_downloads || 0;
  document.getElementById('statRevenue').textContent = '$' + ((stats.total_topup_usdc || 0) + (stats.total_topup_usdt || 0)).toLocaleString();
}

function renderUsersTable(users) {
  const tbody = document.getElementById('usersTable');
  if (users.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-8 text-gray-400"><i class="fas fa-inbox text-2xl mb-2 block"></i>暂无用户</td></tr>';
    return;
  }
  tbody.innerHTML = users.map(u => `
    <tr>
      <td class="font-mono text-xs">#${u.id}</td>
      <td class="font-semibold">${escapeHtml(u.username)}</td>
      <td class="text-xs text-gray-500">${escapeHtml(u.email || '-')}</td>
      <td><span class="text-xs font-bold text-blue-600">${u.coins_usdc || 0} USDC</span><br><span class="text-xs font-bold text-green-600">${u.coins_usdt || 0} USDT</span></td>
      <td class="text-xs">${u.total_topup || 0} / ${u.download_count || 0} 次</td>
      <td class="text-xs text-gray-500">${u.created_at || '-'}</td>
      <td>
        <div class="flex gap-1">
          <button onclick="viewUserDetail(${u.id})" class="px-2 py-1 text-xs bg-primary text-white rounded hover:bg-primary-dark transition"><i class="fas fa-eye mr-1"></i>详情</button>
          <button onclick="showEditUserModal(${u.id})" class="px-2 py-1 text-xs border border-blue-200 text-blue-600 rounded hover:bg-blue-50 transition"><i class="fas fa-edit"></i></button>
          <button onclick="deleteUser(${u.id}, '${escapeHtml(u.username)}')" class="px-2 py-1 text-xs border border-red-200 text-red-500 rounded hover:bg-red-50 transition"><i class="fas fa-trash"></i></button>
        </div>
      </td>
    </tr>
  `).join('');
}

async function viewUserDetail(uid) {
  const r = await api('admin_user_detail&user_id=' + uid);
  if (!r.ok) { toast(r.error || '加载失败', false); return; }
  currentUserDetailId = uid;
  const u = r.user;

  document.getElementById('userListView').classList.add('hidden');
  document.getElementById('userDetailView').classList.remove('hidden');
  document.getElementById('userDetailTitle').textContent = '用户详情 · ' + u.username;

  // 操作按钮
  document.getElementById('userDetailActions').innerHTML = `
    <button onclick="showAddCoinsModal(${u.id})" class="px-3 py-1.5 bg-amber-500 text-white rounded-lg text-xs font-medium hover:bg-amber-600 transition"><i class="fas fa-coins mr-1"></i>充值</button>
    <button onclick="showEditUserModal(${u.id})" class="px-3 py-1.5 border border-blue-300 text-blue-600 rounded-lg text-xs font-medium hover:bg-blue-50 transition"><i class="fas fa-edit mr-1"></i>编辑</button>
    <button onclick="backToUserList()" class="px-3 py-1.5 border rounded-lg text-xs hover:bg-gray-50 transition">返回列表</button>
  `;

  // 用户信息卡片
  document.getElementById('userDetailCard').innerHTML = `
    <div class="grid grid-cols-4 gap-4">
      <div><div class="text-xs text-gray-400">用户ID</div><div class="font-bold text-gray-800">#${u.id}</div></div>
      <div><div class="text-xs text-gray-400">用户名</div><div class="font-bold text-gray-800">${escapeHtml(u.username)}</div></div>
      <div><div class="text-xs text-gray-400">邮箱</div><div class="font-bold text-gray-800">${escapeHtml(u.email || '未设置')}</div></div>
      <div><div class="text-xs text-gray-400">注册时间</div><div class="font-bold text-gray-800">${u.created_at || '-'}</div></div>
      <div><div class="text-xs text-gray-400">余额</div><div class="font-bold text-blue-600">USDC: ${u.coins_usdc || 0}</div><div class="font-bold text-green-600">USDT: ${u.coins_usdt || 0}</div></div>
      <div><div class="text-xs text-gray-400">累计充值 / 下载</div><div class="font-bold text-gray-800">${u.total_topup || 0} / ${u.download_count || 0}</div></div>
      <div><div class="text-xs text-gray-400">累计下载次数</div><div class="font-bold text-gray-800">${u.download_logs ? u.download_logs.length : 0}</div></div>
      <div><div class="text-xs text-gray-400">充值订单数</div><div class="font-bold text-gray-800">${u.orders ? u.orders.length : 0}</div></div>
    </div>
  `;

  // 存储详情数据
  currentUserDetailId = uid;
  window._userDetailOrders = u.orders || [];
  window._userDetailDownloads = u.download_logs || [];

  // 显示订单记录
  switchUserSubTab('orders');
}

function switchUserSubTab(tab) {
  currentUserSubTab = tab;
  document.querySelectorAll('.user-sub-tab').forEach(b => {
    b.classList.toggle('bg-primary', b.dataset.subtab === tab);
    b.classList.toggle('text-white', b.dataset.subtab === tab);
    b.classList.toggle('bg-gray-100', b.dataset.subtab !== tab);
    b.classList.toggle('text-gray-600', b.dataset.subtab !== tab);
  });
  document.getElementById('userSubOrders').classList.toggle('hidden', tab !== 'orders');
  document.getElementById('userSubDownloads').classList.toggle('hidden', tab !== 'downloads');

  if (tab === 'orders') renderDetailOrders();
  if (tab === 'downloads') renderDetailDownloads();
}

function renderDetailOrders() {
  const orders = window._userDetailOrders || [];
  const tbody = document.getElementById('ordersTable');
  if (orders.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-gray-400">暂无充值记录</td></tr>';
    return;
  }
  tbody.innerHTML = '<thead><tr><th>订单ID</th><th>币种</th><th>套餐</th><th>数量</th><th>金额</th><th>链</th><th>状态</th><th>时间</th></tr></thead>' +
    orders.map(o => `
      <tr>
        <td class="font-mono text-xs">#${o.id}</td>
        <td><span class="text-xs font-bold ${o.coin_type === 'USDT' ? 'text-green-600' : 'text-blue-600'}">${o.coin_type || 'USDC'}</span></td>
        <td>${escapeHtml(o.package_name)}</td>
        <td class="font-bold">+${o.coin_amount} ${o.coin_type || 'USDC'}</td>
        <td>$ ${parseFloat(o.price).toFixed(2)}</td>
        <td class="text-xs text-gray-500">${o.network || '-'}</td>
        <td><span class="text-xs px-2 py-0.5 rounded-full ${o.status === 'paid' ? 'bg-green-100 text-green-700' : (o.status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-500')}">${o.status === 'paid' ? '已完成' : (o.status === 'pending' ? '待支付' : '已取消')}</span></td>
        <td class="text-xs text-gray-500">${o.created_at || '-'}</td>
      </tr>
    `).join('');
}

function renderDetailDownloads() {
  const downloads = window._userDetailDownloads || [];
  const tbody = document.getElementById('downloadsTable');
  if (downloads.length === 0) {
    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-8 text-gray-400">暂无下载记录</td></tr>';
    return;
  }
  tbody.innerHTML = '<thead><tr><th>记录ID</th><th>账单类型</th><th>币种</th><th>消耗</th><th>下载时间</th></tr></thead>' +
    downloads.map(d => `
      <tr>
        <td class="font-mono text-xs">#${d.id}</td>
        <td>${escapeHtml(d.bill_type_name || '账单 #' + d.bill_type_id)}</td>
        <td><span class="text-xs font-bold ${d.coin_type === 'USDT' ? 'text-green-600' : 'text-blue-600'}">${d.coin_type || 'USDC'}</span></td>
        <td class="font-bold text-red-500">-${d.coins_used} ${d.coin_type || 'USDC'}</td>
        <td class="text-xs text-gray-500">${d.created_at || '-'}</td>
      </tr>
    `).join('');
}

function backToUserList() {
  document.getElementById('userListView').classList.remove('hidden');
  document.getElementById('userDetailView').classList.add('hidden');
  currentUserDetailId = null;
  loadUsers();
}

// 编辑用户弹窗
function showEditUserModal(uid) {
  let u = null;
  if (uid && allUsersData.length > 0) {
    u = allUsersData.find(x => x.id == uid);
  }

  const modal = document.createElement('div');
  modal.className = 'fixed inset-0 bg-black/50 z-[100] flex items-center justify-center';
  modal.id = 'editUserModal';
  modal.innerHTML = `
    <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-lg mx-4">
      <h3 class="text-lg font-bold mb-4"><i class="fas fa-edit mr-2 text-primary"></i>${u ? '编辑用户 #' + u.id : '编辑用户'}</h3>
      <div class="space-y-3">
        <div><label class="block text-xs font-medium text-gray-600 mb-1">用户名</label><input id="editUsername" type="text" class="form-input" value="${u ? escapeHtml(u.username) : ''}" placeholder="用户名"/></div>
        <div><label class="block text-xs font-medium text-gray-600 mb-1">邮箱</label><input id="editEmail" type="email" class="form-input" value="${u ? escapeHtml(u.email || '') : ''}" placeholder="user@example.com"/></div>
        <div><label class="block text-xs font-medium text-gray-600 mb-1">USDC 余额</label><input id="editCoinsUsdc" type="number" min="0" class="form-input" value="${u ? (u.coins_usdc || 0) : 0}"/></div>
        <div><label class="block text-xs font-medium text-gray-600 mb-1">USDT 余额</label><input id="editCoinsUsdt" type="number" min="0" class="form-input" value="${u ? (u.coins_usdt || 0) : 0}"/></div>
        <div><label class="block text-xs font-medium text-gray-600 mb-1">新密码 <span class="text-gray-400">（留空不修改）</span></label><input id="editPassword" type="password" class="form-input" placeholder="至少6位，留空不修改"/></div>
      </div>
      <div class="flex justify-end gap-2 mt-4">
        <button onclick="closeEditUserModal()" class="px-4 py-2 border rounded-lg text-sm">取消</button>
        <button onclick="saveEditUser(${uid || 0})" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium">保存</button>
      </div>
    </div>
  `;
  document.body.appendChild(modal);
}

function closeEditUserModal() {
  const m = document.getElementById('editUserModal');
  if (m) m.remove();
}

async function saveEditUser(uid) {
  const data = {
    id: uid,
    username: document.getElementById('editUsername').value.trim(),
    email: document.getElementById('editEmail').value.trim(),
    coins_usdc: parseInt(document.getElementById('editCoinsUsdc').value) || 0,
    coins_usdt: parseInt(document.getElementById('editCoinsUsdt').value) || 0,
    password: document.getElementById('editPassword').value,
  };
  if (!data.username) { toast('用户名不能为空', false); return; }

  const r = await api('admin_user_update', data);
  if (r.ok) {
    toast(r.message || '已更新');
    closeEditUserModal();
    loadUsers();
    if (currentUserDetailId == uid) viewUserDetail(uid);
  } else {
    toast(r.error || '更新失败', false);
  }
}

async function deleteUser(uid, uname) {
  if (!confirm(`确定删除用户「${uname}」及其所有订单和下载记录吗？此操作不可撤销。`)) return;
  const r = await api('admin_user_delete&user_id=' + uid);
  if (r.ok) {
    toast('用户已删除');
    loadUsers();
    if (currentUserDetailId == uid) backToUserList();
  } else {
    toast(r.error || '删除失败', false);
  }
}

// 充值 USDC/USDT 弹窗
function showAddCoinsModal(uid) {
  const modal = document.createElement('div');
  modal.className = 'fixed inset-0 bg-black/50 z-[100] flex items-center justify-center';
  modal.id = 'addCoinsModal';
  modal.innerHTML = `
    <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md mx-4">
      <h3 class="text-lg font-bold mb-4"><i class="fas fa-coins mr-2 text-amber-500"></i>管理员充值</h3>
      <div class="space-y-3">
        <div><label class="block text-xs font-medium text-gray-600 mb-1">币种</label>
          <select id="addCoinsType" class="form-input">
            <option value="USDC">USDC</option>
            <option value="USDT">USDT</option>
          </select>
        </div>
        <div><label class="block text-xs font-medium text-gray-600 mb-1">充值数量</label><input id="addCoinsAmount" type="number" min="1" class="form-input" placeholder="输入充值数量" value="30"/></div>
        <div><label class="block text-xs font-medium text-gray-600 mb-1">充值理由</label><input id="addCoinsReason" type="text" class="form-input" value="管理员充值"/></div>
        <div class="p-3 bg-amber-50 rounded-lg border border-amber-200 text-xs text-amber-700">
          <i class="fas fa-info-circle mr-1"></i>充值后立即到账，不产生实际费用（模拟模式）。
        </div>
      </div>
      <div class="flex justify-end gap-2 mt-4">
        <button onclick="closeAddCoinsModal()" class="px-4 py-2 border rounded-lg text-sm">取消</button>
        <button onclick="doAdminAddCoins(${uid})" class="px-4 py-2 bg-amber-500 text-white rounded-lg text-sm font-medium hover:bg-amber-600 transition"><i class="fas fa-check mr-1"></i>确认充值</button>
      </div>
    </div>
  `;
  document.body.appendChild(modal);
}

function closeAddCoinsModal() {
  const m = document.getElementById('addCoinsModal');
  if (m) m.remove();
}

async function doAdminAddCoins(uid) {
  const amount = parseInt(document.getElementById('addCoinsAmount').value) || 0;
  const coinType = document.getElementById('addCoinsType')?.value || 'USDC';
  const reason = document.getElementById('addCoinsReason').value.trim() || '管理员充值';
  if (amount <= 0) { toast('请输入有效的充值数量', false); return; }

  const r = await api('admin_add_coins', { user_id: uid, amount, coin_type: coinType, reason });
  if (r.ok) {
    toast('✅ ' + r.message);
    closeAddCoinsModal();
    loadUsers();
    if (currentUserDetailId == uid) viewUserDetail(uid);
  } else {
    toast(r.error || '充值失败', false);
  }
}

function refreshUsers() {
  backToUserList();
  loadUsers();
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = typeof str === 'string' ? str : '';
  return div.innerHTML;
}

loadRegions();

// ════ 修改密码 ════
function showAdminPwdModal() {
  document.getElementById('oldAdminPwd').value = '';
  document.getElementById('newAdminPwd').value = '';
  document.getElementById('adminPwdModal').classList.remove('hidden');
}

async function saveAdminPwd() {
  const old_password = document.getElementById('oldAdminPwd').value;
  const new_password = document.getElementById('newAdminPwd').value;
  if(!old_password || !new_password) return toast('请完整填写新旧密码', false);

  const r = await api('admin_change_password', { old_password, new_password });
  if(r.ok) {
    toast('密码修改成功，请重新登录！');
    setTimeout(() => location.reload(), 1500);
  } else {
    toast(r.error || '修改失败', false);
  }
}

// ════ 公告管理 JS ════
let announcementEditor = null;

async function loadAnnouncement() {
  // 初始化 CodeMirror（首次）
  if (!announcementEditor) {
    announcementEditor = CodeMirror.fromTextArea(document.getElementById('announcementHtml'), {
      mode: 'htmlmixed', theme: 'dracula', lineNumbers: true, lineWrapping: true
    });
    announcementEditor.on('change', () => updateAnnouncementPreview());
  }
  // 从 API 加载当前配置
  const r = await api('site_config');
  if (r.ok && r.data) {
    document.getElementById('heroTitle').value = r.data.hero_title || '枪王账单学习模版';
    if (announcementEditor) {
      announcementEditor.setValue(r.data.hero_content || '');
    }
  }
  updateAnnouncementPreview();
  setTimeout(() => { if (announcementEditor) announcementEditor.refresh(); }, 100);
}

function updateAnnouncementPreview() {
  const title = document.getElementById('heroTitle').value || '枪王账单学习模版';
  const html = announcementEditor ? announcementEditor.getValue() : '';
  document.getElementById('announcementPreviewTitle').textContent = title;
  document.getElementById('announcementPreviewContent').innerHTML = html;
}

async function saveAnnouncement() {
  const data = {
    hero_title: document.getElementById('heroTitle').value.trim(),
    hero_content: announcementEditor ? announcementEditor.getValue() : ''
  };
  const r = await api('site_config_save', data);
  if (r.ok) toast('公告已保存'); else toast(r.error || '保存失败', false);
}

// ════ 博主审批 JS ════
async function loadBloggers() {
    const r = await api('admin_bloggers');
    if (!r.ok) return;
    if (r.data.length === 0) {
        document.getElementById('bloggersTable').innerHTML = '<tr><td colspan="7" class="text-center py-8 text-gray-400">暂无博主申请记录</td></tr>';
        return;
    }
    document.getElementById('bloggersTable').innerHTML = r.data.map(b => `
        <tr>
            <td class="font-mono text-xs">#${b.id}</td>
            <td class="font-semibold">${escapeHtml(b.username)}</td>
            <td><a href="${b.channel_link}" target="_blank" class="text-blue-500 hover:underline">验证主页</a></td>
            <td class="text-xs">${escapeHtml(b.contact)}</td>
            <td class="text-xs text-gray-500">${b.created_at}</td>
            <td>${b.status == 0 ? '<span class="text-yellow-600 bg-yellow-100 px-2 py-0.5 rounded">待审核</span>' : (b.status == 1 ? '<span class="text-green-600 bg-green-100 px-2 py-0.5 rounded">已通过</span>' : '<span class="text-red-600 bg-red-100 px-2 py-0.5 rounded">已驳回</span>')}</td>
            <td>
                ${b.status == 0 ? `
                    <button onclick="reviewBlogger(${b.id}, 1)" class="px-2 py-1 text-xs bg-green-500 text-white rounded hover:bg-green-600">通过</button>
                    <button onclick="reviewBlogger(${b.id}, 2)" class="px-2 py-1 text-xs bg-red-500 text-white rounded hover:bg-red-600 ml-1">驳回</button>
                ` : '-'}
            </td>
        </tr>
    `).join('');
}

async function reviewBlogger(id, status) {
    const r = await api('admin_review_blogger', {id: id, status: status});
    if(r.ok) loadBloggers();
}
</script>
<?php endif; ?>
</body>
</html>
