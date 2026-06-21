<?php

return [
    // Aviso de captura reducida (proyectos lean / custom)
    'badge' => 'Ligero',
    'reduced_title' => 'Captura ligera activa',
    'reduced_body' => 'Para mantener la base de datos pequeña, algunos recorders están apagados: :list.',
    'query_note' => 'Las consultas se capturan solo por encima de :ms ms — el análisis de N+1 y consultas frecuentes queda desactivado hasta habilitar la captura completa de consultas.',
    'sample_note' => 'Las peticiones se muestrean al :pct% (los errores y las peticiones lentas siempre se conservan).',
    'manage' => 'Gestionar captura',
    'dismiss_session' => 'Descartar',

    // Aviso opt-in de migración (proyectos existentes, capture_profile = null)
    'optin_title' => '¿Reducir el crecimiento de la base de datos?',
    'optin_body' => 'Este proyecto captura todo. Cambia al perfil ligero para conservar solo datos de alta señal (peticiones muestreadas, solo consultas lentas, recorders ruidosos apagados). Puedes ajustarlo cuando quieras.',
    'optin_migrate' => 'Cambiar a ligero',
    'optin_keep' => 'Mantener captura completa',

    // Mensajes flash
    'migrated' => ':name cambiado al perfil de captura ligero.',
    'kept_full' => ':name mantenido en captura completa.',
];
