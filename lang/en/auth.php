<?php

return [
    'login' => [
        'title' => 'Log in to your account',
        'description' => 'Enter your email and password below to log in',
        'head_title' => 'Log in',
        'email' => 'Email address',
        'email_placeholder' => 'email@example.com',
        'password' => 'Password',
        'password_placeholder' => 'Password',
        'forgot_password' => 'Forgot your password?',
        'remember_me' => 'Remember me',
        'submit' => 'Log in',
        'no_account' => "Don't have an account?",
        'sign_up' => 'Sign up',
    ],

    'register' => [
        'title' => 'Create an account',
        'description' => 'Enter your optical shop details to get started',
        'head_title' => 'Register',
        'company_name' => 'Optical shop name',
        'company_name_placeholder' => 'Central Optics',
        'name' => 'Full name',
        'name_placeholder' => 'Full name',
        'email' => 'Email address',
        'email_placeholder' => 'email@example.com',
        'password' => 'Password',
        'password_placeholder' => 'Password',
        'confirm_password' => 'Confirm password',
        'confirm_password_placeholder' => 'Confirm password',
        'submit' => 'Create account',
        'has_account' => 'Already have an account?',
        'log_in' => 'Log in',
    ],

    'forgot_password' => [
        'title' => 'Forgot password',
        'head_title' => 'Forgot password',
        'description' => 'Enter your email to receive a password reset link',
        'email' => 'Email address',
        'email_placeholder' => 'email@example.com',
        'submit' => 'Email password reset link',
        'return_to' => 'Or, return to',
        'log_in' => 'log in',
    ],

    'confirm_password' => [
        'title' => 'Confirm password',
        'head_title' => 'Confirm password',
        'description' => 'This is a secure area of the application. Please confirm your password before continuing.',
        'password' => 'Password',
        'submit' => 'Confirm password',
        'passkey_label' => 'Confirm with passkey',
        'passkey_loading_label' => 'Confirming...',
        'passkey_separator' => 'Or confirm with password',
    ],

    'reset_password' => [
        'title' => 'Reset password',
        'head_title' => 'Reset password',
        'description' => 'Please enter your new password below',
        'email' => 'Email',
        'password' => 'Password',
        'password_placeholder' => 'Password',
        'confirm_password' => 'Confirm password',
        'confirm_password_placeholder' => 'Confirm password',
        'submit' => 'Reset password',
    ],

    'two_factor' => [
        'head_title' => 'Two-factor authentication',
        'code_title' => 'Authentication code',
        'code_description' => 'Enter the authentication code provided by your authenticator application.',
        'code_button_text' => 'login using a recovery code',
        'recovery_title' => 'Recovery code',
        'recovery_description' => 'Please confirm access to your account by entering one of your emergency recovery codes.',
        'recovery_button_text' => 'login using an authentication code',
        'recovery_placeholder' => 'Enter recovery code',
        'continue' => 'Continue',
        'or_you_can' => 'or you can ',
    ],

    'passkey' => [
        'sign_in' => 'Sign in with a passkey',
        'authenticating' => 'Authenticating...',
        'continue_with_email' => 'Or continue with email',
    ],

    'verify_email' => [
        'title' => 'Email verification',
        'head_title' => 'Email verification',
        'description' => 'Please verify your email address by clicking on the link we just emailed to you.',
        'link_sent' => 'A new verification link has been sent to the email address you provided during registration.',
        'resend' => 'Resend verification email',
        'log_out' => 'Log out',
    ],
];
