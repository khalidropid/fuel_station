<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../config/database.php';
$pageTitle='تقرير المبيعات';$from=$_GET['from']??date('Y-m-01');$to=$_GET['to']??date('Y-m-d');$payment=$_GET['payment_method']??'';$rows=[];$total=0;
$where="s.sale_date>=? AND s.sale_date<?";$params=[$from.' 00:00:00',date('Y-m-d',strtotime($to.' +1 day')).' 00:00:00'];
if(in_array($payment,['cash','card','credit'],true)){$where.=" AND s.payment_method=?";$params[]=$payment;}
$stmt=$pdo->prepare("SELECT s.*,e.full_name employee_name,c.full_name customer_name,p.pump_no FROM sales s LEFT JOIN employees e ON e.id=s.employee_id LEFT JOIN customers c ON c.id=s.customer_id LEFT JOIN pumps p ON p.id=s.pump_id WHERE $where ORDER BY s.sale_date DESC,s.id DESC");
$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);foreach($rows as $r)$total+=(float)$r['total'];
require_once __DIR__.'/../includes/header.php';
?>
<div class="page-header"><div><h2>تقرير المبيعات</h2></div><a href="index.php" class="btn">التقارير</a></div>
<div class="card"><form method="get"><div class="form-group"><label>من</label><input type="date" name="from" value="<?=htmlspecialchars($from)?>"></div><div class="form-group"><label>إلى</label><input type="date" name="to" value="<?=htmlspecialchars($to)?>"></div><div class="form-group"><label>طريقة الدفع</label><select name="payment_method"><option value="">الكل</option><option value="cash" <?=$payment==='cash'?'selected':''?>>نقدي</option><option value="card" <?=$payment==='card'?'selected':''?>>بطاقة</option><option value="credit" <?=$payment==='credit'?'selected':''?>>آجل</option></select></div><button class="btn btn-primary">بحث</button></form></div>
<div class="card"><h3>الإجمالي: <?=number_format($total,2)?></h3><table class="table"><thead><tr><th>الفاتورة</th><th>التاريخ</th><th>الطرمبة</th><th>الموظف</th><th>العميل</th><th>الدفع</th><th>الإجمالي</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=htmlspecialchars($r['invoice_no'])?></td><td><?=htmlspecialchars($r['sale_date'])?></td><td><?=htmlspecialchars($r['pump_no']??'')?></td><td><?=htmlspecialchars($r['employee_name']??'')?></td><td><?=htmlspecialchars($r['customer_name']??'نقدي')?></td><td><?=htmlspecialchars($r['payment_method'])?></td><td><?=number_format($r['total'],2)?></td></tr><?php endforeach;?></tbody></table></div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
