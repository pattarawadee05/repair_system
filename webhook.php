<?php
require 'db_connect.php';
require 'env.php';

// บันทึกข้อมูลดิบที่ LINE ส่งมาเก็บไว้ในไฟล์ line_log.txt อัตโนมัติ
$content = file_get_contents('php://input');
file_put_contents('line_log.txt', date('Y-m-d H:i:s') . " - " . $content . "\n", FILE_APPEND);

$events = json_decode($content, true);
// ... โค้ดส่วนที่เหลือต่อจากนี้ ...

if (!is_null($events['events'])) {
    foreach ($events['events'] as $event) {
        
        if ($event['type'] == 'message' && $event['message']['type'] == 'text') {
            $text = trim($event['message']['text']);
            $userId = $event['source']['userId'];
            $groupId = isset($event['source']['groupId']) ? $event['source']['groupId'] : null;
            
            $message_id = $event['message']['id'];
            $quoted_msg_id = isset($event['message']['quotedMessageId']) ? $event['message']['quotedMessageId'] : null;
            $replyToken = isset($event['replyToken']) ? $event['replyToken'] : null;
            
            // ========================================================
            // สเตปที่ 2: ช่างกด Reply ข้อความแจ้งซ่อม (มีคำว่า "ครับ" หรือคำรับงาน)
            // ========================================================
            if ($quoted_msg_id) {
                $text_clean = mb_strtolower(str_replace(' ', '', $text), 'UTF-8');
                
                $accept_words = [
                    'ครับ', 'ค่ะ', 'รับงาน', 'รับทราบ', 'โอเค', 'จัดไป', 'รับเรื่อง', 'กำลังไป', 'ok', 
                    'ได้ครับ', 'ได้ค่ะ', 'ได้ครับผม', 'สักครู่นะครับ', 'เดี๋ยวดูให้ครับ', 'เดี๋ยวแจ้งแม่บ้านให้ครับ'
                ];
                
                $is_accept = false;
                foreach ($accept_words as $w) {
                    if (mb_strpos($text_clean, $w) !== false) {
                        $is_accept = true; 
                        break;
                    }
                }

                if ($is_accept) {
                    // ค้นหาว่าข้อความที่ถูก Reply ตรงกับใบงานไหนในฐานข้อมูล
                    $stmt = $conn->prepare("SELECT ticket_no, status FROM repairs WHERE line_message_id = ?");
                    $stmt->bind_param("s", $quoted_msg_id);
                    $stmt->execute();
                    $job = $stmt->get_result()->fetch_assoc();

                    if ($job && ($job['status'] == 'รอรับเรื่อง' || $job['status'] == 'รอดำเนินการ')) {
                        // ดึงชื่อ LINE Profile ของช่างที่พิมพ์ตอบ
                        $technician_name = get_line_profile($userId, $groupId, $channelAccessToken);
                        
                        // อัปเดตสถานะเป็น "กำลังดำเนินการ" (หรือช่างรับงาน) และบันทึกชื่อช่างลง DB
                        $new_status = "กำลังดำเนินการ";
                        $stmt_up = $conn->prepare("UPDATE repairs SET status = ?, technician_name = ? WHERE ticket_no = ?");
                        $stmt_up->bind_param("sss", $new_status, $technician_name, $job['ticket_no']);
                        if ($stmt_up->execute()) {
                            // ส่ง LINE Reply กลับไปบอกในแชทว่ารับงานเรียบร้อย
                            if ($replyToken) {
                                send_line_reply($replyToken, "🛠️ ช่าง " . $technician_name . " ได้ทำการกดรับงานใบงานเลขที่ " . $job['ticket_no'] . " เรียบร้อยแล้วครับ", $channelAccessToken);
                            }
                        }
                    }
                }
            } 
            // ========================================================
            // สเตปที่ 1: มีคนพิมพ์แจ้งซ่อมใหม่ (@repair-แจ้งซ่อม หรือ แจ้งซ่อม)
            // ========================================================
            else {
                if (mb_strpos($text, '@repair-แจ้งซ่อม') !== false || mb_strpos($text, 'แจ้งซ่อม') !== false) {
                    
                    $user_name = get_line_profile($userId, $groupId, $channelAccessToken);
                    $ticket_no = "MR-" . date("Ymd-His");
                    $status = "รอรับเรื่อง"; 
                    $phone_number = "ไม่ระบุ";
                    
                    // 1. บันทึกข้อมูลเบื้องต้นลง DB ทันที
                    $tmp_equipment = "รอระบุรายละเอียดเพิ่มเติม";
                    $tmp_location = "ไม่ระบุสถานที่";
                    $stmt_insert = $conn->prepare("INSERT INTO repairs (ticket_no, equipment_type, location, problem_desc, status, reporter_name, phone_number, line_user_id, line_message_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt_insert->bind_param("sssssssss", $ticket_no, $tmp_equipment, $tmp_location, $text, $status, $user_name, $phone_number, $userId, $message_id);
                    $stmt_insert->execute();

                    // 2. เรียก Google Gemini AI เพื่อวิเคราะห์ข้อมูลเชิงลึก
                    $gemini_prompt = "ดึงข้อมูลจากประโยค: '$text' ตอบแค่ JSON โครงสร้างนี้เท่านั้น {\"equipment\":\"\",\"building\":\"\",\"room\":\"\",\"problem\":\"\"} ถ้าไม่มีให้ใส่ ไม่ระบุ";

                    $gemini_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent";
                    $gemini_data = [
                        "contents" => [["parts" => [["text" => $gemini_prompt]]]],
                        "generationConfig" => ["temperature" => 0.0, "responseMimeType" => "application/json"]
                    ];

                    $ch = curl_init($gemini_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'x-goog-api-key: ' . $gemini_api_key]);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($gemini_data));
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
                    curl_setopt($ch, CURLOPT_TIMEOUT, 15); 
                    
                    $gemini_response = curl_exec($ch);
                    $curl_error = curl_error($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    // 3. อัปเดตข้อมูลที่ AI วิเคราะห์ได้ลงฐานข้อมูล
                    if (!$curl_error && $http_code == 200) {
                        $gemini_result = json_decode($gemini_response, true);
                        
                        if(isset($gemini_result['candidates'][0]['content']['parts'][0]['text'])) {
                            $ai_data = json_decode($gemini_result['candidates'][0]['content']['parts'][0]['text'], true);

                            $equipment = !empty($ai_data['equipment']) ? $ai_data['equipment'] : 'ไม่ระบุ';
                            $building = !empty($ai_data['building']) ? $ai_data['building'] : '';
                            $room = !empty($ai_data['room']) ? $ai_data['room'] : '';
                            $location = trim($building . ' ' . $room) ?: 'ไม่ระบุสถานที่';
                            
                            $problem = !empty($ai_data['problem']) ? $ai_data['problem'] : 'ไม่ระบุ';
                            if ($problem == 'ไม่ระบุ' || $problem == 'ไม่ระบุอาการ' || $problem == 'null') {
                                $problem = 'มีความผิดปกติ (รอช่างตรวจสอบ)';
                            }
                            
                            if ($equipment != 'ไม่ระบุ' && $equipment != 'ไม่ระบุอุปกรณ์') {
                                $stmt_update = $conn->prepare("UPDATE repairs SET equipment_type = ?, location = ?, problem_desc = ? WHERE ticket_no = ?");
                                $stmt_update->bind_param("ssss", $equipment, $location, $problem, $ticket_no);
                                $stmt_update->execute();
                            }
                        }
                    }

                    // ส่งข้อความยืนยันการรับแจ้งซ่อมกลับไปใน LINE
                    if ($replyToken) {
                        $reply_text = "📥 ระบบได้รับเรื่องแจ้งซ่อมของคุณ " . $user_name . "เรียบร้อยแล้ว\nเลขที่ใบงาน: " . $ticket_no . "\nสถานะ: รอเจ้าหน้าที่รับเรื่องครับ";
                        send_line_reply($replyToken, $reply_text, $channelAccessToken);
                    }
                }
            }
        }
    }
}
echo "OK";

// ฟังก์ชันดึง LINE Profile
function get_line_profile($userId, $groupId, $accessToken) {
    $url = $groupId ? "https://api.line.me/v2/bot/group/$groupId/member/$userId" : "https://api.line.me/v2/bot/profile/$userId";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($result, true);
    return isset($data['displayName']) ? $data['displayName'] : 'ผู้ใช้งาน';
}

// ฟังก์ชันส่งข้อความตอบกลับ LINE Bot
function send_line_reply($replyToken, $message, $accessToken) {
    $url = 'https://api.line.me/v2/bot/message/reply';
    $data = [
        'replyToken' => $replyToken,
        'messages' => [['type' => 'text', 'text' => $message]]
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}
?>