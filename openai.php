<?php
require_once __DIR__.'/db.php';

function openai_chat(array $messages) {
    if (OPENAI_API_KEY === '') {
        throw new Exception('OpenAI API key is not configured. Set OPENAI_API_KEY in the web server environment.');
    }

    $ch = curl_init(OPENAI_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 90,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer '.OPENAI_API_KEY, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['model' => OPENAI_MODEL, 'messages' => $messages, 'temperature' => 0.4]),
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('OpenAI request failed: '.$error);
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $res = json_decode($response, true);
    if (empty($res['choices'][0]['message']['content'])) {
        throw new Exception('OpenAI error (HTTP '.$status.'): '.($res['error']['message'] ?? $response));
    }
    return trim($res['choices'][0]['message']['content']);
}

function system_prompt() {
    $dna = setting('dna', '');
    return "You are the WhatsApp assistant of this business. Use ONLY the BUSINESS DNA below as your source of truth.\n"
         . "Rules: reply in the same language/script the customer uses (Hindi, Hinglish or English). Keep replies short and friendly, "
         . "plain text only (no markdown tables). Never invent prices, offers or policies that are not in the DNA. "
         . "If you do not know, say the team will get back soon and ask for details.\n\n"
         . "=== BUSINESS DNA ===\n".$dna."\n=== END ===";
}

// $userContent: string OR array of content parts (for images)
function openai_reply(array $history, $userContent) {
    $msgs = [['role' => 'system', 'content' => system_prompt()]];
    foreach ($history as $h) {
        $msgs[] = ['role' => $h['direction'] === 'in' ? 'user' : 'assistant', 'content' => (string)$h['body']];
    }
    $msgs[] = ['role' => 'user', 'content' => $userContent];
    return openai_chat($msgs);
}
