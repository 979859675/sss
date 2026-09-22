<?php
/**
 * 枪王 - 前台首页
 * PHP 8.0+
 * ⚠️ 仅限学习演示用途，不得用于欺诈、伪造或其他违法活动
 */
declare(strict_types=1);
require_once __DIR__ . '/config.php';

// 获取用户会话信息
startUserSession();
$userLoggedIn = isUserLoggedIn();
$userInfo = null;
if ($userLoggedIn) {
    $userInfo = getUserInfo(getCurrentUserId());
}
$userCoinsUsdc = $userInfo ? (int)$userInfo['coins_usdc'] : 0;
$userCoinsUsdt = $userInfo ? (int)$userInfo['coins_usdt'] : 0;
$userDownloadCredits = $userInfo ? (int)$userInfo['download_credits'] : 0;

// 获取前端数据
$db = getDB();
$siteConfig = getSiteConfig($db);
$heroTitle = $siteConfig['hero_title'] ?? '枪王账单学习模版';
$heroContent = $siteConfig['hero_content'] ?? '<p class="text-gray-600 max-w-2xl leading-relaxed">多地区最新格式的水费单、电费单、燃气费单、话费单、银行流水单文档示例。<span class="text-orange-600 font-semibold">仅限学习演示用途。</span></p>';
$frontendData = [
    'regions' => getRegions(),
    'bill_types' => getAllBillTypes(),
];
// 获取所有模板
$templates = [];
$tplRows = $db->query('SELECT bt.id as btid, bt.code as bt_code, t.html_template, t.css_template, t.js_template FROM bill_types bt LEFT JOIN templates t ON t.bill_type_id=bt.id WHERE bt.is_active=1')->fetchAll();
foreach ($tplRows as $row) {
    $templates[$row['btid']] = $row;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>枪王 - 枪王账单学习模版</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <!-- 新增：用于多页图片打包 ZIP -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
  <script>tailwind.config={theme:{extend:{colors:{primary:'#1976d2','primary-dark':'#1565c0'}}}}</script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@300;400;500;700&family=Roboto:wght@300;400;500;700&display=swap');
    *{font-family:'Roboto','Noto Sans SC',sans-serif;}
    body{background:#f0f2f5;}
    .region-btn.active{border-color:#1976d2;background:#e3f2fd;box-shadow:0 2px 8px rgba(25,118,210,0.2);}
    .bill-type-btn.active{border-color:#1976d2;background:#e3f2fd;}
    .bill-type-btn.active .bill-icon{color:#1976d2;}
    .form-input{width:100%;padding:10px 14px;border:1px solid #ccc;border-radius:8px;font-size:14px;transition:all 0.2s;}
    .form-input:focus{outline:none;border-color:#1976d2;box-shadow:0 0 0 3px rgba(25,118,210,0.1);}
    .bill-preview-container{background:white;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.15);position:relative;overflow-x:auto;}
#preview-bill{width:max-content;min-width:100%;}
/* 预览水印 */
#preview-watermark{position:absolute;top:0;left:0;width:100%;height:100%;z-index:10;pointer-events:none;display:none;overflow:hidden;}
#preview-watermark canvas{display:block;opacity:0.12;}
    .fade-in{animation:fadeIn 0.4s ease;}
    @keyframes fadeIn{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);}}
    .disclaimer-banner{background:linear-gradient(135deg,#fff3e0 0%,#ffe0b2 100%);border-left:4px solid #ff6f00;}
    .field-group{transition:all 0.3s ease;}
    .field-group.collapsed{max-height:0;overflow:hidden;opacity:0;margin:0;padding:0;}
    .field-group.expanded{max-height:2000px;opacity:1;}
    @media print{.no-print{display:none!important;}.bill-preview-container{box-shadow:none;}}
  </style>
</head>
<body class="min-h-screen">

<!-- Nav -->
<nav class="bg-white shadow-sm sticky top-0 z-50 no-print">
  <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 rounded-lg bg-primary flex items-center justify-center"><i class="fas fa-file-invoice text-white text-lg"></i></div>
      <span class="text-xl font-bold text-gray-800">枪王</span>
      <span class="hidden sm:inline text-xs bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full font-medium">枪王账单学习模版</span>
    </div>
    <div class="flex items-center gap-3">
      <!-- 用户区域 -->
      <div id="userArea" class="flex items-center gap-2">
        <?php if ($userLoggedIn && $userInfo): ?>
        <span class="text-sm text-gray-600">👤 <strong><?=htmlspecialchars($userInfo['username'])?></strong></span>
        <span class="text-xs bg-blue-100 text-blue-700 px-3 py-1.5 rounded-full font-semibold">剩余下载次数：<span id="headerCredits"><?=(int)$userInfo['download_credits']?></span> 次</span>
        <button onclick="showRedeemModal()" class="text-xs px-3 py-1.5 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition font-medium shadow-sm"><i class="fas fa-gift mr-1"></i>兑换福利码</button>
        <button onclick="doUserLogout()" class="text-xs px-2.5 py-1.5 border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 transition"><i class="fas fa-sign-out-alt"></i></button>
        <?php else: ?>
        <button onclick="showAuthModal('login')" class="text-sm px-3 py-1.5 bg-primary text-white rounded-lg hover:bg-primary-dark transition font-medium"><i class="fas fa-sign-in-alt mr-1"></i>登录</button>
        <button onclick="showAuthModal('register')" class="text-sm px-3 py-1.5 border border-primary text-primary rounded-lg hover:bg-primary/5 transition font-medium"><i class="fas fa-user-plus mr-1"></i>注册</button>
        <?php endif; ?>
      </div>
      <a href="blogger.php" class="text-sm text-gray-500 hover:text-primary transition"><i class="fas fa-star text-yellow-500 mr-1"></i>博主后台</a>
    </div>
  </div>
</nav>

<!-- 博主福利横幅 -->
<div class="bg-gradient-to-r from-red-500 to-orange-500 text-white text-center py-2 text-sm font-medium shadow cursor-pointer hover:bg-red-600 transition" onclick="showBloggerApplyModal()">
  📢 粉丝数 > 100 的博主/群主请进！点击申请专属后台，为您的粉丝发放免费下载福利！🎁
</div>

<!-- Disclaimer -->
<div class="disclaimer-banner no-print">
  <div class="max-w-7xl mx-auto px-4 py-2.5 flex items-center gap-2">
    <i class="fas fa-exclamation-triangle text-orange-600"></i>
    <p class="text-sm text-orange-800 font-medium">⚠️ 仅限学习演示用途，不得用于欺诈、伪造或其他违法活动。</p>
  </div>
</div>

<!-- Hero -->
<div class="bg-white border-b no-print">
  <div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-3"><?=htmlspecialchars($heroTitle)?></h1>
    <?=$heroContent?>
  </div>
</div>

<!-- Region Selection -->
<div class="max-w-7xl mx-auto px-4 pt-6 no-print">
  <div class="flex items-center gap-2 mb-3"><i class="fas fa-globe-asia text-primary"></i><span class="font-semibold text-gray-700">选择地区</span></div>
  <div id="regionSelector" class="flex flex-wrap gap-3 mb-6"></div>
  <div class="flex items-center gap-2 mb-3"><i class="fas fa-file-alt text-primary"></i><span class="font-semibold text-gray-700">选择账单类型</span></div>
  <div id="billTypeSelector" class="flex flex-wrap gap-3 mb-6"></div>
</div>

<!-- Main -->
<div class="max-w-7xl mx-auto px-4 pb-12">
  <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
    <!-- LEFT: Form -->
    <div class="lg:col-span-2 no-print">
      <div class="bg-white rounded-2xl shadow-sm border p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-5 flex items-center gap-2"><i class="fas fa-edit text-primary"></i>填写信息</h2>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-user text-gray-400 mr-1"></i>证件姓名 <span class="text-red-500">*</span></label>
          <input id="customerName" type="text" class="form-input" placeholder="如：CHAN KA WAI" oninput="updatePreview()"/>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-building text-gray-400 mr-1"></i>单元地址</label>
          <input id="addressUnit" type="text" class="form-input" placeholder="如：12樓A室" oninput="updatePreview()"/>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-road text-gray-400 mr-1"></i>街道地址</label>
          <input id="addressStreet" type="text" class="form-input" placeholder="如：彌敦道123號" oninput="updatePreview()"/>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-map-marker-alt text-gray-400 mr-1"></i>区域地址</label>
          <input id="addressDistrict" type="text" class="form-input" placeholder="如：九龍旺角" oninput="updatePreview()"/>
        </div>
        <button onclick="fillRandomAddress()" class="w-full mb-4 py-2.5 border-2 border-dashed border-primary/30 rounded-xl text-primary hover:bg-primary/5 transition flex items-center justify-center gap-2 font-medium"><i class="fas fa-dice"></i>填充随机真实地址</button>

        <!-- ──── 通用日期字段（所有类型共用） ──── -->
        <div class="mb-4 grid grid-cols-2 gap-3">
          <div><label class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-calendar text-gray-400 mr-1"></i>起始日期</label><input id="periodStart" type="date" class="form-input" oninput="updatePreview()"/></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-calendar text-gray-400 mr-1"></i>截止日期</label><input id="periodEnd" type="date" class="form-input" oninput="updatePreview()"/></div>
        </div>

        <div class="border rounded-xl overflow-hidden mb-4">
          <button onclick="toggleAdvanced()" class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 hover:bg-gray-100 transition"><span class="font-medium text-gray-700"><i class="fas fa-cog mr-2 text-gray-400"></i>高级选项</span><i id="advancedIcon" class="fas fa-expand-more text-gray-400 transition-transform"></i></button>
          <div id="advancedPanel" class="hidden px-4 pb-4 pt-2 space-y-4 bg-white">

            <!-- ──── 水电/公用事业字段 ──── -->
            <div id="utilityFields" class="field-group expanded space-y-4">
              <div><label class="block text-sm font-medium text-gray-700 mb-1">账单编号</label><input id="billNumber" type="text" class="form-input" oninput="updatePreview()"/></div>
              <div><label class="block text-sm font-medium text-gray-700 mb-1">用量 (立方米)</label><input id="consumption" type="number" class="form-input" oninput="updatePreview()"/></div>
              <div><label class="block text-sm font-medium text-gray-700 mb-1">发出日期</label><input id="issueDate" type="date" class="form-input" oninput="updatePreview()"/></div>
              <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">上次读数</label><input id="meterPrev" type="number" class="form-input" oninput="updatePreview()"/></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">本次读数</label><input id="meterCurr" type="number" class="form-input" oninput="updatePreview()"/></div>
              </div>
              <div><label class="block text-sm font-medium text-gray-700 mb-1">水錶編號</label><input id="meterNo" type="text" class="form-input" oninput="updatePreview()"/></div>
            </div>

            <!-- ──── 银行流水字段 ──── -->
            <div id="bankFields" class="field-group collapsed space-y-4">
              <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-university text-gray-400 mr-1"></i>银行名称</label><input id="bankName" type="text" class="form-input" placeholder="如：中国工商银行" oninput="updatePreview()"/></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">银行英文名</label><input id="bankNameEn" type="text" class="form-input" placeholder="如：ICBC" oninput="updatePreview()"/></div>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">账户类型</label>
                  <input id="accountType" type="text" class="form-input" placeholder="如：储蓄账户 / Savings Account" oninput="updatePreview()"/>
                </div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">账单编号</label><input id="billNumberBank" type="text" class="form-input" oninput="updatePreview()"/></div>
              </div>
              <div><label class="block text-sm font-medium text-gray-700 mb-1">发出日期</label><input id="issueDateBank" type="date" class="form-input" oninput="updatePreview()"/></div>

              <!-- ──── Monzo / UK 专用字段 ──── -->
              <div id="monzoFields" class="hidden space-y-4 p-3 bg-rose-50 rounded-lg border border-rose-200">
                <div class="text-xs font-semibold text-rose-600 mb-1"><i class="fas fa-university mr-1"></i>Monzo / UK Bank Details</div>
                <div class="grid grid-cols-2 gap-3">
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Sort Code</label><input id="sortCode" type="text" class="form-input" placeholder="04-00-06" oninput="updatePreview()"/></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Account Number</label><input id="accountNumber" type="text" class="form-input" placeholder="40008040" oninput="updatePreview()"/></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">BIC</label><input id="bicCode" type="text" class="form-input" placeholder="MONZGB2L" oninput="updatePreview()"/></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">IBAN</label><input id="ibanCode" type="text" class="form-input" placeholder="GB80 MONZ 0400 0640 0080 40" oninput="updatePreview()"/></div>
                </div>
                <div class="border-t border-rose-200 pt-3 mt-1">
                  <div class="text-xs font-semibold text-gray-600 mb-2"><i class="fas fa-map-marker-alt mr-1"></i>Address</div>
                  <div class="space-y-3">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label><input id="monzoName" type="text" class="form-input" placeholder="" oninput="updatePreview()"/></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Street Address</label><input id="monzoStreet" type="text" class="form-input" placeholder="37 Orchard Terrace" oninput="updatePreview()"/></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">City / Town</label><input id="monzoCity" type="text" class="form-input" placeholder="Huddersfield" oninput="updatePreview()"/></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Postcode</label><input id="monzoPostcode" type="text" class="form-input" placeholder="HD4 6DB" oninput="updatePreview()"/></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Country</label><input id="monzoCountry" type="text" class="form-input bg-gray-50" value="United Kingdom" readonly/><p class="text-xs text-gray-400 mt-0.5">此模板锁定为 United Kingdom</p></div>
                  </div>
                </div>
                <div class="border-t border-rose-200 pt-3 mt-1">
                  <div class="text-xs font-semibold text-gray-600 mb-2"><i class="fas fa-pound-sign mr-1"></i>Balances</div>
                  <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Personal Balance</label><input id="openingBalance" type="number" step="0.01" class="form-input" placeholder="0.00" oninput="updatePreview()"/></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Balance in Pots</label><input id="balancePots" type="number" step="0.01" class="form-input" placeholder="0.00" oninput="updatePreview()"/></div>
                  </div>
                  <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Total Outgoings</label><input id="totalOutgoings" type="number" step="0.01" class="form-input" placeholder="0.00" oninput="onMonzoTxnChange()"/></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Total Deposits</label><input id="totalDeposits" type="number" step="0.01" class="form-input" placeholder="0.00" oninput="onMonzoTxnChange()"/></div>
                  </div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">交易笔数</label>
                    <input id="txnCountMonzo" type="number" min="0" step="1" class="form-input" placeholder="0" value="0" oninput="onMonzoTxnChange()"/>
                    <p class="text-xs text-gray-400 mt-1">设为 0 则金额全部为 0，无交易记录</p>
                  </div>
                </div>
              </div>

              <!-- ──── 非Monzo银行字段 ──── -->
              <div id="nonMonzoBankFields" class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">期初余额</label><input id="openingBalanceGeneral" type="number" step="0.01" class="form-input" placeholder="10000.00" oninput="updatePreview()"/></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">期末余额</label><input id="closingBalance" type="text" class="form-input bg-gray-50" readonly placeholder="自动计算"/></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                  <div><label class="block text-sm font-medium text-gray-700 mb-1 text-green-700"><i class="fas fa-arrow-down mr-1"></i>收入合计</label><input id="totalCredits" type="number" step="0.01" class="form-input border-green-300" placeholder="5000.00" oninput="calcClosing()"/></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1 text-red-700"><i class="fas fa-arrow-up mr-1"></i>支出合计</label><input id="totalDebits" type="number" step="0.01" class="form-input border-red-300" placeholder="3000.00" oninput="calcClosing()"/></div>
                </div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">交易笔数</label>
                  <select id="txnCount" class="form-input" onchange="updatePreview()">
                    <option value="8">8 笔</option>
                    <option value="12" selected>12 笔</option>
                    <option value="16">16 笔</option>
                    <option value="20">20 笔</option>
                  </select>
                </div>
              </div>

              <!-- ──── 招商银行信用卡专用字段 ──── -->
              <div id="cmbFields" class="hidden space-y-4 p-3 bg-red-50 rounded-lg border border-red-200">
                <div class="text-xs font-semibold text-red-600 mb-1"><i class="fas fa-credit-card mr-1"></i>招商银行信用卡 CMB Credit Card</div>

                <!-- 地址 (5行: 邮编/市/区+街道/详细/姓名) -->
                <div class="border-t border-red-200 pt-3 mt-1">
                  <div class="text-xs font-semibold text-gray-600 mb-2"><i class="fas fa-map-marker-alt mr-1"></i>地址 (从上到下)</div>
                  <div class="space-y-3">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">邮政编码</label><input id="cmbPostalCode" type="text" class="form-input" placeholder="404000" oninput="updatePreview()"/></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">市/省</label><input id="cmbCity" type="text" class="form-input" placeholder="" oninput="updatePreview()"/></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">区/街道</label><input id="cmbDistrict" type="text" class="form-input" placeholder="" oninput="updatePreview()"/></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">详细地址</label><input id="cmbUnit" type="text" class="form-input" placeholder="楼12幢三单元107室" oninput="updatePreview()"/></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">姓名</label><input id="cmbName" type="text" class="form-input" placeholder="" oninput="updatePreview()"/></div>
                  </div>
                </div>

                <!-- 卡片信息 -->
                <div class="border-t border-red-200 pt-3 mt-1">
                  <div class="text-xs font-semibold text-gray-600 mb-2"><i class="fas fa-credit-card mr-1"></i>卡片信息</div>
                  <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">卡号末四位</label><input id="cmbCardLast4" type="text" class="form-input" placeholder="6277" maxlength="4" oninput="updatePreview()"/></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">信用额度</label><input id="cmbCreditLimit" type="number" step="0.01" class="form-input" placeholder="5000.00" oninput="onCMBChange()"/></div>
                  </div>
                  <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">到期还款日</label><input id="cmbPaymentDueDate" type="date" class="form-input" oninput="updatePreview()"/></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">交易笔数</label><input id="cmbTxnCount" type="number" min="0" step="1" class="form-input" placeholder="3" value="3" oninput="onCMBChange()"/></div>
                  </div>
                </div>

                <!-- 金额 (公式: 本期应还 = 上期账单 - 上期还款 + 本期账单 - 调整 + 利息) -->
                <div class="border-t border-red-200 pt-3 mt-1">
                  <div class="text-xs font-semibold text-gray-600 mb-2"><i class="fas fa-calculator mr-1"></i>金额明细</div>
                  <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">上期账单金额</label><input id="cmbPrevBalance" type="number" step="0.01" class="form-input" placeholder="102.55" oninput="onCMBChange()"/></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">上期还款金额</label><input id="cmbPrevPayment" type="number" step="0.01" class="form-input" placeholder="102.55" oninput="onCMBChange()"/></div>
                  </div>
                  <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">本期账单金额</label><input id="cmbNewCharges" type="number" step="0.01" class="form-input" placeholder="86.62" oninput="onCMBChange()"/></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">本期调整金额</label><input id="cmbAdjustment" type="number" step="0.01" class="form-input" placeholder="0.00" oninput="onCMBChange()"/></div>
                  </div>
                  <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">循环利息</label><input id="cmbInterest" type="number" step="0.01" class="form-input" placeholder="0.00" oninput="onCMBChange()"/></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">本期应还金额</label><input id="cmbNewBalance" type="text" class="form-input bg-gray-50" readonly placeholder="自动计算"/></div>
                  </div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">最低还款额</label><input id="cmbMinPayment" type="text" class="form-input bg-gray-50" readonly placeholder="自动计算"/></div>
                  <p class="text-xs text-gray-400 mt-1">公式: 本期应还 = 上期账单 - 上期还款 + 本期账单 - 调整 + 利息</p>
                </div>
              </div>

              <!-- ──── Kraken专用字段 ──── -->
              <div id="krakenFields" class="hidden space-y-4 p-3 bg-purple-50 rounded-lg border border-purple-200">
                <div class="text-xs font-semibold text-purple-600 mb-1"><i class="fab fa-bitcoin mr-1"></i>Kraken Statement</div>

                <!-- 个人信息 -->
                <div class="border-t border-purple-200 pt-3 mt-1">
                  <div class="text-xs font-semibold text-gray-600 mb-2"><i class="fas fa-user mr-1"></i>Personal Info</div>
                  <div class="space-y-3">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label><input id="krakenName" type="text" class="form-input" placeholder="" oninput="updatePreview()"/></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Street Address</label><input id="krakenStreet" type="text" class="form-input" placeholder="37 ORCHARD TERRACE" oninput="updatePreview()"/></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">City / Postcode</label><input id="krakenCity" type="text" class="form-input" placeholder="Huddersfield, HD4 6DB" oninput="updatePreview()"/></div>
                  </div>
                </div>

                <!-- 账户信息 -->
                <div class="border-t border-purple-200 pt-3 mt-1">
                  <div class="text-xs font-semibold text-gray-600 mb-2"><i class="fas fa-key mr-1"></i>Account Info</div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Kraken Public ID</label><input id="krakenPublicId" type="text" class="form-input" placeholder="AA35 N84G NCHU DYFI" oninput="updatePreview()"/></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Account ID</label><input id="krakenAccountId" type="text" class="form-input" placeholder="WUC9 A8RP HZM7 U7Q5" oninput="updatePreview()"/></div>
                </div>

                <!-- 日期时间 -->
                <div class="border-t border-purple-200 pt-3 mt-1">
                  <div class="text-xs font-semibold text-gray-600 mb-2"><i class="fas fa-calendar-alt mr-1"></i>Balance Date / Time</div>
                  <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Balance Date</label><input id="krakenBalanceDate" type="date" class="form-input" oninput="updatePreview()"/></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Time (UTC)</label><input id="krakenBalanceTime" type="text" class="form-input" placeholder="00:00:00" oninput="updatePreview()"/></div>
                  </div>
                  <p class="text-xs text-gray-400 mt-1">留空则自动设为次月1号 00:00:00 UTC</p>
                </div>

                <!-- 交易笔数 -->
                <div class="border-t border-purple-200 pt-3 mt-1">
                  <div class="text-xs font-semibold text-gray-600 mb-2"><i class="fas fa-list mr-1"></i>Activity</div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">交易笔数</label>
                    <input id="krakenTxnCount" type="number" min="0" step="1" class="form-input" placeholder="8" value="8" oninput="updatePreview()"/>
                    <p class="text-xs text-gray-400 mt-1">设为 0 则无交易记录</p>
                  </div>
                </div>
              </div>

              <!-- ──── Wise EUR 专用字段 ──── -->
              <div id="wiseFields" class="hidden space-y-4 p-3 bg-green-50 rounded-lg border border-green-200">
                <div class="text-xs font-semibold text-green-600 mb-1"><i class="fas fa-exchange-alt mr-1"></i>Wise EUR Statement</div>

                <!-- 日期 -->
                <div class="border-t border-green-200 pt-3 mt-1">
                  <div class="text-xs font-semibold text-gray-600 mb-2"><i class="fas fa-calendar-alt mr-1"></i>日期 / Period</div>
                  <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                      <div><label class="block text-sm font-medium text-gray-700 mb-1">开始日期</label><input id="wisePeriodStart" type="text" class="form-input" placeholder="2026年6月10日" oninput="updatePreview()"/></div>
                      <div><label class="block text-sm font-medium text-gray-700 mb-1">结束日期</label><input id="wisePeriodEnd" type="text" class="form-input" placeholder="2026年6月23日" oninput="updatePreview()"/></div>
                    </div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">生成日期</label><input id="wiseGeneratedDate" type="text" class="form-input" placeholder="2026年6月23日" oninput="updatePreview()"/></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Balance Date</label><input id="wiseBalanceDate" type="text" class="form-input" placeholder="2026年6月23日" oninput="updatePreview()"/></div>
                    <div class="grid grid-cols-2 gap-3">
                      <div><label class="block text-sm font-medium text-gray-700 mb-1">时区标签</label><input id="wiseTimezone" type="text" class="form-input" placeholder="GMT+08:00" value="GMT+08:00" oninput="updatePreview()"/></div>
                      <div><label class="block text-sm font-medium text-gray-700 mb-1">货币</label>
                        <select id="wiseCurrency" class="form-input" oninput="updatePreview()">
                          <option value="EUR">EUR</option>
                          <option value="GBP">GBP</option>
                        </select>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- 个人信息 -->
                <div class="border-t border-green-200 pt-3 mt-1">
                  <div class="text-xs font-semibold text-gray-600 mb-2"><i class="fas fa-user mr-1"></i>Personal Info</div>
                  <div class="space-y-3">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label><input id="wiseName" type="text" class="form-input" placeholder="" oninput="updatePreview()"/></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Street Address</label><input id="wiseStreet" type="text" class="form-input" placeholder="Sichuān Shěng Yíbīn Shì Xùzhōu Qū Bóxī Jiēdào Xīnlóng Cūn 10 Zǔ 104 Hào" oninput="updatePreview()"/></div>
                    <div class="grid grid-cols-3 gap-3">
                      <div><label class="block text-sm font-medium text-gray-700 mb-1">City</label><input id="wiseCity" type="text" class="form-input" placeholder="宜宾" oninput="updatePreview()"/></div>
                      <div><label class="block text-sm font-medium text-gray-700 mb-1">State/Province</label><input id="wiseState" type="text" class="form-input" placeholder="SC" oninput="updatePreview()"/></div>
                      <div><label class="block text-sm font-medium text-gray-700 mb-1">Postcode</label><input id="wisePostcode" type="text" class="form-input" placeholder="644000" oninput="updatePreview()"/></div>
                    </div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Country</label><input id="wiseCountry" type="text" class="form-input" placeholder="China" oninput="updatePreview()"/></div>
                  </div>
                </div>

                <!-- 账户信息 -->
                <div class="border-t border-green-200 pt-3 mt-1">
                  <div class="text-xs font-semibold text-gray-600 mb-2"><i class="fas fa-key mr-1"></i>Account Info</div>
                  <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">账号</label><input id="wiseAccountNumber" type="text" class="form-input" placeholder="82922330" oninput="updatePreview()"/></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Sort Code</label><input id="wiseSortCode" type="text" class="form-input" placeholder="60-84-64" oninput="updatePreview()"/></div>
                  </div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">IBAN</label><input id="wiseIban" type="text" class="form-input" placeholder="GB23 TRWI 6084 6482 9223 30" oninput="updatePreview()"/></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Swift/BIC</label><input id="wiseBic" type="text" class="form-input" placeholder="TRWIGB2LXXX" oninput="updatePreview()"/></div>
                </div>

                <!-- 余额 -->
                <div class="border-t border-green-200 pt-3 mt-1">
                  <div class="text-xs font-semibold text-gray-600 mb-2"><i class="fas fa-euro-sign mr-1"></i>Balance</div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">余额</label><input id="wiseBalance" type="text" class="form-input" placeholder="0.00" oninput="updatePreview()"/></div>
                </div>

                <!-- 交易 -->
                <div class="border-t border-green-200 pt-3 mt-1">
                  <div class="text-xs font-semibold text-gray-600 mb-2"><i class="fas fa-list mr-1"></i>Transactions</div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">交易笔数</label>
                    <input id="wiseTxnCount" type="number" min="0" step="1" class="form-input" placeholder="0" value="0" oninput="updatePreview()"/>
                    <p class="text-xs text-gray-400 mt-1">设为 0 则无交易记录</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- ──── Monese 专用字段 ──── -->
            <div id="moneseFields" class="hidden space-y-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
              <div class="text-xs font-semibold text-blue-600 mb-1"><i class="fas fa-university mr-1"></i>Monese EUR Statement</div>

              <div class="border-t border-blue-200 pt-3 mt-1">
                <div class="grid grid-cols-2 gap-3">
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">开始日期</label><input id="moneseStart" type="date" class="form-input" oninput="updatePreview()"/></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">结束日期</label><input id="moneseEnd" type="date" class="form-input" oninput="updatePreview()"/></div>
                </div>
              </div>

              <div class="border-t border-blue-200 pt-3 mt-1">
                <div class="text-xs font-semibold text-gray-600 mb-2"><i class="fas fa-user mr-1"></i>个人地址信息</div>
                <div class="space-y-3">
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">姓名</label><input id="moneseName" type="text" class="form-input" placeholder="CAO CAO" oninput="updatePreview()"/></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">街道门牌</label><input id="moneseStreet" type="text" class="form-input" placeholder="Hattenheimer Str. 19" oninput="updatePreview()"/></div>
                  <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">邮编</label><input id="monesePostcode" type="text" class="form-input" placeholder="60326" oninput="updatePreview()"/></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">城市</label><input id="moneseCity" type="text" class="form-input" placeholder="Frankfurt am Main" oninput="updatePreview()"/></div>
                  </div>
                </div>
              </div>

              <div class="border-t border-blue-200 pt-3 mt-1">
                <div class="text-xs font-semibold text-gray-600 mb-2"><i class="fas fa-key mr-1"></i>账户与金额</div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">IBAN</label><input id="moneseIban" type="text" class="form-input" placeholder="BE59974132270526" oninput="updatePreview()"/></div>
                <div class="grid grid-cols-2 gap-3 mb-2">
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">BIC</label><input id="moneseBic" type="text" class="form-input" placeholder="PESOBEB1" oninput="updatePreview()"/></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Monese Kenn-nr.</label><input id="moneseAccount" type="text" class="form-input" placeholder="M59199197" oninput="updatePreview()"/></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">上期余额</label><input id="moneseOpening" type="number" step="0.01" class="form-input" placeholder="0.00" oninput="updatePreview()"/></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">交易笔数</label><input id="moneseTxnCount" type="number" step="1" class="form-input" placeholder="3" value="3" oninput="updatePreview()"/></div>
                </div>
                <div class="grid grid-cols-2 gap-3 mt-2">
                  <div><label class="block text-sm font-medium text-gray-700 mb-1 text-green-700">总收入</label><input id="moneseIn" type="number" step="0.01" class="form-input" placeholder="47.39" oninput="updatePreview()"/></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1 text-red-700">总支出</label><input id="moneseOut" type="number" step="0.01" class="form-input" placeholder="47.31" oninput="updatePreview()"/></div>
                </div>
              </div>
            </div>

            <!-- ──── Octopus Energy 专用字段 ──── -->
            <div id="octopusFields" class="hidden space-y-4 p-3 bg-teal-50 rounded-lg border border-teal-200">
              <div class="text-xs font-semibold text-teal-600 mb-1"><i class="fas fa-bolt mr-1"></i>Octopus Energy Details</div>

              <div class="border-t border-teal-200 pt-3 mt-1">
                <div class="text-xs font-semibold text-gray-600 mb-2"><i class="fas fa-user mr-1"></i>地址信息</div>
                <div class="space-y-3">
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">姓名</label><input id="octName" type="text" class="form-input" placeholder="LI HONGWEI" oninput="updatePreview()"/></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">街道</label><input id="octStreet" type="text" class="form-input" placeholder="69 Fairfax St" oninput="updatePreview()"/></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">城市</label><input id="octCity" type="text" class="form-input" placeholder="Birmingham" oninput="updatePreview()"/></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">邮编</label><input id="octPostcode" type="text" class="form-input" placeholder="B5S 8PL" oninput="updatePreview()"/></div>
                </div>
              </div>

              <div class="border-t border-teal-200 pt-3 mt-1">
                <div class="text-xs font-semibold text-gray-600 mb-2"><i class="fas fa-file-invoice mr-1"></i>账单与金额</div>
                <div class="grid grid-cols-2 gap-3">
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">上期余额</label><input id="octPrevBalance" type="text" class="form-input" placeholder="-1,690.47" oninput="updatePreview()"/></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">本期余额</label><input id="octNewBalance" type="text" class="form-input" placeholder="-608.00" oninput="updatePreview()"/></div>
                </div>
                <div class="grid grid-cols-3 gap-3 mt-3">
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">退款1</label><input id="octC1" type="text" class="form-input" placeholder="232.90" oninput="updatePreview()"/></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">退款2</label><input id="octC2" type="text" class="form-input" placeholder="217.54" oninput="updatePreview()"/></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">退款3</label><input id="octC3" type="text" class="form-input" placeholder="246.08" oninput="updatePreview()"/></div>
                </div>
                <div class="grid grid-cols-3 gap-3 mt-3">
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">退款1日期范围</label><input id="octT1Date" type="text" class="form-input" placeholder="29th May - 12th June" oninput="updatePreview()"/></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">退款2日期范围</label><input id="octT2Date" type="text" class="form-input" placeholder="14th May - 28th May" oninput="updatePreview()"/></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">退款3日期范围</label><input id="octT3Date" type="text" class="form-input" placeholder="13th Apr. - 13th May" oninput="updatePreview()"/></div>
                </div>
              </div>

              <div class="border-t border-teal-200 pt-3 mt-1">
                <div class="text-xs font-semibold text-gray-600 mb-2"><i class="fas fa-hashtag mr-1"></i>账号信息</div>
                <div class="grid grid-cols-2 gap-3">
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">账号</label><input id="octAccount" type="text" class="form-input" placeholder="A-420ZB18U" oninput="updatePreview()"/></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">参考编号</label><input id="octBillNum" type="text" class="form-input" placeholder="973075694" oninput="updatePreview()"/></div>
                </div>
              </div>
            </div>

          </div>
        </div>
        <button onclick="generateBill()" id="generateBtn" class="w-full py-3.5 bg-primary hover:bg-primary-dark text-white rounded-xl font-semibold text-base transition-all shadow-md hover:shadow-lg flex items-center justify-center gap-2"><i class="fas fa-magic"></i>生成账单</button>
        <button onclick="doDownloadWithAuth()" id="downloadBtn" class="w-full mt-3 py-3 border-2 border-primary text-primary hover:bg-primary/5 rounded-xl font-semibold text-base transition-all flex items-center justify-center gap-2"><i class="fas fa-file-pdf"></i>下载 PDF <span id="downloadCostBadge" class="text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full">-1 次</span></button>

        <!-- 新增：下载高清图片按钮 -->
        <button onclick="doDownloadImageWithAuth()" id="downloadImgBtn" class="w-full mt-3 py-3 border-2 border-green-500 text-green-600 hover:bg-green-50 rounded-xl font-semibold text-base transition-all flex items-center justify-center gap-2">
          <i class="fas fa-image"></i>下载高清图片
          <span class="text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full">-1 次</span>
        </button>
        <div class="mt-4 p-3 bg-orange-50 rounded-xl border border-orange-200"><p class="text-xs text-orange-700"><i class="fas fa-exclamation-circle mr-1"></i>仅可用于学习演示用途，不得用于欺诈、伪造或其他违法活动。</p></div>
      </div>
    </div>
    <!-- RIGHT: Preview -->
    <div class="lg:col-span-3">
      <div class="sticky top-20">
        <div class="flex items-center justify-between mb-3 no-print"><h2 class="text-lg font-bold text-gray-800 flex items-center gap-2"><i class="fas fa-eye text-primary"></i>预览窗口</h2></div>
        <div class="bill-preview-container" id="previewContainer">
          <div id="preview-area" class="p-1" style="min-height:600px;">
            <div id="preview-watermark"></div>
            <div id="preview-default" class="flex flex-col items-center justify-center py-20 px-8 text-center">
              <div class="w-20 h-20 rounded-full bg-blue-50 flex items-center justify-center mb-4"><i class="fas fa-file-invoice text-3xl text-primary/40"></i></div>
              <h3 class="text-lg font-semibold text-gray-400 mb-2">预览窗口</h3>
              <div class="max-w-sm"><div class="flex items-start gap-3 p-4 bg-blue-50 rounded-xl text-left"><i class="fas fa-info-circle text-primary mt-0.5"></i><div><h4 class="font-semibold text-primary text-sm mb-1">如何使用</h4><p class="text-xs text-gray-600">填写表单信息，然后点击生成按钮，创建您的账单示例。</p><p class="text-xs text-orange-600 mt-2 font-medium">仅可用于学习演示用途。</p></div></div></div>
            </div>
            <div id="preview-bill" class="hidden"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════ -->
<!-- 登录/注册弹窗 -->
<!-- ══════════════════════════════════ -->
<div id="authModal" class="fixed inset-0 z-[9999] hidden items-center justify-center" style="background:rgba(0,0,0,0.5);">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden animate__animated animate__fadeIn">
    <div class="p-6">
      <div class="flex items-center justify-between mb-5">
        <h2 id="authModalTitle" class="text-xl font-bold text-gray-800">登录</h2>
        <button onclick="hideAuthModal()" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
      </div>
      <div id="authError" class="hidden mb-3 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm"></div>
      <form onsubmit="handleAuthSubmit(event)" id="authForm">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">用户名</label>
          <input id="authUsername" type="text" class="form-input" placeholder="请输入用户名" required minlength="2"/>
        </div>
        <div class="mb-5">
          <label class="block text-sm font-medium text-gray-700 mb-1">密码</label>
          <input id="authPassword" type="password" class="form-input" placeholder="请输入密码" required minlength="6"/>
        </div>
        <div id="registerBonusTip" class="hidden mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
          <i class="fas fa-gift mr-1"></i> 注册即送 <strong><?=REGISTER_BONUS?> 次</strong>免费下载机会，也可通过博主福利码获取更多次数。
        </div>
        <button type="submit" id="authSubmitBtn" class="w-full py-3 bg-primary hover:bg-primary-dark text-white rounded-xl font-semibold transition">
          <i class="fas fa-sign-in-alt mr-2"></i>登录
        </button>
      </form>
      <div class="mt-4 text-center">
        <span id="authSwitchText" class="text-sm text-gray-500">没有账号？</span>
        <a href="#" onclick="toggleAuthMode(event)" id="authSwitchLink" class="text-sm text-primary hover:underline font-medium">立即注册</a>
      </div>
      <div class="mt-3 text-center">
        <span class="text-xs text-gray-400"><i class="fas fa-info-circle mr-1"></i>游客可预览，下载需登录</span>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════ -->
<!-- 充值弹窗 -->
<!-- ══════════════════════════════════ -->
<div id="topUpModal" class="fixed inset-0 z-[9999] hidden items-center justify-center" style="background:rgba(0,0,0,0.5);">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
    <div class="p-6">
      <div class="flex items-center justify-between mb-5">
        <h2 class="text-xl font-bold text-gray-800"><i class="fas fa-coins text-amber-500 mr-2"></i>购买 USDC / USDT</h2>
        <button onclick="hideTopUpModal()" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
      </div>
      <div class="mb-4 p-3 bg-blue-50 rounded-lg text-sm text-blue-700">
        <i class="fas fa-info-circle mr-1"></i> 当前余额：<strong><span id="topUpCurrentUsdc">0</span> USDC + <span id="topUpCurrentUsdt">0</span> USDT</strong>，每次下载消耗 <strong><?=DOWNLOAD_COST?> USDC 或 USDT</strong>
      </div>
      <!-- 币种切换 -->
      <div class="flex gap-2 mb-4" id="topUpCoinTabs">
        <button onclick="switchTopUpCoin('USDC')" class="topup-coin-tab flex-1 py-2 rounded-lg text-sm font-semibold transition bg-blue-600 text-white" data-coin="USDC">💵 USDC</button>
        <button onclick="switchTopUpCoin('USDT')" class="topup-coin-tab flex-1 py-2 rounded-lg text-sm font-semibold transition bg-gray-100 text-gray-600" data-coin="USDT">💵 USDT</button>
      </div>
      <div id="topUpMsg" class="hidden mb-3 p-3 rounded-lg text-sm"></div>
      <div id="packageList" class="grid grid-cols-2 gap-3 mb-5">
        <!-- JS 动态填充 -->
      </div>
      <div class="p-3 bg-amber-50 rounded-lg border border-amber-200 text-xs text-amber-700">
        <i class="fas fa-flask mr-1"></i> <strong>模拟链上转账：</strong>本系统为演示模式，模拟 USDC/USDT 链上转账流程，不涉及真实加密货币交易。
      </div>
      <div class="mt-4 text-center">
        <button onclick="hideTopUpModal()" class="text-sm text-gray-500 hover:text-gray-700">关闭</button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════ -->
<!-- 模拟链上支付弹窗 -->
<!-- ══════════════════════════════════ -->
<div id="payModal" class="fixed inset-0 z-[99999] hidden items-center justify-center" style="background:rgba(0,0,0,0.6);">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
    <!-- 头部 -->
    <div class="bg-gradient-to-r from-gray-900 to-gray-800 px-6 py-5 text-white">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-bold"><i class="fas fa-link mr-2"></i>链上转账</h3>
        <button onclick="hidePayModal()" class="text-white/70 hover:text-white text-xl">&times;</button>
      </div>
    </div>
    <div class="p-6">
      <!-- 转账信息 -->
      <div class="mb-5 p-4 bg-gray-50 rounded-xl">
        <div class="flex justify-between mb-2">
          <span class="text-sm text-gray-500">订单编号</span>
          <span class="text-sm font-mono font-bold text-gray-800" id="payOrderId">#--</span>
        </div>
        <div class="flex justify-between mb-2">
          <span class="text-sm text-gray-500">以太币</span>
          <span class="text-sm font-semibold" id="payCoinType">USDC</span>
        </div>
        <div class="flex justify-between mb-2">
          <span class="text-sm text-gray-500">数量</span>
          <span class="text-sm font-bold text-blue-600" id="payCoins">0</span>
        </div>
        <div class="flex justify-between mb-2">
          <span class="text-sm text-gray-500">网络</span>
          <span class="text-sm font-mono text-xs text-gray-600" id="payNetwork">ERC20</span>
        </div>
        <div class="flex justify-between mb-2">
          <span class="text-sm text-gray-500">收款地址</span>
          <span class="text-xs font-mono text-gray-500 truncate ml-2 max-w-[180px]" id="payToAddress">0x...</span>
        </div>
        <div class="border-t pt-2 mt-2 flex justify-between">
          <span class="text-sm font-semibold text-gray-700">应付金额</span>
          <span class="text-lg font-bold text-gray-800" id="payAmount">$ 0.00</span>
        </div>
        <div class="flex justify-between mt-1">
          <span class="text-xs text-gray-400">预估 Gas 费</span>
          <span class="text-xs text-gray-400" id="payGasFee">~ $0.00</span>
        </div>
      </div>

      <!-- 交易状态模拟 -->
      <div id="payStatus" class="hidden mb-4 p-3 rounded-lg text-sm text-center"></div>

      <!-- 转账进度 -->
      <div id="payProgress" class="hidden mb-4 space-y-2">
        <div class="flex items-center gap-2 text-xs">
          <i id="step1Icon" class="fas fa-circle text-gray-300 text-[8px]"></i>
          <span id="step1Text" class="text-gray-400">发起转账交易...</span>
        </div>
        <div class="flex items-center gap-2 text-xs">
          <i id="step2Icon" class="fas fa-circle text-gray-300 text-[8px]"></i>
          <span id="step2Text" class="text-gray-400">等待区块链确认...</span>
        </div>
        <div class="flex items-center gap-2 text-xs">
          <i id="step3Icon" class="fas fa-circle text-gray-300 text-[8px]"></i>
          <span id="step3Text" class="text-gray-400">区块确认中 (1/12)...</span>
        </div>
        <div class="flex items-center gap-2 text-xs">
          <i id="step4Icon" class="fas fa-circle text-gray-300 text-[8px]"></i>
          <span id="step4Text" class="text-gray-400">到账确认...</span>
        </div>
      </div>

      <!-- 确认按钮 -->
      <button onclick="confirmPay()" id="payConfirmBtn" class="w-full py-3.5 bg-gray-900 hover:bg-gray-800 text-white rounded-xl font-semibold text-base transition-all shadow-md flex items-center justify-center gap-2">
        <i class="fas fa-paper-plane"></i> 确认转账
      </button>
      <p class="mt-3 text-xs text-center text-gray-400">
        <i class="fas fa-flask mr-1"></i>模拟链上交易 · 不涉及真实加密货币
      </p>
    </div>
  </div>
</div>

<!-- Footer -->
<footer class="bg-white border-t mt-8 no-print">
  <div class="max-w-7xl mx-auto px-4 py-6 text-center">
    <p class="text-sm text-gray-500">由 <span class="font-semibold text-primary">枪王</span> 用 <span class="text-red-500">♥</span> 开发。</p>
  </div>
</footer>

<script>
// ─── 数据（从 PHP 注入）───
const REGIONS = <?=json_encode($frontendData['regions'], JSON_UNESCAPED_UNICODE)?>;
const BILL_TYPES = <?=json_encode($frontendData['bill_types'], JSON_UNESCAPED_UNICODE)?>;
const TEMPLATES = <?=json_encode($templates, JSON_UNESCAPED_UNICODE)?>;

let currentRegionId = null;
let currentBillTypeId = null;
let isBankType = false;
let isMonzoType = false;
let isCMBType = false;
let isKrakenType = false;
let isWiseType = false;
let isSeaBankType = false;
let isMoneseType = false;
let isOctopusType = false;

// 调试：确认模板已加载
console.log('[枪王] Templates loaded:', Object.keys(TEMPLATES).length, 'keys:', Object.keys(TEMPLATES));

// ─── 初始化 ───
document.addEventListener('DOMContentLoaded', () => {
  if (REGIONS.length > 0) selectRegion(REGIONS[0].id);
  setDefaultDates();
  // 监听预览区内容变化，自动刷新水印
  const pb = document.getElementById('preview-bill');
  if (pb) {
    new MutationObserver(() => {
      if (!pb.classList.contains('hidden')) {
        requestAnimationFrame(() => requestAnimationFrame(renderWatermark));
      }
    }).observe(pb, { childList: true, subtree: true, characterData: true });
  }
  window.addEventListener('resize', renderWatermark);
});

function setDefaultDates() {
  const now = new Date();

  // 1. 起始日期：上个月的第一天 (如: 8月访问则设为 7月1日)
  const start = new Date(now.getFullYear(), now.getMonth() - 1, 1);

  // 2. 截止日期：上个月的最后一天 (如: 8月访问则设为 7月31日)
  const end = new Date(now.getFullYear(), now.getMonth(), 0);

  // 3. 发出日期：默认设为本月2号出账单
  const issue = new Date(now.getFullYear(), now.getMonth(), 2);

  // 安全锁：如果当前真实日期连本月2号都没到（比如今天是8月1日），则发出日期强行拉回到今天
  if (issue > now) {
    issue.setTime(now.getTime());
  }

  document.getElementById('periodStart').value = fmtDate(start);
  document.getElementById('periodEnd').value = fmtDate(end);
  document.getElementById('issueDate').value = fmtDate(issue);

  const issueBankEl = document.getElementById('issueDateBank');
  if (issueBankEl) issueBankEl.value = fmtDate(issue);
}
function fmtDate(d){return d.toISOString().split('T')[0];}
function fmtHK(s){if(!s)return '';const d=new Date(s);return String(d.getDate()).padStart(2,'0')+'/'+String(d.getMonth()+1).padStart(2,'0')+'/'+d.getFullYear();}
function fmtCNDate(d){if(!d)return '';return d.getFullYear()+'年'+String(d.getMonth()+1).padStart(2,'0')+'月'+String(d.getDate()).padStart(2,'0')+'日';}

// ─── 地区选择 ───
function selectRegion(rid) {
  currentRegionId = rid;
  const sel = document.getElementById('regionSelector');
  sel.innerHTML = '';
  REGIONS.forEach(r => {
    const btn = document.createElement('button');
    btn.className = `region-btn flex items-center gap-2 px-5 py-3 border-2 rounded-xl bg-white hover:shadow-md transition-all ${r.id===rid?'active':''}`;
    btn.innerHTML = `<span class="text-2xl">${r.flag_emoji}</span><span class="font-medium text-gray-700">${r.name}</span>`;
    btn.onclick = () => selectRegion(r.id);
    sel.appendChild(btn);
  });
  // 更新账单类型
  const types = BILL_TYPES.filter(bt => bt.region_id === rid);
  const container = document.getElementById('billTypeSelector');
  container.innerHTML = '';
  types.forEach((t, i) => {
    const btn = document.createElement('button');
    btn.className = `bill-type-btn flex items-center gap-2.5 px-5 py-3 border-2 rounded-xl bg-white hover:shadow-md transition-all ${i===0?'active':''}`;
    btn.dataset.billTypeId = t.id;
    btn.innerHTML = `<i class="fas ${t.icon_class} bill-icon text-lg ${i===0?'text-primary':'text-gray-400'}"></i><span class="font-medium text-gray-700">${t.name}</span>`;
    btn.onclick = () => selectBillType(t.id);
    container.appendChild(btn);
  });
  if (types.length > 0) selectBillType(types[0].id);
}

function selectBillType(btid) {
  currentBillTypeId = parseInt(btid);
  document.querySelectorAll('.bill-type-btn').forEach(b => {
    const a = parseInt(b.dataset.billTypeId) === currentBillTypeId;
    b.classList.toggle('active', a);
    b.querySelector('.bill-icon').classList.toggle('text-primary', a);
    b.querySelector('.bill-icon').classList.toggle('text-gray-400', !a);
  });

  // ──── 判断是否为银行类型，切换高级选项面板 ────
  const bt = BILL_TYPES.find(b => b.id == currentBillTypeId);
  const cat = bt ? (bt.category || 'utility') : 'utility';
  isBankType = (cat === 'bank' || cat === 'credit_card' || cat === 'crypto');
  isMonzoType = bt ? bt.code === 'gb-monzo' : false;
  isCMBType = bt ? bt.code === 'cn-cmb-credit' : false;
  isKrakenType = bt ? bt.code === 'gb-kraken' : false;
  isWiseType = bt ? (bt.code === 'gb-wise' || bt.code === 'de-wise' || (bt.name && (bt.name.toLowerCase().includes('wise') || bt.name.includes('Wise')))) : false;
  isSeaBankType = bt ? (bt.code === 'ph-seabank' || (bt.name && (bt.name.toLowerCase().includes('seabank') || bt.name.includes('SeaBank')))) : false;
  isMoneseType = bt ? bt.code === 'de-monese' : false;
  isOctopusType = bt ? (bt.name && bt.name.toLowerCase().includes('octopus')) : false;
  toggleFieldVisibility();

  console.log('[枪王] Selected bill type:', currentBillTypeId, 'category:', cat, 'isBank:', isBankType, 'isMonzo:', isMonzoType, 'isWise:', isWiseType, 'isSeaBank:', isSeaBankType, 'isMonese:', isMoneseType, 'template exists:', !!TEMPLATES[currentBillTypeId]);
}

// ──── 动态切换高级选项面板的字段 ────
function toggleFieldVisibility() {
  const utilityFields = document.getElementById('utilityFields');
  const bankFields = document.getElementById('bankFields');
  const monzoFields = document.getElementById('monzoFields');
  const nonMonzoBankFields = document.getElementById('nonMonzoBankFields');
  const cmbFields = document.getElementById('cmbFields');
  const krakenFields = document.getElementById('krakenFields');
  const wiseFields = document.getElementById('wiseFields');
  if (isBankType) {
    utilityFields.classList.remove('expanded');
    utilityFields.classList.add('collapsed');
    bankFields.classList.remove('collapsed');
    bankFields.classList.add('expanded');
    // Show Monzo, CMB, Kraken, Wise, or general bank fields
    if (isMonzoType) {
      monzoFields.classList.remove('hidden');
      nonMonzoBankFields.classList.add('hidden');
      cmbFields.classList.add('hidden');
      if(krakenFields) krakenFields.classList.add('hidden');
      if(wiseFields) wiseFields.classList.add('hidden');
    } else if (isCMBType) {
      monzoFields.classList.add('hidden');
      nonMonzoBankFields.classList.add('hidden');
      cmbFields.classList.remove('hidden');
      if(krakenFields) krakenFields.classList.add('hidden');
      if(wiseFields) wiseFields.classList.add('hidden');
    } else if (isKrakenType) {
      monzoFields.classList.add('hidden');
      nonMonzoBankFields.classList.add('hidden');
      cmbFields.classList.add('hidden');
      if(krakenFields) krakenFields.classList.remove('hidden');
      if(wiseFields) wiseFields.classList.add('hidden');
    } else if (isWiseType) {
      monzoFields.classList.add('hidden');
      nonMonzoBankFields.classList.add('hidden');
      cmbFields.classList.add('hidden');
      if(krakenFields) krakenFields.classList.add('hidden');
      if(wiseFields) wiseFields.classList.remove('hidden');
    } else if (isSeaBankType) {
      monzoFields.classList.add('hidden');
      nonMonzoBankFields.classList.remove('hidden');
      cmbFields.classList.add('hidden');
      if(krakenFields) krakenFields.classList.add('hidden');
      if(wiseFields) wiseFields.classList.add('hidden');
    } else if (isMoneseType) {
      monzoFields.classList.add('hidden');
      nonMonzoBankFields.classList.add('hidden');
      cmbFields.classList.add('hidden');
      if(krakenFields) krakenFields.classList.add('hidden');
      if(wiseFields) wiseFields.classList.add('hidden');
      const moneseFields = document.getElementById('moneseFields');
      if(moneseFields) moneseFields.classList.remove('hidden');
    } else {
      monzoFields.classList.add('hidden');
      nonMonzoBankFields.classList.remove('hidden');
      cmbFields.classList.add('hidden');
      if(krakenFields) krakenFields.classList.add('hidden');
      if(wiseFields) wiseFields.classList.add('hidden');
    }
  } else {
    // 处理水电/公用事业
    if (isOctopusType) {
      utilityFields.classList.remove('expanded');
      utilityFields.classList.add('collapsed');
      document.getElementById('octopusFields').classList.remove('hidden');
    } else {
      utilityFields.classList.remove('collapsed');
      utilityFields.classList.add('expanded');
      if (document.getElementById('octopusFields')) document.getElementById('octopusFields').classList.add('hidden');
    }
    bankFields.classList.remove('expanded');
    bankFields.classList.add('collapsed');
    monzoFields.classList.add('hidden');
    nonMonzoBankFields.classList.remove('hidden');
    cmbFields.classList.add('hidden');
    if(krakenFields) krakenFields.classList.add('hidden');
    if(wiseFields) wiseFields.classList.add('hidden');
    const moneseFields2 = document.getElementById('moneseFields');
    if(moneseFields2) moneseFields2.classList.add('hidden');
  }
}

// ──── Monzo：交易笔数为0时清零金额 ────
function onMonzoTxnChange() {
  const txnEl = document.getElementById('txnCountMonzo');
  const numTxns = parseInt(txnEl?.value) || 0;
  if (numTxns === 0) {
    const ob = document.getElementById('openingBalance');
    const bp = document.getElementById('balancePots');
    const to = document.getElementById('totalOutgoings');
    const td = document.getElementById('totalDeposits');
    if (ob) ob.value = '0.00';
    if (bp) bp.value = '0.00';
    if (to) to.value = '0.00';
    if (td) td.value = '0.00';
  }
  updatePreview();
}

// ──── CMB招商银行：自动计算本期应还金额和最低还款额 ────
function onCMBChange() {
  const prevBal = parseFloat(document.getElementById('cmbPrevBalance')?.value) || 0;
  const prevPay = parseFloat(document.getElementById('cmbPrevPayment')?.value) || 0;
  const newCharges = parseFloat(document.getElementById('cmbNewCharges')?.value) || 0;
  const adjustment = parseFloat(document.getElementById('cmbAdjustment')?.value) || 0;
  const interest = parseFloat(document.getElementById('cmbInterest')?.value) || 0;
  // 公式: 本期应还 = 上期账单 - 上期还款 + 本期账单 - 调整 + 利息
  const newBalance = Math.round((prevBal - prevPay + newCharges - adjustment + interest) * 100) / 100;
  // 最低还款额 ≈ 5% 本期应还 (最低10元)
  const minPayment = Math.max(10, Math.round(newBalance * 0.05 * 100) / 100);

  const nbEl = document.getElementById('cmbNewBalance');
  const mpEl = document.getElementById('cmbMinPayment');
  if (nbEl) nbEl.value = newBalance.toFixed(2);
  if (mpEl) mpEl.value = minPayment.toFixed(2);

  // 交易笔数为0时，清零本期账单金额
  const txnCount = parseInt(document.getElementById('cmbTxnCount')?.value) || 0;
  if (txnCount === 0) {
    if (document.getElementById('cmbNewCharges')) document.getElementById('cmbNewCharges').value = '0.00';
    if (nbEl) nbEl.value = (prevBal - prevPay - adjustment + interest).toFixed(2);
    if (mpEl) mpEl.value = Math.max(10, Math.round(parseFloat(nbEl.value) * 0.05 * 100) / 100).toFixed(2);
  }

  updatePreview();
}

// ──── 银行字段：自动计算期末余额 ────
function calcClosing() {
  const ob = parseFloat(document.getElementById('openingBalanceGeneral').value) || 0;
  const tc = parseFloat(document.getElementById('totalCredits').value) || 0;
  const td = parseFloat(document.getElementById('totalDebits').value) || 0;
  document.getElementById('closingBalance').value = (ob + tc - td).toFixed(2);
  updatePreview();
}

// ─── 填充随机地址 ───
const ADDRS = {
  cn:[{u:'12栋3单元501室',s:'幸福路88号',d:'天河区'},{u:'5号楼802室',s:'长安街66号',d:'朝阳区'},{u:'6栋1单元302',s:'深南大道168号',d:'福田区'},{u:'3号楼1601',s:'人民路200号',d:'武昌区'}],
  hk:[{u:'12樓A室',s:'彌敦道123號',d:'九龍旺角'},{u:'5樓B室',s:'軒尼詩道456號',d:'香港灣仔'},{u:'28樓C室',s:'廣東道789號',d:'九龍尖沙咀'},{u:'3樓D室',s:'德輔道中200號',d:'香港中環'}],
  au:[{u:'Unit 12',s:'45 George St',d:'Sydney NSW 2000'},{u:'Apt 8',s:'123 Collins St',d:'Melbourne VIC 3000'}],
  ca:[{u:'Apt 1205',s:'33 Bay Street',d:'Toronto ON M5J 2Z3'},{u:'Unit 5B',s:'1500 Rue Peel',d:'Montreal QC H3A 1T1'}],
  sg:[{u:'#12-05',s:'123 Ang Mo Kio Ave 3',d:'Singapore 560123'},{u:'#05-32',s:'88 Orchard Road',d:'Singapore 238836'}],
  gb:[{u:'37 Orchard Terrace',s:'Huddersfield',d:'HD4 6DB'},{u:'Flat 12',s:'45 Baker Street',d:'London W1U 8BH'},{u:'Suite 3',s:'22 Park Lane',d:'London W1K 1AP'}],
  ph:[{u:'SAN JOSE DEL MONTE',s:'BULACAN',d:'2023'},{u:'MAKATI CITY',s:'METRO MANILA',d:'1226'},{u:'QUEZON CITY',s:'METRO MANILA',d:'1100'}]
};
const NAMES = {
  cn:['张三','李明','王芳','刘伟','陈静','杨洋','赵敏','黄丽华'],
  hk:['CHAN KA WAI','WONG TAI MAN','LEE SIU MING','LAM HOI YAN'],
  au:['John Smith','Sarah Johnson','Michael Brown','Emily Wilson'],
  ca:['James Wilson','Marie Tremblay','Robert Chen','Jennifer Lee'],
  sg:['Tan Wei Ming','Lim Siew Ling','Ng Hock Seng','Ooi Chai Hong'],
  gb:['James Smith','Elizabeth Brown','David Taylor','Catherine Jones'],
  ph:['KATHLYN JOY PILAPIL','JUAN DELA CRUZ','MARIA SANTOS','JOSE RIZAL']
};

// 银行名称（按地区）
const BANK_NAMES = {
  cn:[{zh:'中国工商银行',en:'ICBC'},{zh:'中国建设银行',en:'CCB'},{zh:'中国银行',en:'Bank of China'},{zh:'中国农业银行',en:'ABC'},{zh:'招商银行',en:'China Merchants Bank'}],
  hk:[{zh:'香港上海匯豐銀行',en:'HSBC'},{zh:'恒生銀行',en:'Hang Seng Bank'},{zh:'中銀香港',en:'BOCHK'}],
  au:[{zh:'Commonwealth Bank',en:'CommBank'},{zh:'Westpac',en:'Westpac'},{zh:'ANZ',en:'ANZ'}],
  ca:[{zh:'TD Canada Trust',en:'TD Bank'},{zh:'Royal Bank of Canada',en:'RBC'},{zh:'Scotiabank',en:'Scotiabank'}],
  sg:[{zh:'DBS Bank',en:'DBS'},{zh:'OCBC Bank',en:'OCBC'},{zh:'United Overseas Bank',en:'UOB'}],
  gb:[{zh:'Barclays',en:'Barclays'},{zh:'HSBC UK',en:'HSBC UK'},{zh:'Lloyds Bank',en:'Lloyds'},{zh:'Monzo Bank',en:'Monzo'}],
  ph:[{zh:'SeaBank',en:'SEABANK'},{zh:'BDO',en:'BDO'},{zh:'BPI',en:'BPI'}]
};

function fillRandomAddress() {
  const region = REGIONS.find(r => r.id === currentRegionId);
  const code = region ? region.code : 'hk';
  const addrs = ADDRS[code] || ADDRS.hk;
  const names = NAMES[code] || NAMES.hk;
  const a = addrs[Math.floor(Math.random()*addrs.length)];
  document.getElementById('customerName').value = names[Math.floor(Math.random()*names.length)];
  document.getElementById('addressUnit').value = a.u;
  document.getElementById('addressStreet').value = a.s;
  document.getElementById('addressDistrict').value = a.d;

  if (isBankType) {
    // ──── 银行类型随机填充 ────
    const banks = BANK_NAMES[code] || BANK_NAMES.hk;
    const bank = banks[Math.floor(Math.random()*banks.length)];
    document.getElementById('bankName').value = bank.zh;
    document.getElementById('bankNameEn').value = bank.en;
    document.getElementById('billNumberBank').value = 'STMT-'+Math.floor(10000000+Math.random()*90000000);

    // ──── 招商银行信用卡 专用填充 ────
    if (isCMBType) {
      document.getElementById('bankName').value = '招商银行';
      document.getElementById('bankNameEn').value = 'CHINA MERCHANTS BANK';
      document.getElementById('accountType').value = '个人消费卡账户';

      // CMB 地址
      const cmbAddrs = [
        {postal:'100020',city:'北京市',district:'朝阳区建国路88号SOHO现代城',unit:'A座1208室'},
        {postal:'200030',city:'上海市',district:'徐汇区漕溪北路398号',unit:'5栋2单元501室'},
        {postal:'510620',city:'广州市',district:'天河区体育西路189号',unit:'16楼1603室'},
        {postal:'518000',city:'深圳市',district:'福田区深南大道6008号',unit:'3栋A座902室'},
      ];
      const cmbNames = ['张伟','李明','王芳','刘洋','陈静','杨磊','赵敏','黄丽华','周强'];
      const cmbAddr = cmbAddrs[Math.floor(Math.random()*cmbAddrs.length)];
      const cmbName = cmbNames[Math.floor(Math.random()*cmbNames.length)];
      document.getElementById('cmbPostalCode').value = cmbAddr.postal;
      document.getElementById('cmbCity').value = cmbAddr.city;
      document.getElementById('cmbDistrict').value = cmbAddr.district;
      document.getElementById('cmbUnit').value = cmbAddr.unit;
      document.getElementById('cmbName').value = cmbName;
      // Also fill main form fields for compatibility
      document.getElementById('customerName').value = cmbName;
      document.getElementById('addressUnit').value = cmbAddr.unit;
      document.getElementById('addressStreet').value = cmbAddr.district;
      document.getElementById('addressDistrict').value = cmbAddr.city;

      // Card info
      document.getElementById('cmbCardLast4').value = String(1000 + Math.floor(Math.random()*9000));
      document.getElementById('cmbCreditLimit').value = (5000 + Math.floor(Math.random()*45)*1000).toFixed(2);

      // 日期
      // 动态获取上方的账单日期（已是上个月），如果没有则临时生成上个月日期
      let baseDateStr = document.getElementById('issueDateBank').value;
      if (!baseDateStr) {
          const tempDate = new Date();
          tempDate.setMonth(tempDate.getMonth() - 1);
          baseDateStr = fmtDate(tempDate);
      }
      const dueDate = new Date(baseDateStr);
      dueDate.setDate(dueDate.getDate() + 18); // 账单日后推18天为还款日
      document.getElementById('cmbPaymentDueDate').value = fmtDate(dueDate);

      // 金额 - 逻辑一致
      const prevBal = (50 + Math.random()*500).toFixed(2);
      const prevPay = prevBal; // 上期全额还款
      const numConsumes = 2 + Math.floor(Math.random()*5);
      document.getElementById('cmbTxnCount').value = numConsumes + 1; // +1 for payment
      // 随机生成消费金额
      let totalCharges = 0;
      for (let i = 0; i < numConsumes; i++) {
        totalCharges += Math.round((5 + Math.random()*200)*100)/100;
      }
      const newCharges = totalCharges.toFixed(2);
      document.getElementById('cmbPrevBalance').value = prevBal;
      document.getElementById('cmbPrevPayment').value = prevPay;
      document.getElementById('cmbNewCharges').value = newCharges;
      document.getElementById('cmbAdjustment').value = '0.00';
      document.getElementById('cmbInterest').value = '0.00';
      // Auto-calculate
      onCMBChange();
    }
    // ──── Monzo 专用填充 ────
    else if (isMonzoType) {
      document.getElementById('sortCode').value = '04-00-06';
      document.getElementById('accountNumber').value = String(Math.floor(10000000+Math.random()*90000000));
      document.getElementById('bicCode').value = 'MONZGB2L';
      const acctNum = document.getElementById('accountNumber').value;
      document.getElementById('ibanCode').value = 'GB80 MONZ 0400 06' + acctNum.substring(0,4) + ' ' + acctNum.substring(4);
      document.getElementById('monzoCountry').value = 'United Kingdom';
      // Fill Monzo-specific address fields
      const gbAddrs = [
        {street:'37 Orchard Terrace', city:'Huddersfield', postcode:'HD4 6DB'},
        {street:'Flat 12, 45 Baker Street', city:'London', postcode:'W1U 8BH'},
        {street:'22 Park Lane, Suite 3', city:'London', postcode:'W1K 1AP'},
        {street:'88 Kings Road', city:'Manchester', postcode:'M16 0RA'},
        {street:'15 Victoria Street', city:'Edinburgh', postcode:'EH1 1JW'},
      ];
      const gbNames = ['James Smith','Elizabeth Brown','David Taylor','Catherine Jones','Wei Zhang','Sarah Johnson'];
      const gbAddr = gbAddrs[Math.floor(Math.random()*gbAddrs.length)];
      const gbName = gbNames[Math.floor(Math.random()*gbNames.length)];
      document.getElementById('monzoName').value = gbName;
      document.getElementById('monzoStreet').value = gbAddr.street;
      document.getElementById('monzoCity').value = gbAddr.city;
      document.getElementById('monzoPostcode').value = gbAddr.postcode;
      // Also fill the main form fields for compatibility
      document.getElementById('customerName').value = gbName;
      document.getElementById('addressUnit').value = gbAddr.street;
      document.getElementById('addressStreet').value = '';
      document.getElementById('addressDistrict').value = gbAddr.city;
      document.getElementById('postalCode') && (document.getElementById('postalCode').value = gbAddr.postcode);
      const bal = (Math.random()*5000).toFixed(2);
      document.getElementById('openingBalance').value = bal;
      document.getElementById('balancePots').value = (Math.random()*3000).toFixed(2);
      document.getElementById('totalOutgoings').value = (Math.random()*2000).toFixed(2);
      document.getElementById('totalDeposits').value = (Math.random()*3000).toFixed(2);
      document.getElementById('txnCountMonzo').value = Math.floor(5 + Math.random()*10);
      document.getElementById('accountType').value = 'Personal Account';
    }
    // ──── Kraken 专用填充 ────
    else if (isKrakenType) {
      const gbAddrs = [
        {street:'37 ORCHARD TERRACE', city:'Huddersfield, HD4 6DB'},
        {street:'FLAT 12, 45 BAKER STREET', city:'London, W1U 8BH'},
        {street:'22 PARK LANE, SUITE 3', city:'London, W1K 1AP'},
        {street:'88 KINGS ROAD', city:'Manchester, M16 0RA'},
        {street:'15 VICTORIA STREET', city:'Edinburgh, EH1 1JW'},
      ];
      const gbNames = ['JAMES SMITH','ELIZABETH BROWN','DAVID TAYLOR','CATHERINE JONES','WEI ZHANG','SARAH JOHNSON'];
      const gbAddr = gbAddrs[Math.floor(Math.random()*gbAddrs.length)];
      const gbName = gbNames[Math.floor(Math.random()*gbNames.length)];
      document.getElementById('krakenName').value = gbName;
      document.getElementById('krakenStreet').value = gbAddr.street;
      document.getElementById('krakenCity').value = gbAddr.city;
      // Generate random IDs in Kraken format
      const genKrakenId = () => Array.from({length:4}, () => Math.random().toString(36).substring(2,6).toUpperCase()).join(' ');
      document.getElementById('krakenPublicId').value = genKrakenId();
      document.getElementById('krakenAccountId').value = genKrakenId();
      document.getElementById('krakenTxnCount').value = Math.floor(3 + Math.random()*10);
      // Fill main form fields
      document.getElementById('customerName').value = gbName;
      document.getElementById('addressUnit').value = gbAddr.street;
      document.getElementById('addressStreet').value = '';
      document.getElementById('addressDistrict').value = gbAddr.city;
      document.getElementById('bankName').value = 'Kraken';
      document.getElementById('bankNameEn').value = 'KRAKEN';
    }
    // ──── Wise 专用填充 ────
    else if (isWiseType) {
      const isUK = bt && bt.region_code === 'gb'; // UK=GBP, DE=EUR
      const wiseCurrencyDefault = isUK ? 'GBP' : 'EUR';
      const wiseSymbol = isUK ? '£' : '€';
      const wiseBankNameEn = isUK ? 'WISE (UK)' : 'WISE (DE)';
      const wiseAddrs = [
        {street:'Sichuān Shěng Y\xedbīn Sh\xec X\xf9zhōu Qū B\xf3xī Jiēd\xe0o Xīnl\xf3ng Cūn 10 Zǔ 104 H\xe0o', city:'宜宾', state:'SC', postcode:'644000', country:'China'},
        {street:'123 Baker Street, Flat 4B', city:'London', state:'ENG', postcode:'NW1 6XE', country:'United Kingdom'},
        {street:'15 Rue de la Paix, Apt 3', city:'Paris', state:'IDF', postcode:'75002', country:'France'},
        {street:'Friedrichstraße 88', city:'Berlin', state:'BE', postcode:'10117', country:'Germany'},
        {street:'Calle Gran V\xeda 33, Piso 5', city:'Madrid', state:'MD', postcode:'28013', country:'Spain'},
      ];
      const wiseNames = ['James Smith','Elizabeth Brown','David Taylor','Catherine Jones','Wei Zhang','Sarah Johnson'];
      const wiseAddr = wiseAddrs[Math.floor(Math.random()*wiseAddrs.length)];
      const wiseName = wiseNames[Math.floor(Math.random()*wiseNames.length)];
      // 日期：强制对齐上个完整自然月
      const fmtWiseDate = (d) => { return d.getFullYear()+'年'+String(d.getMonth()+1).padStart(2,'0')+'月'+String(d.getDate()).padStart(2,'0')+'日'; };

      const now = new Date();
      const pStart = new Date(now.getFullYear(), now.getMonth() - 1, 1); // 上个月1号
      const pEnd = new Date(now.getFullYear(), now.getMonth(), 0);       // 上个月最后一天

      const issue = new Date(now.getFullYear(), now.getMonth(), 2);      // 本月2号出账单
      if (issue > now) issue.setTime(now.getTime());

      document.getElementById('wisePeriodStart').value = fmtWiseDate(pStart);
      document.getElementById('wisePeriodEnd').value = fmtWiseDate(pEnd);
      document.getElementById('wiseGeneratedDate').value = fmtWiseDate(issue);
      document.getElementById('wiseBalanceDate').value = fmtWiseDate(pEnd);
      document.getElementById('wiseTimezone').value = 'GMT+08:00';
      document.getElementById('wiseCurrency').value = wiseCurrencyDefault;
      // 个人信息
      document.getElementById('wiseName').value = wiseName;
      document.getElementById('wiseStreet').value = wiseAddr.street;
      document.getElementById('wiseCity').value = wiseAddr.city;
      document.getElementById('wiseState').value = wiseAddr.state;
      document.getElementById('wisePostcode').value = wiseAddr.postcode;
      document.getElementById('wiseCountry').value = wiseAddr.country;
      // 账户
      document.getElementById('wiseAccountNumber').value = String(Math.floor(10000000 + Math.random() * 90000000));
      document.getElementById('wiseSortCode').value = '60-84-64';
      document.getElementById('wiseIban').value = 'GB23 TRWI 6084 6482 9223 30';
      document.getElementById('wiseBic').value = 'TRWIGB2LXXX';
      // 余额
      document.getElementById('wiseBalance').value = (Math.random()*5000).toFixed(2);
      // 交易笔数
      document.getElementById('wiseTxnCount').value = Math.floor(2 + Math.random()*8);
      // Fill main form fields
      document.getElementById('customerName').value = wiseName;
      document.getElementById('addressUnit').value = wiseAddr.street;
      document.getElementById('addressStreet').value = '';
      document.getElementById('addressDistrict').value = wiseAddr.city;
      document.getElementById('bankName').value = 'Wise';
      document.getElementById('bankNameEn').value = wiseBankNameEn;
    }
    // ──── SeaBank 专用填充 ────
    else if (bt && bt.code === 'ph-seabank') {
      const phAddrs = ADDRS.ph;
      const phNames = NAMES.ph;
      const phAddr = phAddrs[Math.floor(Math.random()*phAddrs.length)];
      const phName = phNames[Math.floor(Math.random()*phNames.length)];

      document.getElementById('customerName').value = phName;
      document.getElementById('addressUnit').value = phAddr.u;
      document.getElementById('addressStreet').value = phAddr.s;
      document.getElementById('addressDistrict').value = phAddr.d;

      document.getElementById('bankName').value = 'SeaBank';
      document.getElementById('bankNameEn').value = 'SEABANK';
      document.getElementById('accountType').value = 'SAVINGS';
      document.getElementById('billNumberBank').value = 'S/N ' + Math.floor(100+Math.random()*900) + '\n' + Math.floor(10000+Math.random()*90000) + 'BTUOZLFO';
      document.getElementById('generalAccountNumber').value = String(Math.floor(100000000000+Math.random()*900000000000));

      const bal = (Math.random()*50).toFixed(2);
      document.getElementById('openingBalanceGeneral').value = bal;
      const tc = (10000+Math.random()*20000).toFixed(2);
      document.getElementById('totalCredits').value = tc;
      const td = (parseFloat(tc) - (Math.random()*10)).toFixed(2); // In/out are close
      document.getElementById('totalDebits').value = td;
      document.getElementById('closingBalance').value = (parseFloat(bal)+parseFloat(tc)-parseFloat(td)).toFixed(2);
      document.getElementById('txnCount').value = 8;
    }
    // ──── Monese 专用填充 ────
    else if (isMoneseType) {
      const deAddrs = ADDRS.de || ADDRS.gb;
      const deAddr = deAddrs[Math.floor(Math.random()*deAddrs.length)];
      const moneseNames = ['Carlo Dittrich','Anna Weber','Luca Fischer','Sophia Müller','Max Schneider'];
      const mName = moneseNames[Math.floor(Math.random()*moneseNames.length)];

      document.getElementById('customerName').value = mName;
      document.getElementById('addressUnit').value = deAddr.u || deAddr.street;
      document.getElementById('addressStreet').value = deAddr.s || '';
      document.getElementById('addressDistrict').value = deAddr.d || deAddr.city;
      document.getElementById('bankName').value = 'Monese';
      document.getElementById('bankNameEn').value = 'MONESE';

      const now = new Date();
      const pEnd = new Date(now.getFullYear(), now.getMonth(), 0);
      const pStart = new Date(now.getFullYear(), now.getMonth() - 1, 1);
      document.getElementById('moneseStart').value = pStart.toISOString().split('T')[0];
      document.getElementById('moneseEnd').value = pEnd.toISOString().split('T')[0];

      document.getElementById('moneseName').value = mName;
      document.getElementById('moneseStreet').value = 'Hattenheimer Str. 19';
      document.getElementById('monesePostcode').value = '60326';
      document.getElementById('moneseCity').value = 'Frankfurt am Main';
      document.getElementById('moneseIban').value = 'BE59974132270526';
      document.getElementById('moneseBic').value = 'PESOBEB1';
      document.getElementById('moneseAccount').value = 'M59199197';
      document.getElementById('moneseOpening').value = (Math.random() * 100).toFixed(2);
      document.getElementById('moneseIn').value = (40 + Math.random() * 20).toFixed(2);
      document.getElementById('moneseOut').value = (40 + Math.random() * 20).toFixed(2);
      document.getElementById('moneseTxnCount').value = 3;
    } else {
      const ob = (10000+Math.random()*90000).toFixed(2);
      document.getElementById('openingBalanceGeneral').value = ob;
      const tc = (3000+Math.random()*20000).toFixed(2);
      document.getElementById('totalCredits').value = tc;
      const td = (2000+Math.random()*15000).toFixed(2);
      document.getElementById('totalDebits').value = td;
      document.getElementById('closingBalance').value = (parseFloat(ob)+parseFloat(tc)-parseFloat(td)).toFixed(2);

      // 账户类型
      const acctInput = document.getElementById('accountType');
      if (code === 'cn') acctInput.value = '储蓄账户';
      else if (code === 'hk') acctInput.value = '儲蓄賬戶';
      else acctInput.value = Math.random()>0.5 ? 'Savings Account' : 'Current Account';
    }
  } else {
    // ──── 水电类型随机填充 ────
    if (isOctopusType) {
      document.getElementById('octName').value = 'LI HONGWEI';
      document.getElementById('octStreet').value = '69 Fairfax St';
      document.getElementById('octCity').value = 'Birmingham';
      document.getElementById('octPostcode').value = 'B5S 8PL';
      document.getElementById('octPrevBalance').value = '-' + (1000 + Math.random()*1000).toFixed(2);
      document.getElementById('octNewBalance').value = '-' + (500 + Math.random()*500).toFixed(2);
      document.getElementById('octAccount').value = 'A-' + Math.random().toString(36).substring(2,10).toUpperCase();
      document.getElementById('octBillNum').value = Math.floor(100000000 + Math.random()*900000000);

      // 随机生成三个退款金额
      document.getElementById('octC1').value = (Math.random()*200 + 100).toFixed(2);
      document.getElementById('octC2').value = (Math.random()*200 + 100).toFixed(2);
      document.getElementById('octC3').value = (Math.random()*200 + 100).toFixed(2);

      // 动态生成退款日期范围（连续的14天周期，基于当前截止日期往前推）
      const endStr = document.getElementById('periodEnd').value;
      const baseDate = endStr ? new Date(endStr) : new Date();

      const fmtOctShort = (d) => {
        const day = d.getDate();
        const nth = (day>3 && day<21) ? 'th' : ['th','st','nd','rd','th','th','th','th','th','th'][day%10];
        const m = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][d.getMonth()];
        return day + nth + ' ' + m;
      };

      // T1: 往前 1~15天（即 14 天周期）
      const d1End = new Date(baseDate); d1End.setDate(d1End.getDate() - 1);
      const d1Start = new Date(d1End); d1Start.setDate(d1Start.getDate() - 14);

      // T2: 往前 16~30天
      const d2End = new Date(d1Start); d2End.setDate(d2End.getDate() - 1);
      const d2Start = new Date(d2End); d2Start.setDate(d2Start.getDate() - 14);

      // T3: 往前 31~45天
      const d3End = new Date(d2Start); d3End.setDate(d3End.getDate() - 1);
      const d3Start = new Date(d3End); d3Start.setDate(d3Start.getDate() - 14);

      // 填充到输入框中
      document.getElementById('octT1Date').value = fmtOctShort(d1Start) + ' - ' + fmtOctShort(d1End);
      document.getElementById('octT2Date').value = fmtOctShort(d2Start) + ' - ' + fmtOctShort(d2End);
      document.getElementById('octT3Date').value = fmtOctShort(d3Start) + ' - ' + fmtOctShort(d3End);
    } else {
      document.getElementById('billNumber').value = 'WSD-'+Math.floor(10000000+Math.random()*90000000);
      const c = Math.floor(25+Math.random()*20);
      document.getElementById('consumption').value = c;
      const prev = Math.floor(1000+Math.random()*9000);
      document.getElementById('meterPrev').value = prev;
      document.getElementById('meterCurr').value = prev+c;
      document.getElementById('meterNo').value = 'W'+Math.floor(100000+Math.random()*900000);
    }
  }

  updatePreview();
}

let advancedOpen = false;
function toggleAdvanced() {
  advancedOpen = !advancedOpen;
  document.getElementById('advancedPanel').classList.toggle('hidden', !advancedOpen);
  document.getElementById('advancedIcon').style.transform = advancedOpen ? 'rotate(180deg)' : '';
}

function updatePreview() {
  if (!document.getElementById('preview-bill').classList.contains('hidden')) generateBill();
}

// ─── SVG 生成器 ───
function genBarcodeSVG(data, height) {
  height = height || 28; const bars = []; let x = 0;
  for (let i = 0; i < data.length; i++) { const w = parseInt(data[i])===1?2:1; bars.push('<rect x="'+x+'" y="0" width="'+w+'" height="'+height+'" fill="#000"/>'); x+=w+1; }
  return '<svg width="'+x+'" height="'+height+'" xmlns="http://www.w3.org/2000/svg">'+bars.join('')+'</svg>';
}
function genRandomBarcode(len) { let s=''; for(let i=0;i<(len||80);i++) s+=Math.random()>0.45?'1':'0'; return '11010'+s+'1101011'; }
function genQRSVG(size) {
  size=size||56; const cs=Math.floor(size/21); const g=[];
  for(let r=0;r<21;r++){g[r]=[];for(let c=0;c<21;c++)g[r][c]=0;}
  const sf=function(sr,sc){for(let r=0;r<7;r++)for(let c=0;c<7;c++){if(r===0||r===6||c===0||c===6||(r>=2&&r<=4&&c>=2&&c<=4))g[sr+r][sc+c]=1;}};
  sf(0,0);sf(0,14);sf(14,0);
  for(let r=0;r<21;r++)for(let c=0;c<21;c++){if(g[r][c]===0&&!(r<8&&c<8)&&!(r<8&&c>12)&&!(r>12&&c<8))g[r][c]=Math.random()>0.5?1:0;}
  let rects='';for(let r=0;r<21;r++)for(let c=0;c<21;c++){if(g[r][c])rects+='<rect x="'+c*cs+'" y="'+r*cs+'" width="'+cs+'" height="'+cs+'" fill="#000"/>';}
  return '<svg width="'+21*cs+'" height="'+21*cs+'" xmlns="http://www.w3.org/2000/svg"><rect width="'+21*cs+'" height="'+21*cs+'" fill="#fff"/>'+rects+'</svg>';
}
function genBarChartSVG(dailyAvg, pastMonths) {
  const W=280,H=100,pL=30,pB=18,pT=8,pR=8,cW=W-pL-pR,cH=H-pB-pT;
  const maxV=Math.max(dailyAvg,...pastMonths.map(m=>m.value))*1.2||400;
  const bW=cW/(pastMonths.length+1)-6;
  let s='<svg width="'+W+'" height="'+H+'" xmlns="http://www.w3.org/2000/svg" style="font-family:sans-serif;font-size:9px;">';
  s+='<line x1="'+pL+'" y1="'+pT+'" x2="'+pL+'" y2="'+(H-pB)+'" stroke="#999" stroke-width="0.5"/>';
  s+='<line x1="'+pL+'" y1="'+(H-pB)+'" x2="'+(W-pR)+'" y2="'+(H-pB)+'" stroke="#999" stroke-width="0.5"/>';
  for(let i=0;i<=4;i++){const y=pT+cH*(1-i/4);const v=Math.round(maxV*i/4);s+='<line x1="'+pL+'" y1="'+y+'" x2="'+(W-pR)+'" y2="'+y+'" stroke="#e0e0e0" stroke-width="0.5"/>';s+='<text x="'+(pL-3)+'" y="'+(y+3)+'" text-anchor="end" fill="#666">'+v+'</text>';}
  const all=[...pastMonths,{label:'本期',value:dailyAvg,highlight:true}];
  all.forEach(function(m,i){const x=pL+i*(cW/all.length)+3;const bh=Math.max(2,(m.value/maxV)*cH);const y=H-pB-bh;const fill=m.highlight?'#005baa':'#5ba8d8';s+='<rect x="'+x+'" y="'+y+'" width="'+bW+'" height="'+bh+'" fill="'+fill+'" rx="1.5"/>';s+='<text x="'+(x+bW/2)+'" y="'+(H-pB+11)+'" text-anchor="middle" fill="#333" font-size="7.5">'+m.label+'</text>';s+='<text x="'+(x+bW/2)+'" y="'+(y-2)+'" text-anchor="middle" fill="#333" font-size="7.5">'+Math.round(m.value)+'</text>';});
  s+='</svg>'; return s;
}
function monthsAgo(n,ref){const d=new Date(ref);d.setMonth(d.getMonth()-n);return['1月','2月','3月','4月','5月','6月','7月','8月','9月','10月','11月','12月'][d.getMonth()];}

// ─── 银行交易明细生成 ───
function generateBankTransactions(regionCode, currency, numTxns, periodStart, periodEnd, openingBalance, totalCredits, totalDebits) {
  const rows = [];
  const pStart = new Date(periodStart);
  const pEnd = new Date(periodEnd);
  const daysSpan = Math.max(1, Math.round((pEnd - pStart) / 86400000));

  // 交易描述模板
  const descTemplates = {
    cn: {
      credits: ['工资收入','转账收入','利息收入','退款','红包收入','理财收益','奖金','报销款'],
      debits: ['ATM取款','消费支出','转账支出','水电缴费','信用卡还款','网购支付','房贷还款','保险扣款']
    },
    hk: {
      credits: ['Salary','Transfer In','Interest','Refund','Cash Deposit','Cheque Deposit'],
      debits: ['ATM Withdrawal','Transfer Out','Bill Payment','Credit Card Payment','EPS Payment','Autopay']
    },
    au: {
      credits: ['Salary','Transfer In','Interest','Refund','Cash Deposit','BPAY Receipt'],
      debits: ['ATM Withdrawal','EFTPOS','Direct Debit','BPAY Payment','Transfer Out','Card Payment']
    },
    ca: {
      credits: ['Salary','Transfer In','Interest','Refund','Deposit','e-Transfer In'],
      debits: ['ATM Withdrawal','POS Purchase','Bill Payment','e-Transfer Out','Pre-Authorized Debit','Card Payment']
    },
    sg: {
      credits: ['Salary','Transfer In','Interest','Refund','Cash Deposit','GIRO Receipt'],
      debits: ['ATM Withdrawal','NETS Payment','GIRO Payment','Transfer Out','Card Payment','Bill Payment']
    },
    gb: {
      credits: ['Salary','Transfer In','Interest','Refund','Cash Deposit','Standing Order In'],
      debits: ['ATM Withdrawal','Direct Debit','Standing Order','Card Payment','Transfer Out','Bill Payment']
    }
  };

  const templates = descTemplates[regionCode] || descTemplates.hk;
  const creditDescs = templates.credits;
  const debitDescs = templates.debits;

  // 确定每笔交易的金额 - 让总和匹配
  const avgCredit = totalCredits / Math.max(1, Math.ceil(numTxns * 0.4));
  const avgDebit = totalDebits / Math.max(1, Math.floor(numTxns * 0.6));

  // 随机日期生成（排序）
  const txnDates = [];
  for (let i = 0; i < numTxns; i++) {
    const dayOffset = Math.floor(Math.random() * daysSpan);
    const d = new Date(pStart.getTime() + dayOffset * 86400000);
    txnDates.push(d);
  }
  txnDates.sort((a, b) => a - b);

  let runningBalance = openingBalance;

  for (let i = 0; i < numTxns; i++) {
    const isCredit = i < Math.ceil(numTxns * 0.4) ? true : (Math.random() > 0.6);
    const desc = isCredit
      ? creditDescs[Math.floor(Math.random() * creditDescs.length)]
      : debitDescs[Math.floor(Math.random() * debitDescs.length)];

    const amount = isCredit
      ? (avgCredit * (0.3 + Math.random() * 1.4))
      : (avgDebit * (0.3 + Math.random() * 1.4));

    const rounded = Math.round(amount * 100) / 100;
    runningBalance = Math.round((runningBalance + (isCredit ? rounded : -rounded)) * 100) / 100;

    const dateStr = regionCode === 'cn'
      ? txnDates[i].getFullYear() + '-' + String(txnDates[i].getMonth()+1).padStart(2,'0') + '-' + String(txnDates[i].getDate()).padStart(2,'0')
      : String(txnDates[i].getDate()).padStart(2,'0') + '/' + String(txnDates[i].getMonth()+1).padStart(2,'0') + '/' + txnDates[i].getFullYear();

    const otherAcct = String(Math.floor(1000000000 + Math.random() * 8999999999));
    const refNo = String(Math.floor(100000000 + Math.random() * 899999999));

    // 生成不同区域的交易行
    if (regionCode === 'cn') {
      rows.push(`<tr><td>${dateStr}</td><td>${desc}</td><td>${otherAcct}</td><td class="debit">${isCredit?'':currency+rounded.toFixed(2)}</td><td class="credit">${isCredit?currency+rounded.toFixed(2):''}</td><td>${currency}${runningBalance.toFixed(2)}</td><td>${refNo}</td></tr>`);
    } else if (regionCode === 'hk') {
      rows.push(`<tr><td>${dateStr}</td><td>${desc}</td><td></td><td class="debit">${isCredit?'':currency+rounded.toFixed(2)}</td><td class="credit">${isCredit?currency+rounded.toFixed(2):''}</td><td>${currency}${runningBalance.toFixed(2)}</td><td>${refNo}</td></tr>`);
    } else if (regionCode === 'au') {
      rows.push(`<tr><td>${dateStr}</td><td>${desc}</td><td class="debit">${isCredit?'':currency+rounded.toFixed(2)}</td><td class="credit">${isCredit?currency+rounded.toFixed(2):''}</td><td>${currency}${runningBalance.toFixed(2)}</td><td>${refNo}</td></tr>`);
    } else if (regionCode === 'ca') {
      rows.push(`<tr><td>${dateStr}</td><td>${desc}</td><td class="debit">${isCredit?'':currency+rounded.toFixed(2)}</td><td class="credit">${isCredit?currency+rounded.toFixed(2):''}</td><td>${currency}${runningBalance.toFixed(2)}</td><td></td></tr>`);
    } else if (regionCode === 'sg') {
      rows.push(`<tr><td>${dateStr}</td><td>${desc}</td><td class="debit">${isCredit?'':currency+rounded.toFixed(2)}</td><td class="credit">${isCredit?currency+rounded.toFixed(2):''}</td><td>${currency}${runningBalance.toFixed(2)}</td><td>${refNo}</td></tr>`);
    } else if (regionCode === 'gb') {
      const txnType = isCredit ? 'CR' : (desc.includes('ATM') ? 'ATM' : (desc.includes('Direct') ? 'DD' : 'TFR'));
      rows.push(`<tr><td>${dateStr}</td><td>${txnType}</td><td>${desc}</td><td class="debit">${isCredit?'':currency+rounded.toFixed(2)}</td><td class="credit">${isCredit?currency+rounded.toFixed(2):''}</td><td>${currency}${runningBalance.toFixed(2)}</td><td>${refNo}</td></tr>`);
    } else {
      rows.push(`<tr><td>${dateStr}</td><td>${desc}</td><td class="debit">${isCredit?'':currency+rounded.toFixed(2)}</td><td class="credit">${isCredit?currency+rounded.toFixed(2):''}</td><td>${currency}${runningBalance.toFixed(2)}</td><td>${refNo}</td></tr>`);
    }
  }

  return rows.join('\n');
}

// ─── 信用卡交易明细生成 ───
function generateCreditCardTransactions(regionCode, currency, numTxns, periodStart, periodEnd, cardLast4, totalDebits, billTypeCode) {
  const rows = [];
  const pStart = new Date(periodStart);
  const pEnd = new Date(periodEnd);
  const daysSpan = Math.max(1, Math.round((pEnd - pStart) / 86400000));
  const last4 = cardLast4.slice(-4);

  const cnPaymentDescs = ['掌上生活跨行还款','自动还款','网银转账还款','柜面还款'];
  const cnConsumeDescs = ['支付宝 拼多多商户','支付宝 淘宝商户','微信支付 美团外卖','微信支付 京东商城','支付宝 饿了么','微信支付 滴滴出行','POS消费 星巴克','POS消费 肯德基','支付宝 缴费','银联在线 支付','微信支付 盒马鲜生','支付宝 优衣库','POS消费 中国石化','微信支付 电影院'];

  const fmtCMB = (d) => String(d.getMonth()+1).padStart(2,'0') + '/' + String(d.getDate()).padStart(2,'0');
  const fmtStd = (d) => d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');

  if (billTypeCode === 'cn-cmb-credit') {
    // ── 招商银行格式：分类行 + 6列 ──

    // 还款分类
    const payAmount = totalDebits > 0 ? (totalDebits * (0.8 + Math.random()*0.2)).toFixed(2) : '0.00';
    const payDate = new Date(pStart.getTime() + Math.floor(Math.random()*10)*86400000);
    rows.push(`<tr><td colspan="6" class="row-category">还款</td></tr>`);
    rows.push(`<tr class="row-highlight"><td></td><td>${fmtCMB(payDate)}</td><td class="text-left">${cnPaymentDescs[Math.floor(Math.random()*cnPaymentDescs.length)]}</td><td class="text-right">-${payAmount}</td><td>${last4}</td><td class="text-right">-${payAmount}</td></tr>`);

    // 消费分类
    rows.push(`<tr><td colspan="6" class="row-category">消费</td></tr>`);
    const numConsumes = Math.max(2, numTxns - 1);
    const avgConsume = totalDebits / numConsumes;
    const txnDates = [];
    for (let i = 0; i < numConsumes; i++) {
      const dayOffset = Math.floor(Math.random() * daysSpan);
      txnDates.push(new Date(pStart.getTime() + dayOffset * 86400000));
    }
    txnDates.sort((a, b) => a - b);

    for (let i = 0; i < numConsumes; i++) {
      const desc = cnConsumeDescs[Math.floor(Math.random() * cnConsumeDescs.length)];
      const amount = avgConsume * (0.3 + Math.random() * 1.4);
      const rounded = Math.round(amount * 100) / 100;
      const txnDate = txnDates[i];
      const postDate = new Date(txnDate.getTime() + (1 + Math.floor(Math.random()*2)) * 86400000);
      rows.push(`<tr><td>${fmtCMB(txnDate)}</td><td>${fmtCMB(postDate)}</td><td class="text-left">${desc}</td><td class="text-right">${rounded.toFixed(2)}</td><td>${last4}</td><td class="text-right">${rounded.toFixed(2)}(CN)</td></tr>`);
    }
  } else {
    // ── 默认7列格式 ──
    const cnMerchants = ['美团外卖','京东商城','淘宝购物','滴滴出行','星巴克咖啡','盒马鲜生','中国石化加油','电信充值','超市购物','医院挂号费','微信支付','支付宝消费','拼多多','美团打车','肯德基','奶茶店','电影院','优衣库','Apple Store','便利店'];
    const avgDebit = totalDebits / numTxns;
    const txnDates = [];
    for (let i = 0; i < numTxns; i++) {
      const dayOffset = Math.floor(Math.random() * daysSpan);
      txnDates.push(new Date(pStart.getTime() + dayOffset * 86400000));
    }
    txnDates.sort((a, b) => a - b);

    for (let i = 0; i < numTxns; i++) {
      const merchant = cnMerchants[Math.floor(Math.random() * cnMerchants.length)];
      const amount = avgDebit * (0.2 + Math.random() * 1.6);
      const rounded = Math.round(amount * 100) / 100;
      const txnDate = txnDates[i];
      const postDate = new Date(txnDate.getTime() + (1 + Math.floor(Math.random()*2)) * 86400000);
      rows.push(`<tr><td>${fmtStd(txnDate)}</td><td>${fmtStd(postDate)}</td><td>${last4}</td><td style="text-align:left;padding-left:8px;">${merchant}</td><td class="debit">${currency}${rounded.toFixed(2)}</td><td class="debit">${currency}${rounded.toFixed(2)}</td><td></td></tr>`);
    }
  }

  return rows.join('\n');
}

// ─── 生成账单 ───
function generateBill() {
  if (!currentBillTypeId) return;
  const tpl = TEMPLATES[currentBillTypeId];
  if (!tpl || !tpl.html_template) {
    document.getElementById('preview-default').classList.remove('hidden');
    document.getElementById('preview-default').innerHTML = '<div class="py-8 text-center"><p class="text-orange-600 font-semibold text-lg"><i class="fas fa-exclamation-triangle mr-2"></i>此账单类型暂无模板</p><p class="text-gray-500 text-sm mt-2">请在 <a href="admin.php" class="text-primary hover:underline">后台管理 → 模板编辑</a> 中添加模板</p></div>';
    document.getElementById('preview-bill').classList.add('hidden');
    return;
  }

  try {
  // 收集表单数据
  const name = document.getElementById('customerName').value || 'CHAN KA WAI';
  const unit = document.getElementById('addressUnit').value || '';
  const street = document.getElementById('addressStreet').value || '';
  const district = document.getElementById('addressDistrict').value || '';
  const fullAddr = [unit, street, district].filter(Boolean).join('，');
  const periodStart = document.getElementById('periodStart').value || '2025-11-15';
  const periodEnd = document.getElementById('periodEnd').value || '2026-03-10';
  const issueDate = isBankType
    ? (document.getElementById('issueDateBank').value || document.getElementById('issueDate').value || '2026-03-15')
    : (document.getElementById('issueDate').value || '2026-03-15');

  // 获取当前 bill type 的信息
  const bt = BILL_TYPES.find(b => b.id == currentBillTypeId);
  const currency = bt ? bt.currency : 'HK$';
  const region = REGIONS.find(r => r.id === currentRegionId);
  const regionCode = region ? region.code : 'hk';
  const cat = bt ? (bt.category || (bt.icon_class === 'fa-university' ? 'bank' : bt.icon_class === 'fa-credit-card' ? 'credit_card' : bt.icon_class === 'fa-bitcoin-sign' ? 'crypto' : 'utility')) : 'utility';

  // ──── 银行流水替换 ────
  if (isBankType) {
    const bankName = document.getElementById('bankName').value || '银行';
    const bankNameEn = document.getElementById('bankNameEn').value || 'BANK';
    const accountType = document.getElementById('accountType').value || '储蓄账户';
    const billNoBank = document.getElementById('billNumberBank').value || ('STMT-'+Math.floor(10000000+Math.random()*90000000));
    const statementPeriod = fmtHK(periodStart) + ' — ' + fmtHK(periodEnd);

    // ──── Monzo 专用逻辑 ────
    if (isMonzoType) {
      const acctNo = document.getElementById('accountNumber').value || String(Math.floor(10000000+Math.random()*90000000));
      const sortCode = document.getElementById('sortCode').value || '04-00-06';
      const bic = document.getElementById('bicCode').value || 'MONZGB2L';
      const iban = document.getElementById('ibanCode').value || 'GB80 MONZ 0400 0640 0080 40';
      const monzoName = document.getElementById('monzoName').value || name;
      const monzoStreet = document.getElementById('monzoStreet').value || unit;
      const monzoCity = document.getElementById('monzoCity').value || district;
      const monzoPostcode = document.getElementById('monzoPostcode').value || 'HD4 6DB';
      const monzoCountry = 'United Kingdom'; // locked
      const personalBalance = parseFloat(document.getElementById('openingBalance').value) || 0;
      const potsBalance = parseFloat(document.getElementById('balancePots').value) || 0;
      const totalOutgoings = parseFloat(document.getElementById('totalOutgoings').value) || 0;
      const totalDeposits = parseFloat(document.getElementById('totalDeposits').value) || 0;
      const numTxns = parseInt(document.getElementById('txnCountMonzo')?.value) || 0;

      // 交易笔数为0时，金额全部为0
      if (numTxns === 0) {
        // amounts already 0 from onMonzoTxnChange, just ensure template gets zeros
      }

      // Generate transactions if txn count > 0
      let transactionsHtml = '';
      if (numTxns > 0 && totalOutgoings > 0) {
        const txnRows = generateBankTransactions('gb', currency, numTxns, periodStart, periodEnd, personalBalance, totalDeposits, totalOutgoings);
        transactionsHtml = '<table class="bank-table" style="width:100%;border-collapse:collapse;font-size:11px;margin-top:8px;">'
          + '<tr><th>Date</th><th>Type</th><th>Description</th><th>Paid Out</th><th>Paid In</th><th>Balance</th><th>Ref</th></tr>'
          + txnRows
          + '</table>';
      } else {
        transactionsHtml = 'There were no transactions during this period.';
      }

      const replaces = {
        'customer_name': monzoName,
        'full_address': [monzoStreet, monzoCity, monzoPostcode, monzoCountry].filter(Boolean).join(', '),
        'address_unit': monzoStreet,
        'address_street': '',
        'address_district': monzoCity,
        'bill_number': billNoBank,
        'account_number': acctNo,
        'period_start': fmtHK(periodStart),
        'period_end': fmtHK(periodEnd),
        'issue_date': fmtHK(issueDate),
        'currency': currency,
        'bill_type_name': bt ? bt.name : '',
        'unit': bt ? bt.unit : 'txn',
        'bank_name': bankName,
        'bank_name_en': bankNameEn,
        'account_type': accountType || 'Personal Account',
        'statement_period': statementPeriod,
        'opening_balance': personalBalance.toFixed(2),
        'closing_balance': personalBalance.toFixed(2),
        'total_credits': totalDeposits.toFixed(2),
        'total_debits': totalOutgoings.toFixed(2),
        'transactions': transactionsHtml,
        'sort_code': sortCode,
        'bic': bic,
        'iban': iban,
        'country': monzoCountry,
        'postal_code': monzoPostcode,
        'balance_pots': potsBalance.toFixed(2),
        'total_outgoings': totalOutgoings.toFixed(2),
        'total_deposits': totalDeposits.toFixed(2),
        // card/compat
        'card_number': '**** **** **** ' + acctNo.slice(-4),
        'credit_limit': '0.00',
        'available_credit': '0.00',
        'min_payment': '0.00',
        'payment_due_date': '',
        'new_charges': totalOutgoings.toFixed(2),
        'new_payments': totalDeposits.toFixed(2),
        'interest_charge': '0.00',
        'points_balance': '0',
        'previous_balance': '0.00',
        'consumption': '0.0', 'meter_prev': '0', 'meter_curr': '0',
        'meter_number': '', 'days': '0', 'daily_litres': '0',
        't1': '0.0', 't2': '0.0', 't3': '0.0', 't4': '0.0',
        'c1': '0.00', 'c2': '0.00', 'c3': '0.00', 'c4': '0.00',
        'water_charge': '0.00', 'sewage_charge': '0.00',
        'total_due': personalBalance.toFixed(2),
        'last_pay_date': '', 'last_pay_amt': '0.00', 'deposit': '0.00',
        'surcharge_date': '', 'after_surcharge': '0.00',
        'slip_ref': '', 'long_ref': '', 'crc_code': '',
        'bar_chart_svg': '', 'qr_code_svg': '', 'barcode_svg': '', 'bottom_barcode_svg': '',
      };

      let html = tpl.html_template;
      console.log('[枪王] Monzo branch: replacing', Object.keys(replaces).length, 'placeholders, template length:', html.length);
      for (const [key, val] of Object.entries(replaces)) {
        // Use split/join for reliable replacement (avoids regex escaping issues)
        html = html.split('{{' + key + '}}').join(String(val));
      }
      const remainingPh = html.match(/\{\{\w+\}\}/g);
      console.log('[枪王] After replacement, remaining placeholders:', remainingPh ? [...new Set(remainingPh)] : 'none');
      const css = tpl.css_template || '';
      const fullHtml = (css ? '<style>'+css+'</style>' : '') + html;

      document.getElementById('preview-default').classList.add('hidden');
      const pb = document.getElementById('preview-bill');
      pb.innerHTML = fullHtml;
      pb.classList.remove('hidden');
      pb.classList.add('fade-in');

      const btn = document.getElementById('generateBtn');
      btn.innerHTML = '<i class="fas fa-check"></i> 已生成';
      btn.classList.replace('bg-primary','bg-green-600');
      setTimeout(()=>{btn.innerHTML='<i class="fas fa-magic"></i> 生成账单';btn.classList.replace('bg-green-600','bg-primary');},1500);
      return;
    }

    // ──── Wise 专用逻辑 ────
    if (isWiseType) {
      const fmtWD = (d) => { return d.getFullYear()+'年'+String(d.getMonth()+1).padStart(2,'0')+'月'+String(d.getDate()).padStart(2,'0')+'日'; };
      const wiseCurrency = document.getElementById('wiseCurrency')?.value || 'EUR';
      const wiseSymbol = wiseCurrency === 'GBP' ? '£' : '€';
      const wiseTimezone = document.getElementById('wiseTimezone')?.value || 'GMT+08:00';
      const wisePeriodStart = document.getElementById('wisePeriodStart')?.value || fmtWD(new Date(Date.now()-14*86400000));
      const wisePeriodEnd = document.getElementById('wisePeriodEnd')?.value || fmtWD(new Date());
      const wiseGeneratedDate = document.getElementById('wiseGeneratedDate')?.value || fmtWD(new Date());
      const wiseBalanceDate = document.getElementById('wiseBalanceDate')?.value || fmtWD(new Date());
      const wiseBalance = parseFloat(document.getElementById('wiseBalance')?.value) || 0;
      const wiseName = document.getElementById('wiseName')?.value || name;
      const wiseStreet = document.getElementById('wiseStreet')?.value || unit;
      const wiseCity = document.getElementById('wiseCity')?.value || district;
      const wiseState = document.getElementById('wiseState')?.value || '';
      const wisePostcode = document.getElementById('wisePostcode')?.value || '';
      const wiseCountry = document.getElementById('wiseCountry')?.value || 'China';
      const wiseIban = document.getElementById('wiseIban')?.value || 'GB23 TRWI 6084 6482 9223 30';
      const wiseBic = document.getElementById('wiseBic')?.value || 'TRWIGB2LXXX';
      const wiseAccountNumber = document.getElementById('wiseAccountNumber')?.value || '82922330';
      const wiseSortCode = document.getElementById('wiseSortCode')?.value || '60-84-64';
      const wiseTxnCount = parseInt(document.getElementById('wiseTxnCount')?.value) || 0;
      const wiseRef = Array.from({length:8}, () => Math.random().toString(36).substring(2,4)).join('') + '-' + Array.from({length:4}, () => Math.random().toString(36).substring(2,6)).join('-') + Array.from({length:4}, () => Math.random().toString(36).substring(2,6)).join('-') + Array.from({length:12}, () => Math.random().toString(36).substring(2,4)).join('');

      // 生成交易明细
      let txnRows = '';
      if (wiseTxnCount > 0) {
        const txnTypes = ['汇入','汇出'];
        const txnDescs = [
          'Transfer from John Smith','Salary deposit','Online purchase refund',
          'Freelance payment','Transfer to Savings','Utility bill payment',
          'Restaurant payment','Grocery shopping','Transport card top-up',
          'Subscription renewal','Insurance payment','Loan repayment received'
        ];
        const year = new Date().getFullYear();
        const month = new Date().getMonth();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        let runningBal = wiseBalance || (Math.random()*5000);
        // Reverse build: start from current balance, back-calculate opening
        for (let i = 0; i < wiseTxnCount; i++) {
          const txnType = txnTypes[Math.floor(Math.random() * txnTypes.length)];
          const desc = txnDescs[Math.floor(Math.random() * txnDescs.length)];
          const amt = (Math.random()*200 + 1).toFixed(2);
          txnRows = '<div class=\"txn-row\">' +
            '<div class=\"td-desc\">' + desc + '</div>' +
            '<div class=\"td-in\">' + (txnType === '汇入' ? amt : '') + '</div>' +
            '<div class=\"td-out\">' + (txnType === '汇出' ? amt : '') + '</div>' +
            '<div class=\"td-bal\">' + runningBal.toFixed(2) + '</div>' +
            '</div>' + txnRows;
          // Back-calculate: deposit removed, withdrawal added back
          if (txnType === '汇入') {
            runningBal -= parseFloat(amt);
          } else {
            runningBal += parseFloat(amt);
          }
        }
      }

      const replaces = {
        'customer_name': wiseName,
        'full_address': [wiseStreet, wiseCity, wiseState, wisePostcode, wiseCountry].filter(Boolean).join(', '),
        'address_unit': wiseStreet,
        'address_street': '',
        'address_district': wiseCity,
        'bill_number': billNoBank,
        'account_number': wiseAccountNumber,
        'period_start': wisePeriodStart,
        'period_end': wisePeriodEnd,
        'issue_date': wiseGeneratedDate,
        'currency': wiseSymbol,
        'bill_type_name': 'Wise ' + wiseCurrency + ' Statement',
        'unit': 'txn',
        'bank_name': 'Wise',
        'bank_name_en': 'WISE',
        'account_type': wiseCurrency + ' Account',
        'statement_period': wisePeriodStart + ' - ' + wisePeriodEnd,
        'total_due': wiseBalance.toFixed(2),
        'sort_code': wiseSortCode, 'bic': wiseBic, 'iban': wiseIban, 'country': wiseCountry,
        'wise_currency': wiseCurrency,
        'wise_period_start': wisePeriodStart,
        'wise_period_end': wisePeriodEnd,
        'wise_generated_date': wiseGeneratedDate,
        'wise_balance_date': wiseBalanceDate,
        'wise_balance': wiseBalance.toFixed(2),
        'wise_iban': wiseIban,
        'wise_bic': wiseBic,
        'wise_city': wiseCity,
        'wise_state': wiseState,
        'wise_postcode': wisePostcode,
        'wise_country': wiseCountry,
        'wise_ref': wiseRef,
        'wise_transactions': txnRows || '',
        'wise_timezone': wiseTimezone,
        // compat
        'opening_balance': wiseBalance.toFixed(2),
        'closing_balance': wiseBalance.toFixed(2),
        'total_credits': '0.00',
        'total_debits': '0.00',
        'transactions': txnRows || '',
        'sort_code': wiseSortCode, 'bic': wiseBic, 'iban': wiseIban, 'country': wiseCountry,
        'postal_code': wisePostcode, 'balance_pots': '0.00', 'total_outgoings': '0.00', 'total_deposits': '0.00',
        'card_number': '', 'credit_limit': '0.00', 'available_credit': '0.00',
        'min_payment': '0.00', 'payment_due_date': '', 'new_charges': '0.00',
        'new_payments': '0.00', 'interest_charge': '0.00', 'adjustment': '0.00',
        'points_balance': '0', 'previous_balance': '0.00',
        'consumption': '0.0', 'meter_prev': '0', 'meter_curr': '0',
        'meter_number': '', 'days': '0', 'daily_litres': '0',
        't1': '0.0', 't2': '0.0', 't3': '0.0', 't4': '0.0',
        'c1': '0.00', 'c2': '0.00', 'c3': '0.00', 'c4': '0.00',
        'water_charge': '0.00', 'sewage_charge': '0.00',
        'last_pay_date': '', 'last_pay_amt': '0.00', 'deposit': '0.00',
        'surcharge_date': '', 'after_surcharge': '0.00',
        'slip_ref': '', 'long_ref': '', 'crc_code': '',
        'bar_chart_svg': '', 'qr_code_svg': '', 'barcode_svg': '', 'bottom_barcode_svg': '',
      };

      let html = tpl.html_template;
      for (const [key, val] of Object.entries(replaces)) {
        html = html.split('{{' + key + '}}').join(String(val));
      }
      const css = tpl.css_template || '';
      const fullHtml = (css ? '<style>'+css+'</style>' : '') + html;
      document.getElementById('preview-default').classList.add('hidden');
      const pb = document.getElementById('preview-bill');
      pb.innerHTML = fullHtml;
      pb.classList.remove('hidden');
      pb.classList.add('fade-in');
      const btn = document.getElementById('generateBtn');
      btn.innerHTML = '<i class="fas fa-check"></i> 已生成';
      btn.classList.replace('bg-primary','bg-green-600');
      setTimeout(()=>{btn.innerHTML='<i class="fas fa-magic"></i> 生成账单';btn.classList.replace('bg-green-600','bg-primary');},1500);
      return;
    }

    // ──── Monese 专用逻辑 ────
    if (isMoneseType) {
      const mName = document.getElementById('moneseName')?.value || name || 'CAO CAO';
      const mStreet = document.getElementById('moneseStreet')?.value || unit || 'Hattenheimer Str. 19';
      const mPostcode = document.getElementById('monesePostcode')?.value || '60326';
      const mCity = document.getElementById('moneseCity')?.value || district || 'Frankfurt am Main';
      const mIban = document.getElementById('moneseIban')?.value || 'BE' + Math.floor(10000000000000 + Math.random() * 90000000000000);
      const mBic = document.getElementById('moneseBic')?.value || 'PESOBEB1';
      const mAccount = document.getElementById('moneseAccount')?.value || 'M' + Math.floor(10000000 + Math.random() * 90000000);

      const mStart = document.getElementById('moneseStart')?.value || periodStart;
      const mEnd = document.getElementById('moneseEnd')?.value || periodEnd;

      const mOpening = parseFloat(document.getElementById('moneseOpening')?.value) || 0;
      const mIn = parseFloat(document.getElementById('moneseIn')?.value) || 47.39;
      const mOut = parseFloat(document.getElementById('moneseOut')?.value) || 47.31;
      const mClosing = mOpening + mIn - mOut;
      const txnCount = parseInt(document.getElementById('moneseTxnCount')?.value) || 3;

      const deMonths = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
      const fmtDE = (dStr) => {
        if(!dStr) return '';
        const d = new Date(dStr);
        return String(d.getDate()).padStart(2,'0') + ' ' + deMonths[d.getMonth()] + ' ' + d.getFullYear();
      };
      const fmtShort = (d) => String(d.getDate()).padStart(2,'0') + '/' + String(d.getMonth()+1).padStart(2,'0') + '/' + d.getFullYear();

      let txnRows = '';
      if (txnCount > 0) {
        const pStartDt = new Date(mStart);
        const pEndDt = new Date(mEnd);
        const daysSpan = Math.max(1, Math.round((pEndDt - pStartDt) / 86400000));
        let runningBal = mOpening;
        const merchants = [
            {t: 'CAG GmbH', d: 'DE54600501010405802755 | Privacy ReClaim<br>deine EUR'},
            {t: 'Ramona Gewohn', d: 'BE679741377517187 | ih liebe dich'},
            {t: 'LOTTO24', d: 'NL37ADYB2100000010 | Gewinn aus<br>Gluecksspiel C14642139'},
            {t: 'REWE Markt', d: 'Kartenkauf 2026-06<br>Vielen Dank'},
            {t: 'Amazon EU', d: 'AMZN Mktp DE<br>892374982374'}
        ];

        for (let i = 0; i < txnCount; i++) {
          const isCredit = Math.random() > 0.5;
          let amt = isCredit ? (mIn / (txnCount/2)) : (mOut / (txnCount/2));
          amt = amt * (0.8 + Math.random()*0.4);
          const rounded = amt.toFixed(2);
          runningBal += (isCredit ? parseFloat(rounded) : -parseFloat(rounded));

          const d = new Date(pStartDt.getTime() + Math.floor(Math.random() * daysSpan) * 86400000);
          const dateStr = fmtShort(d);
          const merch = merchants[Math.floor(Math.random() * merchants.length)];

          txnRows += '<div class="transaction-row' + (i === txnCount - 1 ? ' last' : '') + '">';
          txnRows += '<div class="date">' + dateStr + '</div>';
          txnRows += '<div class="payment-date">' + dateStr + '</div>';
          txnRows += '<div class="description"><div class="description-title">' + merch.t + '</div><div class="description-detail">' + merch.d + '</div></div>';
          txnRows += '<div class="transaction-amount">' + (isCredit ? '+' : '-') + '€' + rounded + '</div>';
          txnRows += '<div class="balance">€' + runningBal.toFixed(2) + '</div>';
          txnRows += '</div>';
        }
      }

      const replaces = {
        'customer_name': mName,
        'address_street': mStreet,
        'postal_code': mPostcode,
        'address_district': mCity,
        'iban': mIban,
        'bic': mBic,
        'account_number': mAccount,
        'statement_period': fmtDE(mStart) + ' - ' + fmtDE(mEnd),
        'currency': currency || '€',
        'opening_balance': mOpening.toFixed(2),
        'total_credits': mIn.toFixed(2),
        'total_debits': mOut.toFixed(2),
        'closing_balance': mClosing.toFixed(2),
        'transactions': txnRows,
      };

      let html = tpl.html_template;
      for (const [key, val] of Object.entries(replaces)) {
        html = html.split('{{' + key + '}}').join(String(val));
      }
      const css = tpl.css_template || '';
      const fullHtml = (css ? '<style>'+css+'</style>' : '') + html;

      document.getElementById('preview-default').classList.add('hidden');
      const pb = document.getElementById('preview-bill');
      pb.innerHTML = fullHtml;
      pb.classList.remove('hidden'); pb.classList.add('fade-in');
      const btn = document.getElementById('generateBtn');
      btn.innerHTML = '<i class="fas fa-check"></i> 已生成';
      btn.classList.replace('bg-primary','bg-green-600');
      setTimeout(()=>{btn.innerHTML='<i class="fas fa-magic"></i> 生成账单';btn.classList.replace('bg-green-600','bg-primary');},1500);
      return;
    }

    // ──── Kraken专用逻辑 ────
    if (isKrakenType) {
      const krakenName = document.getElementById('krakenName')?.value || name;
      const krakenStreet = document.getElementById('krakenStreet')?.value || '37 ORCHARD TERRACE';
      const krakenCity = document.getElementById('krakenCity')?.value || 'Huddersfield, HD4 6DB';
      const krakenPublicId = document.getElementById('krakenPublicId')?.value || 'AA35 N84G NCHU DYFI';
      const krakenAccountId = document.getElementById('krakenAccountId')?.value || 'WUC9 A8RP HZM7 U7Q5';
      const krakenTxnCount = parseInt(document.getElementById('krakenTxnCount')?.value) || 0;

      // 日期处理 - 从periodEnd推导月份
      const pEndDate = new Date(periodEnd);
      const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
      const statementMonth = monthNames[pEndDate.getMonth()] + ' ' + pEndDate.getFullYear();
      // balance_date: 优先使用用户自定义，否则自动推算次月1号
      const customDate = document.getElementById('krakenBalanceDate')?.value;
      const customTime = document.getElementById('krakenBalanceTime')?.value.trim() || '00:00:00';
      let balanceDate;
      if (customDate) {
        balanceDate = customDate + ' ' + customTime;
      } else {
        const nextMonth = new Date(pEndDate.getFullYear(), pEndDate.getMonth() + 1, 1);
        balanceDate = nextMonth.getFullYear() + '-' + String(nextMonth.getMonth()+1).padStart(2,'0') + '-01 00:00:00';
      }

      // Portfolio table - 随机2-3个资产
      const assets = ['GBP','USDC','BTC','ETH','EUR','SOL'];
      const numAssets = 2 + Math.floor(Math.random() * 2);
      let portfolioRows = '';
      let totalOpenVal = 0, totalCloseVal = 0;
      for (let i = 0; i < numAssets; i++) {
        const asset = assets[i];
        const closeQty = asset === 'GBP' ? (Math.random()*500).toFixed(4) : (Math.random()*10).toFixed(8);
        const closePrice = asset === 'GBP' ? '1' : (Math.random()*50000).toFixed(4);
        const closeVal = (parseFloat(closeQty) * parseFloat(closePrice)).toFixed(4);
        const openQty = '0';
        const openPrice = '-';
        const openVal = '0';
        const netChange = (parseFloat(closeVal) - parseFloat(openVal)).toFixed(4);
        const netSign = parseFloat(netChange) >= 0 ? '+' + netChange : netChange;
        totalOpenVal += parseFloat(openVal);
        totalCloseVal += parseFloat(closeVal);
        portfolioRows += '<tr><td class="align-left">' + asset + '</td><td class="align-left">Everyday</td>' +
          '<td class="align-right">' + openQty + '</td><td class="align-right">' + openPrice + '</td><td class="align-right">' + openVal + '</td>' +
          '<td class="align-right">' + closeQty + '</td><td class="align-right">' + closePrice + '</td><td class="align-right">' + closeVal + '</td>' +
          '<td class="align-right">' + netSign + '</td></tr>';
      }
      const totalNet = (totalCloseVal - totalOpenVal).toFixed(4);
      const totalNetSign = parseFloat(totalNet) >= 0 ? '+' + totalNet : totalNet;
      portfolioRows += '<tr class="total-row"><td class="align-left">Total</td><td></td><td></td><td></td><td class="align-right">' + totalOpenVal.toFixed(4) + '</td><td></td><td></td><td class="align-right">' + totalCloseVal.toFixed(4) + '</td><td class="align-right">' + totalNetSign + '</td></tr>';

      // Activity table
      let activityRows = '';
      if (krakenTxnCount > 0) {
        const types = [
          {type:'Third Party Payment', sub:'Deposit'},
          {type:'Transfer', sub:''},
          {type:'Trade', sub:'Buy'},
          {type:'Trade', sub:'Sell'},
          {type:'Earn', sub:'Reward'},
          {type:'Staking', sub:'Reward'},
          {type:'Withdrawal', sub:''},
        ];
        const activityAssets = ['GBP','USDC','BTC','ETH'];
        const counterparties = ['Weixin','AliPay','Stripe','Revolut','WZCW-S8WF-MFYG-GF2X',''];

        // Generate dates within the month
        const year = pEndDate.getFullYear();
        const month = pEndDate.getMonth();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        for (let i = 0; i < krakenTxnCount; i++) {
          const day = 1 + Math.floor(Math.random() * daysInMonth);
          const hour = Math.floor(Math.random() * 24);
          const min = Math.floor(Math.random() * 60);
          const sec = Math.floor(Math.random() * 60);
          const dateStr = year + '-' + String(month+1).padStart(2,'0') + '-' + String(day).padStart(2,'0') + '<br>' + String(hour).padStart(2,'0') + ':' + String(min).padStart(2,'0') + ':' + String(sec).padStart(2,'0');

          const t = types[Math.floor(Math.random() * types.length)];
          const typeHtml = t.sub ? t.type + '<span class="type-sub">' + t.sub + '</span>' : t.type;

          const asset = activityAssets[Math.floor(Math.random() * activityAssets.length)];
          const isNegative = (t.type === 'Transfer' && Math.random() > 0.5) || t.type === 'Withdrawal' || (t.type === 'Trade' && t.sub === 'Sell');
          const amount = (Math.random() * 100).toFixed(8);
          const amountStr = isNegative ? '-' + amount : amount;

          const price = asset === 'GBP' ? '1' : (Math.random() * 5).toFixed(4);
          const fee = '0';
          const value = (Math.abs(parseFloat(amount)) * parseFloat(price)).toFixed(4);
          const valueStr = isNegative ? '-' + value : value;

          const counterparty = counterparties[Math.floor(Math.random() * counterparties.length)];
          const ref = 'TR' + Math.random().toString(36).substring(2,8).toUpperCase() + '-' + Math.random().toString(36).substring(2,8).toUpperCase() + '-' + Math.random().toString(36).substring(2,8).toUpperCase();

          activityRows += '<tr>' +
            '<td class="align-left">' + dateStr + '</td>' +
            '<td class="align-left">' + typeHtml + '</td>' +
            '<td class="align-left">' + asset + '</td>' +
            '<td class="align-left">Everyday</td>' +
            '<td class="align-right">' + amountStr + '</td>' +
            '<td class="align-right">' + price + '</td>' +
            '<td class="align-right">' + fee + '</td>' +
            '<td class="align-right">' + valueStr + '</td>' +
            '<td class="align-left">' + counterparty + '</td>' +
            '<td class="align-left">' + ref + '</td>' +
            '</tr>';
        }
      }

      const replaces = {
        'customer_name': krakenName,
        'full_address': [krakenStreet, krakenCity].filter(Boolean).join(', '),
        'address_unit': krakenStreet,
        'address_street': '',
        'address_district': krakenCity,
        'bill_number': billNoBank,
        'account_number': krakenAccountId,
        'period_start': statementMonth,
        'period_end': statementMonth,
        'issue_date': statementMonth,
        'currency': currency,
        'bill_type_name': 'Kraken Statement',
        'unit': 'txn',
        'bank_name': 'Kraken',
        'bank_name_en': 'KRAKEN',
        'account_type': '',
        'statement_period': statementMonth,
        'statement_month': statementMonth,
        'balance_date': balanceDate,
        'kraken_public_id': krakenPublicId,
        'portfolio_table': portfolioRows,
        'transactions': activityRows,
        'opening_balance': totalOpenVal.toFixed(4),
        'closing_balance': totalCloseVal.toFixed(4),
        'total_credits': '0.00',
        'total_debits': '0.00',
        'total_due': totalNetSign,
        'sort_code': '', 'bic': '', 'iban': '', 'country': '',
        'postal_code': '', 'balance_pots': '0.00', 'total_outgoings': '0.00', 'total_deposits': '0.00',
        'card_number': '', 'credit_limit': '0.00', 'available_credit': '0.00',
        'min_payment': '0.00', 'payment_due_date': '', 'new_charges': '0.00',
        'new_payments': '0.00', 'interest_charge': '0.00', 'adjustment': '0.00',
        'points_balance': '0', 'previous_balance': '0.00',
        'consumption': '0.0', 'meter_prev': '0', 'meter_curr': '0',
        'meter_number': '', 'days': '0', 'daily_litres': '0',
        't1': '0.0', 't2': '0.0', 't3': '0.0', 't4': '0.0',
        'c1': '0.00', 'c2': '0.00', 'c3': '0.00', 'c4': '0.00',
        'water_charge': '0.00', 'sewage_charge': '0.00',
        'last_pay_date': '', 'last_pay_amt': '0.00', 'deposit': '0.00',
        'surcharge_date': '', 'after_surcharge': '0.00',
        'slip_ref': '', 'long_ref': '', 'crc_code': '',
        'bar_chart_svg': '', 'qr_code_svg': '', 'barcode_svg': '', 'bottom_barcode_svg': '',
        'card_last4': '',
      };

      let html = tpl.html_template;
      for (const [key, val] of Object.entries(replaces)) {
        html = html.split('{{' + key + '}}').join(String(val));
      }
      const css = tpl.css_template || '';
      const fullHtml = (css ? '<style>'+css+'</style>' : '') + html;

      document.getElementById('preview-default').classList.add('hidden');
      const pb = document.getElementById('preview-bill');
      pb.innerHTML = fullHtml;
      pb.classList.remove('hidden');
      pb.classList.add('fade-in');

      const btn = document.getElementById('generateBtn');
      btn.innerHTML = '<i class="fas fa-check"></i> 已生成';
      btn.classList.replace('bg-primary','bg-green-600');
      setTimeout(()=>{btn.innerHTML='<i class="fas fa-magic"></i> 生成账单';btn.classList.replace('bg-green-600','bg-primary');},1500);
      return;
    }

    // ──── SeaBank 专用逻辑 ────
    if (bt && bt.code === 'ph-seabank') {
      const phName = document.getElementById('customerName').value || name;
      const phUnit = document.getElementById('addressUnit').value || unit;
      const phStreet = document.getElementById('addressStreet').value || street;
      const phDistrict = document.getElementById('addressDistrict').value || district;
      const phAcct = document.getElementById('generalAccountNumber')?.value || String(Math.floor(100000000000+Math.random()*900000000000));
      const phBillNo = document.getElementById('billNumberBank')?.value || 'S/N 501<br>221201BTUOZLFO';
      const phTxnCount = parseInt(document.getElementById('txnCount')?.value) || 6;

      // 金额
      const phOpening = parseFloat(document.getElementById('openingBalanceGeneral')?.value) || 3;
      const phCredits = parseFloat(document.getElementById('totalCredits')?.value) || 15000;
      const phDebits = parseFloat(document.getElementById('totalDebits')?.value) || 15000;
      const phClosing = phOpening + phCredits - phDebits;

      // 日期格式化：01 SEPT 2023
      const fmtPHDate = (dStr) => {
        if(!dStr) return '';
        const d = new Date(dStr);
        const m = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEPT','OCT','NOV','DEC'][d.getMonth()];
        return String(d.getDate()).padStart(2,'0') + ' ' + m + ' ' + d.getFullYear();
      };

      // 生成交易明细
      let txnRows = '';
      if (phTxnCount > 0) {
        const pStart = new Date(periodStart);
        const pEnd = new Date(periodEnd);
        const daysSpan = Math.max(1, Math.round((pEnd - pStart) / 86400000));

        // 生成随机交易日期
        const txnDates = [];
        for (let i = 0; i < phTxnCount; i++) {
          const dayOffset = Math.floor(Math.random() * daysSpan);
          txnDates.push(new Date(pStart.getTime() + dayOffset * 86400000));
        }
        txnDates.sort((a, b) => b - a); // 倒序

        const inDescs = ['Savings Interest<span class="col-gray">Interest</span>', 'LODIBET.PH AGENT SALARY(1MONTH)<span class="col-gray">Transfer</span>', 'JILIBET.PH AGENT SALARY (1MONTH)<span class="col-gray">Transfer</span>'];
        const outDescs = ['Savings Withholding Tax<span class="col-gray">Tax</span>', 'G-cash wallet w/ no: ******19820<span class="col-gray">Transfer</span>'];

        let remCredit = phCredits;
        let remDebit = phDebits;

        for (let i = 0; i < phTxnCount; i++) {
          const d = txnDates[i];
          const isCredit = i < Math.ceil(phTxnCount * 0.4) ? true : (Math.random() > 0.5);
          const desc = isCredit ? inDescs[Math.floor(Math.random() * inDescs.length)] : outDescs[Math.floor(Math.random() * outDescs.length)];
          const month = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEPT','OCT','NOV','DEC'][d.getMonth()];
          const dStr = String(d.getDate()).padStart(2,'0') + ' ' + month;

          let amt = isCredit ? (remCredit / (phTxnCount/2)) : (remDebit / (phTxnCount/2));
          amt = amt * (0.8 + Math.random() * 0.4);
          if (desc.includes('Interest')) amt = amt * 0.05;
          if (desc.includes('Tax')) amt = amt * 0.05;

          const outAmt = isCredit ? '' : amt.toFixed(2);
          const inAmt = isCredit ? amt.toFixed(2) : '';

          txnRows += '<tr><td>'+dStr+'</td><td>'+desc+'</td><td class="text-right">'+outAmt+'</td><td class="text-right">'+inAmt+'</td></tr>';
        }
      }

      const replaces = {
        'customer_name': phName,
        'address_unit': phUnit,
        'address_street': phStreet,
        'address_district': phDistrict,
        'account_number': phAcct,
        'bill_number': phBillNo,
        'issue_date': fmtPHDate(issueDate),
        'period_start': fmtPHDate(periodStart),
        'period_end': fmtPHDate(periodEnd),
        'opening_balance': phOpening.toFixed(2),
        'closing_balance': phClosing.toFixed(2),
        'total_credits': phCredits.toFixed(2),
        'total_debits': phDebits.toFixed(2),
        'transactions': txnRows,
        // compat
        'currency': 'PHP',
        'sort_code': '', 'bic': '', 'iban': '', 'country': 'PHILIPPINES'
      };

      let html = tpl.html_template;
      for (const [key, val] of Object.entries(replaces)) {
        html = html.split('{{' + key + '}}').join(String(val));
      }
      const css = tpl.css_template || '';
      const fullHtml = (css ? '<style>'+css+'</style>' : '') + html;
      document.getElementById('preview-default').classList.add('hidden');
      const pb = document.getElementById('preview-bill');
      pb.innerHTML = fullHtml;
      pb.classList.remove('hidden');
      pb.classList.add('fade-in');
      const btn = document.getElementById('generateBtn');
      btn.innerHTML = '<i class="fas fa-check"></i> 已生成';
      btn.classList.replace('bg-primary','bg-green-600');
      setTimeout(()=>{btn.innerHTML='<i class="fas fa-magic"></i> 生成账单';btn.classList.replace('bg-green-600','bg-primary');},1500);
      return;
    }

    // ──── 招商银行信用卡专用逻辑 ────
    if (isCMBType) {
      const cmbName = document.getElementById('cmbName')?.value || name;
      const cmbPostalCode = document.getElementById('cmbPostalCode')?.value || '404000';
      const cmbCity = document.getElementById('cmbCity')?.value || '';
      const cmbDistrict = document.getElementById('cmbDistrict')?.value || '';
      const cmbUnit = document.getElementById('cmbUnit')?.value || '楼12幢三单元107室';
      const cmbCardLast4 = document.getElementById('cmbCardLast4')?.value || '6277';
      const cmbCreditLimit = parseFloat(document.getElementById('cmbCreditLimit')?.value) || 5000;
      const cmbPrevBalance = parseFloat(document.getElementById('cmbPrevBalance')?.value) || 0;
      const cmbPrevPayment = parseFloat(document.getElementById('cmbPrevPayment')?.value) || 0;
      const cmbNewCharges = parseFloat(document.getElementById('cmbNewCharges')?.value) || 0;
      const cmbAdjustment = parseFloat(document.getElementById('cmbAdjustment')?.value) || 0;
      const cmbInterest = parseFloat(document.getElementById('cmbInterest')?.value) || 0;
      const cmbNewBalance = parseFloat(document.getElementById('cmbNewBalance')?.value) || (cmbPrevBalance - cmbPrevPayment + cmbNewCharges - cmbAdjustment + cmbInterest);
      const cmbMinPayment = parseFloat(document.getElementById('cmbMinPayment')?.value) || Math.max(10, cmbNewBalance * 0.05);
      const cmbTxnCount = parseInt(document.getElementById('cmbTxnCount')?.value) || 0;

      // 日期格式: 中文
      const fmtCMBFull = (d) => { if(!d) return ''; const dt=new Date(d); return dt.getFullYear()+'年'+String(dt.getMonth()+1).padStart(2,'0')+'月'+String(dt.getDate()).padStart(2,'0')+'日'; };
      const pEnd = new Date(periodEnd);
      const periodCN = pEnd.getFullYear()+'年'+String(pEnd.getMonth()+1).padStart(2,'0')+'月';
      const periodEN = pEnd.getFullYear()+'.'+String(pEnd.getMonth()+1).padStart(2,'0');

      // 账单日和到期还款日
      const cmbIssueDate = fmtCMBFull(issueDate);
      const cmbPaymentDueDate = document.getElementById('cmbPaymentDueDate')?.value
        ? fmtCMBFull(document.getElementById('cmbPaymentDueDate').value)
        : fmtCMBFull(new Date(new Date(issueDate).getTime() + 18*86400000));

      // 生成交易明细
      let transactionsHtml = '';
      if (cmbTxnCount > 0) {
        transactionsHtml = generateCreditCardTransactions('cn', currency, cmbTxnCount, periodStart, periodEnd, cmbCardLast4, cmbNewCharges, 'cn-cmb-credit');
      } else {
        transactionsHtml = '<tr><td colspan="6" style="text-align:center;padding:20px;color:#999;">There were no transactions during this period.</td></tr>';
      }

      // 金额格式化 (带千位分隔符)
      const fmtAmt = (n) => Number(n).toLocaleString('zh-CN', {minimumFractionDigits:2, maximumFractionDigits:2});

      const replaces = {
        'customer_name': cmbName,
        'full_address': [cmbDistrict, cmbUnit, cmbCity].filter(Boolean).join('，'),
        'address_unit': cmbUnit,
        'address_street': cmbDistrict,
        'address_district': cmbCity,
        'bill_number': billNoBank,
        'account_number': cmbCardLast4,
        'period_start': fmtHK(periodStart),
        'period_end': periodEN,
        'issue_date': cmbIssueDate,
        'currency': currency,
        'bill_type_name': bt ? bt.name : '',
        'unit': bt ? bt.unit : 'txn',
        'bank_name': bankName || '招商银行',
        'bank_name_en': bankNameEn || 'CHINA MERCHANTS BANK',
        'account_type': accountType || '个人消费卡账户',
        'statement_period': periodCN,
        'opening_balance': '0.00',
        'closing_balance': cmbNewBalance.toFixed(2),
        'total_credits': '0.00',
        'total_debits': cmbNewCharges.toFixed(2),
        'transactions': transactionsHtml,
        // CMB 信用卡专用
        'card_number': '**** **** **** ' + cmbCardLast4,
        'card_last4': cmbCardLast4,
        'credit_limit': fmtAmt(cmbCreditLimit),
        'available_credit': fmtAmt(cmbCreditLimit - cmbNewBalance),
        'min_payment': fmtAmt(cmbMinPayment),
        'payment_due_date': cmbPaymentDueDate,
        'new_charges': fmtAmt(cmbNewCharges),
        'new_payments': fmtAmt(cmbPrevPayment),
        'interest_charge': fmtAmt(cmbInterest),
        'adjustment': fmtAmt(cmbAdjustment),
        'points_balance': String(Math.floor(cmbNewBalance * 10)),
        'previous_balance': fmtAmt(cmbPrevBalance),
        'postal_code': cmbPostalCode,
        'total_due': fmtAmt(cmbNewBalance),
        // 兼容占位符
        'consumption': '0.0', 'meter_prev': '0', 'meter_curr': '0',
        'meter_number': '', 'days': '0', 'daily_litres': '0',
        't1': '0.0', 't2': '0.0', 't3': '0.0', 't4': '0.0',
        'c1': '0.00', 'c2': '0.00', 'c3': '0.00', 'c4': '0.00',
        'water_charge': '0.00', 'sewage_charge': '0.00',
        'last_pay_date': '', 'last_pay_amt': '0.00', 'deposit': '0.00',
        'surcharge_date': '', 'after_surcharge': '0.00',
        'slip_ref': '', 'long_ref': '', 'crc_code': '',
        'bar_chart_svg': '', 'qr_code_svg': '', 'barcode_svg': '', 'bottom_barcode_svg': '',
        'sort_code': '', 'bic': '', 'iban': '', 'country': '',
        'balance_pots': '0.00', 'total_outgoings': '0.00', 'total_deposits': '0.00',
      };

      let html = tpl.html_template;
      console.log('[枪王] CMB branch: replacing', Object.keys(replaces).length, 'placeholders, template length:', html.length);
      for (const [key, val] of Object.entries(replaces)) {
        html = html.split('{{' + key + '}}').join(String(val));
      }
      const remainingPh = html.match(/\{\{\w+\}\}/g);
      console.log('[枪王] After CMB replacement, remaining placeholders:', remainingPh ? [...new Set(remainingPh)] : 'none');
      const css = tpl.css_template || '';
      const fullHtml = (css ? '<style>'+css+'</style>' : '') + html;

      document.getElementById('preview-default').classList.add('hidden');
      const pb = document.getElementById('preview-bill');
      pb.innerHTML = fullHtml;
      pb.classList.remove('hidden');
      pb.classList.add('fade-in');

      const btn = document.getElementById('generateBtn');
      btn.innerHTML = '<i class="fas fa-check"></i> 已生成';
      btn.classList.replace('bg-primary','bg-green-600');
      setTimeout(()=>{btn.innerHTML='<i class="fas fa-magic"></i> 生成账单';btn.classList.replace('bg-green-600','bg-primary');},1500);
      return;
    }

    // ──── 非Monzo银行 ────
    const openingBalance = parseFloat(document.getElementById('openingBalanceGeneral').value) || (10000+Math.random()*90000);
    const totalCredits = parseFloat(document.getElementById('totalCredits').value) || (5000+Math.random()*20000);
    const totalDebits = parseFloat(document.getElementById('totalDebits').value) || (3000+Math.random()*15000);
    const closingBalance = Math.round((openingBalance + totalCredits - totalDebits)*100)/100;
    const acctNo = String(Math.floor(10000000+Math.random()*90000000));
    const numTxns = parseInt(document.getElementById('txnCount')?.value) || 12;

    // 生成交易明细
    const isCreditCard = bt && bt.category === 'credit_card';
    const billTypeCode = bt ? bt.code : '';
    let transactions;
    if (isCreditCard) {
      transactions = generateCreditCardTransactions(regionCode, currency, numTxns, periodStart, periodEnd, acctNo, totalDebits, billTypeCode);
    } else {
      transactions = generateBankTransactions(regionCode, currency, numTxns, periodStart, periodEnd, openingBalance, totalCredits, totalDebits);
    }

    const replaces = {
      'customer_name': name,
      'full_address': fullAddr,
      'address_unit': unit,
      'address_street': street,
      'address_district': district,
      'bill_number': billNoBank,
      'account_number': acctNo,
      'period_start': fmtHK(periodStart),
      'period_end': fmtHK(periodEnd),
      'issue_date': billTypeCode === 'cn-cmb-credit'
        ? fmtCNDate(new Date(issueDate))
        : fmtHK(issueDate),
      'currency': currency,
      'bill_type_name': bt ? bt.name : '',
      'unit': bt ? bt.unit : '笔',
      'bank_name': bankName,
      'bank_name_en': bankNameEn,
      'account_type': accountType,
      'statement_period': statementPeriod,
      'opening_balance': openingBalance.toFixed(2),
      'closing_balance': closingBalance.toFixed(2),
      'total_credits': totalCredits.toFixed(2),
      'total_debits': totalDebits.toFixed(2),
      'transactions': transactions,
      // credit-card-specific
      'card_number': '**** **** **** ' + acctNo.slice(-4),
      'credit_limit': '50,000.00',
      'available_credit': (50000 - totalDebits).toFixed(2),
      'min_payment': (totalDebits * 0.1).toFixed(2),
      'payment_due_date': billTypeCode === 'cn-cmb-credit'
        ? fmtCNDate(new Date(new Date(issueDate).getTime() + 18*86400000))
        : fmtHK(new Date(new Date(issueDate).getTime() + 25*86400000).toISOString().split('T')[0]),
      'new_charges': totalDebits.toFixed(2),
      'new_payments': '0.00',
      'interest_charge': '0.00',
      'points_balance': String(Math.floor(1000+Math.random()*99000)),
      'previous_balance': '0.00',
      'postal_code': String(100000+Math.floor(Math.random()*800000)),
      'adjustment': '0.00',
      'card_last4': acctNo.slice(-4),
      // 保留兼容性占位符
      'consumption': '0.0',
      'meter_prev': '0',
      'meter_curr': '0',
      'meter_number': '',
      'days': '0',
      'daily_litres': '0',
      't1': '0.0', 't2': '0.0', 't3': '0.0', 't4': '0.0',
      'c1': '0.00', 'c2': '0.00', 'c3': '0.00', 'c4': '0.00',
      'water_charge': '0.00',
      'sewage_charge': '0.00',
      'total_due': closingBalance.toFixed(2),
      'last_pay_date': '',
      'last_pay_amt': '0.00',
      'deposit': '0.00',
      'surcharge_date': '',
      'after_surcharge': '0.00',
      'slip_ref': '',
      'long_ref': '',
      'crc_code': '',
      'bar_chart_svg': '',
      'qr_code_svg': '',
      'barcode_svg': '',
      'bottom_barcode_svg': '',
    };

    let html = tpl.html_template;
    for (const [key, val] of Object.entries(replaces)) {
      html = html.split('{{' + key + '}}').join(String(val));
    }

    const css = tpl.css_template || '';
    const fullHtml = (css ? '<style>'+css+'</style>' : '') + html;

    document.getElementById('preview-default').classList.add('hidden');
    const pb = document.getElementById('preview-bill');
    pb.innerHTML = fullHtml;
    pb.classList.remove('hidden');
    pb.classList.add('fade-in');

    const btn = document.getElementById('generateBtn');
    btn.innerHTML = '<i class="fas fa-check"></i> 已生成';
    btn.classList.replace('bg-primary','bg-green-600');
    setTimeout(()=>{btn.innerHTML='<i class="fas fa-magic"></i> 生成账单';btn.classList.replace('bg-green-600','bg-primary');},1500);
    return;
  }

    // ──── Octopus Energy 专用替换 ────
    if (isOctopusType) {
      // 1. 基础数据获取
      const octName = document.getElementById('octName')?.value || 'LI HONGWEI';
      const octStreet = document.getElementById('octStreet')?.value || '69 Fairfax St';
      const octCity = document.getElementById('octCity')?.value || 'Birmingham';
      const octPostcode = document.getElementById('octPostcode')?.value || 'B5S 8PL';
      const octPrevBal = document.getElementById('octPrevBalance')?.value || '-1,690.47';
      const octNewBal = document.getElementById('octNewBalance')?.value || '-608.00';
      const octAcc = document.getElementById('octAccount')?.value || 'A-420ZB18U';
      const octBill = document.getElementById('octBillNum')?.value || '973075694';

      // 2. 动态生成交易明细 (保持逻辑一致)
      const fmtOct = (dStr) => {
        if(!dStr) return ''; const dt = new Date(dStr); const day = dt.getDate();
        const nth = (day>3 && day<21) ? 'th' : ['th','st','nd','rd','th','th','th','th','th','th'][day%10];
        const m = ['January','February','March','April','May','June','July','August','September','October','November','December'][dt.getMonth()];
        return day + nth + ' ' + m + ' ' + dt.getFullYear();
      };

      const issueDateStr = fmtOct(issueDate);
      // 随机生成3笔退款金额，避免 hardcode
      const c1 = document.getElementById('octC1')?.value || (Math.random()*300+100).toFixed(2);
      const c2 = document.getElementById('octC2')?.value || (Math.random()*300+100).toFixed(2);
      const c3 = document.getElementById('octC3')?.value || (Math.random()*300+100).toFixed(2);
      const t1DateVal = document.getElementById('octT1Date')?.value;
      const t2DateVal = document.getElementById('octT2Date')?.value;
      const t3DateVal = document.getElementById('octT3Date')?.value;

      // 动态交易日期范围（fallback：基于issueDate推算）
      const fmtTxnRange = (startOffset, endOffset) => {
        const dEnd = new Date(issueDate);
        dEnd.setDate(dEnd.getDate() + endOffset);
        const dStart = new Date(issueDate);
        dStart.setDate(dStart.getDate() + startOffset);
        const fmtShort = (d) => { const day = d.getDate(); const nth = (day>3 && day<21) ? 'th' : ['th','st','nd','rd','th','th','th','th','th','th'][day%10]; const m = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][d.getMonth()]; return day + nth + ' ' + m; };
        return fmtShort(dStart) + ' - ' + fmtShort(dEnd);
      };

      const replaces = {
        'customer_name': octName,
        'address_unit': octStreet,
        'address_street': octCity,
        'address_district': octPostcode,
        'period_start': fmtOct(periodStart),
        'period_end': fmtOct(periodEnd),
        'issue_date': issueDateStr,
        'previous_balance': octPrevBal,
        'total_due': octNewBal,
        'c1': c1, 'c2': c2, 'c3': c3,
        't1_date': t1DateVal || fmtTxnRange(-15, -1),
        't2_date': t2DateVal || fmtTxnRange(-30, -16),
        't3_date': t3DateVal || fmtTxnRange(-45, -31),
        'account_number': octAcc,
        'bill_number': octBill,
        'currency': currency
      };

      let html = tpl.html_template;
      for (const [key, val] of Object.entries(replaces)) {
        html = html.split('{{' + key + '}}').join(String(val));
      }
      const css = tpl.css_template || '';
      const fullHtml = (css ? '<style>'+css+'</style>' : '') + html;

      document.getElementById('preview-default').classList.add('hidden');
      const pb = document.getElementById('preview-bill');
      pb.innerHTML = fullHtml;
      pb.classList.remove('hidden'); pb.classList.add('fade-in');

      const btn = document.getElementById('generateBtn');
      btn.innerHTML = '<i class="fas fa-check"></i> 已生成';
      btn.classList.replace('bg-primary','bg-green-600');
      setTimeout(()=>{btn.innerHTML='<i class="fas fa-magic"></i> 生成账单';btn.classList.replace('bg-green-600','bg-primary');},1500);
      return;
    }

  // ──── 水电/公用事业替换 ────
  const billNo = document.getElementById('billNumber').value || ('WSD-'+Math.floor(10000000+Math.random()*90000000));
  const consumption = parseFloat(document.getElementById('consumption').value) || (25+Math.floor(Math.random()*20));
  const meterPrev = document.getElementById('meterPrev').value || '5432';
  const meterCurr = document.getElementById('meterCurr').value || String(5432+Math.floor(consumption));
  const meterNo = document.getElementById('meterNo').value || ('W'+Math.floor(100000+Math.random()*900000));

  // 计算数据
  const pStart = new Date(periodStart), pEnd = new Date(periodEnd);
  const days = Math.round((pEnd-pStart)/86400000) || 120;
  const dailyLitres = Math.round((consumption/days)*1000);

  const t1 = Math.min(consumption,12), t2 = Math.min(Math.max(consumption-12,0),31);
  const t3 = Math.min(Math.max(consumption-43,0),19), t4 = Math.max(consumption-62,0);
  const c1=t1*0, c2=t2*4.16, c3=t3*6.45, c4=t4*9.05;
  const wc = c1+c2+c3+c4, sc = wc*0.25, td = wc+sc;

  const lastPayAmt = (60+Math.random()*35).toFixed(2);
  const deposit = (300+Math.random()*200).toFixed(2);
  const sDate = new Date(issueDate); sDate.setDate(sDate.getDate()+14);
  const afterSurcharge = (td*1.05).toFixed(2);
  const slipRef = String(Math.floor(1000000000+Math.random()*9000000000));
  const acctNo = String(Math.floor(10000000+Math.random()*90000000));
  const crcCode = String(1000+Math.floor(Math.random()*9000));
  const longRef = billNo.replace('WSD-','')+' '+slipRef.substring(0,4)+' '+slipRef.substring(4,8)+' '+slipRef.substring(8);
  const lastPayDate = fmtHK(new Date(new Date(periodStart).getTime()-3*86400000).toISOString().split('T')[0]);

  // 柱状图
  const pastMonths = [
    {label:monthsAgo(5,pStart),value:180+Math.floor(Math.random()*80)},
    {label:monthsAgo(4,pStart),value:200+Math.floor(Math.random()*60)},
    {label:monthsAgo(3,pStart),value:190+Math.floor(Math.random()*70)},
    {label:monthsAgo(2,pStart),value:210+Math.floor(Math.random()*50)},
    {label:monthsAgo(1,pStart),value:195+Math.floor(Math.random()*60)},
  ];

  // 替换模板占位符
  const replaces = {
    'customer_name': name,
    'full_address': fullAddr,
    'address_unit': unit,
    'address_street': street,
    'address_district': district,
    'bill_number': billNo,
    'account_number': acctNo,
    'period_start': fmtHK(periodStart),
    'period_end': fmtHK(periodEnd),
    'issue_date': fmtHK(issueDate),
    'consumption': consumption.toFixed(1),
    'meter_prev': parseInt(meterPrev).toLocaleString(),
    'meter_curr': parseInt(meterCurr).toLocaleString(),
    'meter_number': meterNo,
    'days': String(days),
    'daily_litres': String(dailyLitres),
    'currency': currency,
    'bill_type_name': bt ? bt.name : '',
    'unit': bt ? bt.unit : 'm³',
    't1': t1.toFixed(1), 't2': t2.toFixed(1), 't3': t3.toFixed(1), 't4': t4.toFixed(1),
    'c1': c1.toFixed(2), 'c2': c2.toFixed(2), 'c3': c3.toFixed(2), 'c4': c4.toFixed(2),
    'water_charge': wc.toFixed(2),
    'sewage_charge': sc.toFixed(2),
    'total_due': td.toFixed(2),
    'last_pay_date': lastPayDate,
    'last_pay_amt': lastPayAmt,
    'deposit': deposit,
    'surcharge_date': fmtHK(sDate.toISOString().split('T')[0]),
    'after_surcharge': afterSurcharge,
    'slip_ref': slipRef,
    'long_ref': longRef,
    'crc_code': crcCode,
    'bar_chart_svg': genBarChartSVG(dailyLitres, pastMonths),
    'qr_code_svg': genQRSVG(56),
    'barcode_svg': genBarcodeSVG(genRandomBarcode(60), 28),
    'bottom_barcode_svg': genBarcodeSVG(genRandomBarcode(100), 24),
    // 银行兼容占位符
    'bank_name': '', 'bank_name_en': '', 'account_type': '',
    'statement_period': '', 'opening_balance': '', 'closing_balance': '',
    'total_credits': '', 'total_debits': '', 'transactions': '',
  };

  let html = tpl.html_template;
  for (const [key, val] of Object.entries(replaces)) {
    html = html.split('{{' + key + '}}').join(String(val));
  }

  // 组装 CSS + HTML
  const css = tpl.css_template || '';
  const fullHtml = (css ? '<style>'+css+'</style>' : '') + html;

  document.getElementById('preview-default').classList.add('hidden');
  const pb = document.getElementById('preview-bill');
  pb.innerHTML = fullHtml;
  pb.classList.remove('hidden');
  pb.classList.add('fade-in');

  const btn = document.getElementById('generateBtn');
  btn.innerHTML = '<i class="fas fa-check"></i> 已生成';
  btn.classList.replace('bg-primary','bg-green-600');
  setTimeout(()=>{btn.innerHTML='<i class="fas fa-magic"></i> 生成账单';btn.classList.replace('bg-green-600','bg-primary');},1500);

  } catch(err) {
    console.error('[枪王] generateBill ERROR:', err);
    // Still try to show template with placeholders so user sees something
    let html = TEMPLATES[currentBillTypeId]?.html_template || '';
    const css = TEMPLATES[currentBillTypeId]?.css_template || '';
    const fullHtml = (css ? '<style>'+css+'</style>' : '') + html;
    document.getElementById('preview-default').classList.add('hidden');
    const pb = document.getElementById('preview-bill');
    pb.innerHTML = fullHtml;
    pb.classList.remove('hidden');
    // Show error overlay
    pb.innerHTML = '<div style="background:#ffebee;border:2px solid #e53935;padding:12px;border-radius:8px;margin-bottom:12px;font-size:13px;color:#b71c1c;"><strong>⚠️ JS错误:</strong> ' + err.message + '</div>' + pb.innerHTML;
  }
}

// ─── PDF ───
async function downloadPDF() {
  const billEl = document.getElementById('preview-bill');
  if (billEl.classList.contains('hidden')) { alert('请先生成账单'); return; }
  const btn = document.getElementById('downloadBtn');
  const orig = btn.innerHTML;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 生成中...';
  btn.disabled = true;
  try {
    // 临时设置全局白底
    const origBillBg = billEl.style.background;
    billEl.style.background = '#ffffff';

    // 1. 将外部图片转为 base64
    const images = billEl.querySelectorAll('img[src^="http"]');
    const imagePromises = [];
    images.forEach(img => {
      if (!img.src.startsWith('data:')) {
        imagePromises.push(new Promise(resolve => {
          try {
            const cv = document.createElement('canvas');
            const ctx = cv.getContext('2d');
            const tempImg = new Image();
            tempImg.crossOrigin = 'anonymous';
            tempImg.onload = function() {
              cv.width = tempImg.naturalWidth;
              cv.height = tempImg.naturalHeight;
              ctx.drawImage(tempImg, 0, 0);
              try { img.src = cv.toDataURL('image/png'); } catch(e) {}
              resolve();
            };
            tempImg.onerror = function() { resolve(); };
            tempImg.src = img.src;
          } catch(e) { resolve(); }
        }));
      }
    });

    // 2. 将 SVG 印章转为 base64
    const stampSvgs = billEl.querySelectorAll('.cmb-stamp-svg');
    stampSvgs.forEach(svg => {
      imagePromises.push(new Promise(resolve => {
        try {
          const svgData = new XMLSerializer().serializeToString(svg);
          const svgBlob = new Blob([svgData], {type:'image/svg+xml;charset=utf-8'});
          const url = URL.createObjectURL(svgBlob);
          const img = new Image();
          img.onload = function() {
            const cv = document.createElement('canvas');
            const scale = 2;
            cv.width = img.width * scale;
            cv.height = img.height * scale;
            const ctx = cv.getContext('2d');
            ctx.scale(scale, scale);
            ctx.drawImage(img, 0, 0);
            try {
              const dataUrl = cv.toDataURL('image/png');
              const parent = svg.parentNode;
              const imgEl = document.createElement('img');
              imgEl.src = dataUrl;
              imgEl.width = svg.getAttribute('width') || 260;
              imgEl.height = svg.getAttribute('height') || 170;
              imgEl.style.cssText = svg.style.cssText;
              parent.replaceChild(imgEl, svg);
            } catch(e) { /* fallback */ }
            URL.revokeObjectURL(url);
            resolve();
          };
          img.onerror = function() { URL.revokeObjectURL(url); resolve(); };
          img.src = url;
        } catch(e) { resolve(); }
      }));
    });

    await Promise.all(imagePromises);

    // 3. 智能多页识别：查找所有 class="page" 的元素
    let pagesToRender = [];
    const pageNodes = billEl.querySelectorAll('.page');
    if (pageNodes.length > 0) {
        pagesToRender = Array.from(pageNodes);
    } else {
        // 修复1：精准捕获 .statement-page，防止 flex 布局导致截图失败报错
        const contentEl = billEl.querySelector('.statement-page') || billEl.querySelector('.monzo-stmt > div') || billEl.querySelector('.cmb-stmt') || billEl.querySelector('.kraken-wrap') || billEl.querySelector('.wise-wrap .document') || billEl.querySelector('.wise-wrap') || billEl.querySelector('.statement-container > div') || billEl.firstElementChild || billEl;
        pagesToRender = [contentEl];
    }

    const {jsPDF} = window.jspdf;
    const pdf = new jsPDF('p','mm','a4');
    const pw = pdf.internal.pageSize.getWidth();   // 210mm
    const ph = pdf.internal.pageSize.getHeight();  // 297mm
    const margin = 5;
    const fitW = pw - margin * 2;
    const fitH = ph - margin * 2;

    // 4. 逐页截图并写入 PDF
    for (let i = 0; i < pagesToRender.length; i++) {
        const el = pagesToRender[i];

        const origBg = el.style.background;
        el.style.background = '#ffffff';

        const canvas = await html2canvas(el, {
          scale: 2,
          useCORS: true,
          backgroundColor: '#ffffff',
          logging: false,
          removeContainer: true,
          imageTimeout: 15000,
          onclone: function(clonedDoc) {
            const clonedBill = clonedDoc.getElementById('preview-bill');
            if (clonedBill) clonedBill.style.background = '#ffffff';
            // 修复2：清除外层阴影，防止截图边缘有黑边
            const stmtEl = clonedBill?.querySelector('.monzo-stmt') || clonedBill?.querySelector('.cmb-stmt') || clonedBill?.querySelector('.wise-wrap') || clonedBill?.querySelector('.seabank-wrap');
            if (stmtEl) { stmtEl.style.background = '#ffffff'; stmtEl.style.boxShadow = 'none'; }
            clonedBill?.querySelectorAll('img').forEach(img => {
              img.style.display = 'inline';
              img.style.verticalAlign = 'middle';
            });
          }
        });

        el.style.background = origBg;

        const imgData = canvas.toDataURL('image/jpeg', 0.98);
        const imgW = pw;
        const imgH = (canvas.height * pw) / canvas.width;

        const scale = Math.min(fitW / imgW, fitH / imgH);
        const finalW = imgW * scale;
        const finalH = imgH * scale;
        const offsetX = (pw - finalW) / 2;
        const offsetY = margin;

        if (i > 0) pdf.addPage();
        pdf.addImage(imgData, 'JPEG', offsetX, offsetY, finalW, finalH);
    }

    billEl.style.background = origBillBg;

    // 5. 保存并下载
    const pdfName = isCMBType ? 'cmb-credit-card-statement' : (isMonzoType ? 'monzo-statement' : (isKrakenType ? 'kraken-statement' : (isWiseType ? 'wise-statement' : (isMoneseType ? 'monese-statement' : (isSeaBankType ? 'seabank-statement' : 'bill-statement')))));
    pdf.save(pdfName + '-' + Date.now() + '.pdf');
  } catch(e) { console.error(e); window.print(); }

  btn.innerHTML = orig; btn.disabled = false;
}

// ══════════════════════════════════
// 用户认证相关 JS
// ══════════════════════════════════
let authMode = 'login';
let userLoggedIn = <?=json_encode($userLoggedIn)?>;
let userCoinsUsdc = <?=json_encode($userCoinsUsdc)?>;
let userCoinsUsdt = <?=json_encode($userCoinsUsdt)?>;
let userDownloadCredits = <?=json_encode($userDownloadCredits)?>;
let topUpCoinType = 'USDC';  // 当前选中的充值币种

function showAuthModal(mode) {
  authMode = mode;
  const modal = document.getElementById('authModal');
  const title = document.getElementById('authModalTitle');
  const submitBtn = document.getElementById('authSubmitBtn');
  const switchText = document.getElementById('authSwitchText');
  const switchLink = document.getElementById('authSwitchLink');
  const bonusTip = document.getElementById('registerBonusTip');
  const errorEl = document.getElementById('authError');

  errorEl.classList.add('hidden');
  document.getElementById('authUsername').value = '';
  document.getElementById('authPassword').value = '';

  if (mode === 'login') {
    title.innerHTML = '<i class="fas fa-sign-in-alt mr-2"></i>登录';
    submitBtn.innerHTML = '<i class="fas fa-sign-in-alt mr-2"></i>登录';
    switchText.textContent = '没有账号？';
    switchLink.textContent = '立即注册';
    bonusTip.classList.add('hidden');
  } else {
    title.innerHTML = '<i class="fas fa-user-plus mr-2"></i>注册';
    submitBtn.innerHTML = '<i class="fas fa-user-plus mr-2"></i>注册';
    switchText.textContent = '已有账号？';
    switchLink.textContent = '去登录';
    bonusTip.classList.remove('hidden');
  }
  modal.classList.remove('hidden');
  modal.classList.add('flex');
  document.getElementById('authUsername').focus();
}

function hideAuthModal() {
  document.getElementById('authModal').classList.add('hidden');
  document.getElementById('authModal').classList.remove('flex');
}

function toggleAuthMode(e) {
  e.preventDefault();
  showAuthModal(authMode === 'login' ? 'register' : 'login');
}

async function handleAuthSubmit(e) {
  e.preventDefault();
  const username = document.getElementById('authUsername').value.trim();
  const password = document.getElementById('authPassword').value.trim();
  const errorEl = document.getElementById('authError');
  const submitBtn = document.getElementById('authSubmitBtn');
  const origHtml = submitBtn.innerHTML;

  if (!username || !password) return;
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 处理中...';
  submitBtn.disabled = true;
  errorEl.classList.add('hidden');

  try {
    const action = authMode === 'login' ? 'user_login' : 'user_register';
    const resp = await fetch(`api.php?action=${action}`, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({username, password})
    });
    const data = await resp.json();

    if (data.ok) {
      userLoggedIn = true;
      userCoinsUsdc = data.coins_usdc || 0;
      userCoinsUsdt = data.coins_usdt || 0;
      userDownloadCredits = data.download_credits || 0;
      updateUserUI(data.username, userCoinsUsdc, userCoinsUsdt);
      hideAuthModal();
      if (authMode === 'register') {
        showToast('🎉 注册成功！赠送 ' + (data.coins_usdc || 0) + ' USDC', 'ok');
      } else {
        showToast('✅ 登录成功', 'ok');
      }
    } else {
      errorEl.textContent = data.error || '操作失败';
      errorEl.classList.remove('hidden');
    }
  } catch(err) {
    errorEl.textContent = '网络错误，请重试';
    errorEl.classList.remove('hidden');
  }
  submitBtn.innerHTML = origHtml;
  submitBtn.disabled = false;
}

async function doUserLogout() {
  try {
    await fetch('api.php?action=user_logout');
    userLoggedIn = false;
    userCoinsUsdc = 0;
    userCoinsUsdt = 0;
    updateUserUI(null, 0, 0);
    showToast('已退出登录', 'ok');
  } catch(e) { console.error(e); }
}

function updateUserUI(username, usdc, usdt) {
  const area = document.getElementById('userArea');
  if (userLoggedIn && username) {
    area.innerHTML = `
      <span class="text-sm text-gray-600">👤 <strong>${escapeHtml(username)}</strong></span>
      <span class="text-xs bg-blue-100 text-blue-700 px-3 py-1.5 rounded-full font-semibold">剩余下载次数：<span id="headerCredits">${(typeof userDownloadCredits!=='undefined')?userDownloadCredits:0}</span> 次</span>
      <button onclick="showRedeemModal()" class="text-xs px-3 py-1.5 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition font-medium shadow-sm"><i class="fas fa-gift mr-1"></i>兑换福利码</button>
      <button onclick="doUserLogout()" class="text-xs px-2.5 py-1.5 border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 transition"><i class="fas fa-sign-out-alt"></i></button>
    `;
  } else {
    area.innerHTML = `
      <button onclick="showAuthModal('login')" class="text-sm px-3 py-1.5 bg-primary text-white rounded-lg hover:bg-primary-dark transition font-medium"><i class="fas fa-sign-in-alt mr-1"></i>登录</button>
      <button onclick="showAuthModal('register')" class="text-sm px-3 py-1.5 border border-primary text-primary rounded-lg hover:bg-primary/5 transition font-medium"><i class="fas fa-user-plus mr-1"></i>注册</button>
    `;
  }
}

// ══════════════════════════════════
// 充值相关 JS
// ══════════════════════════════════
let coinPackages = [];
let coinNetworks = {};

async function loadPackages() {
  try {
    const resp = await fetch('api.php?action=packages&coin_type=' + topUpCoinType);
    const data = await resp.json();
    if (data.ok) {
      coinPackages = data.packages;
      coinNetworks = data.networks || {};
    }
  } catch(e) { console.error(e); }
}

function switchTopUpCoin(coin) {
  topUpCoinType = coin;
  document.querySelectorAll('.topup-coin-tab').forEach(b => {
    b.classList.toggle('bg-blue-600', b.dataset.coin === coin);
    b.classList.toggle('text-white', b.dataset.coin === coin);
    b.classList.toggle('bg-gray-100', b.dataset.coin !== coin);
    b.classList.toggle('text-gray-600', b.dataset.coin !== coin);
  });
  loadPackages().then(() => renderPackageList());
}

function renderPackageList() {
  const pkgList = document.getElementById('packageList');
  if (!pkgList) return;
  pkgList.innerHTML = coinPackages.map(pkg => `
    <button onclick="doTopUp('${pkg.id}')" class="p-4 border-2 border-gray-200 rounded-xl hover:border-primary hover:bg-blue-50/50 transition-all text-left relative">
      ${pkg.badge ? `<span class="absolute -top-2 -right-2 text-xs bg-red-500 text-white px-2 py-0.5 rounded-full font-bold">${pkg.badge}</span>` : ''}
      <div class="text-lg font-bold text-gray-800">${pkg.name}</div>
      <div class="text-xs text-gray-400 mt-1">${pkg.price_desc}</div>
      <div class="text-sm text-primary font-semibold mt-2">${pkg.price}</div>
    </button>
  `).join('');
}

function showTopUpModal() {
  if (!userLoggedIn) {
    showAuthModal('login');
    return;
  }
  const modal = document.getElementById('topUpModal');
  document.getElementById('topUpCurrentUsdc').textContent = userCoinsUsdc;
  document.getElementById('topUpCurrentUsdt').textContent = userCoinsUsdt;
  document.getElementById('topUpMsg').classList.add('hidden');
  renderPackageList();
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function hideTopUpModal() {
  document.getElementById('topUpModal').classList.add('hidden');
  document.getElementById('topUpModal').classList.remove('flex');
}

async function doTopUp(packageId) {
  const msgEl = document.getElementById('topUpMsg');
  if (msgEl) {
    msgEl.classList.remove('hidden');
    msgEl.className = 'mb-3 p-3 rounded-lg text-sm bg-blue-50 text-blue-700';
    msgEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 创建订单中...';
  }

  try {
    const resp = await fetch('api.php?action=user_topup', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({package_id: packageId, coin_type: topUpCoinType, network: 'ERC20'})
    });
    const data = await resp.json();
    if (data.ok) {
      if (msgEl) msgEl.classList.add('hidden');
      window._pendingOrder = {
        order_id: data.order_id,
        coin_type: data.coin_type,
        package: data.package,
        coin_amount: data.coin_amount,
        price: data.price,
        network: data.network,
        tx_hash: data.tx_hash
      };
      showPayModal(window._pendingOrder);
    } else {
      if (msgEl) {
        msgEl.className = 'mb-3 p-3 rounded-lg text-sm bg-red-50 text-red-700';
        msgEl.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i> ' + (data.error || '创建订单失败');
      }
    }
  } catch(e) {
    if (msgEl) {
      msgEl.className = 'mb-3 p-3 rounded-lg text-sm bg-red-50 text-red-700';
      msgEl.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i> 网络错误，请重试';
    }
  }
}

function updateCoinsDisplay(usdc, usdt) {
  const elUsdc = document.getElementById('headerCoinsUsdc');
  if (elUsdc) elUsdc.textContent = usdc;
  const elUsdt = document.getElementById('headerCoinsUsdt');
  if (elUsdt) elUsdt.textContent = usdt;
  const tUsdc = document.getElementById('topUpCurrentUsdc');
  if (tUsdc) tUsdc.textContent = usdc;
  const tUsdt = document.getElementById('topUpCurrentUsdt');
  if (tUsdt) tUsdt.textContent = usdt;
}

// ══════════════════════════════════
// 模拟支付页面 JS
// ══════════════════════════════════
let payTimer = null;
// 模拟收款地址
const RECEIVE_ADDRESSES = {
  USDC_ERC20: '0x7AeC...f3B2Dc1a',
  USDC_TRC20: 'TXk8...Pq7nWm3',
  USDC_BEP20: '0x9Fd2...c8E1Aa4b',
  USDC_SOL: 'DRw...uVkXp',
  USDT_ERC20: '0x3BfA...d9C7Ee2f',
  USDT_TRC20: 'TYm5...Rj2sNk8',
  USDT_BEP20: '0x1Cc8...a6F3Bb9d',
  USDT_SOL: '5Hw...jLqRt',
};

function showPayModal(order) {
  document.getElementById('payOrderId').textContent = '#' + order.order_id;
  document.getElementById('payCoinType').textContent = order.coin_type || 'USDC';
  document.getElementById('payCoins').textContent = order.coin_amount + ' ' + (order.coin_type || 'USDC');
  document.getElementById('payAmount').textContent = (order.package ? order.package.price : ('$ ' + (order.price || 0).toFixed(2)));
  document.getElementById('payNetwork').textContent = order.network || 'ERC20';
  const addrKey = (order.coin_type || 'USDC') + '_' + (order.network || 'ERC20');
  document.getElementById('payToAddress').textContent = RECEIVE_ADDRESSES[addrKey] || '0x7AeC58D2...f3B2Dc1a';
  document.getElementById('payGasFee').textContent = '~ $' + (order.network === 'SOL' ? '0.01' : (order.network === 'TRC20' ? '0.50' : '2.50'));
  document.getElementById('payStatus').classList.add('hidden');
  document.getElementById('payProgress').classList.add('hidden');
  document.getElementById('payConfirmBtn').disabled = false;
  document.getElementById('payConfirmBtn').innerHTML = '<i class="fas fa-paper-plane"></i> 确认转账';

  const modal = document.getElementById('payModal');
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function hidePayModal() {
  if (payTimer) { clearTimeout(payTimer); payTimer = null; }
  document.getElementById('payModal').classList.add('hidden');
  document.getElementById('payModal').classList.remove('flex');
}

async function confirmPay() {
  const order = window._pendingOrder;
  if (!order) return;

  const btn = document.getElementById('payConfirmBtn');
  const statusEl = document.getElementById('payStatus');
  const progressEl = document.getElementById('payProgress');
  const coinType = order.coin_type || 'USDC';

  progressEl.classList.remove('hidden');
  statusEl.classList.add('hidden');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 处理中...';

  // Step 1: 发起转账
  await stepDelay(800);
  updateStep(1, 'fa-spinner fa-spin text-blue-500', 'text-blue-600', '广播转账交易到 ' + order.network + ' 网络...');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 等待确认...';

  // Step 2: 等待确认
  await stepDelay(1200);
  updateStep(1, 'fas fa-check-circle text-green-500', 'text-green-600', '交易已提交，TxHash: ' + (order.tx_hash || '0x...').substring(0, 18) + '...');
  updateStep(2, 'fa-spinner fa-spin text-blue-500', 'text-blue-600', '等待区块链节点确认...');

  // Step 3: 区块确认
  for (let i = 1; i <= 12; i++) {
    await stepDelay(300 + Math.random() * 200);
    updateStep(2, 'fas fa-check-circle text-green-500', 'text-green-600', '交易已在 ' + order.network + ' 网络广播');
    updateStep(3, 'fa-spinner fa-spin text-blue-500', 'text-blue-600', '区块确认中 (' + i + '/12)...');
  }
  updateStep(3, 'fas fa-check-circle text-green-500', 'text-green-600', '已获得 12 个区块确认 ✓');

  // Step 4: 调用后端完成支付
  await stepDelay(500);
  updateStep(4, 'fa-spinner fa-spin text-blue-500', 'text-blue-600', '验证链上交易并到账...');

  try {
    const resp = await fetch('api.php?action=user_pay', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({order_id: order.order_id})
    });
    const data = await resp.json();
    if (data.ok) {
      if (coinType === 'USDT') {
        userCoinsUsdt = data.coins || 0;
      } else {
        userCoinsUsdc = data.coins || 0;
      }
      updateCoinsDisplay(userCoinsUsdc, userCoinsUsdt);
      updateStep(4, 'fas fa-check-circle text-green-500', 'text-green-600', '转账成功！已到账 ✓');

      statusEl.classList.remove('hidden');
      statusEl.className = 'mb-4 p-3 rounded-lg text-sm text-center bg-green-50 text-green-700';
      statusEl.innerHTML = '<i class="fas fa-check-circle mr-1"></i> ✅ ' + data.message;
      btn.innerHTML = '<i class="fas fa-check-circle"></i> 转账完成';
      btn.className = btn.className.replace('bg-gray-900 hover:bg-gray-800', 'bg-gray-400');

      await stepDelay(1800);
      hidePayModal();
      hideTopUpModal();
      showToast('✅ ' + data.message, 'ok');
    } else {
      updateStep(4, 'fas fa-times-circle text-red-500', 'text-red-600', '交易失败');
      statusEl.classList.remove('hidden');
      statusEl.className = 'mb-4 p-3 rounded-lg text-sm text-center bg-red-50 text-red-700';
      statusEl.innerHTML = '<i class="fas fa-times-circle mr-1"></i> ' + (data.error || '交易失败');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-redo mr-1"></i> 重试转账';
    }
  } catch(e) {
    updateStep(4, 'fas fa-times-circle text-red-500', 'text-red-600', '网络错误');
    statusEl.classList.remove('hidden');
    statusEl.className = 'mb-4 p-3 rounded-lg text-sm text-center bg-red-50 text-red-700';
    statusEl.innerHTML = '<i class="fas fa-times-circle mr-1"></i> 网络错误，请重试';
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-redo mr-1"></i> 重试转账';
  }
}

function stepDelay(ms) {
  return new Promise(r => { payTimer = setTimeout(r, ms); });
}

function updateStep(num, iconClass, textClass, text) {
  const icon = document.getElementById('step' + num + 'Icon');
  const textEl = document.getElementById('step' + num + 'Text');
  if (icon) { icon.className = iconClass; icon.classList.add('text-[10px]'); }
  if (textEl) { textEl.className = textClass; textEl.textContent = text; }
}

// ══════════════════════════════════
// ══════════════════════════════════
// 带认证的高清图片下载流程
// ══════════════════════════════════
async function doDownloadImageWithAuth() {
  const billEl = document.getElementById('preview-bill');
  if (billEl.classList.contains('hidden')) {
    showToast('请先生成账单', 'err');
    return;
  }

  if (!userLoggedIn) {
    showAuthModal('login');
    return;
  }

  try {
    const resp = await fetch('api.php?action=deduct_coin', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({bill_type_id: currentBillTypeId})
    });
    const data = await resp.json();
    if (!data.ok) {
      if (data.need_topup) {
        showToast('下载次数不足，请输入博主福利码兑换次数！', 'err');
        showRedeemModal();
      } else {
        showToast(data.error || '扣费失败', 'err');
      }
      return;
    }
    userDownloadCredits = data.credits || 0;
    document.getElementById('headerCredits').textContent = userDownloadCredits;
    showToast('✅ 已消耗 1 次下载机会，剩余 ' + userDownloadCredits + ' 次', 'ok');

    // 扣费成功后执行下载图片
    await downloadImage();
  } catch(e) {
    console.error(e);
    showToast('网络错误，请重试', 'err');
  }
}

// ─── 核心：生成并下载高清图片 ───
async function downloadImage() {
  const billEl = document.getElementById('preview-bill');
  if (billEl.classList.contains('hidden')) { alert('请先生成账单'); return; }
  const btn = document.getElementById('downloadImgBtn');
  const orig = btn.innerHTML;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 生成中...';
  btn.disabled = true;

  try {
    const origBillBg = billEl.style.background;
    billEl.style.background = '#ffffff';

    const images = billEl.querySelectorAll('img[src^="http"]');
    const imagePromises = [];
    images.forEach(img => {
      if (!img.src.startsWith('data:')) {
        imagePromises.push(new Promise(resolve => {
          try {
            const cv = document.createElement('canvas');
            const ctx = cv.getContext('2d');
            const tempImg = new Image();
            tempImg.crossOrigin = 'anonymous';
            tempImg.onload = function() {
              cv.width = tempImg.naturalWidth;
              cv.height = tempImg.naturalHeight;
              ctx.drawImage(tempImg, 0, 0);
              try { img.src = cv.toDataURL('image/png'); } catch(e) {}
              resolve();
            };
            tempImg.onerror = function() { resolve(); };
            tempImg.src = img.src;
          } catch(e) { resolve(); }
        }));
      }
    });

    const stampSvgs = billEl.querySelectorAll('.cmb-stamp-svg');
    stampSvgs.forEach(svg => {
      imagePromises.push(new Promise(resolve => {
        try {
          const svgData = new XMLSerializer().serializeToString(svg);
          const svgBlob = new Blob([svgData], {type:'image/svg+xml;charset=utf-8'});
          const url = URL.createObjectURL(svgBlob);
          const img = new Image();
          img.onload = function() {
            const cv = document.createElement('canvas');
            const scale = 2;
            cv.width = img.width * scale;
            cv.height = img.height * scale;
            const ctx = cv.getContext('2d');
            ctx.scale(scale, scale);
            ctx.drawImage(img, 0, 0);
            try {
              const dataUrl = cv.toDataURL('image/png');
              const parent = svg.parentNode;
              const imgEl = document.createElement('img');
              imgEl.src = dataUrl;
              imgEl.width = svg.getAttribute('width') || 260;
              imgEl.height = svg.getAttribute('height') || 170;
              imgEl.style.cssText = svg.style.cssText;
              parent.replaceChild(imgEl, svg);
            } catch(e) { }
            URL.revokeObjectURL(url);
            resolve();
          };
          img.onerror = function() { URL.revokeObjectURL(url); resolve(); };
          img.src = url;
        } catch(e) { resolve(); }
      }));
    });

    await Promise.all(imagePromises);

    let pagesToRender = [];
    const pageNodes = billEl.querySelectorAll('.page');
    if (pageNodes.length > 0) {
        pagesToRender = Array.from(pageNodes);
    } else {
        // 修复1：精准捕获 .statement-page，防止 flex 布局导致截图失败报错
        const contentEl = billEl.querySelector('.statement-page') || billEl.querySelector('.monzo-stmt > div') || billEl.querySelector('.cmb-stmt') || billEl.querySelector('.kraken-wrap') || billEl.querySelector('.wise-wrap .document') || billEl.querySelector('.wise-wrap') || billEl.querySelector('.statement-container > div') || billEl.firstElementChild || billEl;
        pagesToRender = [contentEl];
    }

    const canvasArray = [];
    for (let i = 0; i < pagesToRender.length; i++) {
        const el = pagesToRender[i];
        const origBg = el.style.background;
        el.style.background = '#ffffff';

        const canvas = await html2canvas(el, {
          scale: 3, // 高清倍率
          useCORS: true,
          backgroundColor: '#ffffff',
          logging: false,
          removeContainer: true,
          imageTimeout: 15000,
          onclone: function(clonedDoc) {
            const clonedBill = clonedDoc.getElementById('preview-bill');
            if (clonedBill) clonedBill.style.background = '#ffffff';
            // 修复2：清除外围阴影
            const stmtEl = clonedBill?.querySelector('.monzo-stmt') || clonedBill?.querySelector('.cmb-stmt') || clonedBill?.querySelector('.wise-wrap') || clonedBill?.querySelector('.seabank-wrap');
            if (stmtEl) { stmtEl.style.background = '#ffffff'; stmtEl.style.boxShadow = 'none'; }
            clonedBill?.querySelectorAll('img').forEach(img => {
              img.style.display = 'inline';
              img.style.verticalAlign = 'middle';
            });
          }
        });
        el.style.background = origBg;
        canvasArray.push(canvas);
    }

    billEl.style.background = origBillBg;
    const fileNameBase = isCMBType ? 'cmb-statement' : (isMonzoType ? 'monzo-statement' : (isKrakenType ? 'kraken-statement' : (isWiseType ? 'wise-statement' : (isMoneseType ? 'monese-statement' : (isSeaBankType ? 'seabank-statement' : 'bill-statement')))));

    if (canvasArray.length === 1) {
        const imgData = canvasArray[0].toDataURL('image/png', 1.0);
        // 修复3：通过 fetch 转换为 blob 再保存，彻底解决 Base64 过长导致的图片损坏问题
        fetch(imgData).then(res => res.blob()).then(blob => {
            saveAs(blob, fileNameBase + '-' + Date.now() + '.png');
        });
    } else {
        const zip = new JSZip();
        canvasArray.forEach((cv, idx) => {
            const imgData = cv.toDataURL('image/png', 1.0);
            const base64Data = imgData.split(',')[1];
            zip.file(`${fileNameBase}-page${idx + 1}.png`, base64Data, {base64: true});
        });
        const content = await zip.generateAsync({type:"blob"});
        saveAs(content, fileNameBase + '-' + Date.now() + '.zip');
    }

  } catch(e) {
    console.error(e);
    alert('生成高清图片失败，请重试');
  }

  btn.innerHTML = orig; btn.disabled = false;
}

// 带认证的下载流程
// ══════════════════════════════════
async function doDownloadWithAuth() {
  const billEl = document.getElementById('preview-bill');
  if (billEl.classList.contains('hidden')) {
    showToast('请先生成账单', 'err');
    return;
  }

  // 未登录：引导登录
  if (!userLoggedIn) {
    showAuthModal('login');
    return;
  }

  // 扣费
  try {
    const resp = await fetch('api.php?action=deduct_coin', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({bill_type_id: currentBillTypeId})
    });
    const data = await resp.json();
    if (!data.ok) {
      if (data.need_topup) {
        showToast('下载次数不足，请输入博主福利码兑换次数！', 'err');
        showRedeemModal();
      } else {
        showToast(data.error || '扣费失败', 'err');
      }
      return;
    }
    // 扣费成功，更新次数并下载
    userDownloadCredits = data.credits || 0;
    document.getElementById('headerCredits').textContent = userDownloadCredits;
    showToast('✅ 已消耗 1 次下载机会，剩余 ' + userDownloadCredits + ' 次', 'ok');

    // 执行真正的 PDF 下载
    await downloadPDF();
  } catch(e) {
    console.error(e);
    showToast('网络错误，请重试', 'err');
  }
}

// ══════════════════════════════════
// 通用提示 Toast
// ══════════════════════════════════
function showToast(msg, type) {
  const existing = document.querySelector('.toast-tip');
  if (existing) existing.remove();

  const toast = document.createElement('div');
  toast.className = 'toast-tip toast-' + (type === 'ok' ? 'ok' : 'err');
  toast.textContent = msg;
  toast.style.cssText = `position:fixed;top:20px;right:20px;padding:12px 20px;border-radius:8px;color:#fff;font-size:14px;z-index:99999;animation:slideIn .3s ease;${type==='ok'?'background:#43a047':'background:#e53935'}`;
  document.body.appendChild(toast);
  setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(() => toast.remove(), 300); }, 2500);
}

