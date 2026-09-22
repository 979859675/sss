<?php
/**
 * 枪王 - API v5 (MySQL/PDO)
 * ⚠️ 仅限学习演示用途
 */
declare(strict_types=1);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$action = $_GET['action'] ?? '';
try {
    $db = getDB();
    match($action) {
        'regions'=>handleRegions($db),'region_create'=>handleRegionCreate($db),'region_update'=>handleRegionUpdate($db),'region_delete'=>handleRegionDelete($db),
        'bill_types'=>handleBillTypes($db),'bill_type_create'=>handleBillTypeCreate($db),'bill_type_update'=>handleBillTypeUpdate($db),'bill_type_delete'=>handleBillTypeDelete($db),
        'template'=>handleTemplate($db),'template_save'=>handleTemplateSave($db),'template_reset'=>handleTemplateReset($db),'template_delete'=>handleTemplateDelete($db),'all_templates'=>handleAllTemplates($db),
        'frontend_data'=>handleFrontendData($db),'generate_bill'=>handleGenerateBill($db),
        'login'=>handleLogin(),'logout'=>handleLogout(),'check_auth'=>handleCheckAuth(),
        'user_register'=>handleUserRegister(),'user_login'=>handleUserLoginApi(),'user_logout'=>handleUserLogoutApi(),'user_info'=>handleUserInfoApi(),'user_topup'=>handleUserCreateOrder(),'user_pay'=>handleUserPayOrder(),'packages'=>handlePackages(),'deduct_coin'=>handleDeductCoin(),
        'admin_users'=>handleAdminUsers(),'admin_user_detail'=>handleAdminUserDetail(),'admin_user_update'=>handleAdminUserUpdate(),'admin_user_delete'=>handleAdminUserDelete(),'admin_add_coins'=>handleAdminAddCoins(),'admin_user_stats'=>handleAdminUserStats(),
        'site_config'=>handleSiteConfig($db),'site_config_save'=>handleSiteConfigSave($db),
        'admin_change_password'=>handleAdminChangePassword(),
        'blogger_apply' => handleBloggerApply(),
        'admin_bloggers' => handleAdminBloggers(),
        'admin_review_blogger' => handleAdminReviewBlogger(),
        'blogger_login' => handleBloggerLogin(),
        'blogger_logout' => handleBloggerLogout(),
        'blogger_codes' => handleBloggerCodes(),
        'blogger_create_code' => handleBloggerCreateCode(),
        'blogger_toggle_code' => handleBloggerToggleCode(),
        'redeem_code' => handleRedeemCode(),
        default=>jsonResponse(['error'=>'Unknown action'],400),
    };
} catch (Throwable $e) { jsonResponse(['error' => $e->getMessage()], 500); }

function handleRegions(PDO $db): void { $r=$db->query('SELECT * FROM regions ORDER BY sort_order');jsonResponse(['ok'=>true,'data'=>$r->fetchAll()]); }
function handleRegionCreate(PDO $db): void { requireLogin();$d=getJsonInput();$s=$db->prepare('INSERT INTO regions (code,name,flag_emoji,sort_order) VALUES (:c,:n,:f,:s)');$s->execute([':c'=>$d['code']??'',':n'=>$d['name']??'',':f'=>$d['flag_emoji']??'🏳️',':s'=>(int)($d['sort_order']??0)]);jsonResponse(['ok'=>true,'id'=>(int)$db->lastInsertId()]); }
function handleRegionUpdate(PDO $db): void { requireLogin();$d=getJsonInput();$s=$db->prepare('UPDATE regions SET code=:c,name=:n,flag_emoji=:f,sort_order=:s,is_active=:a WHERE id=:id');$s->execute([':id'=>(int)($d['id']??0),':c'=>$d['code']??'',':n'=>$d['name']??'',':f'=>$d['flag_emoji']??'🏳️',':s'=>(int)($d['sort_order']??0),':a'=>(int)($d['is_active']??1)]);jsonResponse(['ok'=>true]); }
function handleRegionDelete(PDO $db): void { requireLogin();$id=(int)($_GET['id']??0);$st=$db->prepare('DELETE FROM regions WHERE id=:id');$st->execute([':id'=>$id]);$n=$st->rowCount();jsonResponse(['ok'=>$n>0,'error'=>$n>0?'':'未找到该记录']); }

