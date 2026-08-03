<?php
session_start();
include 'db_connect.php';

$selected_tech = isset($_GET['tech']) ? trim($_GET['tech']) : 'all';
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// กำหนด Header บังคับให้ดาวน์โหลดเป็นไฟล์ Excel
$filename = "MBSRepair_LogBook_All_Overview_" . date('Ymd_His') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=$filename");
header("Pragma: no-cache");
header("Expires: 0");

// เงื่อนไข SQL
$where_conditions = [];
if ($selected_month > 0) {
    $where_conditions[] = "MONTH(created_at) = $selected_month";
}
if ($selected_tech !== 'all' && $selected_tech !== '') {
    $tech_esc = $conn->real_escape_string($selected_tech);
    $where_conditions[] = "TRIM(technician_name) = '$tech_esc'";
}
$where_sql = count($where_conditions) > 0 ? "WHERE " . implode(" AND ", $where_conditions) : "";

$result = $conn->query("SELECT * FROM repairs $where_sql ORDER BY created_at DESC");

$month_names = [1=>"มกราคม", 2=>"กุมภาพันธ์", 3=>"มีนาคม", 4=>"เมษายน", 5=>"พฤษภาคม", 6=>"มิถุนายน", 7=>"กรกฎาคม", 8=>"สิงหาคม", 9=>"กันยายน", 10=>"ตุลาคม", 11=>"พฤศจิกายน", 12=>"ธันวาคม"];
$thai_month_str = $month_names[$selected_month] ?? 'ทั้งหมด';
$thai_year_full = $selected_year + 543;
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<style>
    body { font-family: 'Sarabun', 'Segoe UI', Tahoma, sans-serif; }
    .header-title { font-size: 16px; font-weight: bold; text-align: center; background-color: #033495; color: #ffffff; height: 35px; }
    .meta-table { font-size: 13px; font-weight: bold; }
    .th-header { background-color: #033495; color: #ffffff; font-weight: bold; text-align: center; border: 0.5pt solid #000000; height: 28px; }
    .td-cell { border: 0.5pt solid #000000; font-size: 12px; vertical-align: middle; }
    .text-center { text-align: center; }
</style>
</head>
<body>

<table>
    <tr>
        <td colspan="12" class="header-title">รายงานทะเบียนประวัติการแจ้งซ่อม (Log Book) ผ่านระบบ MBS REPAIR</td>
    </tr>
    <tr class="meta-table">
        <td colspan="12">หน่วยงาน: คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</td>
    </tr>
    <tr class="meta-table">
        <td colspan="12">ขอบเขตการรายงาน: (<?php echo $selected_tech === 'all' ? 'ภาพรวมระบบทั้งหมด' : 'เฉพาะช่าง ' . htmlspecialchars($selected_tech); ?>)</td>
    </tr>
    <tr class="meta-table">
        <td colspan="6">ข้อมูลประจำเดือน: <?php echo $thai_month_str; ?> <?php echo $thai_year_full; ?></td>
        <td colspan="3">วันที่พิมพ์รายงาน: <?php echo date('d/m/Y'); ?></td>
        <td colspan="3">ผู้พิมพ์รายงาน: <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'สุดา รวยล้น'); ?></td>
    </tr>
</table>

<br>

<table border="1">
    <thead>
        <tr>
            <th class="th-header">ลำดับ</th>
            <th class="th-header">เลขที่ใบงาน</th>
            <th class="th-header">วัน/เวลาที่แจ้ง</th>
            <th class="th-header">หมวดหมู่/อุปกรณ์</th>
            <th class="th-header">อาการเสีย</th>
            <th class="th-header">สถานที่/ห้อง</th>
            <th class="th-header">ผู้แจ้ง</th>
            <th class="th-header">เบอร์โทรติดต่อ</th>
            <th class="th-header">สถานะงาน</th>
            <th class="th-header">ช่างผู้ดำเนินการ</th>
            <th class="th-header">เวลาปิดงาน/อัปเดตล่าสุด</th>
            <th class="th-header">หมายเหตุจากช่าง</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        if ($result && $result->num_rows > 0) {
            $i = 1;
            while ($r = $result->fetch_assoc()) {
                $created_time = date('d/m/Y H:i', strtotime($r['created_at']));
                $completed_time = !empty($r['completed_at']) ? date('d/m/Y H:i', strtotime($r['completed_at'])) : (!empty($r['updated_at']) ? date('d/m/Y H:i', strtotime($r['updated_at'])) : '-');
                $tech_name = !empty($r['technician_name']) ? $r['technician_name'] : '-';
                $remark = !empty($r['rating_comment']) ? $r['rating_comment'] : '-';
                
                echo "<tr>
                    <td class='td-cell text-center'>{$i}</td>
                    <td class='td-cell text-center'>{$r['ticket_no']}</td>
                    <td class='td-cell text-center'>{$created_time}</td>
                    <td class='td-cell'>".htmlspecialchars($r['equipment_type'])."</td>
                    <td class='td-cell'>".htmlspecialchars($r['problem_desc'])."</td>
                    <td class='td-cell'>".htmlspecialchars($r['location_room'] ?? '-')."</td>
                    <td class='td-cell'>".htmlspecialchars($r['reporter_name'])."</td>
                    <td class='td-cell text-center'>".htmlspecialchars($r['phone_number'] ?? '-')."</td>
                    <td class='td-cell text-center'>".htmlspecialchars($r['status'])."</td>
                    <td class='td-cell text-center'>".htmlspecialchars($tech_name)."</td>
                    <td class='td-cell text-center'>{$completed_time}</td>
                    <td class='td-cell'>".htmlspecialchars($remark)."</td>
                </tr>";
                $i++;
            }
        } else {
            echo "<tr><td colspan='12' class='td-cell text-center'>ไม่พบข้อมูลการแจ้งซ่อม</td></tr>";
        }
        ?>
    </tbody>
</table>

</body>
</html>