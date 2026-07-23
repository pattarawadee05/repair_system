<?php 
include 'db_connect.php'; 

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
    // 1. KPIs
    $res = $conn->query("SELECT count(*) as c FROM repairs");
    $total_repairs = $res ? $res->fetch_assoc()['c'] : 0;
    
    $res = $conn->query("SELECT count(*) as c FROM repairs WHERE status='ซ่อมเสร็จแล้ว' OR status='เสร็จสิ้น'");
    $completed_repairs = $res ? $res->fetch_assoc()['c'] : 0;
    
    $res = $conn->query("SELECT count(*) as c FROM repairs WHERE status='กำลังดำเนินการ'");
    $in_progress_repairs = $res ? $res->fetch_assoc()['c'] : 0;

    $res = $conn->query("SELECT count(*) as c FROM repairs WHERE status='รอดำเนินการ' OR status='รอรับเรื่อง'");
    $pending_repairs = $res ? $res->fetch_assoc()['c'] : 0;
    
    $success_rate = ($total_repairs > 0) ? round(($completed_repairs / $total_repairs) * 100) : 0;

    $cost_res = $conn->query("SELECT SUM(cost) as total FROM repairs");
    if($cost_res) {
        $cost_row = $cost_res->fetch_assoc();
        $total_cost = $cost_row['total'] ?? 0;
    }

    // 2. Top Equipment
    $top_eq_query = $conn->query("SELECT equipment_type, COUNT(*) as cnt FROM repairs GROUP BY equipment_type ORDER BY cnt DESC LIMIT 1");
    if($top_eq_query && $top_eq_query->num_rows > 0) {
        $top_eq_data = $top_eq_query->fetch_assoc();
        $top_equipment = $top_eq_data['equipment_type'] ?: 'ไม่ระบุ';
        $top_equipment_count = $top_eq_data['cnt'];
    }

    // 3. Top Locations
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

    // 4. Device Chart
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

    // 5. Monthly Trend
    $thai_months = ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
    $current_m = (int)date('m') - 1;
    
    $labels = [];
    $actual_data = [];
    $forecast_data = [];

    for($i = 4; $i >= 0; $i--) {
        $m_index = ($current_m - $i + 12) % 12;
        $m_num = $m_index + 1;
        $labels[] = $thai_months[$m_index];
        
        $m_query = $conn->query("SELECT COUNT(*) as cnt FROM repairs WHERE MONTH(created_at) = {$m_num}");
        $cnt = $m_query ? $m_query->fetch_assoc()['cnt'] : 0;
        $actual_data[] = (int)$cnt;
        $forecast_data[] = null;
    }

    $next_m_index = ($current_m + 1) % 12;
    $labels[] = $thai_months[$next_m_index] . " (คาดการณ์)";
    $avg = count($actual_data) > 0 ? array_sum($actual_data) / count($actual_data) : 0;
    $predicted_value = round($avg * 1.15);
    
    $forecast_data[count($actual_data)-1] = $actual_data[count($actual_data)-1];
    $actual_data[] = null; 
    $forecast_data[] = $predicted_value;

    $monthly_labels_json = json_encode($labels);
    $monthly_data_json = json_encode($actual_data);
    $forecast_data_json = json_encode($forecast_data);
}
?>
<!DOCTYPE html>
<html lang="th" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Control Center - MBS REPAIR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        darkbg: '#0b0f19',
                        darkcard: 'rgba(17, 24, 39, 0.7)',
                        glassborder: 'rgba(255, 255, 255, 0.08)',
                        neonpurple: '#a855f7',
                        neoncyan: '#06b6d4',
                        neonblue: '#3b82f6',
                        neongreen: '#10b981'
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Kanit', 'Plus Jakarta Sans', sans-serif; background-color: #070a12; color: #f3f4f6; }
        .glass-panel {
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 1.25rem;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        }
        .glass-panel:hover {
            border-color: rgba(168, 85, 247, 0.3);
            box-shadow: 0 10px 40px -10px rgba(168, 85, 247, 0.2);
            transition: all 0.3s ease;
        }
        .glow-effect-purple { box-shadow: 0 0 25px -5px rgba(168, 85, 247, 0.25); }
        .glow-effect-cyan { box-shadow: 0 0 25px -5px rgba(6, 182, 212, 0.25); }
        .glow-effect-green { box-shadow: 0 0 25px -5px rgba(16, 185, 129, 0.25); }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.15); border-radius: 10px; }
        
        @media print {
            aside, header, .no-print { display: none !important; }
            main { padding: 0 !important; background: #070a12; }
            .glass-panel { border: 1px solid #334155; }
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden selection:bg-purple-500 selection:text-white">

    <!-- Ambient Gradient Background Shapes -->
    <div class="fixed top-[-10%] left-[-10%] w-[500px] h-[500px] bg-purple-600/20 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="fixed bottom-[-10%] right-[-10%] w-[600px] h-[600px] bg-blue-600/15 rounded-full blur-[150px] pointer-events-none"></div>

    <div id="sidebarOverlay" class="fixed inset-0 bg-black/60 z-40 hidden md:hidden backdrop-blur-sm" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="w-64 md:w-72 glass-panel border-r border-white/10 flex flex-col shrink-0 fixed inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-300 ease-in-out z-50 rounded-none md:rounded-r-2xl my-0">
        <div class="h-24 flex items-center justify-between px-6 border-b border-white/5">
            <div class="flex items-center space-x-3">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-purple-600 via-indigo-500 to-cyan-400 flex items-center justify-center shadow-lg shadow-purple-500/30">
                    <i class="fas fa-microchip text-white text-xl"></i>
                </div>
                <div>
                    <h1 class="text-lg font-bold text-white tracking-wider">MBS SYSTEM</h1>
                    <span class="text-[10px] text-purple-400 font-bold uppercase tracking-widest bg-purple-500/10 px-2 py-0.5 rounded-full border border-purple-500/20">Executive AI</span>
                </div>
            </div>
            <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <nav class="flex-1 px-4 py-8 space-y-2 overflow-y-auto">
            <p class="px-3 text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-3">NAVIGATION</p>
            <button class="w-full flex items-center px-4 py-3.5 rounded-xl bg-gradient-to-r from-purple-600/30 to-indigo-600/20 border border-purple-500/30 text-purple-200 font-medium transition-all shadow-lg shadow-purple-900/20">
                <i class="fas fa-chart-pie text-purple-400 mr-3 text-lg"></i> Executive Dashboard
            </button>
            <div class="pt-8 mt-auto">
                <a href="index.php" class="w-full flex items-center px-4 py-3 rounded-xl text-rose-400 hover:bg-rose-500/10 border border-transparent hover:border-rose-500/20 transition-all text-sm font-medium">
                    <i class="fas fa-power-off mr-3"></i> ออกจากระบบ
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 overflow-hidden relative z-10">
        
        <!-- Header -->
        <header class="h-20 border-b border-white/5 flex items-center justify-between px-6 md:px-10 shrink-0 sticky top-0 backdrop-blur-xl bg-black/20 no-print">
            <div class="flex items-center space-x-4">
                <button onclick="toggleSidebar()" class="md:hidden text-gray-300 hover:text-white">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <div>
                    <h2 class="text-xl md:text-2xl font-extrabold text-white tracking-wide flex items-center gap-2">
                        Executive Control Center <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    </h2>
                </div>
            </div>
            
            <div class="flex items-center space-x-4">
                <button onclick="window.print()" class="hidden sm:flex bg-white/5 hover:bg-white/10 border border-white/10 text-gray-200 px-4 py-2 rounded-xl text-xs font-semibold items-center transition-all">
                    <i class="fas fa-print mr-2 text-purple-400"></i> พิมพ์รายงาน
                </button>
                <div class="flex items-center space-x-3 pl-3 border-l border-white/10">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-purple-500 to-indigo-500 flex items-center justify-center text-white font-bold text-sm shadow-md shadow-purple-500/20">
                        EX
                    </div>
                    <div class="hidden sm:block text-left">
                        <span class="block text-xs font-semibold text-white leading-tight">Executive Board</span>
                        <span class="block text-[10px] text-gray-400">System Admin</span>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-6 md:p-8 space-y-8">
            
            <!-- 1. Key Performance Indicators (KPI Cards) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                
                <!-- KPI 1 -->
                <div class="glass-panel p-5 relative overflow-hidden group">
                    <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-purple-500/10 rounded-full blur-2xl group-hover:bg-purple-500/20 transition-all"></div>
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="text-xs text-gray-400 font-medium uppercase tracking-wider">Success Rate</span>
                            <h3 class="text-3xl font-extrabold text-white mt-1"><?php echo $success_rate; ?><span class="text-lg text-purple-400 font-normal">%</span></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400">
                            <i class="fas fa-shield-halved text-lg"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-xs text-emerald-400">
                        <i class="fas fa-check-circle mr-1.5"></i> สำเร็จ <?php echo $completed_repairs; ?> จาก <?php echo $total_repairs; ?> งาน
                    </div>
                </div>

                <!-- KPI 2 -->
                <div class="glass-panel p-5 relative overflow-hidden group">
                    <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-cyan-500/10 rounded-full blur-2xl group-hover:bg-cyan-500/20 transition-all"></div>
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="text-xs text-gray-400 font-medium uppercase tracking-wider">Total Repairs</span>
                            <h3 class="text-3xl font-extrabold text-white mt-1"><?php echo $total_repairs; ?> <span class="text-xs font-normal text-gray-400">รายการ</span></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400">
                            <i class="fas fa-layer-group text-lg"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-xs text-cyan-400">
                        <i class="fas fa-spinner fa-spin mr-1.5"></i> กำลังซ่อม <?php echo $in_progress_repairs; ?> งาน
                    </div>
                </div>

                <!-- KPI 3 -->
                <div class="glass-panel p-5 relative overflow-hidden group">
                    <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-amber-500/10 rounded-full blur-2xl group-hover:bg-amber-500/20 transition-all"></div>
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="text-xs text-gray-400 font-medium uppercase tracking-wider">Pending Jobs</span>
                            <h3 class="text-3xl font-extrabold text-white mt-1"><?php echo $pending_repairs; ?> <span class="text-xs font-normal text-gray-400">รายการ</span></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                            <i class="fas fa-hourglass-half text-lg"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-xs text-amber-400">
                        <i class="fas fa-exclamation-triangle mr-1.5"></i> รอดำเนินการรับเรื่อง
                    </div>
                </div>

                <!-- KPI 4 -->
                <div class="glass-panel p-5 relative overflow-hidden group">
                    <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-emerald-500/10 rounded-full blur-2xl group-hover:bg-emerald-500/20 transition-all"></div>
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="text-xs text-gray-400 font-medium uppercase tracking-wider">Total Expenses</span>
                            <h3 class="text-2xl font-extrabold text-white mt-1">฿<?php echo number_format($total_cost, 0); ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                            <i class="fas fa-vault text-lg"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-xs text-emerald-400">
                        <i class="fas fa-chart-line mr-1.5"></i> สรุปตามงบที่บันทึก
                    </div>
                </div>

            </div>

            <!-- AI Predictive Insights Banner -->
            <div class="glass-panel p-5 relative overflow-hidden border border-purple-500/30 glow-effect-purple bg-gradient-to-r from-purple-900/30 via-indigo-900/20 to-transparent">
                <div class="flex items-start sm:items-center space-x-4">
                    <div class="w-12 h-12 rounded-2xl bg-purple-500/20 border border-purple-500/40 flex items-center justify-center text-purple-300 shrink-0 shadow-lg shadow-purple-500/30">
                        <i class="fas fa-brain text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center space-x-2">
                            <h4 class="font-bold text-white text-sm">AI Executive Insights</h4>
                            <span class="text-[9px] bg-purple-500/30 text-purple-200 font-bold px-2 py-0.5 rounded-full border border-purple-400/30">PREDICTIVE</span>
                        </div>
                        <p class="text-xs text-gray-300 mt-1 leading-relaxed">
                            อุปกรณ์ประเภท <span class="text-purple-300 font-bold underline decoration-purple-400">"<?php echo htmlspecialchars($top_equipment); ?>"</span> มีความถี่การเสียสูงที่สุด (รวม <?php echo $top_equipment_count; ?> ครั้ง) 
                            <span class="text-purple-300 block sm:inline">💡 ข้อแนะนำ: พิจารณาตั้งงบประมาณเพื่อจัดซื้อทดแทนอุปกรณ์ล็อตเก่าในปีถัดไป</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- 2. Main Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Line Chart (2 Cols) -->
                <div class="lg:col-span-2 glass-panel p-6 flex flex-col">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="font-bold text-white text-base flex items-center">
                                <i class="fas fa-chart-area text-purple-400 mr-2.5"></i> แนวโน้มสถิติและการคาดการณ์อนาคต
                            </h3>
                            <p class="text-xs text-gray-400 mt-0.5">วิเคราะห์สถิติจริงและระบบพยากรณ์ปริมาณงานในเดือนถัดไป</p>
                        </div>
                    </div>
                    <div class="flex-1 relative w-full h-[280px]">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>

                <!-- Doughnut Chart (1 Col) -->
                <div class="glass-panel p-6 flex flex-col">
                    <div class="mb-6">
                        <h3 class="font-bold text-white text-base flex items-center">
                            <i class="fas fa-chart-pie text-cyan-400 mr-2.5"></i> สัดส่วนตามประเภทอุปกรณ์
                        </h3>
                        <p class="text-xs text-gray-400 mt-0.5">จำแนกตามประเภทของครุภัณฑ์</p>
                    </div>
                    <div class="flex-1 relative w-full h-[280px] flex items-center justify-center">
                        <canvas id="deviceChart"></canvas>
                    </div>
                </div>

            </div>

            <!-- 3. Bottom Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Horizontal Bar Chart (1 Col) -->
                <div class="glass-panel p-6 flex flex-col">
                    <div class="mb-6">
                        <h3 class="font-bold text-white text-base flex items-center">
                            <i class="fas fa-building text-amber-400 mr-2.5"></i> Top 5 พื้นที่แจ้งซ่อมสูงสุด
                        </h3>
                        <p class="text-xs text-gray-400 mt-0.5">จัดอันดับหน่วยงานเพื่อกระจายกำลังช่าง</p>
                    </div>
                    <div class="flex-1 relative w-full h-[260px]">
                        <canvas id="locationChart"></canvas>
                    </div>
                </div>

                <!-- Recent Jobs Table (2 Cols) -->
                <div class="lg:col-span-2 glass-panel overflow-hidden flex flex-col">
                    <div class="p-6 border-b border-white/5 flex justify-between items-center">
                        <h3 class="font-bold text-white text-base flex items-center">
                            <i class="fas fa-list-check text-emerald-400 mr-2.5"></i> บันทึกการแจ้งซ่อมล่าสุด
                        </h3>
                        <span class="text-xs text-gray-400 bg-white/5 px-2.5 py-1 rounded-lg border border-white/5">5 รายการล่าสุด</span>
                    </div>
                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left whitespace-nowrap">
                            <thead class="bg-white/5 text-gray-400 text-xs font-semibold uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-3.5">วัน/เวลา</th>
                                    <th class="px-6 py-3.5">เลขใบงาน</th>
                                    <th class="px-6 py-3.5">ประเภทอุปกรณ์</th>
                                    <th class="px-6 py-3.5">สถานที่</th>
                                    <th class="px-6 py-3.5 text-center">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody class="text-xs divide-y divide-white/5">
                                <?php
                                if($check_repairs && $check_repairs->num_rows > 0) {
                                    $recent_res = $conn->query("SELECT * FROM repairs ORDER BY created_at DESC LIMIT 5");
                                    if($recent_res && $recent_res->num_rows > 0){
                                        while($row = $recent_res->fetch_assoc()) {
                                            $date = date("d/m/Y H:i", strtotime($row['created_at']));
                                            
                                            $st = $row['status'] ?? '';
                                            $badgeStyle = "bg-gray-500/10 text-gray-300 border-gray-500/20";
                                            if($st == 'รอรับเรื่อง' || $st == 'รอดำเนินการ') $badgeStyle = "bg-amber-500/10 text-amber-300 border-amber-500/30";
                                            elseif($st == 'กำลังดำเนินการ') $badgeStyle = "bg-cyan-500/10 text-cyan-300 border-cyan-500/30";
                                            elseif($st == 'ซ่อมเสร็จแล้ว' || $st == 'เสร็จสิ้น') $badgeStyle = "bg-emerald-500/10 text-emerald-300 border-emerald-500/30";

                                            $ticket = $row['ticket_no'] ?? ('#REP-'.$row['id']);
                                            $eq = $row['equipment_type'] ?? ($row['device_name'] ?? 'ไม่ระบุ');
                                            $loc = $row['location'] ?? 'ไม่ระบุ';

                                            echo "<tr class='hover:bg-white/5 transition-colors'>
                                                <td class='px-6 py-4 text-gray-400'>{$date}</td>
                                                <td class='px-6 py-4 font-bold text-purple-300'>{$ticket}</td>
                                                <td class='px-6 py-4 font-semibold text-gray-200'>{$eq}</td>
                                                <td class='px-6 py-4 text-gray-300'>{$loc}</td>
                                                <td class='px-6 py-4 text-center'><span class='inline-block px-3 py-1 rounded-full text-[10px] font-bold border {$badgeStyle}'>{$st}</span></td>
                                            </tr>";
                                        }
                                    } else { echo "<tr><td colspan='5' class='px-6 py-8 text-center text-gray-500'>ไม่มีข้อมูลการแจ้งซ่อม</td></tr>"; }
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
        // Set Chart.js Defaults for Dark Mode
        Chart.defaults.color = '#9ca3af';
        Chart.defaults.font.family = "'Kanit', 'Plus Jakarta Sans', sans-serif";

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('sidebarOverlay').classList.toggle('hidden');
        }

        document.addEventListener('DOMContentLoaded', () => {
            
            // 1. Line Chart
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            
            const gradientActual = trendCtx.createLinearGradient(0, 0, 0, 300);
            gradientActual.addColorStop(0, 'rgba(168, 85, 247, 0.4)');
            gradientActual.addColorStop(1, 'rgba(168, 85, 247, 0.0)');

            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: <?php echo $monthly_labels_json; ?>,
                    datasets: [
                        {
                            label: 'งานซ่อมจริง',
                            data: <?php echo $monthly_data_json; ?>,
                            borderColor: '#a855f7',
                            backgroundColor: gradientActual,
                            borderWidth: 3,
                            pointBackgroundColor: '#a855f7',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'คาดการณ์ (Forecast)',
                            data: <?php echo $forecast_data_json; ?>,
                            borderColor: '#06b6d4',
                            borderWidth: 3,
                            borderDash: [6, 6],
                            pointBackgroundColor: '#06b6d4',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 6,
                            pointStyle: 'rectRot',
                            fill: false,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true, 
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 8 } } 
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(255, 255, 255, 0.05)' } },
                        x: { grid: { display: false } }
                    }
                }
            });

            // 2. Doughnut Chart
            const devCtx = document.getElementById('deviceChart').getContext('2d');
            new Chart(devCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo $device_labels_json; ?>,
                    datasets: [{
                        data: <?php echo $device_data_json; ?>,
                        backgroundColor: ['#a855f7', '#06b6d4', '#10b981', '#f59e0b', '#ec4899', '#6366f1'],
                        borderWidth: 3,
                        borderColor: '#0f172a'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } }
                    }
                }
            });

            // 3. Horizontal Bar Chart
            const locCtx = document.getElementById('locationChart').getContext('2d');
            new Chart(locCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo $location_labels_json; ?>,
                    datasets: [{ 
                        label: 'จำนวนงาน', 
                        data: <?php echo $location_data_json; ?>, 
                        backgroundColor: '#3b82f6',
                        borderRadius: 8,
                        barPercentage: 0.5
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    indexAxis: 'y', 
                    plugins: { legend: { display: false } }, 
                    scales: { 
                        x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(255, 255, 255, 0.05)' } }, 
                        y: { grid: { display: false } } 
                    } 
                }
            });

        });
    </script>
</body>
</html>