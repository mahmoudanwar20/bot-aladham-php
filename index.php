<?php

$facebook_token = "EAATV..."; // ← حط هنا التوكن الكامل بتاع الفيسبوك
$openai_key = "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";
$assistant_id = "asst_xg7X9owlXB8J4w3a6CJe5eGK";

// استلام الرسالة
$data = json_decode(file_get_contents('php://input'), true);
$sender_id = $data['entry'][0]['messaging'][0]['sender']['id'] ?? null;
$message = $data['entry'][0]['messaging'][0]['message']['text'] ?? null;

if ($sender_id && $message) {

    // إنشاء محادثة جديدة
    $ch = curl_init("https://api.openai.com/v1/threads");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $openai_key",
        "OpenAI-Beta: assistants=v1",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([]));
    $response = curl_exec($ch);
    $responseData = json_decode($response, true);
    $thread_id = $responseData['id'] ?? null;
    curl_close($ch);

    if ($thread_id) {
        // إرسال الرسالة للمساعد
        $ch = curl_init("https://api.openai.com/v1/threads/$thread_id/messages");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $openai_key",
            "OpenAI-Beta: assistants=v1",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "role" => "user",
            "content" => $message
        ]));
        $response = curl_exec($ch);
        curl_close($ch);

        // تشغيل المساعد على الرسالة
        $ch = curl_init("https://api.openai.com/v1/threads/$thread_id/runs");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $openai_key",
            "OpenAI-Beta: assistants=v1",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "assistant_id" => $assistant_id
        ]));
        $response = curl_exec($ch);
        $responseData = json_decode($response, true);
        $run_id = $responseData['id'] ?? null;
        curl_close($ch);

        // الانتظار لحد ما يرد
        do {
            sleep(2);
            $status_check = curl_init("https://api.openai.com/v1/threads/$thread_id/runs/$run_id");
            curl_setopt($status_check, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($status_check, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $openai_key",
                "OpenAI-Beta: assistants=v1"
            ]);
            $result = json_decode(curl_exec($status_check), true);
            curl_close($status_check);
        } while ($result['status'] !== 'completed');

        // جلب الرد
        $messages_ch = curl_init("https://api.openai.com/v1/threads/$thread_id/messages");
        curl_setopt($messages_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($messages_ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $openai_key",
            "OpenAI-Beta: assistants=v1"
        ]);
        $messages_response = curl_exec($messages_ch);
        curl_close($messages_ch);
        $messages_data = json_decode($messages_response, true);
        $ai_reply = $messages_data['data'][0]['content'][0]['text']['value'] ?? "معرفتش أرد دلوقتي، جرب تاني بعد شوية";

        // إرسال الرد لفيسبوك
        $fb_url = "https://graph.facebook.com/v18.0/me/messages?access_token=$facebook_token";
        $fb_payload = [
            "messaging_type" => "RESPONSE",
            "recipient" => ["id" => $sender_id],
            "message" => ["text" => $ai_reply]
        ];

        $fb_ch = curl_init($fb_url);
        curl_setopt($fb_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($fb_ch, CURLOPT_POST, true);
        curl_setopt($fb_ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($fb_ch, CURLOPT_POSTFIELDS, json_encode($fb_payload));
        curl_exec($fb_ch);
        curl_close($fb_ch);
    }
}
?>