function handleBillTypes(PDO $db): void { $rid=$_GET['region_id']??null;$rows=$rid?getBillTypesByRegion((int)$rid):getAllBillTypes();jsonResponse(['ok'=>true,'data'=>$rows]); }
function handleBillTypeCreate(PDO $db): void {
    requireLogin();$d=getJsonInput();
    $s=$db->prepare('INSERT INTO bill_types (region_id,code,name,icon_class,unit,currency,category,sort_order) VALUES (:r,:c,:n,:i,:u,:cur,:cat,:s)');
    $s->execute([':r'=>(int)($d['region_id']??0),':c'=>$d['code']??'',':n'=>$d['name']??'',':i'=>$d['icon_class']??'fa-file-invoice',':u'=>$d['unit']??'m³',':cur'=>$d['currency']??'HK$',':cat'=>$d['category']??'utility',':s'=>(int)($d['sort_order']??0)]);
    $btId=(int)$db->lastInsertId();
    $copyFrom=(int)($d['copy_template_from']??0);
    if ($copyFrom>0) {
        $src=getTemplate($copyFrom);
        if ($src) {
            $ins=$db->prepare('INSERT INTO templates (bill_type_id,name,html_template,css_template,js_template,placeholder_map) VALUES (:b,:n,:h,:c,:j,:m)');
            $ins->execute([':b'=>$btId,':n'=>($d['name']??'默认').' (复制)',':h'=>$src['html_template'],':c'=>$src['css_template'],':j'=>$src['js_template'],':m'=>$src['placeholder_map']]);
        } else { $db->exec('INSERT INTO templates (bill_type_id,name,html_template) VALUES ('.$btId.',"默认模板","")'); }
    } else { $db->exec('INSERT INTO templates (bill_type_id,name,html_template) VALUES ('.$btId.',"默认模板","")'); }
    jsonResponse(['ok'=>true,'id'=>$btId]);
}
function handleBillTypeUpdate(PDO $db): void { requireLogin();$d=getJsonInput();$s=$db->prepare('UPDATE bill_types SET code=:c,name=:n,icon_class=:i,unit=:u,currency=:cur,category=:cat,sort_order=:s,is_active=:a WHERE id=:id');$s->execute([':id'=>(int)($d['id']??0),':c'=>$d['code']??'',':n'=>$d['name']??'',':i'=>$d['icon_class']??'fa-file-invoice',':u'=>$d['unit']??'m³',':cur'=>$d['currency']??'HK$',':cat'=>$d['category']??'utility',':s'=>(int)($d['sort_order']??0),':a'=>(int)($d['is_active']??1)]);jsonResponse(['ok'=>true]); }
function handleBillTypeDelete(PDO $db): void { requireLogin();$id=(int)($_GET['id']??0);$st=$db->prepare('DELETE FROM bill_types WHERE id=:id');$st->execute([':id'=>$id]);$n=$st->rowCount();jsonResponse(['ok'=>$n>0,'error'=>$n>0?'':'未找到该记录']); }

