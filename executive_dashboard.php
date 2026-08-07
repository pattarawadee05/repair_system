<?php 
session_start();
// เช็คสิทธิ์: ต้องล็อกอิน และต้องเป็น Executive เท่านั้น
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'executive') {
    header("Location: login.php");
    exit();
}
include 'db_connect.php';

// ดึงสถิติต่างๆ จากฐานข้อมูลจริง
$resTotal = $conn->query("SELECT count(*) as c FROM repairs")->fetch_assoc()['c'];
$resPend = $conn->query("SELECT count(*) as c FROM repairs WHERE status='รอรับเรื่อง'")->fetch_assoc()['c'];
$resProg = $conn->query("SELECT count(*) as c FROM repairs WHERE status='กำลังดำเนินการ'")->fetch_assoc()['c'];
$resComp = $conn->query("SELECT count(*) as c FROM repairs WHERE status='ซ่อมเสร็จแล้ว' OR status='เสร็จสิ้น'")->fetch_assoc()['c'];

$success_rate = ($resTotal > 0) ? round(($resComp / $resTotal) * 100) : 0;

// อุปกรณ์ที่แจ้งซ่อมบ่อยที่สุด (สำหรับ AI Recommendation)
$top_eq_query = $conn->query("SELECT equipment_type, COUNT(*) as cnt FROM repairs GROUP BY equipment_type ORDER BY cnt DESC LIMIT 1");
$top_equipment = "อุปกรณ์";
$top_count = 0;
if($top_eq_query && $top_eq_query->num_rows > 0) {
    $eq_data = $top_eq_query->fetch_assoc();
    $top_equipment = $eq_data['equipment_type'];
    $top_count = $eq_data['cnt'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive View | MBS Repair</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', 'Kanit', sans-serif; background-color: #f1f5f9; color: #1e293b; }
        .modern-card { background: #ffffff; border-radius: 20px; box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03); border: 1px solid #f1f5f9; }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-[#f8fafc]">

    <!-- Sidebar ฝั่งผู้บริหาร -->
    <aside class="w-64 bg-white border-r border-slate-100 flex flex-col shrink-0">
        <div class="h-24 px-6 flex items-center border-b border-slate-50">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-purple-600 to-indigo-500 flex items-center justify-center text-white shadow-lg shadow-purple-500/30 mr-3">
                <i class="fas fa-chart-line text-lg"></i>
            </div>
            <div>
                <h1 class="text-lg font-extrabold text-slate-800 tracking-tight">MBS REPAIR</h1>
                <p class="text-[10px] font-bold text-purple-600 uppercase tracking-widest">Executive View</p>
            </div>
        </div>
        
        <nav class="flex-1 py-6 px-4 space-y-2">
            <p class="px-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">สำหรับผู้บริหาร</p>
            <a href="#" class="flex items-center px-4 py-3 rounded-2xl bg-purple-50 text-purple-600 font-bold text-sm">
                <i class="fas fa-pie-chart mr-3"></i> ภาพรวมและสถิติ (KPIs)
            </a>
        </nav>

        <div class="p-4 border-t border-slate-50 space-y-2">
            <a href="logout.php" class="flex items-center px-4 py-2.5 rounded-xl text-rose-600 hover:bg-rose-50 text-sm font-bold transition-all">
                <i class="fas fa-sign-out-alt mr-3"></i> ออกจากระบบ
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 overflow-y-auto p-8">
        
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <h2 class="text-2xl font-extrabold text-slate-800">ภาพรวมเชิงกลยุทธ์</h2>
            <div class="flex items-center gap-4">
                <a href="executive_summary_report.php" target="_blank" class="bg-purple-600 hover:bg-purple-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-md shadow-purple-200 flex items-center transition-all">
                    <i class="fas fa-file-invoice mr-2"></i> ออกรายงานผู้บริหาร
                </a>
                <div class="flex items-center gap-3 bg-white px-4 py-2 rounded-2xl border border-slate-100 shadow-xs">
                    <div class="w-9 h-9 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center font-bold">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div>
                        <span class="block text-sm font-bold text-slate-700 leading-none">
                            <?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'ผู้บริหารระบบ'; ?>
                        </span>
                        <span class="block text-[10px] text-slate-400 font-semibold mt-0.5">ผู้บริหารระบบ</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="modern-card p-6 flex justify-between items-center border-l-4 border-purple-500">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase">อัตราซ่อมสำเร็จ (Success Rate)</p>
                    <h3 class="text-3xl font-extrabold text-slate-800 mt-2"><?php echo $success_rate; ?>%</h3>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>

            <div class="modern-card p-6 flex justify-between items-center border-l-4 border-sky-500">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase">จำนวนงานซ่อมทั้งหมด</p>
                    <h3 class="text-3xl font-extrabold text-slate-800 mt-2"><?php echo $resTotal; ?> <span class="text-sm font-normal text-slate-500">งาน</span></h3>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-sky-50 text-sky-500 flex items-center justify-center text-xl">
                    <i class="fas fa-briefcase"></i>
                </div>
            </div>

            <div class="modern-card p-6 flex justify-between items-center border-l-4 border-amber-500">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase">งานที่รอการดำเนินการ</p>
                    <h3 class="text-3xl font-extrabold text-slate-800 mt-2"><?php echo $resPend + $resProg; ?> <span class="text-sm font-normal text-slate-500">งาน</span></h3>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center text-xl">
                    <i class="fas fa-clock"></i>
                </div>
            </div>

            <div class="modern-card p-6 bg-slate-900 text-white rounded-2xl flex flex-col justify-between">
                <div class="flex items-center space-x-2 text-purple-400 text-xs font-bold uppercase">
                    <i class="fas fa-robot"></i> <span>AI Recommendation</span>
                </div>
                <p class="text-xs text-slate-300 mt-2">
                    พบการแจ้งซ่อม <strong class="text-white">"<?php echo htmlspecialchars($top_equipment); ?>"</strong> บ่อยผิดปกติ (<?php echo $top_count; ?> ครั้ง)
                </p>
            </div>
        </div>

        <!-- Recent Transactions Table -->
        <div class="modern-card overflow-hidden">
            <div class="p-6 border-b border-slate-100">
                <h3 class="font-extrabold text-slate-800 text-lg"><i class="fas fa-history text-purple-600 mr-2"></i> บันทึกงานแจ้งซ่อมล่าสุด</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left whitespace-nowrap">
                    <thead class="bg-slate-50 text-slate-400 text-xs uppercase tracking-wider font-bold">
                        <tr>
                            <th class="px-6 py-4">วัน/เวลาที่รับเรื่อง</th>
                            <th class="px-6 py-4">เลขที่ใบงาน</th>
                            <th class="px-6 py-4">ประเภทอุปกรณ์</th>
                            <th class="px-6 py-4">สถานที่</th>
                            <th class="px-6 py-4 text-center">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-slate-100">
                        <?php
                        $recent = $conn->query("SELECT * FROM repairs ORDER BY created_at DESC LIMIT 10");
                        if($recent && $recent->num_rows > 0) {
                            while($r = $recent->fetch_assoc()) {
                                $badge = "bg-amber-100 text-amber-700";
                                if($r['status'] == 'ซ่อมเสร็จแล้ว' || $r['status'] == 'เสร็จสิ้น') $badge = "bg-emerald-100 text-emerald-700";
                                elseif($r['status'] == 'กำลังดำเนินการ') $badge = "bg-sky-100 text-sky-700";

                                echo "<tr class='hover:bg-slate-50/50 transition-colors'>
                                    <td class='px-6 py-4 text-slate-500'>{$r['created_at']}</td>
                                    <td class='px-6 py-4 font-mono font-bold text-slate-700'>{$r['ticket_no']}</td>
                                    <td class='px-6 py-4 font-medium text-slate-800'>{$r['equipment_type']}</td>
                                    <td class='px-6 py-4 text-slate-500'>".($r['location'] ?? '-')."</td>
                                    <td class='px-6 py-4 text-center'><span class='px-3 py-1 rounded-full text-xs font-bold {$badge}'>{$r['status']}</span></td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' class='px-6 py-8 text-center text-slate-400'>ไม่พบข้อมูลการแจ้งซ่อม</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</body>
</html>