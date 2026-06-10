<?php

return [
    'live' => 'en vivo',
    'save_changes' => 'Guardar cambios',
    'cancel' => 'Cancelar',

    'signed_in_as' => 'Conectado como :role',
    'role_admin' => 'Admin',
    'role_viewer' => 'Lector',
    'sign_in_as_admin' => 'Entrar como admin',
    'sign_out' => 'Salir',
    'self_hosted' => 'Autoalojado · cero deps',

    'read_only_notice' => 'Acceso de solo lectura — entra como admin para gestionar proyectos, alertas y mantenimiento.',

    'health' => [
        'green' => 'Saludable',
        'yellow' => 'Degradado',
        'red' => 'Caído / errores',
    ],

    'getting_started' => [
        'title' => 'Primeros pasos',
        'intro' => 'Aún no hay apps observadas — solo este parent se monitorea a sí mismo. Añade un proyecto para empezar a observar otra app.',
        'step1' => 'Crea un proyecto aquí — Warden genera su token + secret.',
        'step2' => 'En la app que quieres observar, ejecuta el comando de instalación (o pega las claves :env) que te indique.',
        'step3' => 'Mantén el cron del scheduler corriendo en ambos lados — :ship entrega los lotes a este parent cada minuto.',
        'cta' => 'Añadir un proyecto',
    ],
];
