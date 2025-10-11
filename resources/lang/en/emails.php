<?php

return [
    'verify_account' => [
        'subject' => 'Welcome to your :app_name account!',
        'greeting' => 'Hi :name',
        'line1' => 'You\'ve been invited to join a workspace on :app_name.',
        'action' => 'ACCEPT INVITATION',
        'unsubscribe' => 'Unsubscribe',
    ],
    'user_invitation' => [
        'subject' => 'You\'re Invited to :app_name',
        'greeting' => 'Hi :name,',
        'invited_to' => 'You have been invited to join',
        'set_password_text' => 'To get started, please set up your password by clicking the button below.',
        'accept_button' => 'ACCEPT INVITATION',
        'support_note' => 'If you need assistance, please contact your administrator.',
    ],
    'password_reset' => [
        'subject' => 'Reset Your Password',
        'greeting' => 'Hi :name',
        'line1' => 'You requested to reset your password. Click the button below to set a new password.',
        'action' => 'RESET PASSWORD',
        'warning' => 'If you didn\'t request a password reset, please ignore this email.',
        'expire' => 'This password reset link will expire in 24 hours.',
    ],
];
