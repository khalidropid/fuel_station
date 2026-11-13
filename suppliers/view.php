<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/accounting.php';

$pageTitle='حسابات الموردين';$error='';$accounts=[];$summary=null;$ledger=null;
try{
 $accounts=$pdo->query("SELECT id,account_no,account_name,opening_balance,status FROM accounts WHERE account_type='supplier' ORDER BY account_name")->fetchAll(PDO::FETCH_ASSOC);
 $accountId=(int)($_GET['id']??0);
 if($accountId>0){
  $a=accountingGetAccountById($pdo,$accountId);
  if(!$a||$a['account_type']!=='supplier')throw new Exception('حساب المورد غير موجود.');
  $summary=accountingGetBalance($pdo,$accountId);
  $ledger=accountingGetLedger($pdo,$accountId);
 }
}catch(Throwable $e){$error=$e->getMessage();}
require_once __DIR__.'/../includes/header.php';
?>
<div class="page-header"><div><h2>حساب المورد</h2><p>كشف المورد مبني على accounts و account_transactions.</p></div><a href="../accounts/transaction.php" class="btn">تسجيل حركة</a></div>
<?php if($error):?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif;?>
<div class="card"><h3>حسابات الموردين</h3><table class="table"><thead><tr><th>رقم الحساب</th><th>اسم المورد</th><th>الرصيد</th><th>الحالة</th><th></th></tr></thead><tbody>
<?php foreach($accounts as $a):$s=accountingGetBalance($pdo,(int)$a['id']);?><tr><td><?=htmlspecialchars($a['account_no'])?></td><td><?=htmlspecialchars($a['account_name'])?></td><td><?=number_format(abs($s['balance']),2).' '.htmlspecialchars($s['status'])?></td><td><?=htmlspecialchars($a['status'])?></td><td><a class="btn" href="?id=<?=$a['id']?>">كشف</a></td></tr><?php endforeach;?>
<?php if(!$accounts):?><tr><td colspan="5">لا توجد حسابات موردين. أنشئ حسابًا من شاشة الحركة باستخدام رقم يبدأ بـ SUP-.</td></tr><?php endif;?></tbody></table></div>
<?php if($summary&&$ledger):?><div class="card"><h3><?=htmlspecialchars($summary['account']['account_name'])?></h3><div class="stats"><div><strong>له</strong><br><?=number_format(max(0,$summary['balance']),2)?></div><div><strong>عليه</strong><br><?=number_format(max(0,-$summary['balance']),2)?></div><div><strong>الرصيد</strong><br><?=number_format(abs($summary['balance']),2)?> <?=htmlspecialchars($summary['status'])?></div></div></div>
<div class="card"><table class="table"><thead><tr><th>التاريخ</th><th>رقم الحركة</th><th>البيان</th><th>عليه</th><th>له</th><th>الرصيد</th><th></th></tr></thead><tbody><?php foreach($ledger['rows'] as $r):?><tr><td><?=htmlspecialchars($r['transaction_date'])?></td><td><?=htmlspecialchars($r['transaction_no']??'')?></td><td><?=htmlspecialchars($r['description']??'')?></td><td><?=number_format($r['debit'],2)?></td><td><?=number_format($r['credit'],2)?></td><td><?=number_format(abs($r['running_balance']),2)?></td><td><?=htmlspecialchars($r['balance_status'])?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
