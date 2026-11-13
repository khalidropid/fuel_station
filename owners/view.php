<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/accounting.php';
$pageTitle='كشف حساب المالك';$error='';$summary=null;$ledger=null;
try{$account=accountingEnsureOwnerAccount($pdo);$summary=accountingGetBalance($pdo,(int)$account['id']);$ledger=accountingGetLedger($pdo,(int)$account['id']);}catch(Throwable $e){$error=$e->getMessage();}
require_once __DIR__.'/../includes/header.php';
?>
<div class="page-header"><div><h2>كشف حساب المالك</h2><p>جميع حقوق والتزامات المالك من دفتر الحسابات.</p></div><a href="../accounts/transaction.php?account_id=<?=$summary['account']['id']??0?>" class="btn">حركة مالية</a></div>
<?php if($error):?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif;?>
<?php if($summary):?>
<div class="card"><h3><?=htmlspecialchars($summary['account']['account_name'])?></h3><div class="stats"><div><strong>له</strong><br><?=number_format(max(0,$summary['balance']),2)?></div><div><strong>عليه</strong><br><?=number_format(max(0,-$summary['balance']),2)?></div><div><strong>الرصيد</strong><br><?=number_format(abs($summary['balance']),2)?> <?=htmlspecialchars($summary['status'])?></div></div></div>
<div class="card"><h3>كشف الحساب</h3><table class="table"><thead><tr><th>التاريخ</th><th>رقم الحركة</th><th>البيان</th><th>عليه</th><th>له</th><th>الرصيد</th><th>الحالة</th></tr></thead><tbody>
<tr><td>الرصيد الافتتاحي</td><td>—</td><td>رصيد افتتاحي</td><td><?=number_format($ledger['opening_debit'],2)?></td><td><?=number_format($ledger['opening_credit'],2)?></td><td><?=number_format(abs($ledger['opening_credit']-$ledger['opening_debit']),2)?></td><td>—</td></tr>
<?php foreach($ledger['rows'] as $r):?><tr><td><?=htmlspecialchars($r['transaction_date'])?></td><td><?=htmlspecialchars($r['transaction_no']??'')?></td><td><?=htmlspecialchars($r['description']??'')?></td><td><?=number_format($r['debit'],2)?></td><td><?=number_format($r['credit'],2)?></td><td><?=number_format(abs($r['running_balance']),2)?></td><td><?=htmlspecialchars($r['balance_status'])?></td></tr><?php endforeach;?>
</tbody></table></div>
<?php endif;?>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
