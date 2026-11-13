<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/accounting.php';

$pageTitle = 'إنشاء عملية بيع';
$error = '';
$shifts = [];
$fuelTypes = [];
$customers = [];

try {
    $stmt = $pdo->query("
        SELECT s.id,s.shift_no,s.employee_id,s.start_datetime,
               e.employee_no,e.full_name employee_name
        FROM shifts s JOIN employees e ON e.id=s.employee_id
        WHERE s.status='open' ORDER BY s.id DESC
    ");
    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT id,code,name,unit FROM fuel_types WHERE is_active=1 ORDER BY name");
    $fuelTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT id,customer_no,full_name,phone,credit_limit,balance,status
        FROM customers WHERE status='active' ORDER BY full_name
    ");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $shiftId = (int)($_POST['shift_id'] ?? 0);
        $pumpId = (int)($_POST['pump_id'] ?? 0);
        $fuelTypeId = (int)($_POST['fuel_type_id'] ?? 0);
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $quantity = (float)($_POST['quantity'] ?? 0);
        $unitPrice = (float)($_POST['unit_price'] ?? 0);
        $discount = (float)($_POST['discount'] ?? 0);
        $paymentMethod = trim($_POST['payment_method'] ?? 'cash');
        $saleDate = trim($_POST['sale_date'] ?? date('Y-m-d\TH:i'));
        $notes = trim($_POST['notes'] ?? '');

        if ($shiftId <= 0) throw new Exception('يرجى اختيار الوردية.');
        if ($pumpId <= 0) throw new Exception('يرجى اختيار الطرمبة.');
        if ($fuelTypeId <= 0) throw new Exception('يرجى اختيار نوع الوقود.');
        if ($quantity <= 0) throw new Exception('يجب أن تكون الكمية أكبر من صفر.');
        if ($unitPrice <= 0) throw new Exception('سعر اللتر يجب أن يكون أكبر من صفر.');
        if ($discount < 0) throw new Exception('الخصم لا يمكن أن يكون سالبًا.');
        if (!in_array($paymentMethod, ['cash','card','credit'], true)) throw new Exception('طريقة الدفع غير صحيحة.');
        if ($paymentMethod === 'credit' && $customerId <= 0) throw new Exception('يجب اختيار العميل عند البيع الآجل.');
        if ($paymentMethod !== 'credit') $customerId = null;

        $subtotal = round($quantity * $unitPrice, 2);
        if ($discount > $subtotal) throw new Exception('الخصم لا يمكن أن يكون أكبر من قيمة البيع.');
        $total = round($subtotal - $discount, 2);
        if ($total <= 0) throw new Exception('الإجمالي يجب أن يكون أكبر من صفر.');

        $saleDateDb = str_replace('T', ' ', $saleDate);
        if (strtotime($saleDateDb) === false) throw new Exception('تاريخ البيع غير صحيح.');

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT s.*,e.employee_no,e.full_name employee_name
            FROM shifts s JOIN employees e ON e.id=s.employee_id
            WHERE s.id=? LIMIT 1 FOR UPDATE
        ");
        $stmt->execute([$shiftId]);
        $shift = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$shift) throw new Exception('الوردية غير موجودة.');
        if ($shift['status'] !== 'open') throw new Exception('لا يمكن البيع في وردية مغلقة.');

        $employeeId = (int)$shift['employee_id'];
        if (!empty($shift['start_datetime']) && strtotime($saleDateDb) < strtotime($shift['start_datetime'])) {
            throw new Exception('لا يمكن تسجيل البيع قبل بداية الوردية.');
        }

        $stmt = $pdo->prepare("SELECT id,pump_no,name,tank_id,status FROM pumps WHERE id=? LIMIT 1 FOR UPDATE");
        $stmt->execute([$pumpId]);
        $pump = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pump) throw new Exception('الطرمبة غير موجودة.');
        if ($pump['status'] !== 'active') throw new Exception('الطرمبة غير نشطة.');
        if (empty($pump['tank_id'])) throw new Exception('الطرمبة غير مرتبطة بخزان.');

        $stmt = $pdo->prepare("
            SELECT id,shift_id,pump_id,opening_reading,closing_reading,total_liters,total_sales
            FROM shift_pumps WHERE shift_id=? AND pump_id=? LIMIT 1 FOR UPDATE
        ");
        $stmt->execute([$shiftId,$pumpId]);
        $shiftPump = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$shiftPump) throw new Exception('الطرمبة غير مرتبطة بالوردية المحددة.');

        $stmt = $pdo->prepare("SELECT id,name,capacity,current_volume,fuel_type_id FROM tanks WHERE id=? LIMIT 1 FOR UPDATE");
        $stmt->execute([$pump['tank_id']]);
        $tank = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tank) throw new Exception('الخزان المرتبط بالطرمبة غير موجود.');
        if ((int)$tank['fuel_type_id'] !== $fuelTypeId) throw new Exception('نوع الوقود لا يتطابق مع خزان الطرمبة.');

        $stmt = $pdo->prepare("SELECT id,code,name,unit FROM fuel_types WHERE id=? AND is_active=1 LIMIT 1");
        $stmt->execute([$fuelTypeId]);
        $fuelType = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fuelType) throw new Exception('نوع الوقود غير موجود أو غير نشط.');

        $currentVolume = (float)$tank['current_volume'];
        if ($quantity > $currentVolume) {
            throw new Exception('الكمية أكبر من المخزون المتوفر. المتاح: '.number_format($currentVolume,2).' لتر.');
        }

        $customer = null;
        if ($paymentMethod === 'credit') {
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE id=? LIMIT 1 FOR UPDATE");
            $stmt->execute([$customerId]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$customer) throw new Exception('العميل غير موجود.');
            if ($customer['status'] !== 'active') throw new Exception('العميل غير نشط.');

            $customerAccount = accountingEnsureCustomerAccount($pdo, $customerId, true);
            $customerLedger = accountingGetBalance($pdo, (int)$customerAccount['id'], true);
            $currentOwed = max(0, -$customerLedger['balance']);
            $newOwed = round($currentOwed + $total, 2);
            $limit = (float)$customer['credit_limit'];
            if ($limit > 0 && $newOwed > $limit) {
                throw new Exception(
                    'تجاوز حد الائتمان. الحد: '.number_format($limit,2).
                    '، الرصيد الحالي: '.number_format($currentOwed,2).
                    '، بعد البيع: '.number_format($newOwed,2)
                );
            }
        }

        $owner = accountingEnsureOwnerAccount($pdo);
        if ($paymentMethod === 'cash') {
            $debitAccount = accountingEnsureCashAccount($pdo);
        } elseif ($paymentMethod === 'card') {
            $debitAccount = accountingEnsureCardAccount($pdo);
        } else {
            $debitAccount = $customerAccount;
        }

        $invoiceNo = null;
        for ($i=0;$i<10;$i++) {
            $candidate='INV-'.date('YmdHis').'-'.random_int(100,999);
            $q=$pdo->prepare("SELECT id FROM sales WHERE invoice_no=? LIMIT 1");
            $q->execute([$candidate]);
            if (!$q->fetch()) {$invoiceNo=$candidate;break;}
        }
        if (!$invoiceNo) throw new Exception('تعذر إنشاء رقم فاتورة فريد.');

        $stmt=$pdo->prepare("
            INSERT INTO sales
            (invoice_no,shift_id,pump_id,employee_id,customer_id,sale_date,payment_method,subtotal,discount,total,notes)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $invoiceNo,$shiftId,$pumpId,$employeeId,$customerId,$saleDateDb,
            $paymentMethod,$subtotal,$discount,$total,$notes!==''?$notes:null
        ]);
        $saleId=(int)$pdo->lastInsertId();

        $stmt=$pdo->prepare("
            INSERT INTO sale_items (sale_id,fuel_type_id,quantity,unit_price,total)
            VALUES (?,?,?,?,?)
        ");
        $stmt->execute([$saleId,$fuelTypeId,$quantity,$unitPrice,$subtotal]);

        $newVolume=round($currentVolume-$quantity,2);
        $stmt=$pdo->prepare("UPDATE tanks SET current_volume=? WHERE id=?");
        $stmt->execute([$newVolume,$tank['id']]);

        $stmt=$pdo->prepare("
            INSERT INTO tank_movements
            (tank_id,movement_type,quantity,reference_id,movement_date,notes)
            VALUES (?,'sale',?,?,?,?)
        ");
        $stmt->execute([$tank['id'],-$quantity,$saleId,$saleDateDb,'بيع وقود - فاتورة '.$invoiceNo]);

        $stmt=$pdo->prepare("
            UPDATE shift_pumps SET total_liters=?,total_sales=? WHERE id=?
        ");
        $stmt->execute([
            round((float)$shiftPump['total_liters']+$quantity,2),
            round((float)$shiftPump['total_sales']+$total,2),
            $shiftPump['id']
        ]);

        // القيد المحاسبي الوحيد للبيع: مدين واحد + دائن واحد.
        accountingPostBalancedTransaction(
            $pdo,'sale','sale',$saleId,
            [
                ['account_id'=>(int)$debitAccount['id'],'debit'=>$total,'credit'=>0,
                 'description'=>'بيع وقود - فاتورة '.$invoiceNo],
                ['account_id'=>(int)$owner['id'],'debit'=>0,'credit'=>$total,
                 'description'=>'إيراد بيع وقود - فاتورة '.$invoiceNo]
            ],
            $saleDateDb,
            'بيع وقود - فاتورة '.$invoiceNo,
            accountingUserId(),
            $invoiceNo
        );

        if ($paymentMethod === 'credit') {
            accountingRecalculateCustomerLegacyBalance($pdo,$customerId);
        }

        // إجمالي مبيعات الوردية.
        $stmt=$pdo->prepare("
            SELECT COALESCE(SUM(total),0) total_sales,
                   COALESCE(SUM(CASE WHEN payment_method='cash' THEN total ELSE 0 END),0) cash_sales
            FROM sales WHERE shift_id=?
        ");
        $stmt->execute([$shiftId]);
        $totals=$stmt->fetch(PDO::FETCH_ASSOC);

        $stmt=$pdo->prepare("SELECT COALESCE(SUM(amount),0) total_withdrawals FROM employee_withdrawals WHERE shift_id=?");
        $stmt->execute([$shiftId]);
        $withdrawals=(float)$stmt->fetchColumn();

        $expectedCash=round((float)$shift['opening_cash']+(float)$totals['cash_sales']-$withdrawals,2);
        $stmt=$pdo->prepare("
            UPDATE shifts SET total_sales=?,total_withdrawals=?,expected_cash=?
            WHERE id=? AND status='open'
        ");
        $stmt->execute([
            round((float)$totals['total_sales'],2),
            round($withdrawals,2),
            $expectedCash,
            $shiftId
        ]);
        if ($stmt->rowCount() !== 1) throw new Exception('تعذر تحديث إجماليات الوردية.');

        $pdo->commit();
        header('Location: view.php?id='.$saleId);
        exit;
    }
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    $error=$e->getMessage();
}

require_once __DIR__.'/../includes/header.php';
?>
<div class="page-header"><div><h2>تسجيل عملية بيع</h2><p>بيع الوقود مع تحديث المخزون والوردية والقيد المحاسبي المزدوج.</p></div><a href="index.php" class="btn">العودة للمبيعات</a></div>
<?php if($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif; ?>
<div class="card">
<form method="POST" id="saleForm">
<div class="form-group"><label>الوردية</label><select name="shift_id" id="shift_id" required><option value="">اختر الوردية</option>
<?php foreach($shifts as $shift): ?><option value="<?=$shift['id']?>" <?=((int)($_POST['shift_id']??0)===(int)$shift['id']?'selected':'')?>><?=htmlspecialchars($shift['shift_no'].' - '.$shift['employee_name'],ENT_QUOTES,'UTF-8')?></option><?php endforeach; ?></select></div>
<div class="form-group"><label>الطرمبة</label><select name="pump_id" id="pump_id" required><option value="">اختر الوردية أولاً</option></select><small id="pumpInfo"></small></div>
<div class="form-group"><label>الموظف</label><input type="text" id="employee_name" readonly></div>
<div class="form-group"><label>نوع الوقود</label><select name="fuel_type_id" id="fuel_type_id" required><option value="">اختر نوع الوقود</option>
<?php foreach($fuelTypes as $fuel): ?><option value="<?=$fuel['id']?>" <?=((int)($_POST['fuel_type_id']??0)===(int)$fuel['id']?'selected':'')?>><?=htmlspecialchars($fuel['name'],ENT_QUOTES,'UTF-8')?></option><?php endforeach; ?></select></div>
<div class="form-group"><label>طريقة الدفع</label><select name="payment_method" id="payment_method" required>
<option value="cash" <?=($_POST['payment_method']??'cash')==='cash'?'selected':''?>>نقدي</option>
<option value="card" <?=($_POST['payment_method']??'')==='card'?'selected':''?>>بطاقة</option>
<option value="credit" <?=($_POST['payment_method']??'')==='credit'?'selected':''?>>آجل</option>
</select></div>
<div class="form-group" id="customerGroup" style="display:none"><label>العميل <span style="color:red">*</span></label>
<select name="customer_id" id="customer_id"><option value="">اختر العميل</option>
<?php foreach($customers as $c): ?><option value="<?=$c['id']?>" data-balance="<?=htmlspecialchars($c['balance'])?>" data-limit="<?=htmlspecialchars($c['credit_limit'])?>" <?=((int)($_POST['customer_id']??0)===(int)$c['id']?'selected':'')?>><?=htmlspecialchars($c['customer_no'].' - '.$c['full_name'],ENT_QUOTES,'UTF-8')?></option><?php endforeach; ?></select><small id="customerInfo"></small></div>
<div class="form-group"><label>الكمية / لتر</label><input type="number" name="quantity" id="quantity" step="0.01" min="0.01" value="<?=htmlspecialchars($_POST['quantity']??'')?>" required></div>
<div class="form-group"><label>سعر اللتر</label><input type="number" name="unit_price" id="unit_price" step="0.01" min="0.01" value="<?=htmlspecialchars($_POST['unit_price']??'')?>" required></div>
<div class="form-group"><label>الخصم</label><input type="number" name="discount" id="discount" step="0.01" min="0" value="<?=htmlspecialchars($_POST['discount']??'0')?>"></div>
<div class="form-group"><label>المجموع قبل الخصم</label><input type="text" id="subtotal_display" readonly value="0.00"></div>
<div class="form-group"><label>الإجمالي النهائي</label><input type="text" id="total_display" readonly value="0.00"></div>
<div class="form-group"><label>تاريخ ووقت البيع</label><input type="datetime-local" name="sale_date" value="<?=htmlspecialchars($_POST['sale_date']??date('Y-m-d\TH:i'))?>" required></div>
<div class="form-group"><label>ملاحظات</label><textarea name="notes" rows="4"><?=htmlspecialchars($_POST['notes']??'')?></textarea></div>
<div class="form-actions"><button class="btn btn-primary" id="submitButton">تسجيل البيع</button><a href="index.php" class="btn">إلغاء</a></div>
</form></div>
<script>
const shifts=<?=json_encode($shifts,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
const shiftSelect=document.getElementById('shift_id'),pumpSelect=document.getElementById('pump_id'),employeeInput=document.getElementById('employee_name'),pumpInfo=document.getElementById('pumpInfo'),fuelTypeSelect=document.getElementById('fuel_type_id'),paymentMethod=document.getElementById('payment_method'),customerGroup=document.getElementById('customerGroup'),customerSelect=document.getElementById('customer_id'),customerInfo=document.getElementById('customerInfo'),quantity=document.getElementById('quantity'),price=document.getElementById('unit_price'),discount=document.getElementById('discount'),subtotal=document.getElementById('subtotal_display'),total=document.getElementById('total_display'),form=document.getElementById('saleForm'),submitButton=document.getElementById('submitButton');
function calc(){let q=parseFloat(quantity.value)||0,p=parseFloat(price.value)||0,d=parseFloat(discount.value)||0,s=q*p;subtotal.value=s.toFixed(2);total.value=Math.max(s-d,0).toFixed(2);}
function toggleCustomer(){let x=paymentMethod.value==='credit';customerGroup.style.display=x?'block':'none';customerSelect.required=x;if(!x){customerSelect.value='';customerInfo.textContent='';}else updateCustomer();}
function updateCustomer(){let o=customerSelect.options[customerSelect.selectedIndex];if(!o||!o.value){customerInfo.textContent='';return;}customerInfo.textContent='الرصيد الحالي: '+(parseFloat(o.dataset.balance)||0).toFixed(2)+' | حد الائتمان: '+((parseFloat(o.dataset.limit)||0)>0?(parseFloat(o.dataset.limit)||0).toFixed(2):'غير محدد');}
async function loadPumps(id,selected=''){pumpSelect.innerHTML='<option>جاري التحميل...</option>';let s=shifts.find(x=>parseInt(x.id)===parseInt(id));employeeInput.value=s?s.employee_no+' - '+s.employee_name:'';if(!id)return;try{let r=await fetch('get_pumps.php?shift_id='+encodeURIComponent(id),{headers:{Accept:'application/json'}});let d=await r.json();pumpSelect.innerHTML='<option value="">اختر الطرمبة</option>';if(d.success&&Array.isArray(d.pumps)){d.pumps.forEach(p=>{let o=document.createElement('option');o.value=p.id;o.textContent='طرمبة '+p.pump_no+' - '+p.name+' - '+p.fuel_name;o.dataset.fuelTypeId=p.fuel_type_id;o.dataset.fuelName=p.fuel_name;o.dataset.currentVolume=p.current_volume;pumpSelect.appendChild(o);});if(selected){pumpSelect.value=selected;updatePump();}}else pumpSelect.innerHTML='<option value="">لا توجد طرمبات</option>';}catch(e){pumpSelect.innerHTML='<option value="">تعذر تحميل الطرمبات</option>';}}
function updatePump(){let o=pumpSelect.options[pumpSelect.selectedIndex];if(!o||!o.value){pumpInfo.textContent='';return;}pumpInfo.textContent='الوقود: '+o.dataset.fuelName+' | المتوفر: '+(parseFloat(o.dataset.currentVolume)||0).toFixed(2)+' لتر';fuelTypeSelect.value=o.dataset.fuelTypeId;}
shiftSelect.addEventListener('change',()=>loadPumps(shiftSelect.value));pumpSelect.addEventListener('change',updatePump);paymentMethod.addEventListener('change',toggleCustomer);customerSelect.addEventListener('change',updateCustomer);[quantity,price,discount].forEach(x=>x.addEventListener('input',calc));
form.addEventListener('submit',e=>{let q=parseFloat(quantity.value)||0,p=parseFloat(price.value)||0,d=parseFloat(discount.value)||0;if(q<=0||p<=0||d>q*p){e.preventDefault();alert('تحقق من الكمية والسعر والخصم.');return;}if(paymentMethod.value==='credit'&&!customerSelect.value){e.preventDefault();alert('يجب اختيار العميل.');return;}submitButton.disabled=true;submitButton.textContent='جاري التسجيل...';});
document.addEventListener('DOMContentLoaded',()=>{calc();toggleCustomer();updateCustomer();let s=shiftSelect.value;if(s)loadPumps(s,<?=json_encode($_POST['pump_id']??'')?>);});
</script>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
