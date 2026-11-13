<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/accounting.php';

$pageTitle='كشف حساب العميل';$error='';$customer=null;$summary=null;$ledger=null;
$customerId=(int)($_GET['id']??0);
try{
 if($customerId<=0)throw new Exception('معرف العميل غير صحيح.');
 $stmt=$pdo->prepare("SELECT * FROM customers WHERE id=? LIMIT 1");$stmt->execute([$customerId]);$customer=$stmt->fetch(PDO::FETCH_ASSOC);
 if(!$customer)throw new Exception('العميل غير موجود.');
 $account=accountingEnsureCustomerAccount($pdo,$customerId,false);
 $summary=accountingGetBalance($pdo,(int)$account['id']);
 $ledger=accountingGetLedger($pdo,(int)$account['id']);
}catch(Throwable $e){$error=$e->getMessage();}
require_once __DIR__.'/../includes/header.php';
?>
<div class="page-header"><div><h2>كشف حساب العميل</h2><p>هذا الكشف يعتمد على دفتر الحسابات الحقيقي وليس على customer_transactions.</p></div><a href="../accounts/transaction.php?party_type=customer&party_id=<?=$customerId?>" class="btn">تسجيل حركة</a></div>
<?php if($error):?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif;?>
<?php if($customer&&$summary):?>
<div class="card"><h3><?=htmlspecialchars($customer['full_name'])?></h3><p>الرقم: <?=htmlspecialchars($customer['customer_no'])?> | الهاتف: <?=htmlspecialchars($customer['phone']??'')?></p><div class="stats"><div><strong>له</strong><br><?=number_format(max(0,$summary['balance']),2)?></div><div><strong>عليه</strong><br><?=number_format(max(0,-$summary['balance']),2)?></div><div><strong>الرصيد</strong><br><?=number_format(abs($summary['balance']),2)?> <?=htmlspecialchars($summary['status'])?></div><div><strong>حد الائتمان</strong><br><?=number_format($customer['credit_limit'],2)?></div></div></div>
<div class="card"><h3>الحركات</h3><table class="table"><thead><tr><th>التاريخ</th><th>رقم الحركة</th><th>البيان</th><th>عليه</th><th>له</th><th>الرصيد</th><th>الحالة</th></tr></thead><tbody>
<tr><td>الرصيد الافتتاحي</td><td>—</td><td>رصيد افتتاحي</td><td><?=number_format($ledger['opening_debit'],2)?></td><td><?=number_format($ledger['opening_credit'],2)?></td><td><?=number_format(abs($ledger['opening_credit']-$ledger['opening_debit']),2)?></td><td>—</td></tr>
<?php foreach($ledger['rows'] as $r):?><tr><td><?=htmlspecialchars($r['transaction_date'])?></td><td><?=htmlspecialchars($r['transaction_no']??'')?></td><td><?=htmlspecialchars($r['description']??'')?></td><td><?=number_format($r['debit'],2)?></td><td><?=number_format($r['credit'],2)?></td><td><?=number_format(abs($r['running_balance']),2)?></td><td><?=htmlspecialchars($r['balance_status'])?></td></tr><?php endforeach;?>
</tbody></table></div>
<?php endif;?>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
