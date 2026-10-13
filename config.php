<?php

// ====== WHATSAPP CLOUD API CONFIGURATION ======

// Meta Developers → WhatsApp → API Setup
define('WA_TOKEN',        getenv('WA_TOKEN') ?: 'EAAge0dkJZCwcBSlLEdOXbN6xRsctZBlXj6KwKpS2kzWS5GZC2d8PKgUUg2qKS5JcPQ7UKbjxWt0FAYHivC8S4oMTEeivn9fXCfsSJqBjydldXPkzpriSunXm8rQaWy6GQWeZCuZC8pzJZCUoC2cmTt2TfBAVLyxhb8oCAppuZBtpM7OjVs1B7Vaoe583SBdGyMQUdpmF5eEqsWVK92f23ZBL5qdpHjlKe0fKSsNcbYEueFKtaLAympI4ckGlWHLFRZAWa5ZB6VI7r2eRejWLHZA1PkEOdoq');
define('WA_PHONE_ID',     getenv('WA_PHONE_ID') ?: '1338524816018040');

// Meta Webhook verification ke liye apna secret text
define('WA_VERIFY_TOKEN', getenv('WA_VERIFY_TOKEN') ?: 'my_secret_verify_123');

// Optional: Meta App Settings → Basic → App Secret
define('WA_APP_SECRET',   getenv('WA_APP_SECRET') ?: '');

// WhatsApp Graph API version
define('WA_API_VERSION',  'v26.0');


// ====== OPENAI CONFIGURATION ======

// Set OPENAI_API_KEY in the web server environment; never put the key in source code.
define('OPENAI_API_KEY',  getenv('OPENAI_API_KEY') ?: '');
define('OPENAI_MODEL',    'gpt-4o-mini');
define('OPENAI_URL',      'https://api.openai.com/v1/chat/completions');


// ====== ADMIN DASHBOARD ======

define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: 'pp');

// Hosting par upload karne ke baad apna public HTTPS URL daalna
define('BASE_URL',        rtrim(getenv('BASE_URL') ?: '', '/'));

// Local SQLite database file
define('DB_FILE',         __DIR__ . '/data/bot.sqlite');

// Conversation history mein kitne messages AI ko dene hain
define('HISTORY_LIMIT',   10);

?>