function handleTemplate(PDO $db): void { $btid=(int)($_GET['bill_type_id']??0);jsonResponse(['ok'=>true,'data'=>getTemplate($btid)]); }
function handleTemplateSave(PDO $db): void {
    requireLogin();$d=getJsonInput();$btid=(int)($d['bill_type_id']??0);$ex=getTemplate($btid);
    if ($ex) { $s=$db->prepare('UPDATE templates SET name=:n,html_template=:h,css_template=:c,js_template=:j,placeholder_map=:m,updated_at=NOW() WHERE bill_type_id=:b'); }
    else { $s=$db->prepare('INSERT INTO templates (bill_type_id,name,html_template,css_template,js_template,placeholder_map) VALUES (:b,:n,:h,:c,:j,:m)'); }
    $s->execute([':b'=>$btid,':n'=>$d['name']??'自定义模板',':h'=>$d['html_template']??'',':c'=>$d['css_template']??'',':j'=>$d['js_template']??'',':m'=>$d['placeholder_map']??'{}']);
    jsonResponse(['ok'=>true]);
}
function handleTemplateReset(PDO $db): void {
    requireLogin();$btid=(int)($_GET['bill_type_id']??0);
    $s=$db->prepare('SELECT code FROM bill_types WHERE id=:id');$s->execute([':id'=>$btid]);$code=$s->fetchColumn();
    $html=match($code){'wsd'=>getWSDDefaultTemplate(),'cn-water'=>getCNWaterTemplate(),'cn-electric'=>getCNElectricTemplate(),'cn-bank'=>getCNBankTemplate(),'cn-credit-card'=>getCNCreditCardTemplate(),'cn-cmb-credit'=>getCMBBankTemplate(),'hk-bank'=>getHKBankTemplate(),'au-bank'=>getAUBankTemplate(),'ca-bank'=>getCABankTemplate(),'sg-bank'=>getSGBankTemplate(),'gb-bank'=>getGBBankTemplate(),'gb-monzo'=>getGBMonzoTemplate(),'gb-kraken'=>getGBKrakenTemplate(),'gb-wise'=>getDEWiseBankTemplate(),'de-wise'=>getDEWiseBankTemplate(),'de-monese'=>getDEMoneseTemplate(),default=>getGenericTemplate()};
    $css=match($code){'wsd'=>getWSDDefaultCSS(),'cn-water'=>getCNWaterCSS(),'cn-electric'=>getCNElectricCSS(),'cn-bank'=>getCNBankCSS(),'cn-credit-card'=>getCNCreditCardCSS(),'cn-cmb-credit'=>getCMBBankCSS(),'hk-bank'=>getHKBankCSS(),'au-bank'=>getAUBankCSS(),'ca-bank'=>getCABankCSS(),'sg-bank'=>getSGBankCSS(),'gb-bank'=>getGBBankCSS(),'gb-monzo'=>getGBMonzoCSS(),'gb-wise'=>getWiseBankCSS(),'de-wise'=>getWiseBankCSS(),'de-monese'=>getDEMoneseCSS(),default=>''};
    $s=$db->prepare('UPDATE templates SET name="默认模板(已重置)",html_template=:h,css_template=:c,js_template="",updated_at=NOW() WHERE bill_type_id=:b');
    $s->execute([':b'=>$btid,':h'=>$html,':c'=>$css]);
    jsonResponse(['ok'=>true]);
}
function handleAllTemplates(PDO $db): void { jsonResponse(['ok'=>true,'data'=>getAllTemplates()]); }
function handleTemplateDelete(PDO $db): void {
    requireLogin(); $btid=(int)($_GET['bill_type_id']??0);
    if(!$btid) jsonResponse(['error'=>'缺少 bill_type_id'],400);
    $db->exec('DELETE FROM templates WHERE bill_type_id='.$btid);
    jsonResponse(['ok'=>true]);
}
function handleFrontendData(PDO $db): void { jsonResponse(['ok'=>true,'regions'=>getRegions(),'bill_types'=>getAllBillTypes()]); }

function handleGenerateBill(PDO $db): void {
    $d=getJsonInput();$btid=(int)($d['bill_type_id']??0);$tpl=getTemplate($btid);
    if (!$tpl) jsonResponse(['error'=>'模板不存在'],404);
    $ph=buildPlaceholders($d,$db,$btid);
    $html=renderTemplate($tpl['html_template'],$ph);
    jsonResponse(['ok'=>true,'html'=>$html,'css'=>$tpl['css_template']??'']);
}

