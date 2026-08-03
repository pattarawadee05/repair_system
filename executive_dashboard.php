<?php 
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (strtolower($_SESSION['role'] ?? '') === 'technician') {
    header("Location: dashboard.php");
    exit();
}

include 'db_connect.php'; 

// คำนวณ KPIs
$total_repairs = $conn->query("SELECT count(*) as c FROM repairs")->fetch_assoc()['c'] ?? 0;
$completed_repairs = $conn->query("SELECT count(*) as c FROM repairs WHERE status='ซ่อมเสร็จแล้ว'")->fetch_assoc()['c'] ?? 0;
$pending_repairs = $conn->query("SELECT count(*) as c FROM repairs WHERE status != 'ซ่อมเสร็จแล้ว'")->fetch_assoc()['c'] ?? 0;
$success_rate = ($total_repairs > 0) ? round(($completed_repairs / $total_repairs) * 100) : 0;

// อุปกรณ์เสียบ่อยสุด
$top_equipment = "-";
$top_equipment_count = 0;
$top_eq_q = $conn->query("SELECT equipment_type, COUNT(*) as cnt FROM repairs GROUP BY equipment_type ORDER BY cnt DESC LIMIT 1");
if($top_eq_q && $top_eq_q->num_rows > 0) {
    $d = $top_eq_q->fetch_assoc();
    $top_equipment = $d['equipment_type'];
    $top_equipment_count = $d['cnt'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Dashboard - MBS REPAIR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Kanit', sans-serif; background-color: #f0f4f8; }
        .modern-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1.25rem; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

    <aside class="w-64 bg-white border-r border-slate-200 flex flex-col p-6 space-y-6">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-2xl bg-indigo-600 flex items-center justify-center text-white font-bold"><i class="fas fa-chart-line"></i></div>
            <div>
                <h1 class="font-bold text-slate-800">MBS REPAIR</h1>
                <p class="text-xs text-purple-600 font-semibold">Executive View Only</p>
            </div>
        </div>
        <nav class="space-y-1">
            <a href="#" class="flex items-center p-3 bg-indigo-50 text-indigo-600 rounded-xl font-bold text-sm"><i class="fas fa-tachometer-alt mr-3"></i> สรุปรายงานผู้บริหาร</a>
        </nav>
        <div class="mt-auto pt-6 border-t">
            <a href="logout.php" class="flex items-center p-3 text-rose-500 rounded-xl font-bold text-sm"><i class="fas fa-sign-out-alt mr-3"></i> ออกจากระบบ</a>
        </div>
    </aside>

    <main class="flex-1 overflow-y-auto p-8 space-y-6">
        <header class="flex justify-between items-center pb-6 border-b">
            <div>
                <h2 class="text-2xl font-bold text-slate-800">ภาพรวมเชิงกลยุทธ์ (Executive Board)</h2>
                <p class="text-xs text-slate-400">โหมดดูอย่างเดียว ปลอดภัย ชัดเจน</p>
            </div>
            <a href="executive_summary_report.php" target="_blank" class="px-5 py-2.5 bg-indigo-600 text-white font-bold rounded-xl text-sm shadow-md hover:bg-indigo-700">
                <i class="fas fa-print mr-2"></i> พิมพ์เอกสารบันทึกข้อความสรุปผู้บริหาร
            </a>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="modern-card p-6 border-b-4 border-indigo-500">
                <p class="text-slate-400 text-xs font-bold uppercase">อัตราซ่อมสำเร็จ</p>
                <h3 class="text-3xl font-extrabold text-slate-800 mt-2"><?php echo $success_rate; ?>%</h3>
            </div>
            <div class="modern-card p-6 border-b-4 border-sky-500">
                <p class="text-slate-400 text-xs font-bold uppercase">จำนวนงานแจ้งซ่อมทั้งหมด</p>
                <h3 class="text-3xl font-extrabold text-slate-800 mt-2"><?php echo $total_repairs; ?> รายการ</h3>
            </div>
            <div class="modern-card p-6 border-b-4 border-amber-500">
                <p class="text-slate-400 text-xs font-bold uppercase">งานที่กำลังดำเนินการ/รอ</p>
                <h3 class="text-3xl font-extrabold text-amber-500 mt-2"><?php echo $pending_repairs; ?> รายการ</h3>
            </div>
            <div class="modern-card p-6 bg-slate-900 text-white">
                <p class="text-purple-400 text-xs font-bold uppercase">💡 AI Strategic Insight</p>
                <p class="text-xs text-slate-300 mt-2">อุปกรณ์เสียบ่อยสุดคือ <strong class="text-white"><?php echo $top_equipment; ?></strong> (<?php echo $top_equipment_count; ?> ครั้ง) แนะนำพิจารณาจัดตั้งงบจัดซื้อชุดใหม่</p>
            </div>
        </div>

        <div class="modern-card p-6">
            <h3 class="font-bold text-slate-800 mb-4">ตารางสรุปบันทึกรายการแจ้งซ่อมล่าสุด</h3>
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-400 text-xs uppercase font-bold">
                    <tr>
                        <th class="p-3">วันที่</th>
                        <th class="p-3">เลขใบงาน</th>
                        <th class="p-3">สถานที่</th>
                        <th class="p-3">เรื่อง/อุปกรณ์</th>
                        <th class="p-3">ผู้แจ้ง</th>
                        <th class="p-3">สถานะ</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php 
                    $recent = $conn->query("SELECT * FROM repairs ORDER BY created_at DESC LIMIT 10");
                    while($r = $recent->fetch_assoc()){
                        echo "<tr>
                            <td class='p-3 text-slate-400 text-xs'>".date('d/m/Y', strtotime($r['created_at']))."</td>
                            <td class='p-3 font-mono font-bold text-indigo-600'>{$r['ticket_no']}</td>
                            <td class='p-3'>{$r['location']}</td>
                            <td class='p-3 font-semibold'>{$r['equipment_type']}</td>
                            <td class='p-3'>{$r['reporter_name']}</td>
                            <td class='p-3'><span class='px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700'>{$r['status']}</span></td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>