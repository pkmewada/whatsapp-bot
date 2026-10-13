<?php
session_start();
require_once __DIR__.'/db.php';
if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }
if (isset($_POST['password'])) {
    if (hash_equals(ADMIN_PASSWORD, $_POST['password'])) { $_SESSION['ok'] = 1; header('Location: index.php'); exit; }
    $err = 'Wrong password';
}
if (empty($_SESSION['ok'])): ?>
<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login</title><link rel="stylesheet" href="style.css?v=2"></head><body class="login">
<form method="post" class="card"><h2>WhatsApp AI Dashboard</h2>
<?php if (!empty($err)) echo "<p class='err'>$err</p>"; ?>
<input type="password" name="password" placeholder="Admin password" autofocus><button>Login</button></form></body></html>
<?php exit; endif; ?>
<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>WhatsApp AI Dashboard</title><link rel="stylesheet" href="style.css?v=2"></head><body>
<header><b>WhatsApp AI Dashboard</b>
  <div class="header-right"><div id="waStatus" class="wa-status"><span class="status-dot"></span><span>Checking WhatsApp…</span></div>
  <nav><a href="#" onclick="tab('chats')">Chats</a><a href="#" onclick="tab('dna')">Business DNA</a><a href="#" onclick="tab('test')">Test AI</a><a href="?logout=1">Logout</a></nav></div></header>

<section id="chats" class="tab">
  <div class="chat-layout">
    <div class="list" id="list"></div>
    <div class="conv">
      <div id="title" class="ctitle"><span>Select a chat</span></div>
      <div id="msgs" class="msgs"></div>
      <div class="composer">
        <input type="file" id="file" title="Attach image / video / pdf / audio">
        <input type="text" id="text" placeholder="Type a manual reply..." onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg()}">
        <button id="sendButton" onclick="sendMsg()">Send</button>
      </div>
    </div>
  </div>
</section>

<section id="dna" class="tab" style="display:none"><div class="card wide">
  <h3>Business DNA (AI training)</h3>
  <p class="muted">Write everything about your business. The AI answers ONLY from this text. Update it any time.</p>
  <label><input type="checkbox" id="aiOn" onchange="toggleAI()"> AI auto-reply ON</label>
  <textarea id="dnaText" rows="22"></textarea>
  <button onclick="saveDNA()">Save DNA</button> <span id="dnaMsg" class="muted"></span>
</div></section>

<section id="test" class="tab" style="display:none"><div class="card wide">
  <h3>Test AI (no WhatsApp needed)</h3>
  <input type="text" id="q" placeholder="Type a customer question..." onkeydown="if(event.key==='Enter')testAI()">
  <button onclick="testAI()">Ask</button><pre id="ans"></pre>
</div></section>

<script src="app.js?v=2"></script></body></html>