function buildPlaceholders(array $data, PDO $db, int $btid): array {
    $consumption=(float)($data['consumption']??0);
    $periodStart=$data['period_start']??'';$periodEnd=$data['period_end']??'';$issueDate=$data['issue_date']??'';
    $days=120;if($periodStart&&$periodEnd){$diff=(strtotime($periodEnd)-strtotime($periodStart))/86400;if($diff>0)$days=(int)$diff;}
    $dailyLitres=$days>0?(int)(($consumption/$days)*1000):0;
    // Water tier calc
    $t1=min($consumption,12);$t2=min(max($consumption-12,0),31);$t3=min(max($consumption-43,0),19);$t4=max($consumption-62,0);
    $c1=$t1*0;$c2=$t2*4.16;$c3=$t3*6.45;$c4=$t4*9.05;
    $wc=$c1+$c2+$c3+$c4;$sc=$wc*0.25;$td=$wc+$sc;
    $surchargeDate='';$afterSurcharge=0;
    if($issueDate){$sc2=strtotime($issueDate.' +14 days');$surchargeDate=$sc2?date('d/m/Y',$sc2):'';$afterSurcharge=$td*1.05;}
    $lastPayAmt=number_format(60+mt_rand(0,3500)/100,2,'.','');$deposit=number_format(300+mt_rand(0,20000)/100,2,'.','');
    $slipRef=(string)(1000000000+mt_rand(0,8999999999));$acctNo=$data['account_number']??(string)(10000000+mt_rand(0,8999999));$crcCode=(string)(1000+mt_rand(0,8999));
    $longRef=($data['bill_number']??'').' '.substr($slipRef,0,4).' '.substr($slipRef,4,4).' '.substr($slipRef,8);
    $lastPayDate=($periodStart&&strtotime($periodStart))?date('d/m/Y',strtotime($periodStart.' -3 days')):'';
    $s=$db->prepare('SELECT * FROM bill_types WHERE id=:id');$s->execute([':id'=>$btid]);$bt=$s->fetch();

    // ─── 银行特定数据 ───
    $isBank=in_array($bt['category']??'utility', ['bank','credit_card','crypto']);
    $bankName=$data['bank_name']??'';$bankNameEn=$data['bank_name_en']??'';
    $accountType=$data['account_type']??'储蓄账户';
    $statementPeriod=$data['statement_period']??fmtHK($periodStart).' — '.fmtHK($periodEnd);
    $openingBalance=$data['opening_balance']??number_format(10000+mt_rand(0,90000)/100,2,'.','');
    $totalCredits=$data['total_credits']??number_format(5000+mt_rand(0,20000)/100,2,'.','');
    $totalDebits=$data['total_debits']??number_format(3000+mt_rand(0,15000)/100,2,'.','');
    $closingBalance=number_format((float)$openingBalance+(float)$totalCredits-(float)$totalDebits,2,'.','');
    $transactions=$data['transactions']??'';

    return [
        'customer_name'=>$data['customer_name']??'','full_address'=>trim(($data['address_unit']??'').'，'.($data['address_street']??'').'，'.($data['address_district']??''),'，'),
        'address_unit'=>$data['address_unit']??'','address_street'=>$data['address_street']??'','address_district'=>$data['address_district']??'',
        'bill_number'=>$data['bill_number']??'','account_number'=>$acctNo,
        'period_start'=>fmtHK($periodStart),'period_end'=>fmtHK($periodEnd),'issue_date'=>fmtHK($issueDate),
        'consumption'=>number_format($consumption,1),'meter_prev'=>number_format((int)($data['meter_prev']??0)),
        'meter_curr'=>number_format((int)($data['meter_curr']??0)),'meter_number'=>$data['meter_number']??'',
        'days'=>(string)$days,'daily_litres'=>(string)$dailyLitres,
        'currency'=>$bt['currency']??'¥','unit'=>$bt['unit']??'m³','bill_type_name'=>$bt['name']??'',
        't1'=>number_format($t1,1),'t2'=>number_format($t2,1),'t3'=>number_format($t3,1),'t4'=>number_format($t4,1),
        'c1'=>number_format($c1,2),'c2'=>number_format($c2,2),'c3'=>number_format($c3,2),'c4'=>number_format($c4,2),
        'water_charge'=>number_format($wc,2),'sewage_charge'=>number_format($sc,2),'total_due'=>number_format($td,2),
        'last_pay_date'=>$lastPayDate,'last_pay_amt'=>$lastPayAmt,'deposit'=>$deposit,
        'surcharge_date'=>$surchargeDate,'after_surcharge'=>number_format($afterSurcharge,2),
        'slip_ref'=>$slipRef,'long_ref'=>$longRef,'crc_code'=>$crcCode,
        'bar_chart_svg'=>'{{bar_chart_svg}}','qr_code_svg'=>'{{qr_code_svg}}','barcode_svg'=>'{{barcode_svg}}','bottom_barcode_svg'=>'{{bottom_barcode_svg}}',
        'bank_name'=>$bankName,'bank_name_en'=>$bankNameEn,'account_type'=>$accountType,'statement_period'=>$statementPeriod,
        'opening_balance'=>$openingBalance,'closing_balance'=>$closingBalance,
        'total_credits'=>$totalCredits,'total_debits'=>$totalDebits,'transactions'=>$transactions,
        // credit-card-specific
        'card_number'=>$data['card_number']??'**** **** **** '.substr($acctNo,-4),
        'credit_limit'=>$data['credit_limit']??number_format(50000,2,'.',''),
        'available_credit'=>$data['available_credit']??number_format(50000-(float)$totalDebits,2,'.',''),
        'min_payment'=>$data['min_payment']??number_format((float)$totalDebits*0.1,2,'.',''),
        'payment_due_date'=>$data['payment_due_date']??(strtotime($issueDate)?fmtHK(date('Y-m-d',strtotime($issueDate.' +25 days'))):''),
        'new_charges'=>$data['new_charges']??$totalDebits,
        'new_payments'=>$data['new_payments']??number_format(0,2,'.',''),
        'interest_charge'=>$data['interest_charge']??number_format(0,2,'.',''),
        'points_balance'=>$data['points_balance']??strval(mt_rand(1000,99999)),
        'previous_balance'=>$data['previous_balance']??number_format(0,2,'.',''),
        'postal_code'=>$data['postal_code']??'400000',
        // Monzo / UK specific
        'sort_code'=>$data['sort_code']??'04-00-06',
        'bic'=>$data['bic']??'MONZGB2L',
        'iban'=>$data['iban']??'GB80 MONZ 0400 0640 0080 40',
        'country'=>$data['country']??'United Kingdom',
        'balance_pots'=>$data['balance_pots']??number_format(0,2,'.',''),
        'total_outgoings'=>$data['total_outgoings']??number_format(mt_rand(500,5000)/100*100,2,'.',''),
        'total_deposits'=>$data['total_deposits']??number_format(mt_rand(500,5000)/100*100,2,'.',''),
        'adjustment'=>$data['adjustment']??number_format(0,2,'.',''),
        'card_last4'=>$data['card_last4']??'6277',
    ];
}