// ─── 预览水印 ───
function renderWatermark() {
    const wm = document.getElementById('preview-watermark');
    const bill = document.getElementById('preview-bill');
    if (!wm || !bill || bill.classList.contains('hidden')) { wm && (wm.style.display = 'none'); return; }
    const container = document.getElementById('previewContainer');
    const rect = bill.getBoundingClientRect();
    const cRect = container.getBoundingClientRect();
    const w = Math.max(rect.width, cRect.width);
    const h = Math.max(rect.height, cRect.height);
    wm.style.display = 'block';
    wm.style.width = w + 'px';
    wm.style.height = h + 'px';
    wm.innerHTML = '';
    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    wm.appendChild(canvas);
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#000';
    ctx.font = '18px "Noto Sans SC","Microsoft YaHei",sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    const txt = 'TG枪王模版免费生成';
    const cw = ctx.measureText(txt).width;
    const stepX = cw + 180;
    const stepY = 80;
    const angle = -18 * Math.PI / 180;
    for (let y = -h; y < h * 2; y += stepY) {
        for (let x = -w; x < w * 2; x += stepX) {
            ctx.save();
            ctx.translate(x, y);
            ctx.rotate(angle);
            ctx.fillText(txt, 0, 0);
            ctx.restore();
        }
    }
}

