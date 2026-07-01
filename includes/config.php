<?php
// config.php - LOCAL MACHINE ONLY (Do not commit to GitHub)

return [
    // --- DATABASE SETTINGS ---
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'name' => 'emattendancedb',
    
    // --- TIMEZONE SETTINGS ---
    'php_timezone' => 'Asia/Manila',
    'sql_timezone' => '+08:00',
    
    // --- SMTP SETTINGS (Using Mailtrap for Development) ---
    // Mailtrap is a fake SMTP server for development. It captures your emails 
    // in a virtual inbox so you can view them without spamming real users.
    'mail' => [
        'host' => 'sandbox.smtp.mailtrap.io', // <-- From Mailtrap
        'port' => 2525,                      // <-- From Mailtrap
        'username' => '565b356183616b', // <-- From Mailtrap
        'password' => '84d4dc5e5c40a3', // <-- From Mailtrap
        'encryption' => 'tls',               // <-- From Mailtrap (usually TLS)
        'from_address' => 'no-reply@flowtime.com',
        'from_name' => 'FlowTime System'
    ]
];
?>