function fmtHK(string $s): string { if(!$s)return '';$ts=strtotime($s);return $ts?date('d/m/Y',$ts):$s; }

function handleLogin(): void { $d=getJsonInput();$ok=doLogin($d['username']??'',$d['password']??'');jsonResponse(['ok'=>$ok,'error'=>$ok?'':'用户名或密码错误']); }
function handleLogout(): void { doLogout();jsonResponse(['ok'=>true]); }
function handleCheckAuth(): void { jsonResponse(['ok'=>true,'logged_in'=>isLoggedIn()]); }
function getJsonInput(): array { return json_decode(file_get_contents('php://input'),true)??[]; }

// ══════════════════════════════════
// 用户系统 API handlers
// ══════════════════════════════════
function handleUserRegister(): void {
    $d = getJsonInput();
    $result = doUserRegister($d['username'] ?? '', $d['password'] ?? '');
    jsonResponse($result, $result['ok'] ? 200 : 400);
}

function handleUserLoginApi(): void {
    $d = getJsonInput();
    $result = doUserLogin($d['username'] ?? '', $d['password'] ?? '');
    jsonResponse($result, $result['ok'] ? 200 : 401);
}

function handleUserLogoutApi(): void {
    doUserLogout();
    jsonResponse(['ok' => true]);
}

function handleUserInfoApi(): void {
    startUserSession();
    $userId = getCurrentUserId();
    if (!$userId) { jsonResponse(['ok' => false, 'logged_in' => false]); return; }
    $info = getUserInfo($userId);
    jsonResponse(['ok' => true, 'logged_in' => true, 'user' => $info]);
}

function handleUserTopUp(): void {
    requireUserLogin();
    $d = getJsonInput();
    $packageId = $d['package_id'] ?? '';
    $coinType = strtoupper($d['coin_type'] ?? 'USDC');
    $network = $d['network'] ?? 'ERC20';
    if (!in_array($coinType, SUPPORTED_COINS)) { jsonResponse(['ok' => false, 'error' => '不支持的币种'], 400); return; }

    $packages = getCoinPackages($coinType);
    $pkg = null;
    foreach ($packages as $p) { if ($p['id'] === $packageId) { $pkg = $p; break; } }
    if (!$pkg) { jsonResponse(['ok' => false, 'error' => '无效的套餐'], 400); return; }

    $userId = getCurrentUserId();
    $result = doCreateTopUpOrder($userId, $coinType, $pkg['name'], $pkg['coins'], $pkg['price_num'] ?? 0, $network);
    if ($result['ok']) {
        $result['package'] = $pkg;
    }
    jsonResponse($result);
}

