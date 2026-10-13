<?php
require_once __DIR__.'/whatsapp.php';
require_once __DIR__.'/openai.php';

// 1) Meta verification (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (($_GET['hub_mode'] ?? '') === 'subscribe' && ($_GET['hub_verify_token'] ?? '') === WA_VERIFY_TOKEN) {
        echo $_GET['hub_challenge']; exit;
    }
    http_response_code(403); exit('Forbidden');
}

// 2) Incoming events (POST)
$raw = file_get_contents('php://input');
if (WA_APP_SECRET) {
    $sig = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    if (!hash_equals('sha256='.hash_hmac('sha256', $raw, WA_APP_SECRET), $sig)) { http_response_code(401); exit; }
}
$data = json_decode($raw, true);
if (!is_array($data) || ($data['object'] ?? '') !== 'whatsapp_business_account') {
    http_response_code(400); exit('Invalid WhatsApp webhook payload');
}
// answer Meta immediately, then continue working
ignore_user_abort(true); set_time_limit(120);
http_response_code(200); echo 'OK';
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

foreach (($data['entry'] ?? []) as $entry) {
    foreach (($entry['changes'] ?? []) as $change) {
        foreach (($change['value']['messages'] ?? []) as $m) {
            try {
                handle_message($m);
            } catch (Throwable $e) {
                if (!empty($m['id'])) {
                    try {
                        $s = db()->prepare("DELETE FROM seen WHERE wamid=?");
                        $s->execute([$m['id']]);
                    } catch (Throwable $dbError) {
                        file_put_contents(__DIR__.'/data/error.log', date('c').' '.$dbError->getMessage()."\n", FILE_APPEND);
                    }
                }
                file_put_contents(__DIR__.'/data/error.log', date('c').' '.$e->getMessage()."\n", FILE_APPEND);
            }
        }
    }
}

function handle_message($m) {
    $wamid = $m['id']; $phone = $m['from']; $type = $m['type'];

    // ignore duplicates (Meta may retry)
    $ins = db()->prepare("INSERT OR IGNORE INTO seen(wamid) VALUES(?)"); $ins->execute([$wamid]);
    if ($ins->rowCount() === 0) return;
    try {
        wa_mark_read($wamid);
    } catch (Throwable $e) {
        file_put_contents(__DIR__.'/data/error.log', date('c').' '.$e->getMessage()."\n", FILE_APPEND);
    }

    $history = get_history($phone);
    $body = ''; $media = null; $aiInput = '';

    switch ($type) {
        case 'text':
            $body = $m['text']['body']; $aiInput = $body; break;

        case 'image':
            $caption = $m['image']['caption'] ?? '';
            [$media, $mime] = wa_download($m['image']['id']);
            $body = '[image] '.$caption;
            $q = $caption ?: 'Customer sent this image. Look at it and respond helpfully using the business information.';
            if ($media) {
                $b64 = base64_encode(file_get_contents(__DIR__.'/'.$media));
                $aiInput = [['type'=>'text','text'=>$q],
                            ['type'=>'image_url','image_url'=>['url'=>"data:$mime;base64,$b64"]]];
            } else { $aiInput = "[Customer sent an image that could not be loaded] $caption"; }
            break;

        case 'document':
            $fn = $m['document']['filename'] ?? 'file'; $caption = $m['document']['caption'] ?? '';
            [$media] = wa_download($m['document']['id']);
            $body = "[document] $fn $caption";
            $aiInput = "[Customer sent a document named \"$fn\". You cannot open it. Politely confirm it was received and say the team will check it.] $caption";
            break;

        case 'video':
            $caption = $m['video']['caption'] ?? '';
            [$media] = wa_download($m['video']['id']);
            $body = '[video] '.$caption;
            $aiInput = "[Customer sent a video. You cannot watch it. Confirm it was received and ask them to describe the issue in text.] $caption";
            break;

        case 'audio':
            [$media] = wa_download($m['audio']['id']);
            $body = '[voice/audio]';
            $aiInput = "[Customer sent a voice message. You cannot listen to it. Politely ask them to type their question.]";
            break;

        case 'location':
            $body = '[location] '.($m['location']['latitude'] ?? '').','.($m['location']['longitude'] ?? '');
            $aiInput = "[Customer shared a location: $body] Respond helpfully using delivery/service area information in the business DNA."; break;

        case 'button':       $body = $aiInput = $m['button']['text'] ?? ''; break;
        case 'interactive':  $body = $aiInput = $m['interactive']['button_reply']['title'] ?? $m['interactive']['list_reply']['title'] ?? ''; break;

        default:
            $body = "[$type]"; $aiInput = "[Customer sent a $type message which you cannot read. Ask them to send text.]";
    }

    log_msg($phone, 'in', $type, $body, $media);

    if (setting('ai_enabled', '1') !== '1') return;   // AI switched off in dashboard

    $reply = openai_reply($history, $aiInput);
    wa_text($phone, $reply);
    log_msg($phone, 'out', 'text', $reply);
}
