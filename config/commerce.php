<?php

return [
    'currency' => env('COMMERCE_CURRENCY', 'COP'),
    'token_minutes' => (int) env('API_TOKEN_MINUTES', 120),
    'login_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
    'lock_minutes' => (int) env('LOGIN_LOCK_MINUTES', 15),
    'reset_url' => env('PASSWORD_RESET_URL', 'http://localhost:8000/reset-password'),
    'dev_admin_email' => env('DEV_ADMIN_EMAIL'),
    'dev_admin_password' => env('DEV_ADMIN_PASSWORD'),
];