function handleUserCreateOrder(): void {
    handleUserTopUp();
}

function handleUserPayOrder(): void {
    requireUserLogin();
    $d = getJsonInput();
    $orderId = (int)($d['order_id'] ?? 0);
    if ($orderId <= 0) { jsonResponse(['ok' => false, 'error' => '缺少订单ID'], 400); return; }

    $userId = getCurrentUserId();
    $result = doPayOrder($userId, $orderId);
    jsonResponse($result, $result['ok'] ? 200 : 400);
}

function handlePackages(): void {
    $coinType = strtoupper($_GET['coin_type'] ?? 'USDC');
    jsonResponse(['ok' => true, 'packages' => getCoinPackages($coinType), 'networks' => getNetworks(), 'download_cost' => DOWNLOAD_COST, 'register_bonus' => REGISTER_BONUS]);
}

function handleDeductCoin(): void {
    requireUserLogin();
    $db = getDB();
    $userId = getCurrentUserId();

    $db->beginTransaction();
    try {
        // 查询剩余次数
        $st = $db->prepare('SELECT download_credits FROM users WHERE id = :id FOR UPDATE');
        $st->execute([':id' => $userId]);
        $credits = (int)$st->fetchColumn();

        if ($credits < 1) {
            throw new Exception('下载次数不足，请输入博主福利码兑换次数！');
        }

        $db->prepare('UPDATE users SET download_credits = download_credits - 1 WHERE id = :id')->execute([':id' => $userId]);
        // 记录通用日志
        $db->prepare('INSERT INTO download_logs (user_id, bill_type_id, coins_used) VALUES (:uid, :bid, 1)')->execute([
            ':uid' => $userId, ':bid' => (int)($_GET['bill_type_id'] ?? 0)
        ]);

        $db->commit();
        jsonResponse(['ok'=>true, 'credits' => $credits - 1]);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['ok'=>false, 'error'=>$e->getMessage(), 'need_topup'=>true]);
    }
}

// ══════════════════════════════════
// 管理员用户管理 API handlers
// ══════════════════════════════════
function handleAdminUsers(): void {
    requireLogin();
    $users = adminGetAllUsers();
    $stats = adminGetUserStats();
    jsonResponse(['ok' => true, 'users' => $users, 'stats' => $stats]);
}

function handleAdminUserDetail(): void {
    requireLogin();
    $uid = (int)($_GET['user_id'] ?? 0);
    if ($uid <= 0) { jsonResponse(['ok' => false, 'error' => '缺少用户ID'], 400); return; }
    $detail = adminGetUserDetail($uid);
    if (!$detail) { jsonResponse(['ok' => false, 'error' => '用户不存在'], 404); return; }
    jsonResponse(['ok' => true, 'user' => $detail]);
}

function handleAdminUserUpdate(): void {
    requireLogin();
    $d = getJsonInput();
    $uid = (int)($d['id'] ?? 0);
    if ($uid <= 0) { jsonResponse(['ok' => false, 'error' => '缺少用户ID'], 400); return; }
    $result = adminUpdateUser($uid, $d);
    jsonResponse($result, $result['ok'] ? 200 : 400);
}

function handleAdminUserDelete(): void {
    requireLogin();
    $uid = (int)($_GET['user_id'] ?? 0);
    if ($uid <= 0) { jsonResponse(['ok' => false, 'error' => '缺少用户ID'], 400); return; }
    $result = adminDeleteUser($uid);
    jsonResponse($result, $result['ok'] ? 200 : 400);
}

function handleAdminAddCoins(): void {
    requireLogin();
    $d = getJsonInput();
    $uid = (int)($d['user_id'] ?? 0);
    $amount = (int)($d['amount'] ?? 0);
    $coinType = strtoupper($d['coin_type'] ?? 'USDC');
    $reason = $d['reason'] ?? '管理员充值';
    if ($uid <= 0 || $amount <= 0) { jsonResponse(['ok' => false, 'error' => '参数无效'], 400); return; }
    $result = adminAddCoins($uid, $amount, $coinType, $reason);
    jsonResponse($result, $result['ok'] ? 200 : 400);
}

function handleAdminUserStats(): void {
    requireLogin();
    $stats = adminGetUserStats();
    jsonResponse(['ok' => true, 'stats' => $stats]);
}