// ─── 通用：确保生成账单时刷新水印 ───
const __orig_generateBill = (function(){}).constructor; // no-op, placeholder
function showBillWithWatermark(html) {
    const pb = document.getElementById('preview-bill');
    document.getElementById('preview-default').classList.add('hidden');
    pb.innerHTML = html;
    pb.classList.remove('hidden');
    pb.classList.add('fade-in');
    // 等 DOM 渲染后描水印
    requestAnimationFrame(() => requestAnimationFrame(renderWatermark));
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

// 控制兑换弹窗
function showRedeemModal() {
    const m = document.getElementById('redeemModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function hideRedeemModal() {
    const m = document.getElementById('redeemModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

// 控制博主申请弹窗
function showBloggerApplyModal() {
    const m = document.getElementById('bloggerApplyModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function hideBloggerApplyModal() {
    const m = document.getElementById('bloggerApplyModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

// 页面加载时获取套餐和用户信息
document.addEventListener('DOMContentLoaded', () => {
  loadPackages();
  if (userLoggedIn) {
    refreshUserInfo();
  }
});

async function refreshUserInfo() {
  try {
    const resp = await fetch('api.php?action=user_info');
    const data = await resp.json();
    if (data.ok && data.logged_in) {
      userLoggedIn = true;
      userCoinsUsdc = data.user ? (data.user.coins_usdc || 0) : userCoinsUsdc;
      userCoinsUsdt = data.user ? (data.user.coins_usdt || 0) : userCoinsUsdt;
      userDownloadCredits = data.user ? (data.user.download_credits || 0) : userDownloadCredits;
      updateCoinsDisplay(userCoinsUsdc, userCoinsUsdt);
    } else if (!data.ok || !data.logged_in) {
      userLoggedIn = false;
      userCoinsUsdc = 0;
      userCoinsUsdt = 0;
      userDownloadCredits = 0;
      updateUserUI(null, 0, 0);
    }
  } catch(e) { /* ignore */ }
}

// 提交粉丝兑换码
async function submitRedeemCode() {
    const code = document.getElementById('promoCodeInput').value;
    const r = await fetch('api.php?action=redeem_code', { method:'POST', body:JSON.stringify({code: code}) });
    const data = await r.json();
    if(data.ok) {
        showToast(data.message, 'ok');
        hideRedeemModal();
        // 动态刷新当前页面的次数
        document.getElementById('headerCredits').textContent = parseInt(document.getElementById('headerCredits').textContent) + data.added;
    } else {
        showToast(data.error, 'err');
    }
}

// 提交博主申请
async function submitBloggerApply() {
    const r = await fetch('api.php?action=blogger_apply', { method:'POST', body:JSON.stringify({
        username: document.getElementById('applyUser').value,
        password: document.getElementById('applyPwd').value,
        link: document.getElementById('applyLink').value,
        contact: document.getElementById('applyContact').value
    }) });
    const data = await r.json();
    if(data.ok) {
        alert('提交成功！我们将尽快审核。审核通过后您可进入 /blogger.php 登录专属后台。');
        hideBloggerApplyModal();
    } else {
        showToast(data.error, 'err');
    }
}
</script>

<!-- 兑换福利码弹窗 -->
<div id="redeemModal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/50">
  <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm">
    <div class="text-center mb-4">
      <div class="w-12 h-12 rounded-xl bg-orange-100 text-orange-500 flex items-center justify-center mx-auto mb-2"><i class="fas fa-gift text-2xl"></i></div>
      <h2 class="text-xl font-bold">兑换博主福利码</h2>
      <p class="text-xs text-gray-500">输入博主发放的福利码获取免费下载次数</p>
    </div>
    <input type="text" id="promoCodeInput" class="w-full form-input uppercase text-center text-lg font-bold tracking-widest mb-4" placeholder="VIP888" />
    <div class="flex gap-2">
      <button onclick="hideRedeemModal()" class="flex-1 py-3 border rounded-xl text-gray-600 font-medium">取消</button>
      <button onclick="submitRedeemCode()" class="flex-1 py-3 bg-orange-500 text-white rounded-xl font-bold hover:bg-orange-600 shadow-md">立即兑换</button>
    </div>
  </div>
</div>

<!-- 博主申请表单弹窗 -->
<div id="bloggerApplyModal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/50">
  <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md">
    <h2 class="text-xl font-bold text-center mb-4"><i class="fas fa-crown text-yellow-500 mr-2"></i>申请博主入驻</h2>
    <div class="space-y-3">
      <div><label class="block text-xs font-bold mb-1">您的频道/主页链接 (用于核实粉丝数)</label><input id="applyLink" type="url" class="form-input" placeholder="https://t.me/your_channel" /></div>
      <div><label class="block text-xs font-bold mb-1">联系方式 (TG或微信)</label><input id="applyContact" type="text" class="form-input" placeholder="TG: @username" /></div>
      <hr class="my-2 border-dashed">
      <div class="text-xs text-gray-500">请设置您的专属后台登录信息：</div>
      <div><label class="block text-xs font-bold mb-1">拟登录账号</label><input id="applyUser" type="text" class="form-input" placeholder="设置后台账号" /></div>
      <div><label class="block text-xs font-bold mb-1">拟登录密码</label><input id="applyPwd" type="password" class="form-input" placeholder="设置后台密码" /></div>
    </div>
    <div class="flex gap-2 mt-5">
      <button onclick="hideBloggerApplyModal()" class="flex-1 py-2.5 border rounded-xl text-gray-600">再想想</button>
      <button onclick="submitBloggerApply()" class="flex-1 py-2.5 bg-yellow-500 text-white rounded-xl font-bold hover:bg-yellow-600">提交申请</button>
    </div>
  </div>
</div>

</body>
</html>
