<?php
/**
 * 枪王 - 配置文件 v5 (MySQL/PDO)
 * PHP 8.0+ | MySQL 5.7+ / MariaDB 10.3+
 * ⚠️ 仅限学习演示用途
 */
declare(strict_types=1);

define('APP_ROOT', __DIR__);
define('SESSION_NAME', 'QIANGWANG_ADMIN');
define('USER_SESSION_NAME', 'QIANGWANG_USER');
define('DOWNLOAD_COST', 3);            // 每次下载消耗代币数量
define('REGISTER_BONUS', 9);          // 注册赠送代币数量（够下载3次）
define('SUPPORTED_COINS', ['USDC', 'USDT']);  // 支持的币种
define('DEFAULT_COIN', 'USDC');

// ─── MySQL 连接配置 (InfinityFree) ───
// 你需要在 vPanel 控制面板中创建 MySQL 数据库，然后将下面的值改为实际信息
// 数据库主机在 vPanel → Accounts → MySQL Databases → MySQL Host
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'mimicland');
define('DB_USER', 'root');
define('DB_PASS', '123456');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $db = null;
    if ($db === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $db = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        initDB($db);
    }
    return $db;
}

function initDB(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS regions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        flag_emoji VARCHAR(10) NOT NULL,
        sort_order INT DEFAULT 0,
        is_active TINYINT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $db->exec('CREATE TABLE IF NOT EXISTS bill_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        region_id INT NOT NULL,
        code VARCHAR(50) NOT NULL,
        name VARCHAR(200) NOT NULL,
        icon_class VARCHAR(50) NOT NULL DEFAULT "fa-file-invoice",
        unit VARCHAR(20) NOT NULL DEFAULT "m³",
        currency VARCHAR(10) NOT NULL DEFAULT "HK$",
        category VARCHAR(20) NOT NULL DEFAULT "utility" COMMENT "账单类别: utility=水电, bank=银行, credit_card=信用卡, crypto=加密货币",
        sort_order INT DEFAULT 0,
        is_active TINYINT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE CASCADE,
        UNIQUE KEY uq_region_code (region_id, code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $db->exec('CREATE TABLE IF NOT EXISTS templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bill_type_id INT NOT NULL UNIQUE,
        name VARCHAR(200) NOT NULL,
        html_template MEDIUMTEXT NOT NULL,
        css_template MEDIUMTEXT NOT NULL,
        js_template MEDIUMTEXT NOT NULL,
        placeholder_map MEDIUMTEXT NOT NULL,
        is_active TINYINT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (bill_type_id) REFERENCES bill_types(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(200) NOT NULL DEFAULT "",
        coins_usdc INT NOT NULL DEFAULT 0,
        coins_usdt INT NOT NULL DEFAULT 0,
        total_coins_usdc INT NOT NULL DEFAULT 0 COMMENT "累计充值USDC",
        total_coins_usdt INT NOT NULL DEFAULT 0 COMMENT "累计充值USDT",
        download_count INT NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $db->exec('CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        coin_type VARCHAR(10) NOT NULL DEFAULT "USDC" COMMENT "币种: USDC/USDT",
        package_name VARCHAR(100) NOT NULL,
        coin_amount INT NOT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        network VARCHAR(20) NOT NULL DEFAULT "" COMMENT "链: ERC20/TRC20/BEP20/SOL",
        tx_hash VARCHAR(100) NOT NULL DEFAULT "" COMMENT "模拟交易哈希",
        status VARCHAR(20) NOT NULL DEFAULT "pending" COMMENT "pending=待支付, paid=已支付, cancelled=已取消",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        paid_at DATETIME DEFAULT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $db->exec('CREATE TABLE IF NOT EXISTS download_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        bill_type_id INT NOT NULL,
        coin_type VARCHAR(10) NOT NULL DEFAULT "USDC" COMMENT "消耗币种",
        coins_used INT NOT NULL DEFAULT 3,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $db->exec('CREATE TABLE IF NOT EXISTS site_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        config_key VARCHAR(50) NOT NULL UNIQUE,
        config_value MEDIUMTEXT NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $db->exec('CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $db->exec('CREATE TABLE IF NOT EXISTS bloggers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        channel_link VARCHAR(500) NOT NULL DEFAULT "",
        contact VARCHAR(200) NOT NULL DEFAULT "",
        status TINYINT NOT NULL DEFAULT 0 COMMENT "0=待审核,1=已通过,2=已驳回",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $db->exec('CREATE TABLE IF NOT EXISTS promo_codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        blogger_id INT NOT NULL,
        code VARCHAR(50) NOT NULL UNIQUE,
        credits_per_user INT NOT NULL DEFAULT 3,
        max_users INT NOT NULL DEFAULT 100,
        used_users INT NOT NULL DEFAULT 0,
        is_active TINYINT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (blogger_id) REFERENCES bloggers(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $db->exec('CREATE TABLE IF NOT EXISTS redemption_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        promo_code_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (promo_code_id) REFERENCES promo_codes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    seedDefaultData($db);
    migrateSchema($db);

    // 初始化默认管理员账号（始终执行）
    $adminCnt = $db->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    if ($adminCnt == 0) {
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $db->prepare('INSERT INTO admin_users (username, password) VALUES (:u, :p)')->execute([':u' => 'admin', ':p' => $hash]);
    } else {
        // 确保 admin 用户存在
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $db->prepare('INSERT IGNORE INTO admin_users (username, password) VALUES (:u, :p)')->execute([':u' => 'admin', ':p' => $hash]);
    }
}

function migrateSchema(PDO $db): void {
    // v1: 给已有 users 表增加 email 列
    try {
        $cols = $db->query('SHOW COLUMNS FROM users LIKE "email"')->fetchAll();
        if (empty($cols)) {
            $db->exec('ALTER TABLE users ADD COLUMN email VARCHAR(200) NOT NULL DEFAULT "" AFTER password');
        }
    } catch (Throwable $e) { /* users 表不存在则跳过 */ }

    // v2: coins → coins_usdc + coins_usdt (双币种改造)
    try {
        $cols = $db->query('SHOW COLUMNS FROM users LIKE "coins_usdc"')->fetchAll();
        if (empty($cols)) {
            // 把旧 coins 值迁移到 coins_usdc
            $hasCoins = $db->query('SHOW COLUMNS FROM users LIKE "coins"')->fetchAll();
            if (!empty($hasCoins)) {
                $db->exec('ALTER TABLE users CHANGE COLUMN coins coins_usdc INT NOT NULL DEFAULT 0');
            } else {
                $db->exec('ALTER TABLE users ADD COLUMN coins_usdc INT NOT NULL DEFAULT 0 AFTER email');
            }
        }
    } catch (Throwable $e) { /* skip */ }

    try {
        $cols = $db->query('SHOW COLUMNS FROM users LIKE "coins_usdt"')->fetchAll();
        if (empty($cols)) {
            $db->exec('ALTER TABLE users ADD COLUMN coins_usdt INT NOT NULL DEFAULT 0 AFTER coins_usdc');
        }
    } catch (Throwable $e) { /* skip */ }

    try {
        $cols = $db->query('SHOW COLUMNS FROM users LIKE "total_coins_usdc"')->fetchAll();
        if (empty($cols)) {
            $db->exec('ALTER TABLE users ADD COLUMN total_coins_usdc INT NOT NULL DEFAULT 0 AFTER coins_usdt');
        }
    } catch (Throwable $e) { /* skip */ }

    try {
        $cols = $db->query('SHOW COLUMNS FROM users LIKE "total_coins_usdt"')->fetchAll();
        if (empty($cols)) {
            $db->exec('ALTER TABLE users ADD COLUMN total_coins_usdt INT NOT NULL DEFAULT 0 AFTER total_coins_usdc');
        }
    } catch (Throwable $e) { /* skip */ }

    try {
        $cols = $db->query('SHOW COLUMNS FROM users LIKE "download_count"')->fetchAll();
        if (empty($cols)) {
            $db->exec('ALTER TABLE users ADD COLUMN download_count INT NOT NULL DEFAULT 0 AFTER total_coins_usdt');
        }
    } catch (Throwable $e) { /* skip */ }

    // orders 表加新字段
    try {
        $cols = $db->query('SHOW COLUMNS FROM orders LIKE "coin_type"')->fetchAll();
        if (empty($cols)) {
            $db->exec('ALTER TABLE orders ADD COLUMN coin_type VARCHAR(10) NOT NULL DEFAULT "USDC" AFTER user_id');
        }
    } catch (Throwable $e) { /* skip */ }

    try {
        $cols = $db->query('SHOW COLUMNS FROM orders LIKE "network"')->fetchAll();
        if (empty($cols)) {
            $db->exec('ALTER TABLE orders ADD COLUMN network VARCHAR(20) NOT NULL DEFAULT "" AFTER price');
        }
    } catch (Throwable $e) { /* skip */ }

    try {
        $cols = $db->query('SHOW COLUMNS FROM orders LIKE "tx_hash"')->fetchAll();
        if (empty($cols)) {
            $db->exec('ALTER TABLE orders ADD COLUMN tx_hash VARCHAR(100) NOT NULL DEFAULT "" AFTER network');
        }
    } catch (Throwable $e) { /* skip */ }

    // download_logs 加币种字段
    try {
        $cols = $db->query('SHOW COLUMNS FROM download_logs LIKE "coin_type"')->fetchAll();
        if (empty($cols)) {
            $db->exec('ALTER TABLE download_logs ADD COLUMN coin_type VARCHAR(10) NOT NULL DEFAULT "USDC" AFTER bill_type_id');
        }
    } catch (Throwable $e) { /* skip */ }

    // v4: users 表增加 download_credits 字段（博主福利码次数）
    try {
        $cols = $db->query('SHOW COLUMNS FROM users LIKE "download_credits"')->fetchAll();
        if (empty($cols)) {
            $db->exec('ALTER TABLE users ADD COLUMN download_credits INT NOT NULL DEFAULT 0 AFTER download_count');
        }
    } catch (Throwable $e) { /* skip */ }

    // v3: site_config 表（从旧版迁移）
    try {
        $tables = $db->query('SHOW TABLES LIKE "site_config"')->fetchAll();
        if (empty($tables)) {
            $db->exec('CREATE TABLE IF NOT EXISTS site_config (
                id INT AUTO_INCREMENT PRIMARY KEY,
                config_key VARCHAR(50) NOT NULL UNIQUE,
                config_value MEDIUMTEXT NOT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    } catch (Throwable $e) { /* skip */ }
}

function seedDefaultData(PDO $db): void {
    $cnt = $db->query('SELECT COUNT(*) FROM regions')->fetchColumn();
    if ($cnt > 0) return;

    // ─── 地区 ───
    $regions = [
        ['cn','中国','🇨🇳',0],['hk','香港','🇭🇰',1],
        ['au','澳洲','🇦🇺',2],['ca','加拿大','🇨🇦',3],
        ['sg','新加坡','🇸🇬',4],['gb','英国','🇬🇧',5],
        ['ph','菲律宾','🇵🇭',6],
    ];
    $st = $db->prepare('INSERT IGNORE INTO regions (code,name,flag_emoji,sort_order) VALUES (:c,:n,:f,:s)');
    foreach ($regions as $r) {
        $st->execute([':c'=>$r[0],':n'=>$r[1],':f'=>$r[2],':s'=>$r[3]]);
    }

    // ─── 账单类型 ───
    $billTypes = [
        ['cn','cn-cmb-credit','招商银行信用卡对账单','fa-credit-card','txn','￥','credit_card'],
        ['gb','gb-monzo','Monzo Bank Statement','fa-university','txn','£','bank'],
        ['gb','gb-kraken','Kraken Statement','fa-bitcoin-sign','txn','£','crypto'],
        ['gb','gb-wise','Wise GBP Statement (UK)','fa-university','txn','£','bank'],
        ['de','de-wise','Wise EUR Statement (DE)','fa-university','txn','€','bank'],
        ['de','de-monese','Monese EUR Statement (DE)','fa-university','txn','€','bank'],
        ['ph','ph-seabank','SeaBank Statement','fa-university','txn','₱','bank'],
    ];
    $st = $db->prepare('INSERT IGNORE INTO bill_types (region_id,code,name,icon_class,unit,currency,category,sort_order) SELECT r.id,:c,:n,:i,:u,:cur,:cat,:s FROM regions r WHERE r.code=:r');
    foreach ($billTypes as $i=>$bt) {
        $st->execute([':r'=>$bt[0],':c'=>$bt[1],':n'=>$bt[2],':i'=>$bt[3],':u'=>$bt[4],':cur'=>$bt[5],':cat'=>$bt[6],':s'=>$i+1]);
    }

    // ─── 占位符 ───
    $pm = json_encode([
        'customer_name','full_address','address_unit','address_street','address_district',
        'bill_number','account_number','period_start','period_end','issue_date',
        'consumption','meter_prev','meter_curr','meter_number','days','daily_litres',
        'currency','unit','bill_type_name',
        't1','t2','t3','t4','c1','c2','c3','c4',
        'water_charge','sewage_charge','total_due',
        'last_pay_date','last_pay_amt','deposit',
        'surcharge_date','after_surcharge','slip_ref','long_ref','crc_code',
        'bar_chart_svg','qr_code_svg','barcode_svg','bottom_barcode_svg',
        'bank_name','bank_name_en','account_type','statement_period',
        'opening_balance','closing_balance','total_credits','total_debits',
        'transactions',
        'card_number','credit_limit','available_credit','min_payment',
        'payment_due_date','new_charges','new_payments','interest_charge',
        'points_balance','previous_balance',
        'postal_code',
        'sort_code','bic','iban','country',
        'balance_pots','total_outgoings','total_deposits',
        'adjustment','card_last4','kraken_public_id',
        'statement_month','portfolio_table','balance_date',
        'wise_currency','wise_period_start','wise_period_end',
        'wise_generated_date','wise_balance_date','wise_balance',
        'wise_iban','wise_bic','wise_city','wise_state',
        'wise_postcode','wise_country','wise_ref','wise_transactions','wise_timezone',
    ], JSON_UNESCAPED_UNICODE);

    // ─── 各模板 ───
    $tplMap = [
        'cn-cmb-credit' => ['招商银行信用卡对账单模板', getCMBBankTemplate(), getCMBBankCSS()],
        'gb-monzo'  => ['Monzo Bank Statement Template', getGBMonzoTemplate(), getGBMonzoCSS()],
        'gb-kraken' => ['Kraken Statement Template', getGBKrakenTemplate(), ''],
        'gb-wise'   => ['Wise GBP Statement Template (UK)', getDEWiseBankTemplate(), getWiseBankCSS()],
        'de-wise'   => ['Wise EUR Statement Template (DE)', getDEWiseBankTemplate(), getWiseBankCSS()],
        'de-monese' => ['Monese EUR Statement Template (DE)', getDEMoneseTemplate(), getDEMoneseCSS()],
        'ph-seabank' => ['SeaBank Statement Template', getPHSeaBankTemplate(), getPHSeaBankCSS()],
    ];
    $st = $db->prepare('INSERT IGNORE INTO templates (bill_type_id,name,html_template,css_template,js_template,placeholder_map) SELECT bt.id,:n,:h,:c,:j,:m FROM bill_types bt WHERE bt.code=:code');
    foreach ($tplMap as $code => $t) {
        $st->execute([':code'=>$code,':n'=>$t[0],':h'=>$t[1],':c'=>$t[2],':j'=>'',':m'=>$pm]);
    }

    // ─── 公告默认值 ───
    $db->exec("INSERT IGNORE INTO site_config (config_key, config_value) VALUES ('hero_title', '枪王账单学习模版')");
    $db->exec("INSERT IGNORE INTO site_config (config_key, config_value) VALUES ('hero_content', '<p class=\"text-gray-600 max-w-2xl leading-relaxed\">多地区最新格式的水费单、电费单、燃气费单、话费单、银行流水单文档示例。<span class=\"text-orange-600 font-semibold\">仅限学习演示用途。</span></p>')");

    // ─── 默认管理员（首次自动创建，存在则跳过保留现有密码） ───
    $cnt = $db->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    if ($cnt == 0) {
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $db->prepare('INSERT INTO admin_users (username, password) VALUES (:u, :p)')->execute([':u' => 'admin', ':p' => $hash]);
    } else {
        // 确保 admin 用户至少存在（INSERT IGNORE 不会覆盖已有密码）
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $db->prepare('INSERT IGNORE INTO admin_users (username, password) VALUES (:u, :p)')->execute([':u' => 'admin', ':p' => $hash]);
    }
}

// ══════════════════════════════════
// 公告配置辅助函数
// ══════════════════════════════════
function getSiteConfig(PDO $db): array {
    $rows = $db->query('SELECT config_key, config_value FROM site_config')->fetchAll();
    $config = [];
    foreach ($rows as $row) {
        $config[$row['config_key']] = $row['config_value'];
    }
    return $config;
}

function saveSiteConfig(PDO $db, string $key, string $value): void {
    $st = $db->prepare('INSERT INTO site_config (config_key, config_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE config_value = :v2, updated_at = NOW()');
    $st->execute([':k' => $key, ':v' => $value, ':v2' => $value]);
}

// ══════════════════════════════════
// 中国银行流水单
// ══════════════════════════════════
function getCNBankTemplate(): string {
    return <<<'HTML'
<div class="cn-bank" style="width:210mm;max-width:100%;margin:0 auto;font-family:'SimSun','Noto Sans SC',serif;background:#fff;">
  <!-- 工行红 Header -->
  <div style="background:linear-gradient(135deg,#b71c1c,#c62828);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;">
    <div style="display:flex;align-items:center;gap:12px;">
      <div style="width:48px;height:48px;background:rgba(255,255,255,0.2);border-radius:8px;display:flex;align-items:center;justify-content:center;">
        <i class="fas fa-university" style="font-size:22px;"></i>
      </div>
      <div>
        <div style="font-size:18px;font-weight:700;letter-spacing:3px;">中国工商银行</div>
        <div style="font-size:10px;opacity:0.85;">ICBC Industrial and Commercial Bank of China</div>
      </div>
    </div>
    <div style="text-align:right;">
      <div style="font-size:15px;font-weight:700;">银行流水明细</div>
      <div style="font-size:9px;opacity:0.85;">BANK STATEMENT</div>
    </div>
  </div>

  <!-- 客户信息 -->
  <div style="padding:10px 20px;border-bottom:2px solid #b71c1c;">
    <table style="width:100%;font-size:12px;border-collapse:collapse;">
      <tr>
        <td style="width:14%;color:#888;padding:3px 0;">户名</td>
        <td style="width:36%;font-weight:700;padding:3px 0;">{{customer_name}}</td>
        <td style="width:14%;color:#888;padding:3px 0;">账号</td>
        <td style="width:36%;font-weight:700;padding:3px 0;">{{account_number}}</td>
      </tr>
      <tr>
        <td style="color:#888;padding:3px 0;">地址</td>
        <td colspan="3" style="font-weight:700;padding:3px 0;">{{full_address}}</td>
      </tr>
      <tr>
        <td style="color:#888;padding:3px 0;">账户类型</td>
        <td style="padding:3px 0;">{{account_type}}</td>
        <td style="color:#888;padding:3px 0;">流水期间</td>
        <td style="padding:3px 0;">{{statement_period}}</td>
      </tr>
    </table>
  </div>

  <!-- 余额摘要 -->
  <div style="padding:8px 20px;background:#fce4ec;">
    <table style="width:100%;font-size:12px;border-collapse:collapse;text-align:center;">
      <tr>
        <td style="width:25%;padding:4px;"><div style="font-size:10px;color:#888;">期初余额</div><div style="font-weight:700;font-size:15px;">{{currency}}{{opening_balance}}</div></td>
        <td style="width:25%;padding:4px;border-left:1px solid #e0e0e0;border-right:1px solid #e0e0e0;"><div style="font-size:10px;color:#2e7d32;">收入合计</div><div style="font-weight:700;font-size:15px;color:#2e7d32;">+{{currency}}{{total_credits}}</div></td>
        <td style="width:25%;padding:4px;border-right:1px solid #e0e0e0;"><div style="font-size:10px;color:#c62828;">支出合计</div><div style="font-weight:700;font-size:15px;color:#c62828;">-{{currency}}{{total_debits}}</div></td>
        <td style="width:25%;padding:4px;"><div style="font-size:10px;color:#888;">期末余额</div><div style="font-weight:700;font-size:15px;">{{currency}}{{closing_balance}}</div></td>
      </tr>
    </table>
  </div>

  <!-- 交易明细 -->
  <div style="padding:8px 16px 4px;">
    <div style="font-size:12px;font-weight:700;color:#b71c1c;border-bottom:1px solid #b71c1c;padding-bottom:2px;margin-bottom:6px;">◆ 交易明细 Transaction Details</div>
    <table class="bank-table" style="width:100%;font-size:11px;">
      <tr>
        <th style="width:14%;">日期</th>
        <th style="width:14%;">摘要</th>
        <th style="width:14%;">对方账号</th>
        <th style="width:12%;">支出(元)</th>
        <th style="width:12%;">收入(元)</th>
        <th style="width:14%;">余额(元)</th>
        <th style="width:20%;">备注</th>
      </tr>
      {{transactions}}
    </table>
  </div>

  <!-- 底部 -->
  <div style="padding:6px 20px;border-top:2px solid #b71c1c;font-size:9px;color:#999;text-align:center;line-height:1.8;">
    中国工商银行 · 客服热线 95588 · 本单据仅供参考，不作为正式凭证 · 打印日期：{{issue_date}}
  </div>
</div>
HTML;
}

function getCNBankCSS(): string {
    return <<<'CSS'
.cn-bank .bank-table{width:100%;border-collapse:collapse;font-size:11px;}
.cn-bank .bank-table th,.cn-bank .bank-table td{border:1px solid #ddd;padding:5px 6px;text-align:center;vertical-align:middle;}
.cn-bank .bank-table th{background:#f5f5f5;font-weight:600;font-size:10px;color:#555;}
.cn-bank .bank-table td.credit{color:#2e7d32;font-weight:600;}
.cn-bank .bank-table td.debit{color:#c62828;font-weight:600;}
CSS;
}

// ══════════════════════════════════
// 中国信用卡对账单
// ══════════════════════════════════
function getCNCreditCardTemplate(): string {
    return <<<'HTML'
<div class="cn-credit-card" style="width:210mm;max-width:100%;margin:0 auto;font-family:'SimSun','Noto Sans SC',serif;background:#fff;">
  <!-- Header -->
  <div style="background:linear-gradient(135deg,#1a237e,#283593);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;">
    <div style="display:flex;align-items:center;gap:12px;">
      <div style="width:48px;height:48px;background:rgba(255,255,255,0.15);border-radius:8px;display:flex;align-items:center;justify-content:center;">
        <i class="fas fa-credit-card" style="font-size:22px;"></i>
      </div>
      <div>
        <div style="font-size:18px;font-weight:700;letter-spacing:2px;">{{bank_name}}</div>
        <div style="font-size:10px;opacity:0.85;">{{bank_name_en}}</div>
      </div>
    </div>
    <div style="text-align:right;">
      <div style="font-size:15px;font-weight:700;">信用卡对账单</div>
      <div style="font-size:9px;opacity:0.85;">CREDIT CARD STATEMENT</div>
    </div>
  </div>

  <!-- 账单周期 & 卡号 -->
  <div style="background:#e8eaf6;padding:6px 20px;display:flex;justify-content:space-between;font-size:11px;color:#333;">
    <span>账单周期：{{statement_period}}</span>
    <span>账单日期：{{issue_date}}</span>
  </div>

  <!-- 持卡人信息 -->
  <div style="padding:10px 20px;border-bottom:2px solid #1a237e;">
    <table style="width:100%;font-size:12px;border-collapse:collapse;">
      <tr>
        <td style="width:14%;color:#888;padding:3px 0;">持卡人</td>
        <td style="width:36%;font-weight:700;padding:3px 0;">{{customer_name}}</td>
        <td style="width:14%;color:#888;padding:3px 0;">卡号</td>
        <td style="width:36%;font-weight:700;padding:3px 0;letter-spacing:1px;">{{card_number}}</td>
      </tr>
      <tr>
        <td style="color:#888;padding:3px 0;">地址</td>
        <td colspan="3" style="font-weight:700;padding:3px 0;">{{full_address}}</td>
      </tr>
      <tr>
        <td style="color:#888;padding:3px 0;">账户类型</td>
        <td style="padding:3px 0;">{{account_type}}</td>
        <td style="color:#888;padding:3px 0;">信用额度</td>
        <td style="font-weight:700;padding:3px 0;">{{currency}}{{credit_limit}}</td>
      </tr>
    </table>
  </div>

  <!-- 应还金额摘要 -->
  <div style="padding:10px 20px;background:#e8eaf6;">
    <table style="width:100%;font-size:12px;border-collapse:collapse;text-align:center;">
      <tr>
        <td style="width:20%;padding:6px;border-right:1px solid #c5cae9;">
          <div style="font-size:10px;color:#888;">上期余额</div>
          <div style="font-weight:700;font-size:14px;">{{currency}}{{previous_balance}}</div>
        </td>
        <td style="width:20%;padding:6px;border-right:1px solid #c5cae9;">
          <div style="font-size:10px;color:#2e7d32;">本期还款</div>
          <div style="font-weight:700;font-size:14px;color:#2e7d32;">-{{currency}}{{new_payments}}</div>
        </td>
        <td style="width:20%;padding:6px;border-right:1px solid #c5cae9;">
          <div style="font-size:10px;color:#c62828;">本期消费</div>
          <div style="font-weight:700;font-size:14px;color:#c62828;">+{{currency}}{{new_charges}}</div>
        </td>
        <td style="width:20%;padding:6px;border-right:1px solid #c5cae9;">
          <div style="font-size:10px;color:#e65100;">循环利息</div>
          <div style="font-weight:700;font-size:14px;color:#e65100;">+{{currency}}{{interest_charge}}</div>
        </td>
        <td style="width:20%;padding:6px;">
          <div style="font-size:10px;color:#1a237e;">本期应还</div>
          <div style="font-weight:700;font-size:16px;color:#1a237e;">{{currency}}{{total_due}}</div>
        </td>
      </tr>
    </table>
  </div>

  <!-- 还款信息 -->
  <div style="padding:8px 20px;border-bottom:1px solid #e0e0e0;display:flex;gap:24px;font-size:12px;">
    <div><span style="color:#888;">最低还款额：</span><span style="font-weight:700;color:#c62828;">{{currency}}{{min_payment}}</span></div>
    <div><span style="color:#888;">还款到期日：</span><span style="font-weight:700;color:#c62828;">{{payment_due_date}}</span></div>
    <div><span style="color:#888;">可用额度：</span><span style="font-weight:700;color:#2e7d32;">{{currency}}{{available_credit}}</span></div>
    <div><span style="color:#888;">积分余额：</span><span style="font-weight:700;">{{points_balance}}</span></div>
  </div>

  <!-- 交易明细 -->
  <div style="padding:8px 16px 4px;">
    <div style="font-size:12px;font-weight:700;color:#1a237e;border-bottom:1px solid #1a237e;padding-bottom:2px;margin-bottom:6px;">◆ 交易明细 Transaction Details</div>
    <table class="cc-table" style="width:100%;font-size:11px;">
      <tr>
        <th style="width:12%;">交易日</th>
        <th style="width:12%;">记账日</th>
        <th style="width:6%;">卡末四位</th>
        <th style="width:28%;">交易描述</th>
        <th style="width:10%;">金额</th>
        <th style="width:14%;">记账金额(元)</th>
        <th style="width:18%;">备注</th>
      </tr>
      {{transactions}}
    </table>
  </div>

  <!-- 底部 -->
  <div style="padding:8px 20px;border-top:2px solid #1a237e;font-size:9px;color:#999;line-height:1.8;">
    <div style="text-align:center;">{{bank_name}} · 客服热线 95588 · 本对账单仅供参考 · 还款方式：自动还款 / 网银转账 / 柜台还款 / ATM还款</div>
    <div style="text-align:center;margin-top:2px;">温馨提示：如在到期还款日前全额还款，可享受免息还款期待遇。最低还款额还款将产生循环利息（日利率万分之五）。</div>
  </div>
</div>
HTML;
}

function getCNCreditCardCSS(): string {
    return <<<'CSS'
.cn-credit-card .cc-table{width:100%;border-collapse:collapse;font-size:11px;}
.cn-credit-card .cc-table th,.cn-credit-card .cc-table td{border:1px solid #ddd;padding:5px 6px;text-align:center;vertical-align:middle;}
.cn-credit-card .cc-table th{background:#f5f5f5;font-weight:600;font-size:10px;color:#555;}
.cn-credit-card .cc-table td.credit{color:#2e7d32;font-weight:600;}
.cn-credit-card .cc-table td.debit{color:#c62828;font-weight:600;}
CSS;
}

// ══════════════════════════════════
// 招商银行信用卡对账单 (v3 - 适配A4一页+纯HTML印章)
// ══════════════════════════════════
function getCMBBankTemplate(): string {
    return <<<'HTML'
<div class="cmb-stmt" style="max-width:800px;margin:0 auto;background:#f7f9fb;padding:24px 48px;font-family:'PingFang SC','Microsoft YaHei','Hiragino Sans GB','Heiti SC',sans-serif;color:#000;box-shadow:0 1px 3px rgba(0,0,0,0.08);">

  <!-- Logo Row -->
  <table style="border-collapse:collapse;"><tr>
    <td style="vertical-align:middle;padding:0;">
      <img src="https://cczd.great-site.net/zs.png" width="58" height="58" alt="招商银行" style="display:block;" />
    </td>
    <td style="vertical-align:middle;padding:0 0 0 12px;">
      <div style="font-size:30px;font-weight:bold;letter-spacing:6px;color:#E60012;line-height:1;">{{bank_name}}</div>
      <div style="font-size:11px;font-weight:600;letter-spacing:1px;color:#E60012;margin-top:4px;font-family:'Courier New',Courier,monospace;">{{bank_name_en}}</div>
    </td>
    <td style="vertical-align:middle;padding:0 4px;">
      <div style="width:1px;height:40px;background:#E60012;"></div>
    </td>
    <td style="vertical-align:middle;padding:0;">
      <div style="font-size:26px;font-weight:bold;letter-spacing:4px;color:#E60012;line-height:1;">信用卡</div>
      <div style="font-size:12px;font-style:italic;color:#E60012;margin-top:4px;font-family:'Courier New',Courier,monospace;">Credit Card</div>
    </td>
  </tr></table>

  <!-- Title -->
  <div style="text-align:center;margin-top:32px;">
    <div style="font-size:20px;font-weight:bold;white-space:nowrap;">招商银行信用卡对账单（个人消费卡账户&nbsp;&nbsp;{{statement_period}}）（补）</div>
    <div style="font-size:22px;margin-top:8px;font-family:'Courier New',Courier,monospace;">CMB Credit Card Statement ({{period_end}})</div>
  </div>

  <!-- Address -->
  <div style="margin-top:24px;font-size:17px;font-weight:bold;line-height:2.3;">
    <div style="font-family:'Courier New',Courier,monospace;">{{postal_code}}</div>
    <div>{{address_district}}</div>
    <div>{{address_street}}</div>
    <div>{{address_unit}}</div>
    <div>{{customer_name}}</div>
  </div>

  <!-- Divider 1 (双线台阶式 - 匹配原始SVG分割线) -->
  <div style="position:relative;height:12px;margin:12px 0;">
    <div style="position:absolute;top:0;left:0;width:25%;border-top:1.5px solid #9a9a9a;"></div>
    <div style="position:absolute;top:0;left:25%;height:8px;border-left:1.5px solid #9a9a9a;"></div>
    <div style="position:absolute;top:7px;left:25%;right:0;border-top:1.5px solid #9a9a9a;"></div>
    <div style="position:absolute;top:3px;left:0;width:25%;border-top:1.5px solid #9a9a9a;"></div>
    <div style="position:absolute;top:3px;left:25%;height:8px;border-left:1.5px solid #9a9a9a;"></div>
    <div style="position:absolute;top:10px;left:25%;right:0;border-top:1.5px solid #9a9a9a;"></div>
  </div>

  <!-- Info Grid -->
  <div style="display:grid;grid-template-columns:1fr 1fr;column-gap:40px;margin-top:16px;">
    <div>
      <div style="display:flex;align-items:flex-end;justify-content:space-between;padding-right:24px;margin-bottom:20px;">
        <div><div style="font-size:19px;font-weight:bold;">账单日</div><div style="font-size:15px;margin-top:2px;font-family:'Courier New',Courier,monospace;">Statement Date</div></div>
        <div style="font-size:20px;font-weight:bold;white-space:nowrap;">{{issue_date}}</div>
      </div>
      <div style="display:flex;align-items:flex-end;justify-content:space-between;padding-right:24px;margin-bottom:20px;">
        <div><div style="font-size:19px;font-weight:bold;">到期还款日</div><div style="font-size:15px;margin-top:2px;font-family:'Courier New',Courier,monospace;">Payment Due Date</div></div>
        <div style="font-size:20px;font-weight:bold;white-space:nowrap;">{{payment_due_date}}</div>
      </div>
      <div style="display:flex;align-items:flex-end;justify-content:space-between;padding-right:24px;margin-bottom:20px;">
        <div><div style="font-size:19px;font-weight:bold;">本期应还金额</div><div style="font-size:15px;margin-top:2px;font-family:'Courier New',Courier,monospace;">New Balance</div></div>
        <div style="font-size:20px;font-weight:bold;white-space:nowrap;font-family:'Courier New',Courier,monospace;">￥{{total_due}}</div>
      </div>
      <div style="display:flex;align-items:flex-end;justify-content:space-between;padding-right:24px;margin-bottom:20px;">
        <div><div style="font-size:19px;font-weight:bold;">本期最低还款额</div><div style="font-size:15px;margin-top:2px;font-family:'Courier New',Courier,monospace;">Min. Payment</div></div>
        <div style="font-size:20px;font-weight:bold;white-space:nowrap;font-family:'Courier New',Courier,monospace;">￥{{min_payment}}</div>
      </div>
    </div>
    <div>
      <div style="display:flex;align-items:flex-end;justify-content:space-between;padding-right:24px;margin-bottom:20px;">
        <div><div style="font-size:19px;font-weight:bold;">信用额度</div><div style="font-size:15px;margin-top:2px;font-family:'Courier New',Courier,monospace;">Credit Limit</div></div>
        <div style="font-size:20px;font-weight:bold;white-space:nowrap;font-family:'Courier New',Courier,monospace;">￥{{credit_limit}}</div>
      </div>
    </div>
  </div>

  <!-- Divider 2 (双线台阶式) -->
  <div style="position:relative;height:12px;margin:12px 0;">
    <div style="position:absolute;top:0;left:0;width:25%;border-top:1.5px solid #9a9a9a;"></div>
    <div style="position:absolute;top:0;left:25%;height:8px;border-left:1.5px solid #9a9a9a;"></div>
    <div style="position:absolute;top:7px;left:25%;right:0;border-top:1.5px solid #9a9a9a;"></div>
    <div style="position:absolute;top:3px;left:0;width:25%;border-top:1.5px solid #9a9a9a;"></div>
    <div style="position:absolute;top:3px;left:25%;height:8px;border-left:1.5px solid #9a9a9a;"></div>
    <div style="position:absolute;top:10px;left:25%;right:0;border-top:1.5px solid #9a9a9a;"></div>
  </div>

  <!-- Transaction Details -->
  <div style="font-size:22px;font-weight:bold;margin-top:12px;">本期账务明细&nbsp;&nbsp;<span style="font-family:'Courier New',Courier,monospace;">Transaction Details</span></div>
  <div style="font-size:13px;margin-top:8px;">人民币账户&nbsp;&nbsp;<span style="font-family:'Courier New',Courier,monospace;">RMB A/C</span></div>

  <table class="cmb-txn" style="width:100%;border-collapse:collapse;margin-top:8px;font-size:13px;">
    <thead>
      <tr>
        <th style="background:#d9d9d9;padding:6px 8px;text-align:left;vertical-align:top;font-weight:normal;line-height:1.5;">交易日<br><span style="font-family:'Courier New',Courier,monospace;">Trans<br>Date</span></th>
        <th style="background:#d9d9d9;padding:6px 8px;text-align:left;vertical-align:top;font-weight:normal;line-height:1.5;">记账日<br><span style="font-family:'Courier New',Courier,monospace;">Post<br>Date</span></th>
        <th style="background:#d9d9d9;padding:6px 8px;text-align:left;vertical-align:top;font-weight:normal;line-height:1.5;">交易摘要<br><span style="font-family:'Courier New',Courier,monospace;">Description</span></th>
        <th style="background:#d9d9d9;padding:6px 8px;text-align:right;vertical-align:top;font-weight:normal;line-height:1.5;">人民币金额<br><span style="font-family:'Courier New',Courier,monospace;">RMB Amount</span></th>
        <th style="background:#d9d9d9;padding:6px 8px;text-align:center;vertical-align:top;font-weight:normal;line-height:1.5;">卡号末四位<br><span style="font-family:'Courier New',Courier,monospace;">Card Number</span><br><span style="font-family:'Courier New',Courier,monospace;">(last 4 digits)</span></th>
        <th style="background:#d9d9d9;padding:6px 8px;text-align:right;vertical-align:top;font-weight:normal;line-height:1.5;">交易地金额<br><span style="font-family:'Courier New',Courier,monospace;">Original Trans</span><br><span style="font-family:'Courier New',Courier,monospace;">Amount</span></th>
      </tr>
    </thead>
    <tbody>
      {{transactions}}
    </tbody>
  </table>

  <!-- Formula Row -->
  <div style="display:flex;align-items:stretch;margin-top:20px;">
    <div style="flex:1;border:1px solid #000;">
      <div style="padding:8px 4px 0 4px;text-align:center;"><div style="font-size:14px;font-weight:bold;">本期应还金额</div><div style="font-size:13px;font-family:'Courier New',Courier,monospace;">New Balance</div></div>
      <div style="padding:12px 0;text-align:center;font-size:15px;font-family:'Courier New',Courier,monospace;">￥{{total_due}}</div>
    </div>
    <div style="width:20px;display:flex;align-items:center;justify-content:center;font-size:20px;font-family:'Courier New',Courier,monospace;">=</div>
    <div style="flex:1;border:1px solid #000;">
      <div style="padding:8px 4px 0 4px;text-align:center;"><div style="font-size:14px;font-weight:bold;">上期账单金额</div><div style="font-size:13px;font-family:'Courier New',Courier,monospace;">Balance B/F</div></div>
      <div style="padding:12px 0;text-align:center;font-size:15px;font-family:'Courier New',Courier,monospace;">￥{{previous_balance}}</div>
    </div>
    <div style="width:20px;display:flex;align-items:center;justify-content:center;font-size:20px;font-family:'Courier New',Courier,monospace;">-</div>
    <div style="flex:1;border:1px solid #000;">
      <div style="padding:8px 4px 0 4px;text-align:center;"><div style="font-size:14px;font-weight:bold;">上期还款金额</div><div style="font-size:13px;font-family:'Courier New',Courier,monospace;">Payment</div></div>
      <div style="padding:12px 0;text-align:center;font-size:15px;font-family:'Courier New',Courier,monospace;">￥{{new_payments}}</div>
    </div>
    <div style="width:20px;display:flex;align-items:center;justify-content:center;font-size:20px;font-family:'Courier New',Courier,monospace;">+</div>
    <div style="flex:1;border:1px solid #000;">
      <div style="padding:8px 4px 0 4px;text-align:center;"><div style="font-size:14px;font-weight:bold;">本期账单金额</div><div style="font-size:13px;font-family:'Courier New',Courier,monospace;">New Charges</div></div>
      <div style="padding:12px 0;text-align:center;font-size:15px;font-family:'Courier New',Courier,monospace;">￥{{new_charges}}</div>
    </div>
    <div style="width:20px;display:flex;align-items:center;justify-content:center;font-size:20px;font-family:'Courier New',Courier,monospace;">-</div>
    <div style="flex:1;border:1px solid #000;">
      <div style="padding:8px 4px 0 4px;text-align:center;"><div style="font-size:14px;font-weight:bold;">本期调整金额</div><div style="font-size:13px;font-family:'Courier New',Courier,monospace;">Adjustment</div></div>
      <div style="padding:12px 0;text-align:center;font-size:15px;font-family:'Courier New',Courier,monospace;">￥{{adjustment}}</div>
    </div>
    <div style="width:20px;display:flex;align-items:center;justify-content:center;font-size:20px;font-family:'Courier New',Courier,monospace;">+</div>
    <div style="flex:1;border:1px solid #000;">
      <div style="padding:8px 4px 0 4px;text-align:center;"><div style="font-size:14px;font-weight:bold;">循环利息</div><div style="font-size:13px;font-family:'Courier New',Courier,monospace;">Interest</div></div>
      <div style="padding:12px 0;text-align:center;font-size:15px;font-family:'Courier New',Courier,monospace;">￥{{interest_charge}}</div>
    </div>
  </div>

  <!-- Notes -->
  <div style="margin-top:16px;font-size:13px;line-height:1.7;">
    <p style="margin:4px 0;">(1)上述交易摘要中的商户名称仅供您参考，如与签购单不符，请以签购单为准。</p>
    <p style="margin:4px 0;">(2)若您的人民币或美元账户的"本期应还金额"为负数，表示本期该人民币或美元账户中尚有溢缴款，您不需另行还款，本期账单仅供您作为对账参考。</p>
    <p style="margin:4px 0;">(3)通过本行系统缴款，您的信用额度一般可于缴款后立即恢复。</p>
  </div>

  <!-- Reminder -->
  <div style="margin-top:20px;font-size:15px;line-height:1.7;">
    ★友情提醒：依据《征信业管理条例》相关规定，我行会如实上报您的个人信用信息至金融信用信息基础数据库，该信息将对您与银行等金融机构发生的借贷业务产生重要影响，为维护良好的信用记录，请您及时还款！
  </div>

  <div style="margin-top:12px;font-size:15px;font-family:'Courier New',Courier,monospace;">[END]</div>

  <div style="margin-top:16px;font-size:15px;text-align:right;">【{{bank_name}}信用卡24小时服务热线：<span style="font-family:'Courier New',Courier,monospace;">400-820-5555</span>】</div>

  <!-- Divider 3 (双线台阶式) -->
  <div style="position:relative;height:12px;margin:12px 0;">
    <div style="position:absolute;top:0;left:0;width:25%;border-top:1.5px solid #9a9a9a;"></div>
    <div style="position:absolute;top:0;left:25%;height:8px;border-left:1.5px solid #9a9a9a;"></div>
    <div style="position:absolute;top:7px;left:25%;right:0;border-top:1.5px solid #9a9a9a;"></div>
    <div style="position:absolute;top:3px;left:0;width:25%;border-top:1.5px solid #9a9a9a;"></div>
    <div style="position:absolute;top:3px;left:25%;height:8px;border-left:1.5px solid #9a9a9a;"></div>
    <div style="position:absolute;top:10px;left:25%;right:0;border-top:1.5px solid #9a9a9a;"></div>
  </div>

  <!-- Electronic Stamp - SVG椭圆公章 -->
  <div class="stamp-row" style="margin-top:24px;display:flex;justify-content:flex-end;padding-right:24px;">
    <svg class="cmb-stamp-svg" width="260" height="170" viewBox="0 0 300 200" aria-hidden="true" style="transform:rotate(-8deg);opacity:0.85;">
      <defs>
        <path id="arcTop" d="M 35,118 A 118,72 0 0 1 265,118" fill="none"></path>
      </defs>
      <ellipse cx="150" cy="105" rx="138" ry="82" fill="none" stroke="#E60012" stroke-width="3"></ellipse>
      <text fill="#E60012" font-size="22" font-weight="bold" font-family="PingFang SC,Microsoft YaHei,sans-serif">
        <textPath href="#arcTop" startoffset="50%" text-anchor="middle">{{bank_name}}股份有限公司信用卡</textPath>
      </text>
      <text x="150" y="90" text-anchor="middle" fill="#E60012" font-size="22" font-weight="bold" font-family="PingFang SC,Microsoft YaHei,sans-serif">中心</text>
      <text x="150" y="118" text-anchor="middle" fill="#E60012" font-size="26" font-weight="bold" font-family="PingFang SC,Microsoft YaHei,sans-serif">业务受理专用章</text>
      <text x="150" y="155" text-anchor="middle" fill="#E60012" font-size="22" font-weight="bold" font-family="PingFang SC,Microsoft YaHei,sans-serif">（电子）</text>
    </svg>
  </div>

</div>
HTML;
}

function getCMBBankCSS(): string {
    return <<<'CSS'
.cmb-stmt table.cmb-txn{width:100%;border-collapse:collapse;font-size:13px;}
.cmb-stmt table.cmb-txn thead tr{background:#d9d9d9;}
.cmb-stmt table.cmb-txn th{padding:6px 8px;text-align:left;vertical-align:top;font-weight:normal;line-height:1.5;}
.cmb-stmt table.cmb-txn th.right,.cmb-stmt table.cmb-txn td.right{text-align:right;}
.cmb-stmt table.cmb-txn th.center,.cmb-stmt table.cmb-txn td.center{text-align:center;}
.cmb-stmt table.cmb-txn td{padding:6px 8px;}
.cmb-stmt table.cmb-txn tr.group td{font-weight:bold;padding:6px 8px;}
.cmb-stmt table.cmb-txn tr.stripe{background:#ededed;}
.cmb-stmt table.cmb-txn tr.row-category td{font-weight:bold;padding:6px 8px;}
.cmb-stmt table.cmb-txn tr.row-highlight{background:#ededed;}
.cmb-stmt table.cmb-txn td.text-left{text-align:left;}
.cmb-stmt table.cmb-txn td.text-right{text-align:right;}
CSS;
}

// ══════════════════════════════════
// 香港匯豐銀行
// ══════════════════════════════════
function getHKBankTemplate(): string {
    return <<<'HTML'
<div class="hk-bank" style="width:210mm;max-width:100%;margin:0 auto;font-family:'Noto Sans SC',Helvetica,sans-serif;background:#fff;">
  <div style="background:linear-gradient(135deg,#b71c1c,#d32f2f);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;">
    <div style="display:flex;align-items:center;gap:12px;">
      <div style="width:48px;height:48px;background:rgba(255,255,255,0.2);border-radius:8px;display:flex;align-items:center;justify-content:center;">
        <i class="fas fa-university" style="font-size:22px;"></i>
      </div>
      <div><div style="font-size:18px;font-weight:700;letter-spacing:2px;">香港上海匯豐銀行</div><div style="font-size:10px;opacity:0.85;">The Hongkong and Shanghai Banking Corporation Limited</div></div>
    </div>
    <div style="text-align:right;"><div style="font-size:15px;font-weight:700;">結單</div><div style="font-size:9px;opacity:0.85;">BANK STATEMENT</div></div>
  </div>
  <div style="padding:10px 20px;border-bottom:2px solid #c62828;">
    <table style="width:100%;font-size:12px;border-collapse:collapse;">
      <tr><td style="width:14%;color:#888;padding:3px 0;">戶名</td><td style="width:36%;font-weight:700;">{{customer_name}}</td><td style="width:14%;color:#888;padding:3px 0;">賬戶號碼</td><td style="width:36%;font-weight:700;">{{account_number}}</td></tr>
      <tr><td style="color:#888;padding:3px 0;">地址</td><td colspan="3" style="font-weight:700;">{{full_address}}</td></tr>
      <tr><td style="color:#888;padding:3px 0;">賬戶類別</td><td>{{account_type}}</td><td style="color:#888;padding:3px 0;">結單週期</td><td>{{statement_period}}</td></tr>
    </table>
  </div>
  <div style="padding:8px 20px;background:#fce4ec;">
    <table style="width:100%;font-size:12px;border-collapse:collapse;text-align:center;">
      <tr>
        <td style="width:25%;padding:4px;"><div style="font-size:10px;color:#888;">上期結餘</div><div style="font-weight:700;font-size:15px;">{{currency}}{{opening_balance}}</div></td>
        <td style="width:25%;padding:4px;border-left:1px solid #e0e0e0;border-right:1px solid #e0e0e0;"><div style="font-size:10px;color:#2e7d32;">存入合計</div><div style="font-weight:700;font-size:15px;color:#2e7d32;">+{{currency}}{{total_credits}}</div></td>
        <td style="width:25%;padding:4px;border-right:1px solid #e0e0e0;"><div style="font-size:10px;color:#c62828;">支出合計</div><div style="font-weight:700;font-size:15px;color:#c62828;">-{{currency}}{{total_debits}}</div></td>
        <td style="width:25%;padding:4px;"><div style="font-size:10px;color:#888;">本期結餘</div><div style="font-weight:700;font-size:15px;">{{currency}}{{closing_balance}}</div></td>
      </tr>
    </table>
  </div>
  <div style="padding:8px 16px;">
    <div style="font-size:12px;font-weight:700;color:#c62828;border-bottom:1px solid #c62828;padding-bottom:2px;margin-bottom:6px;">◆ 交易明細 Transaction Details</div>
    <table class="bank-table" style="width:100%;font-size:11px;">
      <tr><th style="width:12%;">日期</th><th style="width:22%;">摘要</th><th style="width:12%;">支票</th><th style="width:12%;">支出</th><th style="width:12%;">存入</th><th style="width:14%;">結餘</th><th style="width:16%;">備註</th></tr>
      {{transactions}}
    </table>
  </div>
  <div style="padding:6px 20px;border-top:2px solid #c62828;font-size:9px;color:#999;text-align:center;line-height:1.8;">
    香港上海匯豐銀行有限公司 · 客戶服務熱線 (852) 2233 3000 · www.hsbc.com.hk · 發出日期：{{issue_date}}
  </div>
</div>
HTML;
}
function getHKBankCSS(): string { return getCNBankCSS(); }

// ══════════════════════════════════
// AU Commonwealth Bank
// ══════════════════════════════════
function getAUBankTemplate(): string {
    return <<<'HTML'
<div class="au-bank" style="width:210mm;max-width:100%;margin:0 auto;font-family:Helvetica,Arial,sans-serif;background:#fff;">
  <div style="background:linear-gradient(135deg,#f57f17,#f9a825);color:#000;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;">
    <div style="display:flex;align-items:center;gap:12px;">
      <div style="width:48px;height:48px;background:rgba(0,0,0,0.1);border-radius:8px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-university" style="font-size:22px;"></i></div>
      <div><div style="font-size:18px;font-weight:700;">Commonwealth Bank</div><div style="font-size:10px;opacity:0.7;">Australia</div></div>
    </div>
    <div style="text-align:right;"><div style="font-size:15px;font-weight:700;">Statement</div><div style="font-size:9px;opacity:0.7;">ACCOUNT SUMMARY</div></div>
  </div>
  <div style="padding:10px 20px;border-bottom:2px solid #f57f17;">
    <table style="width:100%;font-size:12px;border-collapse:collapse;">
      <tr><td style="width:14%;color:#888;padding:3px 0;">Name</td><td style="width:36%;font-weight:700;">{{customer_name}}</td><td style="width:14%;color:#888;padding:3px 0;">Account</td><td style="width:36%;font-weight:700;">{{account_number}}</td></tr>
      <tr><td style="color:#888;padding:3px 0;">Address</td><td colspan="3" style="font-weight:700;">{{full_address}}</td></tr>
      <tr><td style="color:#888;padding:3px 0;">Account Type</td><td>{{account_type}}</td><td style="color:#888;padding:3px 0;">Period</td><td>{{statement_period}}</td></tr>
    </table>
  </div>
  <div style="padding:8px 20px;background:#fff8e1;">
    <table style="width:100%;font-size:12px;border-collapse:collapse;text-align:center;">
      <tr>
        <td style="width:25%;padding:4px;"><div style="font-size:10px;color:#888;">Opening Balance</div><div style="font-weight:700;font-size:15px;">{{currency}}{{opening_balance}}</div></td>
        <td style="width:25%;padding:4px;border-left:1px solid #e0e0e0;border-right:1px solid #e0e0e0;"><div style="font-size:10px;color:#2e7d32;">Total Credits</div><div style="font-weight:700;font-size:15px;color:#2e7d32;">+{{currency}}{{total_credits}}</div></td>
        <td style="width:25%;padding:4px;border-right:1px solid #e0e0e0;"><div style="font-size:10px;color:#c62828;">Total Debits</div><div style="font-weight:700;font-size:15px;color:#c62828;">-{{currency}}{{total_debits}}</div></td>
        <td style="width:25%;padding:4px;"><div style="font-size:10px;color:#888;">Closing Balance</div><div style="font-weight:700;font-size:15px;">{{currency}}{{closing_balance}}</div></td>
      </tr>
    </table>
  </div>
  <div style="padding:8px 16px;">
    <div style="font-size:12px;font-weight:700;color:#f57f17;border-bottom:1px solid #f57f17;padding-bottom:2px;margin-bottom:6px;">◆ Transactions</div>
    <table class="bank-table" style="width:100%;font-size:11px;">
      <tr><th style="width:14%;">Date</th><th style="width:30%;">Description</th><th style="width:14%;">Debit</th><th style="width:14%;">Credit</th><th style="width:14%;">Balance</th><th style="width:14%;">Ref</th></tr>
      {{transactions}}
    </table>
  </div>
  <div style="padding:6px 20px;border-top:2px solid #f57f17;font-size:9px;color:#999;text-align:center;">Commonwealth Bank of Australia ABN 48 123 123 124 · 13 2221 · commbank.com.au · Date: {{issue_date}}</div>
</div>
HTML;
}
function getAUBankCSS(): string { return getCNBankCSS(); }

// ══════════════════════════════════
// CA TD Bank
// ══════════════════════════════════
function getCABankTemplate(): string {
    return <<<'HTML'
<div class="ca-bank" style="width:210mm;max-width:100%;margin:0 auto;font-family:Helvetica,Arial,sans-serif;background:#fff;">
  <div style="background:linear-gradient(135deg,#1b5e20,#2e7d32);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;">
    <div style="display:flex;align-items:center;gap:12px;">
      <div style="width:48px;height:48px;background:rgba(255,255,255,0.2);border-radius:8px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-university" style="font-size:22px;"></i></div>
      <div><div style="font-size:18px;font-weight:700;">TD Canada Trust</div><div style="font-size:10px;opacity:0.85;">Banking Made Comfortable</div></div>
    </div>
    <div style="text-align:right;"><div style="font-size:15px;font-weight:700;">Account Statement</div><div style="font-size:9px;opacity:0.85;">RELEVE DE COMPTE</div></div>
  </div>
  <div style="padding:10px 20px;border-bottom:2px solid #2e7d32;">
    <table style="width:100%;font-size:12px;border-collapse:collapse;">
      <tr><td style="width:14%;color:#888;padding:3px 0;">Name</td><td style="width:36%;font-weight:700;">{{customer_name}}</td><td style="width:14%;color:#888;padding:3px 0;">Account</td><td style="width:36%;font-weight:700;">{{account_number}}</td></tr>
      <tr><td style="color:#888;padding:3px 0;">Address</td><td colspan="3" style="font-weight:700;">{{full_address}}</td></tr>
      <tr><td style="color:#888;padding:3px 0;">Type</td><td>{{account_type}}</td><td style="color:#888;padding:3px 0;">Period</td><td>{{statement_period}}</td></tr>
    </table>
  </div>
  <div style="padding:8px 20px;background:#e8f5e9;">
    <table style="width:100%;font-size:12px;border-collapse:collapse;text-align:center;">
      <tr>
        <td style="width:25%;padding:4px;"><div style="font-size:10px;color:#888;">Opening</div><div style="font-weight:700;font-size:15px;">{{currency}}{{opening_balance}}</div></td>
        <td style="width:25%;padding:4px;border-left:1px solid #c8e6c9;border-right:1px solid #c8e6c9;"><div style="font-size:10px;color:#2e7d32;">Deposits</div><div style="font-weight:700;font-size:15px;color:#2e7d32;">+{{currency}}{{total_credits}}</div></td>
        <td style="width:25%;padding:4px;border-right:1px solid #c8e6c9;"><div style="font-size:10px;color:#c62828;">Withdrawals</div><div style="font-weight:700;font-size:15px;color:#c62828;">-{{currency}}{{total_debits}}</div></td>
        <td style="width:25%;padding:4px;"><div style="font-size:10px;color:#888;">Closing</div><div style="font-weight:700;font-size:15px;">{{currency}}{{closing_balance}}</div></td>
      </tr>
    </table>
  </div>
  <div style="padding:8px 16px;">
    <div style="font-size:12px;font-weight:700;color:#2e7d32;border-bottom:1px solid #2e7d32;padding-bottom:2px;margin-bottom:6px;">◆ Transactions</div>
    <table class="bank-table" style="width:100%;font-size:11px;">
      <tr><th style="width:14%;">Date</th><th style="width:28%;">Description</th><th style="width:12%;">Withdrawal</th><th style="width:12%;">Deposit</th><th style="width:16%;">Balance</th><th style="width:18%;">Cheque #</th></tr>
      {{transactions}}
    </table>
  </div>
  <div style="padding:6px 20px;border-top:2px solid #2e7d32;font-size:9px;color:#999;text-align:center;">TD Canada Trust · 1-866-222-3456 · td.com · Statement Date: {{issue_date}}</div>
</div>
HTML;
}
function getCABankCSS(): string { return getCNBankCSS(); }

// ══════════════════════════════════
// SG DBS Bank
// ══════════════════════════════════
function getSGBankTemplate(): string {
    return <<<'HTML'
<div class="sg-bank" style="width:210mm;max-width:100%;margin:0 auto;font-family:'Noto Sans SC',Helvetica,sans-serif;background:#fff;">
  <div style="background:linear-gradient(135deg,#b71c1c,#d32f2f);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;">
    <div style="display:flex;align-items:center;gap:12px;">
      <div style="width:48px;height:48px;background:rgba(255,255,255,0.2);border-radius:8px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-university" style="font-size:22px;"></i></div>
      <div><div style="font-size:18px;font-weight:700;">DBS Bank</div><div style="font-size:10px;opacity:0.85;">Development Bank of Singapore</div></div>
    </div>
    <div style="text-align:right;"><div style="font-size:15px;font-weight:700;">银行结单</div><div style="font-size:9px;opacity:0.85;">BANK STATEMENT</div></div>
  </div>
  <div style="padding:10px 20px;border-bottom:2px solid #c62828;">
    <table style="width:100%;font-size:12px;border-collapse:collapse;">
      <tr><td style="width:14%;color:#888;padding:3px 0;">户名</td><td style="width:36%;font-weight:700;">{{customer_name}}</td><td style="width:14%;color:#888;padding:3px 0;">账号</td><td style="width:36%;font-weight:700;">{{account_number}}</td></tr>
      <tr><td style="color:#888;padding:3px 0;">地址</td><td colspan="3" style="font-weight:700;">{{full_address}}</td></tr>
      <tr><td style="color:#888;padding:3px 0;">账户类别</td><td>{{account_type}}</td><td style="color:#888;padding:3px 0;">结单周期</td><td>{{statement_period}}</td></tr>
    </table>
  </div>
  <div style="padding:8px 20px;background:#fce4ec;">
    <table style="width:100%;font-size:12px;border-collapse:collapse;text-align:center;">
      <tr>
        <td style="width:25%;padding:4px;"><div style="font-size:10px;color:#888;">上期结余</div><div style="font-weight:700;font-size:15px;">{{currency}}{{opening_balance}}</div></td>
        <td style="width:25%;padding:4px;border-left:1px solid #e0e0e0;border-right:1px solid #e0e0e0;"><div style="font-size:10px;color:#2e7d32;">存入合计</div><div style="font-weight:700;font-size:15px;color:#2e7d32;">+{{currency}}{{total_credits}}</div></td>
        <td style="width:25%;padding:4px;border-right:1px solid #e0e0e0;"><div style="font-size:10px;color:#c62828;">支出合计</div><div style="font-weight:700;font-size:15px;color:#c62828;">-{{currency}}{{total_debits}}</div></td>
        <td style="width:25%;padding:4px;"><div style="font-size:10px;color:#888;">本期结余</div><div style="font-weight:700;font-size:15px;">{{currency}}{{closing_balance}}</div></td>
      </tr>
    </table>
  </div>
  <div style="padding:8px 16px;">
    <div style="font-size:12px;font-weight:700;color:#c62828;border-bottom:1px solid #c62828;padding-bottom:2px;margin-bottom:6px;">◆ 交易明细</div>
    <table class="bank-table" style="width:100%;font-size:11px;">
      <tr><th style="width:14%;">日期</th><th style="width:26%;">摘要</th><th style="width:12%;">支出</th><th style="width:12%;">存入</th><th style="width:16%;">余额</th><th style="width:20%;">参考编号</th></tr>
      {{transactions}}
    </table>
  </div>
  <div style="padding:6px 20px;border-top:2px solid #c62828;font-size:9px;color:#999;text-align:center;">DBS Bank Ltd · 客服 1800 339 6666 · dbs.com.sg · {{issue_date}}</div>
</div>
HTML;
}
function getSGBankCSS(): string { return getCNBankCSS(); }

// ══════════════════════════════════
// GB Barclays
// ══════════════════════════════════
function getGBBankTemplate(): string {
    return <<<'HTML'
<div class="gb-bank" style="width:210mm;max-width:100%;margin:0 auto;font-family:Helvetica,Arial,sans-serif;background:#fff;">
  <div style="background:linear-gradient(135deg,#0d47a1,#1565c0);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;">
    <div style="display:flex;align-items:center;gap:12px;">
      <div style="width:48px;height:48px;background:rgba(255,255,255,0.2);border-radius:8px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-university" style="font-size:22px;"></i></div>
      <div><div style="font-size:18px;font-weight:700;">Barclays</div><div style="font-size:10px;opacity:0.85;">Wealth and Investment Management</div></div>
    </div>
    <div style="text-align:right;"><div style="font-size:15px;font-weight:700;">Statement</div><div style="font-size:9px;opacity:0.85;">ACCOUNT STATEMENT</div></div>
  </div>
  <div style="padding:10px 20px;border-bottom:2px solid #1565c0;">
    <table style="width:100%;font-size:12px;border-collapse:collapse;">
      <tr><td style="width:14%;color:#888;padding:3px 0;">Name</td><td style="width:36%;font-weight:700;">{{customer_name}}</td><td style="width:14%;color:#888;padding:3px 0;">Sort Code</td><td style="width:36%;font-weight:700;">{{account_number}}</td></tr>
      <tr><td style="color:#888;padding:3px 0;">Address</td><td colspan="3" style="font-weight:700;">{{full_address}}</td></tr>
      <tr><td style="color:#888;padding:3px 0;">Account</td><td>{{account_type}}</td><td style="color:#888;padding:3px 0;">Period</td><td>{{statement_period}}</td></tr>
    </table>
  </div>
  <div style="padding:8px 20px;background:#e3f2fd;">
    <table style="width:100%;font-size:12px;border-collapse:collapse;text-align:center;">
      <tr>
        <td style="width:25%;padding:4px;"><div style="font-size:10px;color:#888;">Brought Forward</div><div style="font-weight:700;font-size:15px;">{{currency}}{{opening_balance}}</div></td>
        <td style="width:25%;padding:4px;border-left:1px solid #bbdefb;border-right:1px solid #bbdefb;"><div style="font-size:10px;color:#2e7d32;">Paid In</div><div style="font-weight:700;font-size:15px;color:#2e7d32;">+{{currency}}{{total_credits}}</div></td>
        <td style="width:25%;padding:4px;border-right:1px solid #bbdefb;"><div style="font-size:10px;color:#c62828;">Paid Out</div><div style="font-weight:700;font-size:15px;color:#c62828;">-{{currency}}{{total_debits}}</div></td>
        <td style="width:25%;padding:4px;"><div style="font-size:10px;color:#888;">Balance</div><div style="font-weight:700;font-size:15px;">{{currency}}{{closing_balance}}</div></td>
      </tr>
    </table>
  </div>
  <div style="padding:8px 16px;">
    <div style="font-size:12px;font-weight:700;color:#1565c0;border-bottom:1px solid #1565c0;padding-bottom:2px;margin-bottom:6px;">◆ Transactions</div>
    <table class="bank-table" style="width:100%;font-size:11px;">
      <tr><th style="width:12%;">Date</th><th style="width:10%;">Type</th><th style="width:28%;">Description</th><th style="width:12%;">Paid Out</th><th style="width:12%;">Paid In</th><th style="width:14%;">Balance</th><th style="width:12%;">Ref</th></tr>
      {{transactions}}
    </table>
  </div>
  <div style="padding:6px 20px;border-top:2px solid #1565c0;font-size:9px;color:#999;text-align:center;">Barclays Bank UK PLC · 0345 734 5345 · barclays.co.uk · Statement Date: {{issue_date}}</div>
</div>
HTML;
}
function getGBBankCSS(): string { return getCNBankCSS(); }

// ══════════════════════════════════
// GB Monzo Bank Statement
// ══════════════════════════════════
function getGBMonzoTemplate(): string {
    return <<<'HTML'
<div class="monzo-stmt" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f1f1f;margin:0;padding:40px 20px;background:#ffffff;display:flex;justify-content:center;">
  <div style="background:#fff;width:100%;max-width:800px;padding:40px;box-sizing:border-box;box-shadow:0 4px 12px rgba(0,0,0,.05);">

    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:40px;">
      <div style="font-size:38px;font-weight:800;color:#ff4b4b;letter-spacing:-1px;">monzo</div>
      <div style="text-align:right;">
        <h1 style="margin:0 0 5px 0;font-size:24px;font-weight:700;color:#000;">Personal Account statement</h1>
        <div style="font-size:14px;font-weight:600;color:#000;">{{period_start}} - {{period_end}}</div>
      </div>
    </div>

    <!-- Main Content -->
    <div style="display:flex;justify-content:space-between;margin-bottom:40px;">
      <!-- Left: Address + Account -->
      <div style="width:50%;font-size:13px;line-height:1.5;">
        <div style="margin-bottom:30px;">
          <strong style="font-size:14px;">{{customer_name}}</strong><br>
          {{address_unit}}<br>
          {{address_district}}<br>
          {{postal_code}}<br>
          {{country}}
        </div>
        <div>
          <p style="margin:4px 0;"><strong>Sort code:</strong> {{sort_code}}</p>
          <p style="margin:4px 0;"><strong>Account number:</strong> {{account_number}}</p>
          <p style="margin:4px 0;"><strong>BIC:</strong> {{bic}}</p>
          <p style="margin:4px 0;"><strong>IBAN:</strong> {{iban}}</p>
        </div>
      </div>

      <!-- Right: Balances -->
      <div style="width:45%;text-align:right;">
        <div style="margin-bottom:20px;">
          <p style="font-size:16px;font-weight:700;margin:0;">{{currency}}{{opening_balance}}</p>
          <p style="font-size:12px;color:#1f1f1f;margin:2px 0 0 0;">Personal Account balance</p>
          <p style="font-size:10px;color:#666;margin:1px 0 0 0;">(Excluding all Pots)</p>
        </div>
        <div style="margin-bottom:20px;">
          <p style="font-size:16px;font-weight:700;margin:0;">{{currency}}{{balance_pots}}</p>
          <p style="font-size:12px;color:#1f1f1f;margin:2px 0 0 0;">Balance in Pots</p>
          <p style="font-size:10px;color:#666;margin:1px 0 0 0;">(This includes both Regular Pots with Monzo and Savings Pots with external providers)</p>
        </div>
        <div style="margin-bottom:20px;">
          <p style="font-size:16px;font-weight:700;margin:0;">{{currency}}{{total_outgoings}}</p>
          <p style="font-size:12px;color:#1f1f1f;margin:2px 0 0 0;">Total outgoings</p>
        </div>
        <div style="margin-bottom:20px;">
          <p style="font-size:16px;font-weight:700;margin:0;">+{{currency}}{{total_deposits}}</p>
          <p style="font-size:12px;color:#1f1f1f;margin:2px 0 0 0;">Total deposits</p>
        </div>
      </div>
    </div>

    <!-- Transaction Status -->
    <div style="border-top:1px dashed #ddd;border-bottom:1px dashed #ddd;padding:12px 0;font-size:13px;color:#1f1f1f;margin-bottom:40px;">
      {{transactions}}
    </div>

    <!-- Footer -->
    <div style="font-size:10px;color:#555;line-height:1.5;border-top:1px solid #eee;padding-top:15px;">
      Monzo Bank Limited (<a href="https://monzo.com" style="color:#555;text-decoration:none;">https://monzo.com</a>) is a company registered in England No. 9446231. Registered Office: Broadwalk House, 5 Appold Street, London EC2A 2AG. Monzo Bank Ltd is authorised by the Prudential Regulation Authority and regulated by the Financial Conduct Authority and the Prudential Regulation Authority. Our Financial Services Register number is 730427.
    </div>
  </div>
</div>
HTML;
}

function getGBMonzoCSS(): string {
    return <<<'CSS'
.monzo-stmt a{color:#555!important;text-decoration:none!important;}
.monzo-stmt .bank-table{width:100%;border-collapse:collapse;font-size:11px;}
.monzo-stmt .bank-table th,.monzo-stmt .bank-table td{border:1px solid #ddd;padding:5px 6px;text-align:center;vertical-align:middle;}
.monzo-stmt .bank-table th{background:#f5f5f5;font-weight:600;font-size:10px;color:#555;}
.monzo-stmt .bank-table td.credit{color:#2e7d32;font-weight:600;}
.monzo-stmt .bank-table td.debit{color:#c62828;font-weight:600;}
CSS;
}

// ══════════════════════════════════
// GB Kraken Statement
// ══════════════════════════════════
function getGBKrakenTemplate(): string {
    return <<<'HTML'
<style>
    .kraken-wrap { margin:0; padding:20px; background-color:#f4f4f4; font-family:Arial,sans-serif; }
    .kraken-wrap .page { background-color:#ffffff; width:1000px; margin:0 auto 20px auto; padding:40px 50px; box-sizing:border-box; box-shadow:0 0 10px rgba(0,0,0,0.1); position:relative; min-height:1200px; color:#000; }
    .kraken-wrap .header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:50px; }
    .kraken-wrap .logo-container { display:flex; align-items:center; gap:6px; }
    .kraken-wrap .krak-img { height:48px; width:auto; background-color:#ffffff; display:block; }
    .kraken-wrap .krak-text { font-size:38px; font-weight:bold; letter-spacing:-2px; color:#000; }
    .kraken-wrap .header-right { text-align:right; font-size:11px; line-height:1.5; }
    .kraken-wrap .info-section { display:flex; gap:40px; margin-bottom:50px; font-size:12px; line-height:1.6; }
    .kraken-wrap .info-left { flex:1; }
    .kraken-wrap .info-right { flex:1; border-left:2px solid #e0e0e0; padding-left:20px; }
    .kraken-wrap .section-title { color:#e33f3e; font-weight:bold; font-size:14px; margin-top:30px; margin-bottom:10px; }
    .kraken-wrap table { width:100%; border-collapse:collapse; font-size:10px; margin-bottom:20px; }
    .kraken-wrap th { background-color:#fadada; color:#000; font-weight:bold; padding:8px 10px; border-right:2px solid #fff; }
    .kraken-wrap th:last-child { border-right:none; }
    .kraken-wrap td { padding:10px 10px; vertical-align:top; }
    .kraken-wrap tbody tr:nth-child(even) { background-color:#f9f9f9; }
    .kraken-wrap tbody tr:nth-child(odd) { background-color:#ffffff; }
    .kraken-wrap .total-row td { font-weight:bold; border-top:1px solid #ddd; }
    .kraken-wrap .align-left { text-align:left; }
    .kraken-wrap .align-right { text-align:right; }
    .kraken-wrap .type-sub { color:#888; display:block; margin-top:2px; }
    .kraken-wrap .footer { position:absolute; bottom:30px; right:50px; font-size:14px; font-weight:bold; }
    .kraken-wrap .disclaimer-title { color:#e33f3e; font-weight:bold; font-size:13px; margin-bottom:15px; }
    .kraken-wrap .text-block { font-size:11px; line-height:1.6; margin-bottom:15px; }
    .kraken-wrap .text-block strong { font-size:12px; display:block; margin-bottom:5px; margin-top:20px; }
    .kraken-wrap a { color:#e33f3e; text-decoration:none; }
</style>

<div class="kraken-wrap">

<!-- Page 1 -->
<div class="page">
    <div class="header">
        <div class="logo-container">
            <img src="https://cczd.great-site.net/krak.png" class="krak-img" alt="krak logo">
            <span class="krak-text">krak</span>
        </div>
        <div class="header-right">
            Monthly Statement: {{statement_month}}<br>
            All portfolio balances are recorded as of {{balance_date}} UTC
        </div>
    </div>

    <div class="info-section">
        <div class="info-left">
            <strong>Payward Services Limited and Payward Ltd.</strong><br><br>
            6th Floor, One London Wall,<br>
            London, United Kingdom, EC2Y 5EB
        </div>
        <div class="info-right">
            <strong>{{customer_name}}</strong><br><br>
            {{address_unit}}<br>
            {{address_district}}<br><br>
            Kraken Public ID:<br>
            {{kraken_public_id}}<br><br>
            Account ID:<br>
            {{account_number}}
        </div>
    </div>

    <div class="section-title">Portfolio</div>
    <table>
        <thead>
            <tr>
                <th class="align-left">Asset</th>
                <th class="align-left">Wallet</th>
                <th class="align-right">Open Qty</th>
                <th class="align-right">Open Price (GBP)</th>
                <th class="align-right">Open Value (GBP)</th>
                <th class="align-right">Close Qty</th>
                <th class="align-right">Close Price (GBP)</th>
                <th class="align-right">Close Value (GBP)</th>
                <th class="align-right">Net Change (GBP)</th>
            </tr>
        </thead>
        <tbody>
            {{portfolio_table}}
        </tbody>
    </table>

    <div class="section-title">Activity</div>
    <table>
        <thead>
            <tr>
                <th class="align-left">Date (UTC)</th>
                <th class="align-left">Type</th>
                <th class="align-left">Asset</th>
                <th class="align-left">Wallet</th>
                <th class="align-right">Amount</th>
                <th class="align-right">Price (GBP)</th>
                <th class="align-right">Fee (GBP)</th>
                <th class="align-right">Value (GBP)</th>
                <th class="align-left">Counter party</th>
                <th class="align-left">Reference</th>
            </tr>
        </thead>
        <tbody>
            {{transactions}}
        </tbody>
    </table>
    
    <div class="footer">1 / 2</div>
</div>

<!-- Page 2 -->
<div class="page">
    <div class="header">
        <div class="logo-container">
            <img src="https://cczd.great-site.net/krak.png" class="krak-img" alt="krak logo">
            <span class="krak-text">krak</span>
        </div>
        <div class="header-right">
            Monthly Statement: {{statement_month}}<br>
            All portfolio balances are recorded as of {{balance_date}} UTC
        </div>
    </div>

    <div class="disclaimer-title">Disclaimer:</div>
    <div class="text-block">
        Your Kraken monthly account statement ("Statement") is an important document and contains a record of your Kraken account balances held with, and transaction activity facilitated by, your Kraken account servicing entities. Your Kraken account servicing entities depend on your location, as detailed in our Terms of Use: <a href="https://www.kraken.com/legal">https://www.kraken.com/legal</a>
    </div>

    <div class="text-block">
        <strong>Understanding this Statement:</strong>
        This Statement is intended to provide you with a monthly snapshot of your account and should not be used for the purpose of tax reporting. This Statement is prepared from information Kraken believes to be reliable in order to provide you with this service.<br><br>
        Use of this Statement is governed by Kraken's Terms of Use which sets out the relationship between you and Kraken.
    </div>

    <div class="text-block">
        <strong>Handling discrepancies and other issues relating to this Statement:</strong>
        You must review this Statement carefully and notify us as soon as you become aware of any errors or omissions in this Statement, including any transactions that you did not authorize or that you do not recognise, or if you have any other questions or concerns. Failing to do so could impact whether you are entitled to a refund (for example, if there is an unauthorized transaction on your account). If you have any questions or concerns of this kind, please report them to <a href="https://support.kraken.com/hc/en-us">https://support.kraken.com/hc/en-us</a>.
    </div>

    <div class="text-block">
        Contact Kraken Support: <a href="https://support.kraken.com/hc/en-us">https://support.kraken.com/hc/en-us</a><br><br>
        Activity Types Explained: <a href="https://support.kraken.com/hc/en-us/articles/360001169383-How-to-interpret-Ledger-history-fields">https://support.kraken.com/hc/en-us/articles/360001169383-How-to-interpret-Ledger-history-fields</a><br><br>
        Rates: Xe <a href="https://www.xe.com/">https://www.xe.com/</a>
    </div>

    <div class="text-block" style="margin-top: 30px;">
        Krak Card is issued by Monavate Limited, authorised by the Financial Conduct Authority to carry on electronic money activities and related payment services (FRN: 901097).<br><br>
        Payward Services Limited (company no. 12861311) is authorised by the Financial Conduct Authority to carry out electronic money activities under the Electronic Money Regulations 2011 (FRN: 1010381). E-money is not a bank deposit, is not covered by the Financial Services Compensation Scheme (FSCS), and is safeguarded in segregated accounts in accordance with the FCA's rules.<br><br>
        Payward Ltd (company no. 08593670) is registered with the Financial Conduct Authority as a cryptoasset business pursuant to the Money Laundering Regulations 2017 (FRN: 928768). Cryptoasset services offered by Payward Ltd are unregulated and not within the jurisdiction of the Financial Ombudsman Service or subject to protection under the Financial Services Compensation Scheme. The value of cryptoassets can go down as well as up, gains may be subject to Capital Gains Tax and there may be extra charges when paying via credit card from your provider.<br><br>
        The registered office for both Payward Services Limited and Payward Ltd. is 6th Floor, One London Wall, London, United Kingdom, EC2Y 5EB.
    </div>

    <div class="footer">2 / 2</div>
</div>

</div>
HTML;
}

// ══════════════════════════════════
// Wise 共用 CSS
// ══════════════════════════════════
function getWiseBankCSS(): string {
    return <<<'CSS'
.table-header{display:flex;justify-content:space-between;padding:10px 0;font-size:12px;font-weight:700;color:#4a4a4a;}
.th-right{display:flex;justify-content:flex-end;width:50%;}
.th-right span{width:33.33%;text-align:right;}
.th-left{width:50%;}
.txn-row{display:flex;font-size:13px;line-height:1.8;color:#333;}
.txn-row .td-desc{flex:4;}
.txn-row .td-in{flex:1;text-align:right;}
.txn-row .td-out{flex:1;text-align:right;}
.txn-row .td-bal{flex:1;text-align:right;}
CSS;
}

// ══════════════════════════════════
// DE Wise EUR Statement (德国)
// ══════════════════════════════════
function getDEWiseBankTemplate(): string {
    return <<<'HTML'
<div class="page" style="background-color:#ffffff;width:210mm;min-height:297mm;padding:20mm 20mm 40mm 20mm;box-sizing:border-box;box-shadow:0 4px 12px rgba(0,0,0,0.1);position:relative;font-size:13px;line-height:1.5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1a1a1a;">
    <div class="logo-container" style="margin-bottom:25px;">
        <img src="https://cczd.great-site.net/wise.png" alt="Wise Logo" style="width:120px;height:auto;display:block;">
    </div>

    <div class="company-address" style="color:#4a4a4a;margin-bottom:45px;">
        <strong style="color:#1a1a1a;font-size:14px;">Wise Payments Ltd.</strong><br>
        1st Floor, Worship Square, 65 Clifton Street<br>
        London<br>
        EC2A 4JE<br>
        United Kingdom
    </div>

    <h1 style="font-size:26px;font-weight:700;margin:0 0 20px 0;letter-spacing:-0.5px;">{{wise_currency}} 对账单</h1>

    <div class="date-range" style="font-size:15px;font-weight:700;margin-bottom:12px;">{{wise_period_start}} [{{wise_timezone}}] - {{wise_period_end}} [{{wise_timezone}}]</div>
    <div class="generation-date" style="color:#666666;margin-bottom:25px;">生成日期: {{wise_generated_date}}</div>

    <hr class="divider" style="border:none;border-top:1px solid #e0e0e0;margin:0 0 15px 0;">

    <div class="account-section" style="display:flex;justify-content:space-between;margin-bottom:30px;">
        <div class="account-left" style="width:48%;">
            <div class="section-label" style="font-weight:700;margin-bottom:5px;font-size:13px;">账户持有人</div>
            <div class="account-details" style="color:#1a1a1a;">
                {{customer_name}}<br>
                {{address_unit}}<br>
                {{wise_city}}<br>
                {{wise_state}}<br>
                {{wise_postcode}}<br>
                {{wise_country}}
            </div>
        </div>
        <div class="account-right" style="width:48%;display:grid;grid-template-columns:1fr 1fr;row-gap:20px;">
            <div>
                <div class="section-label" style="font-weight:700;margin-bottom:5px;font-size:13px;">IBAN 代码</div>
                <div class="account-details" style="color:#1a1a1a;">{{wise_iban}}</div>
            </div>
            <div>
                <div class="section-label" style="font-weight:700;margin-bottom:5px;font-size:13px;">Swift/BIC</div>
                <div class="account-details" style="color:#1a1a1a;">{{wise_bic}}</div>
            </div>
        </div>
    </div>

    <hr class="divider" style="border:none;border-top:1px solid #e0e0e0;margin:0 0 15px 0;">

    <div class="balance-row" style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;font-size:16px;font-weight:700;">
        <div>{{wise_currency}}，{{wise_balance_date}} [{{wise_timezone}}]</div>
        <div>{{wise_balance}} {{wise_currency}}</div>
    </div>

    <hr class="divider" style="border:none;border-top:1px solid #e0e0e0;margin:0 0 15px 0;">

    <div class="table-header" style="display:flex;padding:10px 0;font-size:12px;font-weight:700;color:#4a4a4a;">
        <div class="th-desc" style="flex:4;">描述</div>
        <div class="th-in" style="flex:1;text-align:right;">汇入</div>
        <div class="th-out" style="flex:1;text-align:right;">汇出</div>
        <div class="th-bal" style="flex:1;text-align:right;">金额</div>
    </div>

    <hr class="divider" style="border:none;border-top:1px solid #e0e0e0;margin:0 0 15px 0;">

    <!-- 交易明细 -->
    {{wise_transactions}}

    <div class="footer-text" style="margin-top:60px;font-size:12px;color:#4a4a4a;line-height:1.6;">
        <p>Wise Payments Limited 以 Wise 名称交易，是由英国金融行为监管局 (FCA) 授权的电子货币机构，公司编号为 900507。Wise Payments Limited 通过 Companies House 在英格兰和威尔士注册，公司注册号为 7209813。电话: +44 (0) 203 6950 999</p>
        <p style="margin-top:15px;">需要帮助？ 请访问 <a href="https://wise.com/help" class="help-link" style="font-weight:700;color:#1a1a1a;text-decoration:underline;">wise.com/help</a></p>
    </div>

    <div class="absolute-footer" style="position:absolute;bottom:20mm;left:20mm;right:20mm;display:flex;justify-content:space-between;font-size:10px;color:#666666;">
        <span>ref:{{wise_ref}}</span>
        <span>1/1</span>
    </div>
</div>
HTML;
}
// ══════════════════════════════════
// 水/电/通用（不变）
// ══════════════════════════════════
function getCNWaterTemplate(): string {
    return <<<'HTML'
<div class="cn-bill" style="width:210mm;max-width:100%;margin:0 auto;font-family:'SimSun','Noto Sans SC',serif;">
  <div style="background:linear-gradient(135deg,#c62828,#d32f2f);color:#fff;padding:12px 20px;display:flex;align-items:center;justify-content:space-between;">
    <div style="display:flex;align-items:center;gap:10px;"><div style="width:42px;height:42px;background:rgba(255,255,255,0.2);border-radius:6px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-tint" style="font-size:20px;color:#fff;"></i></div><div><div style="font-size:17px;font-weight:700;letter-spacing:2px;">XX市自来水公司</div><div style="font-size:10px;opacity:0.85;">XX Municipal Water Supply Company</div></div></div>
    <div style="text-align:right;"><div style="font-size:14px;font-weight:700;">水费缴费通知单</div><div style="font-size:9px;opacity:0.85;">WATER BILL NOTICE</div></div>
  </div>
  <div style="padding:10px 20px 8px;border-bottom:2px solid #c62828;">
    <table style="width:100%;font-size:12px;border-collapse:collapse;">
      <tr><td style="width:16%;padding:3px 0;color:#888;">户名</td><td style="width:34%;font-weight:700;">{{customer_name}}</td><td style="width:16%;padding:3px 0;color:#888;">户号</td><td style="width:34%;font-weight:700;">{{account_number}}</td></tr>
      <tr><td style="padding:3px 0;color:#888;">地址</td><td colspan="3" style="font-weight:700;">{{full_address}}</td></tr>
      <tr><td style="padding:3px 0;color:#888;">账单编号</td><td>{{bill_number}}</td><td style="padding:3px 0;color:#888;">抄表日期</td><td>{{period_end}}</td></tr>
    </table>
  </div>
  <div style="padding:8px 16px 4px;"><div style="font-size:12px;font-weight:700;color:#c62828;border-bottom:1px solid #c62828;padding-bottom:2px;margin-bottom:6px;">◆ 用水明细</div>
    <table class="cn-table" style="width:100%;font-size:11px;"><tr><th style="width:25%;">水表编号</th><th style="width:25%;">上期读数</th><th style="width:25%;">本期读数</th><th style="width:25%;">用水量(m³)</th></tr>
    <tr><td style="text-align:center;">{{meter_number}}</td><td style="text-align:center;">{{meter_prev}}</td><td style="text-align:center;">{{meter_curr}}</td><td style="text-align:center;font-weight:700;color:#c62828;">{{consumption}}</td></tr></table>
  </div>
  <div style="padding:4px 16px;"><div style="font-size:12px;font-weight:700;color:#c62828;border-bottom:1px solid #c62828;padding-bottom:2px;margin-bottom:6px;">◆ 费用明细</div>
    <table class="cn-table" style="width:100%;font-size:11px;"><tr><th style="width:40%;">项目</th><th style="width:20%;">用量(m³)</th><th style="width:20%;">单价(元)</th><th style="width:20%;">金额(元)</th></tr>
    <tr><td>第一阶梯 (0-180m³/年)</td><td style="text-align:right;">{{t1}}</td><td style="text-align:right;">2.80</td><td style="text-align:right;">{{c1}}</td></tr>
    <tr><td>第二阶梯 (181-260m³/年)</td><td style="text-align:right;">{{t2}}</td><td style="text-align:right;">4.20</td><td style="text-align:right;">{{c2}}</td></tr>
    <tr><td>第三阶梯 (261m³以上/年)</td><td style="text-align:right;">{{t3}}</td><td style="text-align:right;">7.00</td><td style="text-align:right;">{{c3}}</td></tr>
    <tr style="background:#fff3e0;"><td>污水处理费</td><td style="text-align:right;">{{consumption}}</td><td style="text-align:right;">1.50</td><td style="text-align:right;">{{sewage_charge}}</td></tr>
    <tr style="background:#fff3e0;"><td>水资源费</td><td style="text-align:right;">{{consumption}}</td><td style="text-align:right;">0.50</td><td style="text-align:right;">{{c4}}</td></tr></table>
  </div>
  <div style="padding:8px 16px;"><table style="width:100%;font-size:13px;border-collapse:collapse;"><tr style="background:#c62828;color:#fff;"><td style="padding:8px 12px;font-weight:700;border:1px solid #c62828;">应缴总额</td><td style="padding:8px 12px;text-align:right;font-size:18px;font-weight:700;border:1px solid #c62828;">¥{{total_due}}</td></tr></table></div>
  <div style="padding:4px 20px 8px;font-size:10px;color:#666;line-height:1.8;"><strong>缴费说明：</strong><br/>1. 请于 {{surcharge_date}} 前完成缴费。2. 缴费方式：微信/支付宝/银行代扣/营业厅。3. 客服热线：96116</div>
  <div style="padding:6px 20px;border-top:1px solid #e0e0e0;font-size:9px;color:#999;text-align:center;">XX市自来水公司 · 打印日期：{{issue_date}} · 此单据由系统自动生成</div>
</div>
HTML;
}
function getCNWaterCSS(): string { return '.cn-bill .cn-table{width:100%;border-collapse:collapse;font-size:11px;}.cn-bill .cn-table th,.cn-bill .cn-table td{border:1px solid #ccc;padding:5px 8px;text-align:left;}.cn-bill .cn-table th{background:#f5f5f5;font-weight:600;font-size:10.5px;}'; }

function getCNElectricTemplate(): string {
    return <<<'HTML'
<div class="cn-bill" style="width:210mm;max-width:100%;margin:0 auto;font-family:'SimSun','Noto Sans SC',serif;">
  <div style="background:linear-gradient(135deg,#1b5e20,#2e7d32);color:#fff;padding:12px 20px;display:flex;align-items:center;justify-content:space-between;">
    <div style="display:flex;align-items:center;gap:10px;"><div style="width:42px;height:42px;background:rgba(255,255,255,0.2);border-radius:6px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-bolt" style="font-size:20px;color:#fff;"></i></div><div><div style="font-size:17px;font-weight:700;letter-spacing:2px;">XX市供电公司</div><div style="font-size:10px;opacity:0.85;">XX Municipal Power Supply</div></div></div>
    <div style="text-align:right;"><div style="font-size:14px;font-weight:700;">电费缴费通知单</div><div style="font-size:9px;opacity:0.85;">ELECTRICITY BILL NOTICE</div></div>
  </div>
  <div style="padding:10px 20px 8px;border-bottom:2px solid #2e7d32;">
    <table style="width:100%;font-size:12px;border-collapse:collapse;">
      <tr><td style="width:16%;padding:3px 0;color:#888;">户名</td><td style="width:34%;font-weight:700;">{{customer_name}}</td><td style="width:16%;padding:3px 0;color:#888;">户号</td><td style="width:34%;font-weight:700;">{{account_number}}</td></tr>
      <tr><td style="padding:3px 0;color:#888;">地址</td><td colspan="3" style="font-weight:700;">{{full_address}}</td></tr>
    </table>
  </div>
  <div style="padding:8px 16px 4px;"><div style="font-size:12px;font-weight:700;color:#2e7d32;border-bottom:1px solid #2e7d32;padding-bottom:2px;margin-bottom:6px;">◆ 用电明细</div>
    <table class="cn-table" style="width:100%;font-size:11px;"><tr><th>电表编号</th><th>上期读数</th><th>本期读数</th><th>用电量(kWh)</th></tr>
    <tr><td style="text-align:center;">{{meter_number}}</td><td style="text-align:center;">{{meter_prev}}</td><td style="text-align:center;">{{meter_curr}}</td><td style="text-align:center;font-weight:700;color:#2e7d32;">{{consumption}}</td></tr></table>
  </div>
  <div style="padding:4px 16px;"><div style="font-size:12px;font-weight:700;color:#2e7d32;border-bottom:1px solid #2e7d32;padding-bottom:2px;margin-bottom:6px;">◆ 费用明细</div>
    <table class="cn-table" style="width:100%;font-size:11px;"><tr><th style="width:40%;">项目</th><th style="width:20%;">用量</th><th style="width:20%;">单价</th><th style="width:20%;">金额</th></tr>
    <tr><td>第一阶梯</td><td style="text-align:right;">{{t1}}</td><td style="text-align:right;">0.52</td><td style="text-align:right;">{{c1}}</td></tr>
    <tr><td>第二阶梯</td><td style="text-align:right;">{{t2}}</td><td style="text-align:right;">0.57</td><td style="text-align:right;">{{c2}}</td></tr>
    <tr><td>第三阶梯</td><td style="text-align:right;">{{t3}}</td><td style="text-align:right;">0.82</td><td style="text-align:right;">{{c3}}</td></tr>
    <tr style="background:#e8f5e9;"><td>电网建设费</td><td style="text-align:right;">{{consumption}}</td><td style="text-align:right;">0.03</td><td style="text-align:right;">{{c4}}</td></tr>
    <tr style="background:#e8f5e9;"><td>可再生能源附加</td><td style="text-align:right;">{{consumption}}</td><td style="text-align:right;">0.01</td><td style="text-align:right;">{{sewage_charge}}</td></tr></table>
  </div>
  <div style="padding:8px 16px;"><table style="width:100%;font-size:13px;border-collapse:collapse;"><tr style="background:#2e7d32;color:#fff;"><td style="padding:8px 12px;font-weight:700;border:1px solid #2e7d32;">应缴总额</td><td style="padding:8px 12px;text-align:right;font-size:18px;font-weight:700;border:1px solid #2e7d32;">¥{{total_due}}</td></tr></table></div>
  <div style="padding:4px 20px 8px;font-size:10px;color:#666;line-height:1.8;"><strong>缴费说明：</strong><br/>1. 请于 {{surcharge_date}} 前完成缴费。2. 缴费方式：微信/支付宝/银行代扣/营业厅。3. 用电咨询：95598</div>
  <div style="padding:6px 20px;border-top:1px solid #e0e0e0;font-size:9px;color:#999;text-align:center;">XX市供电公司 · 打印日期：{{issue_date}}</div>
</div>
HTML;
}
function getCNElectricCSS(): string { return getCNWaterCSS(); }

function getWSDDefaultTemplate(): string {
    return <<<'HTML'
<div class="wsd-bill" style="width:210mm;max-width:100%;margin:0 auto;">
  <div class="wsd-header"><div class="logo-icon"><svg viewBox="0 0 40 40" width="40" height="40"><circle cx="20" cy="16" r="10" fill="none" stroke="rgba(255,255,255,0.9)" stroke-width="1.5"/><path d="M20 6 Q20 16 30 16" fill="none" stroke="rgba(255,255,255,0.9)" stroke-width="1.5"/><path d="M20 6 Q20 16 10 16" fill="none" stroke="rgba(255,255,255,0.9)" stroke-width="1.5"/><rect x="18" y="16" width="4" height="12" rx="1" fill="rgba(255,255,255,0.9)"/><rect x="14" y="28" width="12" height="3" rx="1.5" fill="rgba(255,255,255,0.9)"/><text x="20" y="38" text-anchor="middle" font-size="5" fill="rgba(255,255,255,0.7)" font-weight="bold">WSD</text></svg></div>
    <div style="flex:1;"><h1>水務署 <span style="font-weight:400;font-size:14px;">Water Supplies Department</span></h1><div style="display:flex;justify-content:space-between;align-items:baseline;margin-top:4px;"><div class="sub">付款通知書 PAYMENT NOTICE</div><div style="font-size:11px;opacity:0.9;">賬戶號碼: <strong style="font-size:13px;letter-spacing:1px;">{{account_number}}</strong></div></div></div></div>
  <div style="background:#fff8dc;border-bottom:1px solid #e0d8a0;padding:3px 12px;font-size:9.5px;color:#666;display:flex;justify-content:space-between;"><span>發出日期: <strong>{{issue_date}}</strong></span><span>通知書編號: {{bill_number}}</span></div>
  <div style="padding:10px 20px 8px;border-bottom:1px solid #ccc;"><div style="font-size:9px;color:#888;">收件人 To:</div><div style="font-size:13px;font-weight:700;">{{customer_name}}</div><div style="font-size:11.5px;color:#333;">{{full_address}}</div></div>
  <div style="padding:6px 12px;"><table class="wsd-table" style="font-size:10.5px;"><tr><th style="width:25%;">上次繳款日期</th><th style="width:25%;">上次繳款金額</th><th style="width:25%;">現存按金</th><th style="width:25%;">按金利息</th></tr><tr><td class="num">{{last_pay_date}}</td><td class="num">${{last_pay_amt}}</td><td class="num">${{deposit}}</td><td class="num">$0.00</td></tr></table></div>
  <div style="padding:4px 20px 2px;font-size:10px;color:#555;border-bottom:1px solid #eee;">用水樓宇地址: <strong>{{full_address}}</strong></div>
  <div style="padding:8px 16px 4px;"><div class="section-title">水錶讀數</div><table class="wsd-table" style="font-size:10.5px;"><tr><th style="width:20%;">水錶編號</th><th style="width:15%;">口徑</th><th style="width:25%;">上次讀數</th><th style="width:25%;">今次讀數</th><th style="width:15%;">用水量</th></tr><tr><td style="text-align:center;font-weight:600;">{{meter_number}}</td><td style="text-align:center;">15mm</td><td class="num">{{meter_prev}}</td><td class="num">{{meter_curr}}</td><td class="num" style="font-weight:700;color:#003d7c;">{{consumption}} m³</td></tr></table></div>
  <div style="padding:6px 16px 8px;display:flex;gap:16px;"><div style="flex:0 0 140px;"><div class="section-title">用水量分析</div><div style="font-size:10px;color:#555;">日數: {{days}} 總量: {{consumption}} m³<div style="margin-top:4px;padding:4px 8px;background:#e3f2fd;border-radius:4px;border:1px solid #90caf9;">每日平均<br/><span style="font-size:18px;font-weight:700;color:#003d7c;">{{daily_litres}}</span> 公升</div></div></div><div style="flex:1;padding-top:22px;">{{bar_chart_svg}}</div></div>
  <div class="save-banner">💧 每日慳水10公升 Save 10 Litres a Day 💧</div>
  <div style="padding:8px 16px 2px;"><div class="section-title">水費</div><table class="wsd-table" style="font-size:10.5px;"><tr><th style="width:40%;">級別</th><th style="width:20%;">用量</th><th style="width:20%;">費率</th><th style="width:20%;">金額</th></tr><tr><td>第一級 (12m³)</td><td class="num">{{t1}}</td><td class="num">0.00</td><td class="num">{{c1}}</td></tr><tr><td>第二級 (31m³)</td><td class="num">{{t2}}</td><td class="num">4.16</td><td class="num">{{c2}}</td></tr><tr><td>第三級 (19m³)</td><td class="num">{{t3}}</td><td class="num">6.45</td><td class="num">{{c3}}</td></tr><tr><td>第四級</td><td class="num">{{t4}}</td><td class="num">9.05</td><td class="num">{{c4}}</td></tr><tr style="background:#f0f4f8;font-weight:700;"><td>水費合計</td><td class="num">{{consumption}}</td><td></td><td class="num">${{water_charge}}</td></tr></table></div>
  <div style="padding:4px 16px 2px;"><div class="section-title">排污費</div><table class="wsd-table" style="font-size:10.5px;"><tr><th>項目</th><th>基礎</th><th>費率</th><th>金額</th></tr><tr><td>排污費</td><td class="num">${{water_charge}}</td><td class="num">25%</td><td class="num">${{sewage_charge}}</td></tr></table></div>
  <div style="padding:6px 16px;"><table class="wsd-table" style="font-size:12px;font-weight:700;"><tr style="background:#003d7c;color:#fff;"><td style="width:60%;border-color:#003d7c;padding:8px 12px;">應繳總額</td><td class="num" style="border-color:#003d7c;font-size:16px;padding:8px 12px;">{{currency}}{{total_due}}</td></tr></table></div>
  <div style="padding:4px 20px;font-size:9px;color:#666;border-top:1px solid #e0e0e0;margin:0 16px;">備註: 水費按四級制計算。排污費按水費25%。查詢2824 5000。</div>
  <div style="padding:8px 12px;"><div class="dashed-line"></div><div class="payment-slip"><div class="slip-title">繳款單</div><div style="padding:8px 12px;"><div style="display:flex;justify-content:space-between;"><div><div style="font-size:9px;color:#888;">繳款單編號</div><div style="font-size:12px;font-weight:700;">{{slip_ref}}</div></div><div style="text-align:right;"><div style="font-size:9px;color:#888;">賬戶號碼</div><div style="font-size:12px;font-weight:700;">{{account_number}}</div></div></div><div style="margin-top:6px;border-top:1px solid #ddd;padding-top:4px;display:flex;justify-content:space-between;align-items:center;"><div><div style="font-size:9px;color:#888;">應繳總額</div><div style="font-size:20px;font-weight:700;color:#003d7c;">{{currency}}{{total_due}}</div></div><div style="text-align:right;"><div style="font-size:9px;color:#888;">附加費徵收日</div><div style="font-size:12px;color:#c62828;">{{surcharge_date}}</div></div></div></div></div><div class="dashed-line"></div></div>
  <div style="padding:4px 16px 8px;display:flex;gap:12px;"><div style="flex:0 0 56px;">{{qr_code_svg}}<div style="font-size:7px;color:#888;text-align:center;">FPS</div></div><div style="flex:1;font-size:9px;color:#555;">繳費: AutoPay·PPS·網上·郵寄·便利店 參考: {{long_ref}} CRC: <span class="crc-box">{{crc_code}}</span></div><div style="font-size:8px;color:#888;text-align:right;">客戶編號<br/><span style="font-size:11px;font-weight:700;letter-spacing:2px;">{{account_number}}</span></div></div>
  <footer style="font-size:9px;color:#666;text-align:center;padding:6px;">水務署 · 灣仔告士打道7號 · 2824 5000 · www.wsd.gov.hk</footer>
</div>
HTML;
}
function getWSDDefaultCSS(): string { return '.wsd-bill{font-family:"Noto Sans SC",sans-serif;font-size:11px;color:#000;line-height:1.5;background:#fff;}.wsd-bill .wsd-header{background:linear-gradient(135deg,#003d7c,#005baa,#0066b8);color:#fff;padding:14px 20px 10px;display:flex;align-items:center;gap:12px;}.wsd-bill h1{font-size:18px;font-weight:700;margin:0;}.wsd-bill .sub{font-size:11px;opacity:0.85;}.wsd-bill .logo-icon{width:52px;height:52px;background:rgba(255,255,255,0.15);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}.wsd-bill table.wsd-table{width:100%;border-collapse:collapse;font-size:11px;}.wsd-bill table.wsd-table th,table.wsd-table td{border:1px solid #b0b0b0;padding:4px 8px;text-align:left;}.wsd-bill table.wsd-table th{background:#e8e8e8;font-weight:600;font-size:10.5px;}.wsd-bill table.wsd-table td.num{text-align:right;font-variant-numeric:tabular-nums;}.wsd-bill .section-title{font-size:11.5px;font-weight:700;margin:10px 0 4px;padding-bottom:2px;border-bottom:1.5px solid #003d7c;color:#003d7c;}.wsd-bill .save-banner{background:#0078c8;color:#fff;text-align:center;padding:6px 0;font-size:12px;font-weight:700;}.wsd-bill .save-banner .drop{color:#7fd4ff;}.wsd-bill .payment-slip{border:2px solid #666;margin:10px 0;}.wsd-bill .payment-slip .slip-title{background:#e8e8e8;font-weight:700;padding:3px 8px;font-size:11px;border-bottom:1px solid #999;text-align:center;}.wsd-bill .dashed-line{border-top:1.5px dashed #888;margin:8px 0;}.wsd-bill .crc-box{border:1.5px solid #000;padding:2px 6px;display:inline-block;font-family:"Courier New",monospace;font-size:11px;font-weight:700;}'; }

function getGenericTemplate(): string {
    return <<<'HTML'
<div style="font-family:'Noto Sans SC','Roboto',sans-serif;font-size:13px;color:#333;background:#fff;max-width:100%;">
  <div style="background:linear-gradient(135deg,#1976d2,#42a5f5);color:#fff;padding:16px 20px;border-radius:8px 8px 0 0;display:flex;align-items:center;gap:10px;">
    <div style="width:44px;height:44px;background:rgba(255,255,255,0.2);border-radius:8px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-file-invoice" style="font-size:20px;color:#fff;"></i></div>
    <div style="flex:1;"><div style="font-size:16px;font-weight:700;">{{bill_type_name}}</div></div>
    <div style="text-align:right;font-size:10px;"><div>編號: <strong>{{bill_number}}</strong></div><div>日期: {{issue_date}}</div></div>
  </div>
  <div style="padding:12px 20px;border-bottom:1px solid #e0e0e0;"><div style="font-size:9px;color:#888;">收件人</div><div style="font-weight:600;">{{customer_name}}</div><div style="font-size:12px;color:#555;">{{full_address}}</div></div>
  <div style="padding:10px 20px;font-size:11px;">週期: {{period_start}} — {{period_end}} 用量: {{consumption}}{{unit}} 金額: {{currency}}{{total_due}}</div>
  <div style="padding:10px 20px;background:#f5f5f5;display:flex;justify-content:space-between;"><span>應繳總額</span><span style="font-size:22px;font-weight:700;color:#c62828;">{{currency}}{{total_due}}</span></div>
</div>
HTML;
}

// ══════════════════════════════════
// PH SeaBank Statement
// ══════════════════════════════════
function getPHSeaBankTemplate(): string {
    return <<<'HTML'
<div class="seabank-wrap">
    <div class="statement-page">
        <!-- Header -->
        <div class="header">
            <div class="brand">
                <img src="flb.png" alt="SeaBank Logo" class="logo-img">
            </div>
            <div class="statement-meta">
                BANK STATEMENT<br>
                {{bill_number}}<br>
                {{issue_date}}
            </div>
        </div>

        <!-- Information Section -->
        <div class="info-grid">
            <div class="customer-info">
                <h1>{{customer_name}}</h1>
                <p>
                    SEABANK ACCOUNT: {{account_number}}<br>
                    {{address_unit}}<br>
                    {{address_street}}<br>
                    {{address_district}}<br>
                    {{country}}
                </p>
            </div>
            <div class="contact-info">
                <p>Contact Us</p>
                <p>
                    Call 1500 130<br>
                    <span class="indent">0800 1500 130 toll-free</span>
                    <span class="indent">+6221 5086 7070 from overseas</span>
                </p>
                <p>Email cs@seabank.co.id</p>
                <p>Find us on live chat in SeaBank app</p>
            </div>
        </div>

        <!-- Account Summary Section -->
        <div class="section-header">
            <h2 class="section-title">ACCOUNT SUMMARY</h2>
            <p class="section-subtitle">{{period_start}} to {{period_end}}</p>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ACCOUNT</th>
                        <th class="text-right">STARTING BALANCE ({{currency}})</th>
                        <th class="text-right">TOTAL OUTGOING ({{currency}})</th>
                        <th class="text-right">TOTAL INCOMING ({{currency}})</th>
                        <th class="text-right">ENDING BALANCE ({{currency}})</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>SAVINGS</td>
                        <td class="text-right">{{opening_balance}}</td>
                        <td class="text-right">{{total_debits}}</td>
                        <td class="text-right">{{total_credits}}</td>
                        <td class="text-right">{{closing_balance}}</td>
                    </tr>
                </tbody>
            </table>
            <div class="summary-footer">
                TOTAL: 0
            </div>
        </div>

        <!-- Transaction Details Section -->
        <div class="section-header">
            <h2 class="section-title">SAVINGS - TRANSACTION DETAILS</h2>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>DATE</th>
                        <th>TRANSACTION</th>
                        <th class="text-right">OUTGOING ({{currency}})</th>
                        <th class="text-right">INCOMING ({{currency}})</th>
                    </tr>
                </thead>
                <tbody>
                    {{transactions}}
                </tbody>
            </table>
        </div>

        <!-- Interest & Tax Details Section Placeholder -->
        <div class="section-header">
            <h2 class="section-title">SAVINGS - INTEREST & TAX DETAILS</h2>
        </div>

        <!-- Footer -->
        <div class="page-footer">
            page 1 of 1
        </div>
    </div>
</div>
HTML;
}

function getPHSeaBankCSS(): string {
    return <<<'CSS'
.seabank-wrap {
    padding: 20px;
    background-color: #eef2f5;
    font-family: Arial, Helvetica, sans-serif;
    display: flex;
    justify-content: center;
}
.seabank-wrap * {
    box-sizing: border-box;
}
.seabank-wrap .statement-page {
    width: 850px;
    background-color: #ffffff;
    padding: 50px 60px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    position: relative;
    min-height: 1100px;
    color: #1a1a1a;
}
.seabank-wrap .header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 50px;
}
.seabank-wrap .brand {
    display: flex;
    align-items: center;
    height: 60px;
    overflow: visible;
}
.seabank-wrap .logo-img {
    width: 260px;
    height: auto;
    object-fit: contain;
    margin-left: -40px;
    margin-top: -10px;
}
.seabank-wrap .statement-meta {
    text-align: right;
    color: #999;
    font-size: 14px;
    line-height: 1.5;
    letter-spacing: 0.5px;
}
.seabank-wrap .info-grid {
    display: flex;
    justify-content: space-between;
    margin-bottom: 50px;
}
.seabank-wrap .customer-info {
    flex: 1;
}
.seabank-wrap .customer-info h1 {
    font-size: 20px;
    margin: 0 0 12px 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #000;
}
.seabank-wrap .customer-info p {
    margin: 0;
    font-size: 12px;
    line-height: 1.6;
    text-transform: uppercase;
}
.seabank-wrap .contact-info {
    width: 320px;
    border-left: 1px solid #eaeaea;
    padding-left: 25px;
    font-size: 12px;
    line-height: 1.6;
}
.seabank-wrap .contact-info p {
    margin: 0 0 12px 0;
}
.seabank-wrap .contact-info p:last-child {
    margin-bottom: 0;
}
.seabank-wrap .indent {
    display: block;
    margin-left: 30px;
}
.seabank-wrap .section-header {
    text-align: center;
    margin: 35px 0 20px 0;
}
.seabank-wrap .section-title {
    font-size: 18px;
    font-weight: normal;
    margin: 0 0 8px 0;
    letter-spacing: 0.5px;
}
.seabank-wrap .section-subtitle {
    font-size: 12px;
    color: #999;
    margin: 0;
    text-transform: uppercase;
}
.seabank-wrap .table-container {
    border: 1px solid #eaeaea;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 35px;
}
.seabank-wrap table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.seabank-wrap th {
    background-color: #f7f7f7;
    color: #555;
    font-weight: normal;
    text-align: left;
    padding: 14px 15px;
    border-bottom: 1px solid #eaeaea;
}
.seabank-wrap td {
    padding: 18px 15px;
    border-bottom: 1px solid #eaeaea;
    vertical-align: top;
    line-height: 1.5;
}
.seabank-wrap tr:last-child td {
    border-bottom: none;
}
.seabank-wrap .text-right {
    text-align: right;
}
.seabank-wrap .col-gray {
    color: #888;
    font-size: 11px;
    display: block;
    margin-top: 4px;
}
.seabank-wrap .summary-footer {
    background-color: #f4f4f4;
    text-align: right;
    padding: 14px 15px;
    font-size: 12px;
    border-top: 1px solid #eaeaea;
    color: #000;
}
.seabank-wrap .page-footer {
    position: absolute;
    bottom: 40px;
    right: 60px;
    color: #999;
    font-size: 13px;
    text-align: right;
}
CSS;
}

// ══════════════════════════════════
// DE Monese EUR Statement (德国)
// ══════════════════════════════════
function getDEMoneseTemplate(): string {
    return <<<'HTML'
<div class="monese-stmt page">
  <header class="header">
    <div class="logo">
      <svg viewBox="0 0 48 40" xmlns="http://www.w3.org/2000/svg" aria-label="Monese logo">
        <g fill="none" stroke="#28628a" stroke-width="3.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3.5 34V8.5c0-4 4.8-6 7.7-3.1L24 18.4 36.8 5.3c2.9-2.9 7.7-.9 7.7 3.1V34"/>
          <path d="M4.7 6.6 20.8 33c1.5 2.5 4.9 2.6 6.5.1L43.2 6.6"/>
          <path d="M4.2 33.2c1.7 2 4.8 2.1 6.7.2L24 20.2l13.1 13.2c1.8 1.9 4.9 1.8 6.7-.2"/>
        </g>
      </svg>
      <span class="logo-name">monese</span>
    </div>
    <div class="address-block">
      <div class="info-title">{{customer_name}}</div>
      <div>{{address_street}}</div>
      <div>{{postal_code}}</div>
      <div>{{address_district}}</div>
    </div>
    <div class="bank-block">
      <div class="info-title">IBAN</div>
      <div>{{iban}}</div>
      <div class="bank-space"></div>
      <div class="info-title">BIC</div>
      <div>{{bic}}</div>
      <div class="reference">
        <div class="info-title">Monese kenn-nr.</div>
        <div>{{account_number}}</div>
      </div>
    </div>
  </header>

  <section class="account-summary">
    <h1>EUR-Kontoauszug</h1>
    <div class="period">{{statement_period}}</div>
    <div class="summary-table">
      <div class="summary-row opening">
        <div>Neuer kontostand</div>
        <div class="amount">{{currency}}{{opening_balance}}</div>
      </div>
      <div class="summary-row first-detail">
        <div>Zahlungseingänge</div>
        <div class="amount">+{{currency}}{{total_credits}}</div>
      </div>
      <div class="summary-row">
        <div>Zahlungsausgänge</div>
        <div class="amount">-{{currency}}{{total_debits}}</div>
      </div>
      <div class="summary-row pending">
        <div>Vorgemerkte zahlungen</div>
        <div class="amount">-{{currency}}0.00</div>
      </div>
      <div class="summary-row final">
        <div>"Neuer kontostand"</div>
        <div class="amount">{{currency}}{{closing_balance}}</div>
      </div>
    </div>
  </section>

  <section class="transactions">
    <h2>Transaktionen</h2>
    <div class="transaction-table">
      <div class="table-header">
        <div>Bearbeitungsdatum</div>
        <div>Datum der zahlung</div>
        <div>Beschreibung</div>
        <div>"Betrag"</div>
        <div>"Kontostand"</div>
      </div>
      {{transactions}}
    </div>
  </section>
</div>
HTML;
}

function getDEMoneseCSS(): string {
    return <<<'CSS'
.monese-stmt { position: relative; width: 848px; min-height: 1216px; background: #ffffff; overflow: hidden; font-family: "Arial Narrow", Arial, Helvetica, sans-serif; color: #080808; margin: 0 auto; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.monese-stmt * { box-sizing: border-box; }
.monese-stmt .header { position: absolute; top: 47px; left: 40px; right: 40px; height: 145px; }
.monese-stmt .logo { position: absolute; top: 0; left: 0; display: flex; align-items: center; color: #28628a; }
.monese-stmt .logo svg { display: block; width: 40px; height: 36px; margin-right: 8px; }
.monese-stmt .logo-name { position: relative; top: -1px; font-size: 28px; line-height: 1; font-weight: 600; letter-spacing: -1px; }
.monese-stmt .address-block, .monese-stmt .bank-block { position: absolute; top: 0; font-size: 12px; line-height: 18px; }
.monese-stmt .address-block { left: 430px; width: 160px; }
.monese-stmt .bank-block { left: 602px; width: 165px; }
.monese-stmt .info-title { font-weight: 700; }
.monese-stmt .bank-space { height: 10px; }
.monese-stmt .reference { margin-top: 15px; }
.monese-stmt .account-summary { position: absolute; top: 274px; left: 40px; width: 423px; }
.monese-stmt .account-summary h1 { margin: 0; font-size: 34px; line-height: 42px; font-weight: 400; letter-spacing: -1px; }
.monese-stmt .period { margin-top: 7px; font-size: 19px; line-height: 25px; }
.monese-stmt .summary-table { margin-top: 43px; width: 100%; font-size: 14px; }
.monese-stmt .summary-row { display: grid; grid-template-columns: 1fr 115px; align-items: center; min-height: 42px; }
.monese-stmt .summary-row .amount { text-align: right; }
.monese-stmt .summary-row.opening { min-height: 63px; border-top: 1.5px solid #111111; border-bottom: 1px solid #b7b7b7; font-weight: 700; }
.monese-stmt .summary-row.first-detail { padding-top: 10px; }
.monese-stmt .summary-row.pending { padding-bottom: 11px; }
.monese-stmt .summary-row.final { min-height: 58px; border-top: 1px solid #b7b7b7; font-weight: 700; }
.monese-stmt .transactions { position: absolute; top: 749px; left: 40px; width: 763px; }
.monese-stmt .transactions h2 { margin: 0; font-size: 24px; line-height: 30px; font-weight: 400; }
.monese-stmt .transaction-table { margin-top: 33px; width: 100%; font-size: 14px; }
.monese-stmt .table-header, .monese-stmt .transaction-row { display: grid; grid-template-columns: 153px 153px 1fr 100px 107px; }
.monese-stmt .table-header { min-height: 77px; align-items: center; border-top: 1.5px solid #111111; border-bottom: 1.5px solid #111111; font-weight: 700; }
.monese-stmt .table-header > div:nth-child(4), .monese-stmt .table-header > div:nth-child(5) { text-align: right; }
.monese-stmt .transaction-row { position: relative; align-items: start; min-height: 93px; padding-top: 21px; }
.monese-stmt .transaction-row.last { border-top: 1px solid #c8c8c8; }
.monese-stmt .date, .monese-stmt .payment-date, .monese-stmt .transaction-amount, .monese-stmt .balance { line-height: 18px; white-space: nowrap; }
.monese-stmt .transaction-amount, .monese-stmt .balance { text-align: right; }
.monese-stmt .description { padding-right: 14px; line-height: 17px; }
.monese-stmt .description-title { font-size: 14px; line-height: 18px; }
.monese-stmt .description-detail { margin-top: 2px; font-size: 11px; line-height: 14px; overflow-wrap: anywhere; }
CSS;
}

// ─── 辅助函数 ───
function getRegions(): array { $db=getDB();$r=$db->query('SELECT * FROM regions WHERE is_active=1 ORDER BY sort_order');return $r->fetchAll(); }
function getBillTypesByRegion(int $rid): array { $db=getDB();$s=$db->prepare('SELECT * FROM bill_types WHERE region_id=:rid AND is_active=1 ORDER BY sort_order');$s->execute([':rid'=>$rid]);return $s->fetchAll(); }
function getTemplate(int $btid): ?array { $db=getDB();$s=$db->prepare('SELECT * FROM templates WHERE bill_type_id=:b AND is_active=1 LIMIT 1');$s->execute([':b'=>$btid]);$row=$s->fetch();return $row?:null; }
function getAllBillTypes(): array { $db=getDB();$r=$db->query('SELECT bt.*,r.name as region_name,r.code as region_code FROM bill_types bt JOIN regions r ON bt.region_id=r.id ORDER BY r.sort_order,bt.sort_order');return $r->fetchAll(); }
function getAllTemplates(): array { $db=getDB();$r=$db->query('SELECT t.*,bt.name as bt_name,bt.category,r.name as region_name FROM templates t JOIN bill_types bt ON t.bill_type_id=bt.id JOIN regions r ON bt.region_id=r.id ORDER BY r.sort_order,bt.sort_order');return $r->fetchAll(); }
function renderTemplate(string $t,array $d): string { return preg_replace_callback('/\{\{(\w+)\}\}/',function($m)use($d){return isset($d[$m[1]])?(string)$d[$m[1]]:'{{'.$m[1].'}}';},$t); }
function startSession(): void {
    // 如果当前已有其他名称的 Session 在运行（例如前台用户 Session），先关闭再开启管理员 Session
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (session_name() !== SESSION_NAME) {
            session_write_close();
        } else {
            return; // 已经是管理员 Session，无需操作
        }
    }
    session_name(SESSION_NAME);
    session_start();
}
function isLoggedIn(): bool { startSession();return isset($_SESSION['logged_in'])&&$_SESSION['logged_in']===true; }
function requireLogin(): void { if(!isLoggedIn()){header('Location: admin.php');exit;} }
function doLogin(string $u,string $p): bool {
    $db = getDB();
    $st = $db->prepare('SELECT id, username, password FROM admin_users WHERE username = :u LIMIT 1');
    $st->execute([':u' => $u]);
    $admin = $st->fetch();
    if ($admin && password_verify($p, $admin['password'])) {
        startSession();
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $admin['username'];
        return true;
    }
    return false;
}
function doLogout(): void {
    // 确保销毁的是管理员 Session
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    } elseif (session_name() !== SESSION_NAME) {
        session_write_close();
        session_name(SESSION_NAME);
        session_start();
    }
    $_SESSION = [];
    session_destroy();
}
function jsonResponse(array $d,int $c=200): void { http_response_code($c);header('Content-Type: application/json; charset=utf-8');echo json_encode($d,JSON_UNESCAPED_UNICODE);exit; }

// ══════════════════════════════════
// 用户系统函数
// ══════════════════════════════════
function startUserSession(): void {
    // 如果当前已有其他名称的 Session 在运行（例如管理员 Session），先关闭再开启用户 Session
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (session_name() !== USER_SESSION_NAME) {
            session_write_close();
        } else {
            return; // 已经是用户 Session，无需操作
        }
    }
    session_name(USER_SESSION_NAME);
    session_start();
}

function isUserLoggedIn(): bool {
    startUserSession();
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

function getCurrentUserId(): ?int {
    return isUserLoggedIn() ? (int)$_SESSION['user_id'] : null;
}

function requireUserLogin(): void {
    if (!isUserLoggedIn()) {
        jsonResponse(['ok' => false, 'error' => '请先登录', 'need_login' => true], 401);
    }
}

function doUserRegister(string $username, string $password, string $email = ''): array {
    $db = getDB();
    $username = trim($username);
    $email = trim($email);
    if (mb_strlen($username) < 2) return ['ok' => false, 'error' => '用户名至少2个字符'];
    if (mb_strlen($password) < 6) return ['ok' => false, 'error' => '密码至少6个字符'];

    // 检查是否已存在
    $st = $db->prepare('SELECT COUNT(*) FROM users WHERE username = :u');
    $st->execute([':u' => $username]);
    if ($st->fetchColumn() > 0) return ['ok' => false, 'error' => '用户名已存在'];

    // 检查邮箱是否已存在
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $st = $db->prepare('SELECT COUNT(*) FROM users WHERE email = :e');
        $st->execute([':e' => $email]);
        if ($st->fetchColumn() > 0) return ['ok' => false, 'error' => '邮箱已被注册'];
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $bonus = REGISTER_BONUS;
    $st = $db->prepare('INSERT INTO users (username, password, email, coins_usdc, coins_usdt) VALUES (:u, :p, :e, :c, 0)');
    $st->execute([':u' => $username, ':p' => $hash, ':e' => $email, ':c' => $bonus]);

    $userId = (int)$db->lastInsertId();
    startUserSession();
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;

    return ['ok' => true, 'user_id' => $userId, 'username' => $username, 'coins_usdc' => $bonus, 'coins_usdt' => 0, 'message' => '注册成功，赠送 ' . $bonus . ' USDC'];
}

function doUserLogin(string $username, string $password): array {
    $db = getDB();
    $st = $db->prepare('SELECT id, username, password, coins_usdc, coins_usdt FROM users WHERE username = :u');
    $st->execute([':u' => trim($username)]);
    $user = $st->fetch();
    if (!$user || !password_verify($password, $user['password'])) {
        return ['ok' => false, 'error' => '用户名或密码错误'];
    }
    startUserSession();
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    return ['ok' => true, 'user_id' => (int)$user['id'], 'username' => $user['username'], 'coins_usdc' => (int)$user['coins_usdc'], 'coins_usdt' => (int)$user['coins_usdt']];
}

function doUserLogout(): void {
    // 确保销毁的是用户 Session
    if (session_status() === PHP_SESSION_NONE) {
        session_name(USER_SESSION_NAME);
        session_start();
    } elseif (session_name() !== USER_SESSION_NAME) {
        session_write_close();
        session_name(USER_SESSION_NAME);
        session_start();
    }
    $_SESSION = [];
    session_destroy();
}

function getUserInfo(int $userId): ?array {
    $db = getDB();
    $st = $db->prepare('SELECT id, username, email, coins_usdc, coins_usdt, download_count, download_credits, created_at FROM users WHERE id = :id');
    $st->execute([':id' => $userId]);
    $row = $st->fetch();
    return $row ?: null;
}

function getUserBalance(int $userId, string $coinType): int {
    $db = getDB();
    $col = ($coinType === 'USDT') ? 'coins_usdt' : 'coins_usdc';
    $st = $db->prepare("SELECT {$col} FROM users WHERE id = :id");
    $st->execute([':id' => $userId]);
    return (int)$st->fetchColumn();
}

// 模拟充值 — 创建待支付订单
function doCreateTopUpOrder(int $userId, string $coinType, string $packageName, int $coinAmount, float $price, string $network): array {
    if (!in_array($coinType, SUPPORTED_COINS)) return ['ok' => false, 'error' => '不支持的币种'];

    $db = getDB();
    $txHash = '0x' . bin2hex(random_bytes(32)) . substr(md5((string)time()), 0, 8);
    $st = $db->prepare('INSERT INTO orders (user_id, coin_type, package_name, coin_amount, price, network, tx_hash, status) VALUES (:u, :ct, :pn, :ca, :p, :nw, :tx, "pending")');
    $st->execute([
        ':u' => $userId, ':ct' => $coinType, ':pn' => $packageName,
        ':ca' => $coinAmount, ':p' => $price, ':nw' => $network, ':tx' => $txHash,
    ]);
    $orderId = (int)$db->lastInsertId();
    return ['ok' => true, 'order_id' => $orderId, 'coin_type' => $coinType, 'coin_amount' => $coinAmount, 'price' => $price, 'network' => $network, 'tx_hash' => $txHash];
}

// 模拟链上支付 — 模拟交易确认 + 到账
function doPayOrder(int $userId, int $orderId): array {
    $db = getDB();

    $st = $db->prepare('SELECT * FROM orders WHERE id = :oid AND user_id = :uid');
    $st->execute([':oid' => $orderId, ':uid' => $userId]);
    $order = $st->fetch();
    if (!$order) return ['ok' => false, 'error' => '订单不存在'];
    if ($order['status'] === 'paid') return ['ok' => false, 'error' => '订单已支付'];
    if ($order['status'] === 'cancelled') return ['ok' => false, 'error' => '订单已取消'];

    $coinType = $order['coin_type'] ?? 'USDC';
    $col = ($coinType === 'USDT') ? 'coins_usdt' : 'coins_usdc';

    $db->beginTransaction();
    try {
        $st = $db->prepare('UPDATE orders SET status = "paid", paid_at = NOW() WHERE id = :oid');
        $st->execute([':oid' => $orderId]);

        $st = $db->prepare("UPDATE users SET {$col} = {$col} + :c WHERE id = :id");
        $st->execute([':c' => (int)$order['coin_amount'], ':id' => $userId]);

        $db->commit();
        $balance = getUserBalance($userId, $coinType);
        return ['ok' => true, 'coin_type' => $coinType, 'coins' => $balance, 'added' => (int)$order['coin_amount'], 'message' => '转账成功！获得 ' . $order['coin_amount'] . ' ' . $coinType];
    } catch (Throwable $e) {
        $db->rollBack();
        return ['ok' => false, 'error' => '链上交易失败，请重试'];
    }
}

// 扣减代币并记录日志（按币种优先扣 USDC，不够则扣 USDT）
function deductDownloadCoin(int $userId, int $billTypeId, string $coinType): array {
    $db = getDB();
    $col = ($coinType === 'USDT') ? 'coins_usdt' : 'coins_usdc';
    $balance = getUserBalance($userId, $coinType);
    if ($balance < DOWNLOAD_COST) {
        return ['ok' => false, 'error' => $coinType . ' 余额不足，请先充值', 'coins' => $balance, 'need_topup' => true];
    }

    $db->beginTransaction();
    try {
        $st = $db->prepare("UPDATE users SET {$col} = {$col} - :c WHERE id = :id");
        $st->execute([':c' => DOWNLOAD_COST, ':id' => $userId]);
        $st = $db->prepare('INSERT INTO download_logs (user_id, bill_type_id, coin_type, coins_used) VALUES (:u, :b, :ct, :c)');
        $st->execute([':u' => $userId, ':b' => $billTypeId, ':ct' => $coinType, ':c' => DOWNLOAD_COST]);
        $db->commit();
        return ['ok' => true, 'coin_type' => $coinType, 'coins' => getUserBalance($userId, $coinType), 'used' => DOWNLOAD_COST];
    } catch (Throwable $e) {
        $db->rollBack();
        return ['ok' => false, 'error' => '扣费失败，请重试'];
    }
}

// 获取套餐列表（USDC / USDT 通用）
function getCoinPackages(string $coinType = 'USDC'): array {
    $c = strtoupper($coinType);
    return [
        ['id' => 'pkg_10', 'name' => '10 ' . $c, 'coins' => 10, 'price' => '$ 10.00', 'price_num' => 10.00, 'price_desc' => '$ 1.00 / ' . $c, 'badge' => '体验'],
        ['id' => 'pkg_30', 'name' => '30 ' . $c, 'coins' => 30, 'price' => '$ 28.50', 'price_num' => 28.50, 'price_desc' => '$ 0.95 / ' . $c, 'badge' => '推荐'],
        ['id' => 'pkg_60', 'name' => '60 ' . $c, 'coins' => 60, 'price' => '$ 54.00', 'price_num' => 54.00, 'price_desc' => '$ 0.90 / ' . $c, 'badge' => '划算'],
        ['id' => 'pkg_150', 'name' => '150 ' . $c, 'coins' => 150, 'price' => '$ 120.00', 'price_num' => 120.00, 'price_desc' => '$ 0.80 / ' . $c, 'badge' => '最值'],
    ];
}

// 支持的链
function getNetworks(): array {
    return ['ERC20' => 'Ethereum (ERC20)', 'TRC20' => 'Tron (TRC20)', 'BEP20' => 'BSC (BEP20)', 'SOL' => 'Solana'];
}

// ══════════════════════════════════
// 管理员用户管理函数
// ══════════════════════════════════
function adminGetAllUsers(): array {
    $db = getDB();
    $rows = $db->query('
        SELECT u.id, u.username, u.email, u.coins_usdc, u.coins_usdt, u.download_count, u.created_at,
               (SELECT COUNT(*) FROM orders WHERE user_id = u.id AND status = "paid") as order_count,
               (SELECT SUM(coin_amount) FROM orders WHERE user_id = u.id AND status = "paid") as total_topup,
               (SELECT COUNT(*) FROM download_logs WHERE user_id = u.id) as total_downloads
        FROM users u ORDER BY u.id DESC
    ')->fetchAll();
    return $rows ?: [];
}

function adminGetUserDetail(int $userId): ?array {
    $db = getDB();
    $st = $db->prepare('SELECT id, username, email, coins_usdc, coins_usdt, download_count, download_credits, created_at FROM users WHERE id = :id');
    $st->execute([':id' => $userId]);
    $user = $st->fetch();
    if (!$user) return null;

    // 订单记录
    $st = $db->prepare('SELECT * FROM orders WHERE user_id = :uid ORDER BY created_at DESC LIMIT 100');
    $st->execute([':uid' => $userId]);
    $user['orders'] = $st->fetchAll();

    // 下载记录
    $st = $db->prepare('SELECT dl.*, bt.name as bill_type_name FROM download_logs dl LEFT JOIN bill_types bt ON dl.bill_type_id = bt.id WHERE dl.user_id = :uid ORDER BY dl.created_at DESC LIMIT 100');
    $st->execute([':uid' => $userId]);
    $user['download_logs'] = $st->fetchAll();

    return $user;
}

function adminUpdateUser(int $userId, array $data): array {
    $db = getDB();
    $updates = [];
    $params = [':id' => $userId];

    if (isset($data['username']) && trim($data['username']) !== '') {
        // 检查用户名是否被其他用户占用
        $st = $db->prepare('SELECT id FROM users WHERE username = :u AND id != :id');
        $st->execute([':u' => trim($data['username']), ':id' => $userId]);
        if ($st->fetch()) return ['ok' => false, 'error' => '用户名已被其他用户使用'];

        $updates[] = 'username = :u';
        $params[':u'] = trim($data['username']);
    }
    if (isset($data['email'])) {
        $updates[] = 'email = :e';
        $params[':e'] = trim($data['email']);
    }
    if (isset($data['coins_usdc'])) {
        $updates[] = 'coins_usdc = :c1';
        $params[':c1'] = max(0, (int)$data['coins_usdc']);
    }
    if (isset($data['coins_usdt'])) {
        $updates[] = 'coins_usdt = :c2';
        $params[':c2'] = max(0, (int)$data['coins_usdt']);
    }
    if (isset($data['password']) && trim($data['password']) !== '') {
        if (mb_strlen(trim($data['password'])) < 6) return ['ok' => false, 'error' => '密码至少6个字符'];
        $updates[] = 'password = :p';
        $params[':p'] = password_hash(trim($data['password']), PASSWORD_BCRYPT);
    }

    if (empty($updates)) return ['ok' => false, 'error' => '没有要更新的字段'];

    $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :id';
    $db->prepare($sql)->execute($params);
    return ['ok' => true, 'message' => '用户信息已更新'];
}

function adminDeleteUser(int $userId): array {
    $db = getDB();
    $st = $db->prepare('SELECT COUNT(*) FROM users WHERE id = :id');
    $st->execute([':id' => $userId]);
    if ($st->fetchColumn() == 0) return ['ok' => false, 'error' => '用户不存在'];

    $db->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $userId]);
    return ['ok' => true, 'message' => '用户已删除'];
}

function adminAddCoins(int $userId, int $amount, string $coinType = 'USDC', string $reason = '管理员充值'): array {
    if (!in_array($coinType, SUPPORTED_COINS)) return ['ok' => false, 'error' => '不支持的币种'];
    if ($amount <= 0) return ['ok' => false, 'error' => '充值金额必须大于0'];

    $db = getDB();
    $st = $db->prepare('SELECT COUNT(*) FROM users WHERE id = :id');
    $st->execute([':id' => $userId]);
    if ($st->fetchColumn() == 0) return ['ok' => false, 'error' => '用户不存在'];

    $col = ($coinType === 'USDT') ? 'coins_usdt' : 'coins_usdc';

    $st = $db->prepare('INSERT INTO orders (user_id, coin_type, package_name, coin_amount, price, network, status, paid_at) VALUES (:u, :ct, :pn, :ca, 0.00, "ADMIN", "paid", NOW())');
    $st->execute([':u' => $userId, ':ct' => $coinType, ':pn' => $reason, ':ca' => $amount]);

    $st = $db->prepare("UPDATE users SET {$col} = {$col} + :c WHERE id = :id");
    $st->execute([':c' => $amount, ':id' => $userId]);

    $newBalance = getUserBalance($userId, $coinType);
    return ['ok' => true, 'added' => $amount, 'coin_type' => $coinType, 'coins' => $newBalance, 'message' => "成功为用户充值 {$amount} " . $coinType];
}

// 获取用户统计摘要
function adminGetUserStats(): array {
    $db = getDB();
    return [
        'total_users' => (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'total_topup_usdc' => (int)$db->query('SELECT COALESCE(SUM(coin_amount), 0) FROM orders WHERE status = "paid" AND coin_type = "USDC"')->fetchColumn(),
        'total_topup_usdt' => (int)$db->query('SELECT COALESCE(SUM(coin_amount), 0) FROM orders WHERE status = "paid" AND coin_type = "USDT"')->fetchColumn(),
        'total_downloads' => (int)$db->query('SELECT COUNT(*) FROM download_logs')->fetchColumn(),
    ];
}