function handleSiteConfig(PDO $db): void {
    jsonResponse(['ok' => true, 'data' => getSiteConfig($db)]);
}

function handleSiteConfigSave(PDO $db): void {
    requireLogin();
    $d = getJsonInput();
    if (isset($d['hero_title'])) saveSiteConfig($db, 'hero_title', $d['hero_title']);
    if (isset($d['hero_content'])) saveSiteConfig($db, 'hero_content', $d['hero_content']);
    jsonResponse(['ok' => true, 'message' => '公告已保存']);
}

// ══════════════════════════════════
// 管理员修改密码
// ══════════════════════════════════
function handleAdminChangePassword(): void {
    requireLogin();
    $d = getJsonInput();
    $old = (string)($d['old_password'] ?? '');
    $new = (string)($d['new_password'] ?? '');

    if (mb_strlen($new) < 6) jsonResponse(['error' => '新密码至少6位字符'], 400);

    $db = getDB();
    startSession();
    $u = $_SESSION['username'] ?? '';

    $st = $db->prepare('SELECT password FROM admin_users WHERE username = :u');
    $st->execute([':u' => $u]);
    $admin = $st->fetch();

    if (!$admin || !password_verify($old, $admin['password'])) {
        jsonResponse(['error' => '旧密码不正确'], 400);
    }

    $hash = password_hash($new, PASSWORD_BCRYPT);
    $st = $db->prepare('UPDATE admin_users SET password = :p WHERE username = :u');
    $st->execute([':p' => $hash, ':u' => $u]);
    jsonResponse(['ok' => true]);
}

// --- 粉丝兑换福利码 ---
function handleRedeemCode() {
    requireUserLogin();
    $d = getJsonInput();
    $code = strtoupper(trim($d['code'] ?? ''));
    if (!$code) jsonResponse(['ok'=>false, 'error'=>'请输入兑换码']);

    $db = getDB();
    $userId = getCurrentUserId();

    $db->beginTransaction();
    try {
        $st = $db->prepare('SELECT * FROM promo_codes WHERE code = :code FOR UPDATE');
        $st->execute([':code' => $code]);
        $promo = $st->fetch();

        if (!$promo) throw new Exception('福利码无效');
        if ($promo['is_active'] == 0) throw new Exception('福利码已被停用');
        if ($promo['used_users'] >= $promo['max_users']) throw new Exception('该福利码已被兑换完，手慢了哦！');

        $st2 = $db->prepare('SELECT COUNT(*) FROM redemption_logs WHERE user_id = :uid AND promo_code_id = :pid');
        $st2->execute([':uid' => $userId, ':pid' => $promo['id']]);
        if ($st2->fetchColumn() > 0) throw new Exception('您已经兑换过这个福利码了，不可重复白嫖哦');

        // 更新名额，发放次数，记录日志
        $db->prepare('UPDATE promo_codes SET used_users = used_users + 1 WHERE id = :id')->execute([':id' => $promo['id']]);
        $db->prepare('INSERT INTO redemption_logs (user_id, promo_code_id) VALUES (:uid, :pid)')->execute([':uid' => $userId, ':pid' => $promo['id']]);
        $db->prepare('UPDATE users SET download_credits = download_credits + :c WHERE id = :uid')->execute([':c' => $promo['credits_per_user'], ':uid' => $userId]);

        $db->commit();
        jsonResponse(['ok'=>true, 'message'=>'兑换成功！已为您增加 '.$promo['credits_per_user'].' 次免费下载机会', 'added'=>$promo['credits_per_user']]);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['ok'=>false, 'error'=>$e->getMessage()]);
    }
}

// --- 博主前台自主申请 ---
function handleBloggerApply() {
    $d = getJsonInput();
    $u = trim($d['username'] ?? '');
    $p = trim($d['password'] ?? '');
    $l = trim($d['link'] ?? '');
    $c = trim($d['contact'] ?? '');

    if (!$u || !$p || !$l || !$c) jsonResponse(['ok'=>false, 'error'=>'请填写完整申请信息']);

    $db = getDB();
    $st = $db->prepare('SELECT COUNT(*) FROM bloggers WHERE username = :u');
    $st->execute([':u'=>$u]);
    if ($st->fetchColumn() > 0) jsonResponse(['ok'=>false, 'error'=>'拟登录账号已被占用']);

    $st = $db->prepare('INSERT INTO bloggers (username, password, channel_link, contact) VALUES (:u, :p, :l, :c)');
    $st->execute([':u'=>$u, ':p'=>password_hash($p, PASSWORD_BCRYPT), ':l'=>$l, ':c'=>$c]);
    jsonResponse(['ok'=>true, 'message'=>'申请成功！请等待超管审核。']);
}

