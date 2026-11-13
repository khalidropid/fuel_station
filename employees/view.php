<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/accounting.php';

$pageTitle='كشف حساب الموظف';
$error=''; $employee=null; $ledger=null; $summary=null;
$employeeId=(int)($_GET['id']??0);
try{
 if($employeeId<=0) throw new Exception('معرف الموظف غير صحيح.');
 $stmt=$pdo->prepare("SELECT * FROM employees WHERE id=? LIMIT 1");
 $stmt->execute([$employeeId]); $employee=$stmt->fetch(PDO::FETCH_ASSOC);
 if(!$employee) throw new Exception('الموظف غير موجود.');
 $account=accountingEnsureEmployeeAccount($pdo,$employeeId,false);
 $summary=accountingGetBalance($pdo,(int)$account['id']);
 $ledger=accountingGetLedger($pdo,(int)$account['id']);
}catch(Throwable $e){$error=$e->getMessage();}
require_once __DIR__.'/../includes/header.php';
?>
<div class="page-header"><div><h2>كشف حساب الموظف</h2><p>الاستحقاقات والسلف والسداد من الحساب المحاسبي الحقيقي.</p></div><a href="index.php" class="btn">العودة</a></div>
<?php if($error):?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif;?>
<?php if($employee&&$summary):?>
<div class="card"><h3><?=htmlspecialchars($employee['full_name'])?></h3><p>الرقم: <?=htmlspecialchars($employee['employee_no'])?> | الوظيفة: <?=htmlspecialchars($employee['job_title']??'')?></p>
<div class="stats"><div><strong>له</strong><br><?=number_format(max(0,$summary['balance']),2)?></div><div><strong>عليه</strong><br><?=number_format(max(0,-$summary['balance']),2)?></div><div><strong>الرصيد</strong><br><?=number_format(abs($summary['balance']),2)?> <?=htmlspecialchars($summary['status'])?></div></div></div>
<div class="card"><h3>الحركات</h3><table class="table"><thead><tr><th>التاريخ</th><th>رقم الحركة</th><th>البيان</th><th>عليه</th><th>له</th><th>الرصيد</th><th>الحالة</th></tr></thead><tbody>
<tr><td>الرصيد الافتتاحي</td><td>—</td><td>رصيد افتتاحي</td><td><?=number_format($ledger['opening_debit'],2)?></td><td><?=number_format($ledger['opening_credit'],2)?></td><td><?=number_format($ledger['opening_credit']-$ledger['opening_debit'],2)?></td><td>—</td></tr>
<?php foreach($ledger['rows'] as $r):?><tr><td><?=htmlspecialchars($r['transaction_date'])?></td><td><?=htmlspecialchars($r['transaction_no']??'')?></td><td><?=htmlspecialchars($r['description']??'')?></td><td><?=number_format($r['debit'],2)?></td><td><?=number_format($r['credit'],2)?></td><td><?=number_format(abs($r['running_balance']),2)?></td><td><?=htmlspecialchars($r['balance_status'])?></td></tr><?php endforeach;?>
</tbody></table></div>
<?php endif;?>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
