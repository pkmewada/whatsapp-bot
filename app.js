let cur = null;
const $ = id => document.getElementById(id);
const esc = s => (s || '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

async function request(url, options = {}, notify = true) {
  try {
    const response = await fetch(url, options);
    const result = await response.json();
    if (!response.ok || result.error) throw new Error(result.error || `Request failed (${response.status})`);
    return result;
  } catch (error) {
    if (notify) alert(error.message || 'Could not connect to the dashboard.');
    return notify ? null : { error: error.message || 'Could not connect to the dashboard.' };
  }
}
async function api(a, data = {}, files = null) {
  const fd = new FormData();
  for (const k in data) fd.append(k, data[k]);
  if (files) for (const k in files) fd.append(k, files[k]);
  return request('api.php?a=' + encodeURIComponent(a), { method: 'POST', body: fd });
}
async function get(a, qs = '', notify = true) {
  return request('api.php?a=' + encodeURIComponent(a) + qs, {}, notify);
}

function tab(n) {
  document.querySelectorAll('.tab').forEach(t => t.style.display = 'none');
  $(n).style.display = 'block';
  if (n === 'dna') loadSettings();
}

async function loadChats() {
  const rows = await get('chats');
  if (!Array.isArray(rows)) return;
  $('list').innerHTML = rows.length ? rows.map(r => {
    const phone = String(r.phone || '').replace(/\D/g, '');
    return `<button class="item ${phone === cur ? 'sel' : ''}" onclick="openChat('${phone}')"><b>+${esc(phone)}</b><small>${esc(r.body)}</small><time>${esc(r.created_at)}</time></button>`;
  }).join('') : '<div class="empty-list">Incoming WhatsApp chats will appear here.</div>';
}
async function openChat(p) {
  cur = String(p || '').replace(/\D/g, '');
  $('title').innerHTML = `<span>+${esc(cur)}</span><small>WhatsApp customer</small>`;
  await loadMsgs(); loadChats();
}

function media(m) {
  if (!m.media_path) return '';
  const u = esc(m.media_path);
  if (m.type === 'image') return `<a href="${u}" target="_blank"><img src="${u}"></a>`;
  if (m.type === 'video') return `<video src="${u}" controls></video>`;
  if (m.type === 'audio') return `<audio src="${u}" controls></audio>`;
  return `<a href="${u}" target="_blank">Open file</a>`;
}
async function loadMsgs() {
  if (!cur) return;
  const rows = await get('messages', '&phone=' + encodeURIComponent(cur));
  if (!Array.isArray(rows)) return;
  const box = $('msgs'), atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 60;
  box.innerHTML = rows.length ? rows.map(m => `<div class="m ${m.direction === 'out' ? 'out' : 'in'}">${media(m)}${esc(m.body)}<small>${esc(m.created_at)}</small></div>`).join('') : '<div class="empty-chat">No messages yet.</div>';
  if (atBottom) box.scrollTop = box.scrollHeight;
}
async function sendMsg() {
  if (!cur) return alert('Select a chat first');
  const f = $('file').files[0];
  if (!f && !$('text').value.trim()) return alert('Type a message or attach a file first.');
  const button = $('sendButton');
  button.disabled = true;
  button.textContent = 'Sending…';
  const result = await api('send', { phone: cur, text: $('text').value }, f ? { file: f } : null);
  button.disabled = false;
  button.textContent = 'Send';
  if (!result) return;
  $('text').value = ''; $('file').value = '';
  await loadMsgs(); await loadChats();
}

async function loadSettings() {
  const s = await get('get_settings');
  if (!s) return;
  $('dnaText').value = s.dna || DNA_TEMPLATE; $('aiOn').checked = s.ai_enabled === '1';
}
async function saveDNA() { if (await api('save_dna', { dna: $('dnaText').value })) $('dnaMsg').textContent = 'Saved'; }
async function toggleAI() { await api('toggle_ai', { on: $('aiOn').checked ? '1' : '0' }); }
async function testAI() {
  $('ans').textContent = 'Thinking…';
  const r = await api('test_ai', { q: $('q').value });
  $('ans').textContent = r ? (r.reply || '') : '';
}
async function loadWhatsAppStatus() {
  const status = $('waStatus');
  const result = await get('wa_status', '', false);
  if (!result || result.error) {
    status.classList.add('disconnected');
    const error = result && result.error ? ` · ${esc(result.error)}` : '';
    status.innerHTML = `<span class="status-dot"></span><span>WhatsApp not connected${error}</span>`;
    return;
  }
  const name = result.verified_name ? ` · ${esc(result.verified_name)}` : '';
  status.classList.remove('disconnected');
  status.innerHTML = `<span class="status-dot"></span><span>Connected · ${esc(result.display_phone_number || 'Number unavailable')}${name}</span>`;
}

const DNA_TEMPLATE = `BUSINESS NAME:
ABOUT US (story, what we do):
PRODUCTS / SERVICES (name - price - size/details):
DELIVERY / SERVICE AREA:
PAYMENT METHODS:
TIMINGS:
OFFERS / DISCOUNTS:
RETURN / REFUND POLICY:
CONTACT / ADDRESS:
TONE OF VOICE (e.g. friendly, Hinglish, use emojis lightly):
FAQ (question -> answer):
WHEN TO HAND OVER TO HUMAN (e.g. complaints, bulk orders):`;

setInterval(() => { loadChats(); loadMsgs(); }, 5000);
loadChats();
loadWhatsAppStatus();
