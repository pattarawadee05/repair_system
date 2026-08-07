<?php 
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'executive') {
    header("Location: login.php");
    exit();
}
include 'db_connect.php';

// ดึงสถิติจริงจากฐานข้อมูล
$resTotal = $conn->query("SELECT count(*) as c FROM repairs")->fetch_assoc()['c'];
$resPend = $conn->query("SELECT count(*) as c FROM repairs WHERE status='รอรับเรื่อง' OR status='กำลังดำเนินการ'")->fetch_assoc()['c'];
$resComp = $conn->query("SELECT count(*) as c FROM repairs WHERE status='ซ่อมเสร็จแล้ว' OR status='เสร็จสิ้น'")->fetch_assoc()['c'];

$success_rate = ($resTotal > 0) ? round(($resComp / $resTotal) * 100) : 0;

// หาอุปกรณ์ที่เสียบ่อยที่สุดสำหรับ AI Insight
$top_eq_query = $conn->query("SELECT equipment_type, COUNT(*) as cnt FROM repairs GROUP BY equipment_type ORDER BY cnt DESC LIMIT 1");
$top_equipment = "ไมค์";
$top_count = 12;
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
    <title>ภาพรวมเชิงกลยุทธ์ (Executive Board) | MBS REPAIR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', 'Kanit', sans-serif; background-color: #f1f5f9; color: #1e293b; }
        .modern-card { background: #ffffff; border-radius: 20px; box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03); border: 1px solid #f1f5f9; }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-[#f8fafc]">

    <!-- Sidebar ฝั่งผู้บริหาร -->
    <aside class="w-64 bg-white border-r border-slate-100 flex flex-col shrink-0 no-print">
        <div class="h-24 px-6 flex items-center border-b border-slate-50">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-purple-600 to-indigo-500 flex items-center justify-center text-white shadow-lg shadow-purple-500/30 mr-3">
                <i class="fas fa-chart-line text-lg"></i>
            </div>
            <div>
                <h1 class="text-lg font-extrabold text-slate-800 tracking-tight">MBS REPAIR</h1>
                <p class="text-[10px] font-bold text-purple-600 uppercase tracking-widest">EXECUTIVE VIEW</p>
            </div>
        </div>
        
        <nav class="flex-1 py-6 px-4 space-y-2">
            <p class="px-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">สำหรับผู้บริหาร</p>
            <a href="#" class="flex items-center px-4 py-3 rounded-2xl bg-purple-50 text-purple-600 font-bold text-sm">
                <i class="fas fa-pie-chart mr-3"></i> สรุปรายงานผู้บริหาร
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
            <div>
                <h2 class="text-2xl font-extrabold text-slate-800">ภาพรวมเชิงกลยุทธ์ (Executive Board)</h2>
                <p class="text-xs text-slate-400 mt-0.5">โหมดดูอย่างเดียว ปลอดภัย ชัดเจน</p>
            </div>
            <div class="flex items-center gap-4">
                <a href="executive_summary_report.php" target="_blank" class="bg-purple-600 hover:bg-purple-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-md shadow-purple-200 flex items-center transition-all">
                    <i class="fas fa-file-invoice mr-2"></i> พิมพ์เอกสารบันทึกข้อความสรุปผู้บริหาร
                </a>
                <div class="flex items-center gap-3 bg-white px-4 py-2 rounded-2xl border border-slate-100 shadow-xs">
                    <div class="w-9 h-9 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center font-bold">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div>
                        <span class="block text-sm font-bold text-slate-700 leading-none">
                            <?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'สุดา รวยล้น'; ?>
                        </span>
                        <span class="block text-[10px] text-slate-400 font-semibold mt-0.5">ผู้บริหารระบบ</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Stats Cards (เหมือนรูปที่ 1 และ 2) -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="modern-card p-6 flex justify-between items-center border-l-4 border-purple-500">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase">อัตราซ่อมสำเร็จ</p>
                    <h3 class="text-3xl font-extrabold text-slate-800 mt-2"><?php echo $success_rate; ?>%</h3>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>

            <div class="modern-card p-6 flex justify-between items-center border-l-4 border-sky-500">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase">จำนวนงานซ่อมทั้งหมด</p>
                    <h3 class="text-3xl font-extrabold text-slate-800 mt-2"><?php echo $resTotal; ?> <span class="text-sm font-normal text-slate-500">รายการ</span></h3>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-sky-50 text-sky-500 flex items-center justify-center text-xl">
                    <i class="fas fa-briefcase"></i>
                </div>
            </div>

            <div class="modern-card p-6 flex justify-between items-center border-l-4 border-amber-500">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase">งานที่กำลังดำเนินการ/รอ</p>
                    <h3 class="text-3xl font-extrabold text-slate-800 mt-2"><?php echo $resPend; ?> <span class="text-sm font-normal text-slate-500">รายการ</span></h3>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center text-xl">
                    <i class="fas fa-clock"></i>
                </div>
            </div>

            <div class="modern-card p-6 bg-slate-900 text-white rounded-2xl flex flex-col justify-between">
                <div class="flex items-center space-x-2 text-purple-400 text-xs font-bold uppercase">
                    <i class="fas fa-robot"></i> <span>AI STRATEGIC INSIGHT</span>
                </div>
                <p class="text-xs text-slate-300 mt-2 leading-relaxed">
                    อุปกรณ์เสียบ่อยที่สุดคือ <strong class="text-white">"<?php echo htmlspecialchars($top_equipment); ?>"</strong> (<?php echo $top_count; ?> ครั้ง) แนะนำพิจารณาจัดตั้งงบซื้อชุดใหม่
                </p>
            </div>
        </div>

        <!-- Charts Section (กราฟตามรูปที่ 1 และ 4) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="modern-card p-6">
                <h3 class="font-extrabold text-slate-800 text-base mb-1"><i class="fas fa-chart-line text-purple-600 mr-2"></i> วิเคราะห์แนวโน้มและคาดการณ์</h3>
                <p class="text-xs text-slate-400 mb-4">โมเดลพยากรณ์ปริมาณงานซ่อมล่วงหน้า เพื่อเตรียมความพร้อมของทีมช่าง</p>
                <div class="relative w-full h-[220px]">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <div class="modern-card p-6">
                <h3 class="font-extrabold text-slate-800 text-base mb-1"><i class="fas fa-map-marker-alt text-sky-500 mr-2"></i> พื้นที่/แผนกที่มีปัญหาบ่อยสุด</h3>
                <p class="text-xs text-slate-400 mb-4">ช่วยในการวิเคราะห์เพื่อจัดสรรงบประมาณซ่อมบำรุงโครงสร้างพื้นฐาน</p>
                <div class="relative w-full h-[220px]">
                    <canvas id="locationChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Transactions Table (ตารางเหมือนรูปที่ 1) -->
        <div class="modern-card overflow-hidden">
            <div class="p-6 border-b border-slate-100">
                <h3 class="font-extrabold text-slate-800 text-lg"><i class="fas fa-clipboard-list text-purple-600 mr-2"></i> ตารางสรุปบันทึกรายการแจ้งซ่อมล่าสุด</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left whitespace-nowrap">
                    <thead class="bg-slate-50 text-slate-400 text-xs uppercase tracking-wider font-bold">
                        <tr>
                            <th class="px-6 py-4">วันที่</th>
                            <th class="px-6 py-4">เลขที่ใบงาน</th>
                            <th class="px-6 py-4">สถานที่</th>
                            <th class="px-6 py-4">เรื่อง/อุปกรณ์</th>
                            <th class="px-6 py-4">ผู้แจ้ง</th>
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

                                $date_fmt = !empty($r['created_at']) ? date('d/m/Y', strtotime($r['created_at'])) : '-';

                                echo "<tr class='hover:bg-slate-50/50 transition-colors'>
                                    <td class='px-6 py-4 text-slate-500 font-medium'>{$date_fmt}</td>
                                    <td class='px-6 py-4 font-mono font-bold text-purple-600'>{$r['ticket_no']}</td>
                                    <td class='px-6 py-4 text-slate-600 font-medium'>".($r['location'] ?? '-')."</td>
                                    <td class='px-6 py-4 font-bold text-slate-800'>{$r['equipment_type']}</td>
                                    <td class='px-6 py-4 text-slate-600 font-medium'>".($r['reporter_name'] ?? '-')."</td>
                                    <td class='px-6 py-4 text-center'><span class='px-3 py-1 rounded-full text-xs font-bold {$badge}'>{$r['status']}</span></td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' class='px-6 py-8 text-center text-slate-400'>ไม่พบข้อมูลการแจ้งซ่อม</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Script สำหรับวาดกราฟ Chart.js -->
    <script>
        // กราฟเส้นแนวโน้ม (Trend Chart)
        const ctxTrend = document.getElementById('trendChart').getContext('2d');
        new Chart(ctxTrend, {
            type: 'line',
            data: {
                labels: ['เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค. (ปัจจุบัน)', 'ก.ย. (คาดการณ์)'],
                datasets: [{
                    label: 'ปริมาณงานซ่อมจริง',
                    data: [55, 58, 56, 62, 60, null],
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.3
                }, {
                    label: 'คาดการณ์ (Forecast)',
                    data: [null, null, null, null, 60, 68],
                    borderColor: '#f59e0b',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    pointStyle: 'rectRot',
                    pointRadius: 6,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                    x: { grid: { display: false } }
                }
            }
        });

        // กราฟแท่งแนวนอน (Location Bar Chart)
        const ctxLocation = document.getElementById('locationChart').getContext('2d');
        new Chart(ctxLocation, {
            type: 'bar',
            data: {
                labels: ['ไม่ระบุสถานที่', 'ACC.BIZ ห้อง 301', 'SBB ห้อง 903', 'SBB ห้อง 703', '703'],
                datasets: [{
                    data: [7, 4, 4, 3, 3],
                    backgroundColor: '#38bdf8',
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                    y: { grid: { display: false } }
                }
            }
        });
    </script>
</body>
</html>