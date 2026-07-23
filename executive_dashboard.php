<?php 
include 'db_connect.php'; 

// ตรวจสอบการมีอยู่ของตาราง repairs
$check_repairs = $conn->query("SHOW TABLES LIKE 'repairs'");

$total_repairs = 0;
$completed_repairs = 0;
$pending_repairs = 0;
$in_progress_repairs = 0;
$success_rate = 0;
$total_cost = 0;

$monthly_labels_json = "[]";
$monthly_data_json = "[]";
$forecast_data_json = "[]";

$location_labels_json = "[]";
$location_data_json = "[]";

$device_labels_json = "[]";
$device_data_json = "[]";

$top_equipment = "ไม่มีข้อมูล";
$top_equipment_count = 0;

if($check_repairs && $check_repairs->num_rows > 0) {
    // 1. KPIs ภาพรวม
    $res = $conn->query("SELECT count(*) as c FROM repairs");
    $total_repairs = $res ? $res->fetch_assoc()['c'] : 0;
    
    $res = $conn->query("SELECT count(*) as c FROM repairs WHERE status='ซ่อมเสร็จแล้ว' OR status='เสร็จสิ้น'");
    $completed_repairs = $res ? $res->fetch_assoc()['c'] : 0;
    
    $res = $conn->query("SELECT count(*) as c FROM repairs WHERE status='กำลังดำเนินการ'");
    $in_progress_repairs = $res ? $res->fetch_assoc()['c'] : 0;

    $res = $conn->query("SELECT count(*) as c FROM repairs WHERE status='รอดำเนินการ' OR status='รอรับเรื่อง'");
    $pending_repairs = $res ? $res->fetch_assoc()['c'] : 0;
    
    $success_rate = ($total_repairs > 0) ? round(($completed_repairs / $total_repairs) * 100) : 0;

    // สรุปค่าใช้จ่ายรวม (ถ้ามี column cost)
    $cost_res = $conn->query("SELECT SUM(cost) as total FROM repairs");
    if($cost_res) {
        $cost_row = $cost_res->fetch_assoc();
        $total_cost = $cost_row['total'] ?? 0;
    }

    // 2. วิเคราะห์อุปกรณ์ที่เสียบ่อยที่สุด (Top Equipment)
    $top_eq_query = $conn->query("SELECT equipment_type, COUNT(*) as cnt FROM repairs GROUP BY equipment_type ORDER BY cnt DESC LIMIT 1");
    if($top_eq_query && $top_eq_query->num_rows > 0) {
        $top_eq_data = $top_eq_query->fetch_assoc();
        $top_equipment = $top_eq_data['equipment_type'] ?: 'ไม่ระบุ';
        $top_equipment_count = $top_eq_data['cnt'];
    }

    // 3. สถิติตามหน่วยงาน/สถานที่ (Top Locations)
    $loc_res = $conn->query("SELECT location, COUNT(*) as cnt FROM repairs GROUP BY location ORDER BY cnt DESC LIMIT 5");
    $loc_labels = []; $loc_counts = [];
    if ($loc_res) {
        while($loc = $loc_res->fetch_assoc()){ 
            $loc_labels[] = $loc['location'] ?: 'ไม่ระบุ'; 
            $loc_counts[] = $loc['cnt']; 
        }
    }
    $location_labels_json = json_encode($loc_labels);
    $location_data_json = json_encode($loc_counts);

    // 4. สถิติตามประเภทอุปกรณ์ (Device Type Chart)
    $dev_res = $conn->query("SELECT equipment_type, COUNT(*) as cnt FROM repairs GROUP BY equipment_type ORDER BY cnt DESC");
    $dev_labels = []; $dev_counts = [];
    if ($dev_res) {
        while($dev = $dev_res->fetch_assoc()){ 
            $dev_labels[] = $dev['equipment_type'] ?: 'อื่นๆ'; 
            $dev_counts[] = $dev['cnt']; 
        }
    }
    $device_labels_json = json_encode($dev_labels);
    $device_data_json = json_encode($dev_counts);

    // 5. แนวโน้มรายเดือน & คาดการณ์
    $thai_months = ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
    $current_m = (int)date('m') - 1;
    
    $labels = [];
    $actual_data = [];
    $forecast_data = [];

    // ดึงสถิติจริง 5 เดือนล่าสุด
    for($i = 4; $i >= 0; $i--) {
        $m_index = ($current_m - $i + 12) % 12;
        $m_num = $m_index + 1;
        $labels[] = $thai_months[$m_index];
        
        $m_query = $conn->query("SELECT COUNT(*) as cnt FROM repairs WHERE MONTH(created_at) = {$m_num}");
        $cnt = $m_query ? $m_query->fetch_assoc()['cnt'] : 0;
        $actual_data[] = (int)$cnt;
        $forecast_data[] = null;
    }

    // เดือนหน้า (คาดการณ์)
    $next_m_index = ($current_m + 1) % 12;
    $labels[] = $thai_months[$next_m_index] . " (คาดการณ์)";
    $avg = count($actual_data) > 0 ? array_sum($actual_data) / count($actual_data) : 0;
    $predicted_value = round($avg * 1.1); // เพิ่มประมาณ 10%
    
    $forecast_data[count($actual_data)-1] = $actual_data[count($actual_data)-1]; // เชื่อมเส้น
    $actual_data[] = null; 
    $forecast_data[] = $predicted_value;

    $monthly_labels_json = json_encode($labels);
    $monthly_data_json = json_encode($actual_data);
    $forecast_data_json = json_encode($forecast_data);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>Executive Dashboard - MBS Smart Maintenance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { color-scheme: light; }
        body { font-family: 'Kanit', sans-serif; background-color: #f8fafc; color: #334155; }
        .modern-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1.25rem; box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03); transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .modern-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px -2px rgba(0, 0, 0, 0.06); }
        .nav-btn { width: 100%; display: flex; align-items: center; padding: 0.875rem 1.25rem; margin-bottom: 0.25rem; border-radius: 0.75rem; color: #64748b; font-weight: 500; transition: all 0.2s; }
        .nav-btn i { width: 1.5rem; text-align: center; font-size: 1.25rem; margin-right: 0.75rem; color: #94a3b8; transition: all 0.2s; }
        .nav-btn:hover { background-color: #f8fafc; color: #0284c7; }
        .nav-btn:hover i { color: #0ea5e9; transform: scale(1.1); }
        .active-btn { background-color: #f0f9ff; color: #0369a1; font-weight: 600; box-shadow: 0 2px 10px rgba(14, 165, 233, 0.1); border: 1px solid #bae6fd; }
        .active-btn i { color: #0284c7; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        
        @media print {
            aside, header, .no-print { display: none !important; }
            main { padding: 0 !important; margin: 0 !important; background: white; }
            .modern-card { box-shadow: none; border: 1px solid #ddd; break-inside: avoid; }
            body { background: white; }
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden selection:bg-sky-200">

    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden transition-opacity" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="w-64 md:w-72 bg-white border-r border-slate-200 flex flex-col shrink-0 fixed inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-300 ease-in-out z-50 shadow-[4px_0_24px_rgba(0,0,0,0.02)] no-print">
        <div class="h-20 md:h-24 flex items-center justify-between px-5 md:px-8 border-b border-slate-100">
            <div class="flex items-center">
                <div class="w-10 h-10 md:w-12 md:h-12 rounded-2xl bg-gradient-to-tr from-indigo-600 to-purple-500 flex items-center justify-center shadow-lg shadow-purple-500/30 mr-3 md:mr-4 shrink-0">
                    <i class="fas fa-chart-line text-white text-lg md:text-xl"></i>
                </div>
                <div class="overflow-hidden flex-1">
                    <h1 class="text-lg md:text-xl font-bold text-slate-800 leading-tight tracking-tight">MBS REPAIR</h1>
                    <p class="text-[10px] md:text-xs text-purple-600 font-semibold tracking-widest uppercase mt-0.5">Executive View</p>
                </div>
            </div>
            <button onclick="toggleSidebar()" class="md:hidden text-slate-400 hover:text-red-500 focus:outline-none">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <nav class="flex-1 px-4 md:px-5 py-6 md:py-8 flex flex-col overflow-y-auto">
            <p class="px-2 text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">เมนูผู้บริหาร</p>
            <button class="nav-btn active-btn"><i class="fas fa-tachometer-alt"></i> Dashboard ภาพรวม</button>
            <div class="mt-auto pt-4 border-t border-slate-100">
                <a href="index.php" class="nav-btn text-rose-500 hover:bg-rose-50 hover:text-rose-600"><i class="fas fa-sign-out-alt text-rose-400"></i> ออกจากระบบ</a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 overflow-hidden relative">
        <div class="absolute top-0 left-0 w-full h-96 bg-gradient-to-b from-indigo-50/80 to-transparent -z-10 no-print"></div>
        
        <!-- Header -->
        <header class="h-20 bg-white/80 backdrop-blur-md border-b border-slate-200 flex items-center justify-between px-4 md:px-10 shrink-0 z-10 sticky top-0 no-print">
            <div class="flex items-center overflow-hidden">
                <button onclick="toggleSidebar()" class="md:hidden mr-4 text-slate-500 hover:text-indigo-600 focus:outline-none shrink-0">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h2 class="text-xl md:text-2xl font-bold text-slate-800 tracking-wide truncate">Executive Dashboard</h2>
            </div>
            
            <div class="flex items-center space-x-3 md:space-x-6 shrink-0">
                <button onclick="window.print()" class="hidden sm:flex bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 hover:text-indigo-600 px-4 py-2 rounded-xl text-sm font-bold shadow-sm items-center transition-colors">
                    <i class="fas fa-print mr-2"></i> พิมพ์รายงาน
                </button>
                <div class="flex items-center space-x-3 p-1.5 md:pr-4 rounded-full border border-slate-200 bg-white shadow-sm">
                    <div class="w-8 h-8 md:w-9 md:h-9 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 font-bold"><i class="fas fa-user-tie text-sm"></i></div>
                    <div class="hidden sm:block text-left"><span class="block text-sm font-semibold text-slate-700 leading-none mb-1">Executive Board</span><span class="block text-[11px] text-slate-500 uppercase tracking-wide leading-none">ผู้บริหารระบบ</span></div>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 md:p-8 print:p-0">
            
            <!-- 1. Executive KPIs Section -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
                <!-- KPI 1 -->
                <div class="modern-card p-5 border-b-4 border-indigo-500 bg-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500 text-xs md:text-sm font-medium mb-1">อัตราซ่อมสำเร็จ (KPI)</p>
                            <h3 class="text-3xl font-extrabold text-slate-800"><?php echo $success_rate; ?><span class="text-xl text-slate-400">%</span></h3>
                            <p class="text-[11px] text-emerald-600 font-semibold mt-2"><i class="fas fa-arrow-up mr-1"></i>เสร็จแล้ว <?php echo $completed_repairs; ?> จาก <?php echo $total_repairs; ?> งาน</p>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-500"><i class="fas fa-check-double text-lg"></i></div>
                    </div>
                </div>
                
                <!-- KPI 2 -->
                <div class="modern-card p-5 border-b-4 border-sky-500 bg-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500 text-xs md:text-sm font-medium mb-1">งานซ่อมทั้งหมด</p>
                            <h3 class="text-3xl font-extrabold text-slate-800"><?php echo $total_repairs; ?> <span class="text-base font-medium text-slate-400">งาน</span></h3>
                            <p class="text-[11px] text-sky-600 font-semibold mt-2"><i class="fas fa-spinner fa-spin mr-1"></i>กำลังทำ <?php echo $in_progress_repairs; ?> งาน</p>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-sky-50 flex items-center justify-center text-sky-500"><i class="fas fa-tools text-lg"></i></div>
                    </div>
                </div>

                <!-- KPI 3 -->
                <div class="modern-card p-5 border-b-4 border-amber-500 bg-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500 text-xs md:text-sm font-medium mb-1">งานค้าง / รอดำเนินการ</p>
                            <h3 class="text-3xl font-extrabold text-slate-800"><?php echo $pending_repairs; ?> <span class="text-base font-medium text-slate-400">งาน</span></h3>
                            <p class="text-[11px] text-amber-600 font-semibold mt-2"><i class="fas fa-clock mr-1"></i>ต้องรีบจัดสรรช่าง</p>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center text-amber-500"><i class="fas fa-hourglass-half text-lg"></i></div>
                    </div>
                </div>

                <!-- KPI 4: ค่าใช้จ่าย -->
                <div class="modern-card p-5 border-b-4 border-emerald-500 bg-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500 text-xs md:text-sm font-medium mb-1">งบประมาณ / ค่าใช้จ่ายรวม</p>
                            <h3 class="text-2xl md:text-3xl font-extrabold text-slate-800">฿<?php echo number_format($total_cost, 0); ?></h3>
                            <p class="text-[11px] text-emerald-600 font-semibold mt-2"><i class="fas fa-wallet mr-1"></i>สรุปตามการลงบันทึก</p>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-500"><i class="fas fa-coins text-lg"></i></div>
                    </div>
                </div>
            </div>

            <!-- AI Insight Highlight -->
            <div class="modern-card p-5 mb-8 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white shadow-xl">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-8 h-8 rounded-lg bg-purple-500/20 flex items-center justify-center text-purple-300"><i class="fas fa-robot text-lg"></i></div>
                    <h3 class="font-bold text-white tracking-wide">Executive AI Recommendation</h3>
                </div>
                <p class="text-sm text-slate-300 leading-relaxed pl-11">
                    อุปกรณ์ประเภท <strong class="text-purple-200 underline decoration-purple-400 underline-offset-4">"<?php echo htmlspecialchars($top_equipment); ?>"</strong> มีอัตราการแจ้งเสียสูงที่สุดในองค์กร (รวม <?php echo $top_equipment_count; ?> ครั้ง) 
                    <span class="text-purple-300 block sm:inline mt-1 sm:mt-0">💡 <strong>ข้อเสนอแนะ:</strong> พิจารณาตั้งงบเปลี่ยนทดแทนอุปกรณ์ล็อตเก่าเพื่อลดค่าใช้จ่ายซ่อมบำรุงระยะยาว</span>
                </p>
            </div>

            <!-- 2. Charts Grid (3 แผง) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                
                <!-- Chart 1: สถิติรายเดือน & คาดการณ์ (2 Cols) -->
                <div class="lg:col-span-2 modern-card p-5 bg-white flex flex-col">
                    <div class="mb-4 flex justify-between items-center">
                        <div>
                            <h3 class="font-bold text-slate-800 text-base md:text-lg flex items-center"><i class="fas fa-chart-line text-indigo-500 mr-2"></i> สถิติการแจ้งซ่อมและคาดการณ์แนวโน้ม</h3>
                            <p class="text-xs text-slate-500 mt-0.5">วิเคราะห์สถิติย้อนหลังและประเมินปริมาณงานในเดือนถัดไป</p>
                        </div>
                    </div>
                    <div class="flex-1 relative w-full h-[260px]">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>

                <!-- Chart 2: สัดส่วนอุปกรณ์ (1 Col) -->
                <div class="modern-card p-5 bg-white flex flex-col">
                    <div class="mb-4">
                        <h3 class="font-bold text-slate-800 text-base md:text-lg flex items-center"><i class="fas fa-chart-pie text-purple-500 mr-2"></i> สัดส่วนตามประเภทอุปกรณ์</h3>
                        <p class="text-xs text-slate-500 mt-0.5">จำแนกตามประเภทของครุภัณฑ์</p>
                    </div>
                    <div class="flex-1 relative w-full h-[260px] flex items-center justify-center">
                        <canvas id="deviceChart"></canvas>
                    </div>
                </div>

            </div>

            <!-- 3. Bottom Row: สถิติแผนก & ตารางแจ้งซ่อมล่าสุด -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                
                <!-- Chart 3: สถิติตามสถานที่/แผนก (1 Col) -->
                <div class="modern-card p-5 bg-white flex flex-col">
                    <div class="mb-4">
                        <h3 class="font-bold text-slate-800 text-base md:text-lg flex items-center"><i class="fas fa-building text-sky-500 mr-2"></i> 5 หน่วยงานที่แจ้งซ่อมมากสุด</h3>
                        <p class="text-xs text-slate-500 mt-0.5">ช่วยในการกระจายกำลังช่างประจำจุด</p>
                    </div>
                    <div class="flex-1 relative w-full h-[250px]">
                        <canvas id="locationChart"></canvas>
                    </div>
                </div>

                <!-- Recent Jobs Table (2 Cols) -->
                <div class="lg:col-span-2 modern-card bg-white overflow-hidden flex flex-col">
                    <div class="p-5 border-b border-slate-100 flex justify-between items-center">
                        <h3 class="font-bold text-slate-800 text-base"><i class="fas fa-list-alt text-slate-400 mr-2"></i> บันทึกงานแจ้งซ่อมล่าสุด</h3>
                        <span class="text-xs text-slate-400">แสดง 5 รายการล่าสุด</span>
                    </div>
                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left whitespace-nowrap">
                            <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 text-xs uppercase font-semibold">
                                <tr>
                                    <th class="px-5 py-3">วัน/เวลา</th>
                                    <th class="px-5 py-3">เลขใบงาน</th>
                                    <th class="px-5 py-3">ประเภทอุปกรณ์</th>
                                    <th class="px-5 py-3">สถานที่</th>
                                    <th class="px-5 py-3 text-center">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody class="text-xs md:text-sm divide-y divide-slate-100">
                                <?php
                                if($check_repairs && $check_repairs->num_rows > 0) {
                                    $recent_res = $conn->query("SELECT * FROM repairs ORDER BY created_at DESC LIMIT 5");
                                    if($recent_res && $recent_res->num_rows > 0){
                                        while($row = $recent_res->fetch_assoc()) {
                                            $date = date("d/m/Y H:i", strtotime($row['created_at']));
                                            $statusClass = "bg-slate-100 text-slate-600 border-slate-200"; 
                                            
                                            $st = $row['status'] ?? '';
                                            if($st == 'รอรับเรื่อง' || $st == 'รอดำเนินการ') $statusClass = "bg-amber-50 text-amber-600 border-amber-200";
                                            elseif($st == 'กำลังดำเนินการ') $statusClass = "bg-sky-50 text-sky-600 border-sky-200";
                                            elseif($st == 'ซ่อมเสร็จแล้ว' || $st == 'เสร็จสิ้น') $statusClass = "bg-emerald-50 text-emerald-600 border-emerald-200";

                                            $ticket = $row['ticket_no'] ?? ('#REP-'.$row['id']);
                                            $eq = $row['equipment_type'] ?? ($row['device_name'] ?? 'ไม่ระบุ');
                                            $loc = $row['location'] ?? 'ไม่ระบุ';

                                            echo "<tr class='hover:bg-slate-50 transition-colors'>
                                                <td class='px-5 py-3 text-slate-500'>{$date}</td>
                                                <td class='px-5 py-3 font-bold text-slate-700'>{$ticket}</td>
                                                <td class='px-5 py-3 font-semibold text-slate-800'>{$eq}</td>
                                                <td class='px-5 py-3 text-slate-600'>{$loc}</td>
                                                <td class='px-5 py-3 text-center'><span class='inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border {$statusClass}'>{$st}</span></td>
                                            </tr>";
                                        }
                                    } else { echo "<tr><td colspan='5' class='px-5 py-8 text-center text-slate-400'>ไม่มีข้อมูลการแจ้งซ่อม</td></tr>"; }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>
    </main>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('sidebarOverlay').classList.toggle('hidden');
        }

        document.addEventListener('DOMContentLoaded', () => {
            
            // 1. Line Chart (สถิติรายเดือน + Predictive)
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: <?php echo $monthly_labels_json; ?>,
                    datasets: [
                        {
                            label: 'งานซ่อมจริง',
                            data: <?php echo $monthly_data_json; ?>,
                            borderColor: '#4f46e5',
                            backgroundColor: 'rgba(79, 70, 229, 0.08)',
                            borderWidth: 3,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#4f46e5',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            fill: true,
                            tension: 0.3
                        },
                        {
                            label: 'คาดการณ์ (Forecast)',
                            data: <?php echo $forecast_data_json; ?>,
                            borderColor: '#f59e0b',
                            borderWidth: 3,
                            borderDash: [5, 5],
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#f59e0b',
                            pointBorderWidth: 2,
                            pointRadius: 6,
                            pointStyle: 'rectRot',
                            fill: false,
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true, 
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { position: 'top', labels: { usePointStyle: true, font: { family: "'Kanit', sans-serif" } } } 
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0, font: { family: "'Kanit', sans-serif" } }, grid: { borderDash: [4, 4] } },
                        x: { ticks: { font: { family: "'Kanit', sans-serif" } }, grid: { display: false } }
                    }
                }
            });

            // 2. Doughnut Chart (ประเภทอุปกรณ์)
            const devCtx = document.getElementById('deviceChart').getContext('2d');
            new Chart(devCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo $device_labels_json; ?>,
                    datasets: [{
                        data: <?php echo $device_data_json; ?>,
                        backgroundColor: ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6'],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { family: "'Kanit', sans-serif", size: 11 } } }
                    }
                }
            });

            // 3. Horizontal Bar Chart (สถิติตามสถานที่)
            const locCtx = document.getElementById('locationChart').getContext('2d');
            new Chart(locCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo $location_labels_json; ?>,
                    datasets: [{ 
                        label: 'จำนวนงาน', 
                        data: <?php echo $location_data_json; ?>, 
                        backgroundColor: '#0ea5e9',
                        borderRadius: 6,
                        barPercentage: 0.6
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    indexAxis: 'y', 
                    plugins: { legend: { display: false } }, 
                    scales: { 
                        x: { beginAtZero: true, ticks: { precision: 0, font: { family: "'Kanit', sans-serif" } }, grid: { borderDash: [4, 4] } }, 
                        y: { ticks: { font: { family: "'Kanit', sans-serif", weight: '500' } }, grid: { display: false } } 
                    } 
                }
            });

        });
    </script>
</body>
</html>