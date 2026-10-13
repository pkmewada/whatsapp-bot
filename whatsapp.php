<?php
require_once __DIR__.'/db.php';

function wa_post($payload) {
    if (WA_TOKEN === '' || WA_PHONE_ID === '') {
        throw new Exception('WhatsApp is not configured. Set WA_TOKEN and verify WA_PHONE_ID.');
    }
    $ch = curl_init("https://graph.facebook.com/".WA_API_VERSION."/".WA_PHONE_ID."/messages");
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer '.WA_TOKEN, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload + ['messaging_product' => 'whatsapp']),
    ]);
    $res = curl_exec($ch);
    if ($res === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('WhatsApp request failed: '.$error);
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($res, true);
    if ($status < 200 || $status >= 300 || !is_array($data) || isset($data['error'])) {
        throw new Exception('WhatsApp API error (HTTP '.$status.'): '.($data['error']['message'] ?? $res));
    }
    return $data;
}
function wa_phone_profile() {
    if (WA_TOKEN === '' || WA_PHONE_ID === '') {
        throw new Exception('WhatsApp is not configured. Set WA_TOKEN and verify WA_PHONE_ID.');
    }
    $url = "https://graph.facebook.com/".WA_API_VERSION."/".rawurlencode(WA_PHONE_ID)
         ."?fields=display_phone_number,verified_name,quality_rating";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer '.WA_TOKEN],
    ]);
    $res = curl_exec($ch);
    if ($res === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('WhatsApp status request failed: '.$error);
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($res, true);
    if ($status < 200 || $status >= 300 || !is_array($data) || isset($data['error'])) {
        throw new Exception('WhatsApp API error (HTTP '.$status.'): '.($data['error']['message'] ?? $res));
    }
    return $data;
}
function wa_text($to, $text) {
    return wa_post(['to' => $to, 'type' => 'text', 'text' => ['body' => mb_substr($text, 0, 4000)]]);
}
// $type: image | video | audio | document  ($link must be a public HTTPS URL)
function wa_media($to, $type, $link, $caption = '', $filename = '') {
    if (filter_var($link, FILTER_VALIDATE_URL) === false || parse_url($link, PHP_URL_SCHEME) !== 'https') {
        throw new Exception('Media sending requires a valid public HTTPS URL. Set BASE_URL to your public HTTPS domain.');
    }
    $obj = ['link' => $link];
    if ($caption && $type !== 'audio') $obj['caption'] = $caption;
    if ($type === 'document' && $filename) $obj['filename'] = $filename;
    return wa_post(['to' => $to, 'type' => $type, $type => $obj]);
}
function wa_mark_read($wamid) {
    wa_post(['status' => 'read', 'message_id' => $wamid]);
}
// Download incoming media -> returns [relative_path, mime] or [null, null]
function wa_download($mediaId) {
    $h = ['Authorization: Bearer '.WA_TOKEN];
    $ch = curl_init("https://graph.facebook.com/".WA_API_VERSION."/$mediaId");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $h]);
    $meta = json_decode(curl_exec($ch), true); curl_close($ch);
    if (empty($meta['url'])) return [null, null];

    $ch = curl_init($meta['url']);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $h, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60]);
    $bin = curl_exec($ch); curl_close($ch);
    if (!$bin) return [null, null];

    $mime = $meta['mime_type'] ?? 'application/octet-stream';
    $map = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','video/mp4'=>'mp4','video/3gpp'=>'3gp',
            'audio/ogg'=>'ogg','audio/mpeg'=>'mp3','audio/mp4'=>'m4a','audio/amr'=>'amr','application/pdf'=>'pdf',
            'application/msword'=>'doc','application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx',
            'application/vnd.ms-excel'=>'xls','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx',
            'text/plain'=>'txt'];
    $ext = $map[explode(';', $mime)[0]] ?? 'bin';
    $rel = 'uploads/in/'.bin2hex(random_bytes(8)).'.'.$ext;
    file_put_contents(__DIR__.'/'.$rel, $bin);
    return [$rel, $mime];
}
