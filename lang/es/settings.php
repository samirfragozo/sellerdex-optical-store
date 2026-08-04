<?php

return [
    'appearance' => [
        'breadcrumb' => 'Apariencia',
        'title' => 'Apariencia',
        'description' => 'Actualiza la apariencia de tu cuenta',
    ],

    'profile' => [
        'breadcrumb' => 'Perfil',
        'title' => 'Perfil',
        'description' => 'Actualiza tu nombre y correo electrónico',
        'name' => 'Nombre',
        'name_placeholder' => 'Nombre completo',
        'email' => 'Correo electrónico',
        'email_placeholder' => 'Correo electrónico',
        'unverified_email' => 'Tu correo electrónico no está verificado.',
        'resend_verification' => 'Haz clic aquí para reenviar el correo de verificación.',
        'verification_link_sent' => 'Se ha enviado un nuevo enlace de verificación a tu correo.',
        'save' => 'Guardar',
    ],

    'security' => [
        'breadcrumb' => 'Seguridad',
        'title' => 'Actualizar contraseña',
        'description' => 'Asegúrate de usar una contraseña larga y aleatoria para mantener tu cuenta segura',
        'current_password' => 'Contraseña actual',
        'new_password' => 'Nueva contraseña',
        'confirm_password' => 'Confirmar contraseña',
        'save' => 'Guardar',
    ],

    'index' => [
        'title' => 'Configuración',
        'description' => 'Administra tu perfil y la configuración de tu cuenta',
    ],

    'delete_account' => [
        'title' => 'Eliminar cuenta',
        'description' => 'Elimina tu cuenta y todos sus recursos',
        'warning_title' => 'Advertencia',
        'warning_description' => 'Por favor procede con precaución, esta acción no se puede deshacer.',
        'confirm_title' => '¿Estás seguro de que quieres eliminar tu cuenta?',
        'confirm_description' => 'Una vez que tu cuenta sea eliminada, todos sus recursos y datos también se eliminarán permanentemente. Ingresa tu contraseña para confirmar que deseas eliminarla de forma permanente.',
        'password' => 'Contraseña',
        'cancel' => 'Cancelar',
        'delete' => 'Eliminar cuenta',
    ],

    'passkeys' => [
        'title' => 'Llaves de acceso',
        'description' => 'Administra tus llaves de acceso para iniciar sesión sin contraseña',
        'empty_title' => 'Aún no hay llaves de acceso',
        'empty_description' => 'Agrega una llave de acceso para iniciar sesión sin contraseña',
        'not_supported' => 'Las llaves de acceso no son compatibles con este navegador.',
        'add' => 'Agregar llave de acceso',
        'name' => 'Nombre de la llave',
        'name_placeholder' => 'p. ej., MacBook Pro, iPhone',
        'name_hint' => 'Un nombre te ayuda a identificar esta llave más tarde.',
        'register' => 'Registrar llave',
        'registering' => 'Registrando...',
        'cancel' => 'Cancelar',
        'remove' => 'Eliminar',
        'remove_confirm_title' => 'Eliminar llave de acceso',
        'remove_confirm_description' => '¿Estás seguro de que quieres eliminar la llave ":name"? Ya no podrás usarla para iniciar sesión.',
        'removing' => 'Eliminando...',
        'added' => 'Agregada',
        'last_used' => 'Último uso',
    ],

    'two_factor' => [
        'title' => 'Autenticación de dos factores',
        'description' => 'Administra la configuración de autenticación de dos factores',
        'enable_notice' => 'Al habilitar la autenticación de dos factores, se te pedirá un PIN seguro al iniciar sesión. Este PIN se obtiene desde una aplicación TOTP en tu teléfono.',
        'continue_setup' => 'Continuar configuración',
        'enable' => 'Habilitar 2FA',
        'enabled_notice' => 'Se te pedirá un PIN seguro y aleatorio al iniciar sesión, el cual puedes obtener desde la aplicación TOTP en tu teléfono.',
        'disable' => 'Deshabilitar 2FA',

        'recovery_codes' => [
            'title' => 'Códigos de recuperación 2FA',
            'description' => 'Los códigos de recuperación te permiten recuperar el acceso si pierdes tu dispositivo 2FA. Guárdalos en un gestor de contraseñas seguro.',
            'view' => 'Ver códigos de recuperación',
            'hide' => 'Ocultar códigos de recuperación',
            'regenerate' => 'Regenerar códigos',
            'footer_note' => 'Cada código de recuperación se puede usar una sola vez para acceder a tu cuenta y se eliminará después de usarlo. Si necesitas más, haz clic en Regenerar códigos arriba.',
        ],

        'setup_modal' => [
            'enabled_title' => 'Autenticación de dos factores habilitada',
            'enabled_description' => 'La autenticación de dos factores ya está habilitada. Escanea el código QR o ingresa la clave de configuración en tu aplicación de autenticación.',
            'close' => 'Cerrar',
            'verify_title' => 'Verificar código de autenticación',
            'verify_description' => 'Ingresa el código de 6 dígitos de tu aplicación de autenticación',
            'continue' => 'Continuar',
            'enable_title' => 'Habilitar autenticación de dos factores',
            'enable_description' => 'Para terminar de habilitar la autenticación de dos factores, escanea el código QR o ingresa la clave de configuración en tu aplicación de autenticación',
            'enter_manually' => 'o, ingresa el código manualmente',
            'back' => 'Atrás',
            'confirm' => 'Confirmar',
        ],
    ],
];