// --- 超管后台博主管理 ---
function handleAdminBloggers() {
    requireLogin();
    $db = getDB();
    $rows = $db->query('SELECT id, username, channel_link, contact, status, created_at FROM bloggers ORDER BY id DESC')->fetchAll();
    jsonResponse(['ok'=>true, 'data'=>$rows]);
}

function handleAdminReviewBlogger() {
    requireLogin();
    $d = getJsonInput();
    $db = getDB();
    $st = $db->prepare('UPDATE bloggers SET status = :s WHERE id = :id');
    $st->execute([':s'=>(int)$d['status'], ':id'=>(int)$d['id']]);
    jsonResponse(['ok'=>true]);
}

// --- 博主后台系列 API ---
function startBloggerSession() {
    if (session_status() === PHP_SESSION_ACTIVE && session_name() !== 'QIANGWANG_BLOGGER') session_write_close();
    session_name('QIANGWANG_BLOGGER');
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
}
function requireBlogger() {
    startBloggerSession();
    if (empty($_SESSION['blogger_id'])) jsonResponse(['ok'=>false, 'error'=>'博主未登录'], 401);
}

function handleBloggerLogin() {
    $d = getJsonInput();
    $db = getDB();
    $st = $db->prepare('SELECT id, username, password, status FROM bloggers WHERE username = :u LIMIT 1');
    $st->execute([':u' => trim($d['u'] ?? '')]);
    $b = $st->fetch();

    if (!$b || !password_verify(trim($d['p'] ?? ''), $b['password'])) jsonResponse(['ok'=>false, 'error'=>'账号或密码错误']);
    if ($b['status'] == 0) jsonResponse(['ok'=>false, 'error'=>'您的申请还在审核中...']);
    if ($b['status'] == 2) jsonResponse(['ok'=>false, 'error'=>'很抱歉，您的申请已被驳回']);

    startBloggerSession();
    $_SESSION['blogger_id'] = $b['id'];
    $_SESSION['blogger_username'] = $b['username'];
    jsonResponse(['ok'=>true]);
}

function handleBloggerLogout() {
    startBloggerSession();
    session_destroy();
    jsonResponse(['ok'=>true]);
}

function handleBloggerCodes() {
    requireBlogger();
    $db = getDB();
    $st = $db->prepare('SELECT * FROM promo_codes WHERE blogger_id = :id ORDER BY id DESC');
    $st->execute([':id' => $_SESSION['blogger_id']]);
    jsonResponse(['ok'=>true, 'data'=>$st->fetchAll()]);
}

function handleBloggerCreateCode() {
    requireBlogger();
    $d = getJsonInput();
    $code = strtoupper(trim($d['code'] ?? ''));
    if (!$code) jsonResponse(['ok'=>false, 'error'=>'自定义兑换码不能为空']);

    $db = getDB();
    try {
        $st = $db->prepare('INSERT INTO promo_codes (blogger_id, code, credits_per_user, max_users) VALUES (:b, :c, :cp, :m)');
        $st->execute([':b'=>$_SESSION['blogger_id'], ':c'=>$code, ':cp'=>(int)$d['credits'], ':m'=>(int)$d['max']]);
        jsonResponse(['ok'=>true]);
    } catch (PDOException $e) {
        if ($e->errorInfo[1] == 1062) jsonResponse(['ok'=>false, 'error'=>'该福利码已经被其他博主占用了，换一个吧！']);
        jsonResponse(['ok'=>false, 'error'=>'生成失败']);
    }
}

function handleBloggerToggleCode() {
    requireBlogger();
    $d = getJsonInput();
    $db = getDB();
    $st = $db->prepare('UPDATE promo_codes SET is_active = :s WHERE id = :id AND blogger_id = :b');
    $st->execute([':s'=>(int)$d['status'], ':id'=>(int)$d['id'], ':b'=>$_SESSION['blogger_id']]);
    jsonResponse(['ok'=>true]);
}
