<?php
session_start();
require_once __DIR__.'/whatsapp.php';
require_once __DIR__.'/openai.php';
header('Content-Type: application/json');
if (empty($_SESSION['ok'])) { http_response_code(401); exit(json_encode(['error' => 'login required'])); }

$a = $_GET['a'] ?? '';
try {
switch ($a) {
    case 'wa_status':
        echo json_encode(wa_phone_profile()); break;

    case 'chats':
        $rows = db()->query("SELECT phone, body, type, created_at FROM messages
            WHERE id IN (SELECT MAX(id) FROM messages GROUP BY phone) ORDER BY id DESC")->fetchAll();
        echo json_encode($rows); break;

    case 'messages':
        $s = db()->prepare("SELECT * FROM messages WHERE phone=? ORDER BY id ASC"); $s->execute([$_GET['phone'] ?? '']);
        echo json_encode($s->fetchAll()); break;

    case 'send':   // manual reply from dashboard (text or file)
        $phone = preg_replace('/\D/', '', $_POST['phone'] ?? ''); $text = trim($_POST['text'] ?? '');
        if (!$phone) throw new Exception('phone missing');
        if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_OK && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
            throw new Exception('File upload failed (code '.$_FILES['file']['error'].').');
        }
        if (!empty($_FILES['file']['tmp_name'])) {
            $mime = mime_content_type($_FILES['file']['tmp_name']);
            $map = ['image/jpeg'=>'jpg','image/png'=>'png','video/mp4'=>'mp4','audio/mpeg'=>'mp3','audio/ogg'=>'ogg','application/pdf'=>'pdf'];
            if (!isset($map[$mime])) throw new Exception("File type not allowed ($mime). Use jpg, png, mp4, mp3, ogg, pdf");
            if (BASE_URL === '') throw new Exception('Set BASE_URL to your public HTTPS URL before sending media.');
            $rel = 'uploads/out/'.bin2hex(random_bytes(8)).'.'.$map[$mime];
            if (!move_uploaded_file($_FILES['file']['tmp_name'], __DIR__.'/'.$rel)) {
                throw new Exception('Could not save the uploaded file.');
            }
            $kind = str_starts_with($mime, 'image') ? 'image' : (str_starts_with($mime, 'video') ? 'video' : (str_starts_with($mime, 'audio') ? 'audio' : 'document'));
            $res = wa_media($phone, $kind, BASE_URL.'/'.$rel, $text, $_FILES['file']['name']);
            log_msg($phone, 'out', $kind, "[$kind] $text", $rel);
        } else {
            if ($text === '') throw new Exception('empty message');
            $res = wa_text($phone, $text);
            log_msg($phone, 'out', 'text', $text);
        }
        echo json_encode(['ok' => true, 'wa' => $res]); break;

    case 'get_settings':
        echo json_encode(['dna' => setting('dna'), 'ai_enabled' => setting('ai_enabled', '1')]); break;

    case 'save_dna':
        set_setting('dna', $_POST['dna'] ?? ''); echo json_encode(['ok' => true]); break;

    case 'toggle_ai':
        set_setting('ai_enabled', ($_POST['on'] ?? '1') === '1' ? '1' : '0'); echo json_encode(['ok' => true]); break;

    case 'test_ai':    // test OpenAI + DNA without WhatsApp
        echo json_encode(['reply' => openai_reply([], $_POST['q'] ?? 'Hello')]); break;

    default: echo json_encode(['error' => 'unknown action']);
}
} catch (Throwable $e) { http_response_code(400); echo json_encode(['error' => $e->getMessage()]); }
