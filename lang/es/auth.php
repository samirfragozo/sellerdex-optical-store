<?php

return [
    'login' => [
        'title' => 'Inicia sesión en tu cuenta',
        'description' => 'Ingresa tu correo y contraseña para iniciar sesión',
        'head_title' => 'Iniciar sesión',
        'email' => 'Correo electrónico',
        'email_placeholder' => 'correo@ejemplo.com',
        'password' => 'Contraseña',
        'password_placeholder' => 'Contraseña',
        'forgot_password' => '¿Olvidaste tu contraseña?',
        'remember_me' => 'Recuérdame',
        'submit' => 'Iniciar sesión',
        'no_account' => '¿No tienes una cuenta?',
        'sign_up' => 'Regístrate',
    ],

    'register' => [
        'title' => 'Crear una cuenta',
        'description' => 'Ingresa los datos de tu óptica para comenzar',
        'head_title' => 'Registro',
        'company_name' => 'Nombre de la óptica',
        'company_name_placeholder' => 'Óptica Central',
        'name' => 'Nombre completo',
        'name_placeholder' => 'Nombre completo',
        'email' => 'Correo electrónico',
        'email_placeholder' => 'correo@ejemplo.com',
        'password' => 'Contraseña',
        'password_placeholder' => 'Contraseña',
        'confirm_password' => 'Confirmar contraseña',
        'confirm_password_placeholder' => 'Confirmar contraseña',
        'submit' => 'Crear cuenta',
        'has_account' => '¿Ya tienes una cuenta?',
        'log_in' => 'Iniciar sesión',
    ],

    'forgot_password' => [
        'title' => 'Olvidé mi contraseña',
        'head_title' => 'Olvidé mi contraseña',
        'description' => 'Ingresa tu correo para recibir un enlace de restablecimiento',
        'email' => 'Correo electrónico',
        'email_placeholder' => 'correo@ejemplo.com',
        'submit' => 'Enviar enlace de restablecimiento',
        'return_to' => 'O bien, vuelve a',
        'log_in' => 'iniciar sesión',
    ],

    'confirm_password' => [
        'title' => 'Confirmar contraseña',
        'head_title' => 'Confirmar contraseña',
        'description' => 'Esta es un área segura de la aplicación. Confirma tu contraseña antes de continuar.',
        'password' => 'Contraseña',
        'submit' => 'Confirmar contraseña',
        'passkey_label' => 'Confirmar con llave de acceso',
        'passkey_loading_label' => 'Confirmando...',
        'passkey_separator' => 'O confirma con tu contraseña',
    ],

    'reset_password' => [
        'title' => 'Restablecer contraseña',
        'head_title' => 'Restablecer contraseña',
        'description' => 'Ingresa tu nueva contraseña',
        'email' => 'Correo',
        'password' => 'Contraseña',
        'password_placeholder' => 'Contraseña',
        'confirm_password' => 'Confirmar contraseña',
        'confirm_password_placeholder' => 'Confirmar contraseña',
        'submit' => 'Restablecer contraseña',
    ],

    'two_factor' => [
        'head_title' => 'Autenticación de dos factores',
        'code_title' => 'Código de autenticación',
        'code_description' => 'Ingresa el código de autenticación provisto por tu aplicación de autenticación.',
        'code_button_text' => 'iniciar sesión usando un código de recuperación',
        'recovery_title' => 'Código de recuperación',
        'recovery_description' => 'Confirma el acceso a tu cuenta ingresando uno de tus códigos de recuperación de emergencia.',
        'recovery_button_text' => 'iniciar sesión usando un código de autenticación',
        'recovery_placeholder' => 'Ingresa el código de recuperación',
        'continue' => 'Continuar',
        'or_you_can' => 'o puedes ',
    ],

    'passkey' => [
        'sign_in' => 'Iniciar sesión con una llave de acceso',
        'authenticating' => 'Autenticando...',
        'continue_with_email' => 'O continúa con tu correo',
    ],

    'verify_email' => [
        'title' => 'Verificación de correo',
        'head_title' => 'Verificación de correo',
        'description' => 'Verifica tu correo haciendo clic en el enlace que te acabamos de enviar.',
        'link_sent' => 'Se ha enviado un nuevo enlace de verificación a la dirección de correo que registraste.',
        'resend' => 'Reenviar correo de verificación',
        'log_out' => 'Cerrar sesión',
    ],
];
