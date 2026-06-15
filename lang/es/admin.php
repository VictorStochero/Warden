<?php

return [

    'projects' => [
        'title' => 'Proyectos',
        'heading' => 'Proyectos',
        'subheading' => 'Crea y gestiona proyectos observados',

        'credentials_shown_once' => 'Credenciales de :name — mostradas solo una vez',
        'option_a_title' => 'Opción A · comando de instalación',
        'option_a_description' => 'Ejecútalo en el child — escribe los archivos de config y las claves del .env por ti:',
        'option_b_title' => 'Opción B · claves del .env',
        'option_b_description' => 'O ejecuta <code>warden:install</code> localmente para generar la config y pega estas claves directamente en el <code>.env</code> de producción:',

        'add_project' => 'Agregar proyecto',
        'col_name' => 'Nombre',
        'col_slug' => 'Slug',
        'col_status' => 'Estado',
        'col_last_seen' => 'Última vez visto',
        'col_settings' => 'Ajustes',
        'col_actions' => 'Acciones',

        'status_active' => 'activo',
        'status_inactive' => 'inactivo',
        'last_seen_never' => 'nunca',

        'label_audit' => 'Auditoría',
        'label_tz' => 'Zona',
        'tz_auto' => 'Detectando…',
        'run_now' => 'Ejecutar ahora',
        'btn_edit' => 'Editar',
        'btn_credentials' => 'Credenciales',
        'btn_rotate' => 'Rotar',
        'btn_deactivate' => 'Desactivar',
        'btn_activate' => 'Activar',
        'btn_reset_metrics' => 'Restablecer métricas',
        'btn_delete' => 'Eliminar',

        'confirm_rotate' => '¿Rotar credenciales de :name? El token y el secreto actuales dejan de funcionar de inmediato — el child deberá reconfigurarse con los nuevos.',
        'confirm_deactivate' => '¿Desactivar :name? Los batches entrantes serán rechazados mientras esté inactivo.',
        'confirm_activate' => '¿Activar :name?',
        'confirm_reset' => '¿Restablecer TODAS las métricas guardadas de :name? Eventos crudos, rollups, issues, incidentes, heartbeats y cursores se eliminarán permanentemente. Las credenciales se conservan.',
        'confirm_delete' => '¿Eliminar permanentemente :name y TODOS sus datos (eventos, rollups, issues, incidentes, heartbeats, cursores)? Esto no se puede deshacer.',
        'deleted' => ':name y todos sus datos fueron eliminados.',
        'cannot_delete_self' => 'El proyecto de automonitoreo del parent no se puede eliminar.',

        'no_projects' => 'Aún no hay proyectos.',

        'modal_add_title' => 'Agregar proyecto',
        'modal_name_label' => 'Nombre',
        'modal_name_placeholder' => 'Mi App',
        'modal_slug_label' => 'Slug (opcional)',
        'modal_create_btn' => 'Crear proyecto',

        'audit_off' => 'Apagado',
    ],

    'edit' => [
        'title' => 'Editar proyecto',
        'heading' => 'Editar proyecto',

        'section_details' => 'Detalles',

        'name_label' => 'Nombre',
        'client_label' => 'Cliente',
        'client_placeholder' => 'ej.: Acme Inc.',
        'contact_label' => 'Contacto',
        'contact_placeholder' => 'nombre o e-mail',
        'group_label' => 'Grupo',
        'group_placeholder' => 'escribe para crear o seleccionar',
        'group_help' => 'Los proyectos con el mismo grupo se agrupan en la vista general. Déjalo vacío para ninguno.',
        'tags_label' => 'Etiquetas',
        'tags_placeholder' => 'separadas por coma, ej.: prod, facturación',
        'tags_existing' => 'Existentes:',

        'section_intervals' => 'Intervalos',
        'intervals_help' => 'Cuándo se ejecuta la auditoría de seguridad y la ventana del KPI de uptime. Los horarios están en la zona horaria del proyecto.',

        'audit_frequency_label' => 'Frecuencia de auditoría',
        'audit_day_of_week' => 'Día de la semana',
        'audit_day_of_month' => 'Día del mes',
        'audit_hour_label' => 'Hora',
        'audit_any_hour' => 'Cualquier hora',

        'freq_off' => 'Apagado',
        'freq_daily' => 'Diario',
        'freq_weekly' => 'Semanal',
        'freq_monthly' => 'Mensual',

        'weekday_0' => 'Domingo',
        'weekday_1' => 'Lunes',
        'weekday_2' => 'Martes',
        'weekday_3' => 'Miércoles',
        'weekday_4' => 'Jueves',
        'weekday_5' => 'Viernes',
        'weekday_6' => 'Sábado',

        'uptime_window_label' => 'Ventana de uptime',
        'uptime_24h' => 'Últimas 24 horas',
        'uptime_7d' => 'Últimos 7 días',
        'uptime_30d' => 'Últimos 30 días',
        'uptime_help' => 'KPI de disponibilidad destacado en la sección Uptime del proyecto.',

        'section_alerts' => 'Alertas',
        'alerts_help' => 'Reemplaza la configuración global de alertas por e-mail para este proyecto. Deja los campos en blanco para heredar los valores globales.',

        'alert_override_label' => 'Anular alertas por e-mail para este proyecto',
        'alert_enabled_label' => 'Habilitar alertas por e-mail',
        'recipients_label' => 'Destinatarios',
        'recipients_placeholder' => 'déjalo en blanco para usar los destinatarios globales',
        'recipients_help' => 'Separados por coma, punto y coma o nueva línea.',
        'min_severity_label' => 'Severidad mínima',
        'min_severity_inherit' => 'Heredar global',
    ],

    'audit' => [
        'title' => 'Registro de auditoría',
        'heading' => 'Registro de auditoría',
        'subheading' => 'Quién hizo qué en el panel',
        'empty' => 'Aún no hay acciones registradas.',
        'col_when' => 'Cuándo',
        'col_actor' => 'Actor',
        'col_action' => 'Acción',
        'col_target' => 'Objetivo',
        'col_ip' => 'IP',
    ],

    'maintenance' => [
        'title' => 'Mantenimiento',
        'heading' => 'Mantenimiento',
        'subheading' => 'Dispara comandos de mantenimiento del parent bajo demanda',

        'intro' => 'Los comandos se ejecutan en la cola. ¿Sin worker? El scheduler ya los ejecuta automáticamente.',
        'never_run' => 'nunca ejecutado',
        'last_output' => 'Salida de la última ejecución',
        'no_output' => 'Completado sin salida.',
        'run_now' => 'Ejecutar ahora',

        'confirm_prune' => '¿Ejecutar warden:prune? Elimina permanentemente eventos crudos y agregados fuera de la ventana de retención.',

        'dead_letter_title' => 'Batches descartados (dead-letter)',
        'dead_letter_empty' => 'Ninguno — todos los batches fueron entregados.',
        'col_batch' => 'Batch',
        'col_reason' => 'Motivo',
        'col_attempts' => 'Intentos',
        'col_reported' => 'Reportado',
    ],

    'settings' => [
        'title' => 'Configuración de alertas',
        'heading' => 'Configuración de alertas',
        'subheading' => 'Canal global de alertas por e-mail',

        'section_email' => 'Alertas por e-mail',
        'email_help' => 'Las transiciones de incidentes se envían por e-mail a través del mailer configurado en esta app. Los proyectos individuales pueden anular estos valores en su página de edición.',
        'email_enabled_label' => 'Habilitar alertas por e-mail',

        'recipients_label' => 'Destinatarios',
        'recipients_placeholder' => 'ops@ejemplo.com, oncall@ejemplo.com',
        'recipients_help' => 'Separados por coma, punto y coma o nueva línea.',

        'min_severity_label' => 'Severidad mínima',
        'min_severity_help' => 'Solo los incidentes en esta severidad o superior se envían por e-mail.',

        'cooldown_label' => 'Cooldown (segundos)',
        'cooldown_help' => 'Intervalo mínimo entre alertas repetidas para el mismo incidente.',

        'save_btn' => 'Guardar configuración',
    ],

    'confirm_modal' => [
        'default_message' => '¿Estás seguro?',
        'cancel' => 'Cancelar',
        'confirm' => 'Confirmar',
    ],

];
