<?php

return [

    // ── project.blade.php ────────────────────────────────────────────────
    'subheading' => ':section · visto por última vez :ago',

    'kpi' => [
        'throughput' => 'Throughput',
        'requests' => 'solicitudes',
        'error_rate' => 'Tasa de error',
        'errors' => ':count errores',
        'p95_latency' => 'Latencia p95',
        'slow_reqs' => 'Reqs lentas',
        'failed_jobs' => 'Jobs fallidos',
        'cache_hit' => 'Cache hit',
        'open_issues' => 'Issues abiertas',
        'uptime_30d' => 'Uptime · 30d',
    ],

    // ── sections/overview.blade.php ──────────────────────────────────────
    'overview' => [
        'throughput' => 'Throughput',
        'p95_latency' => 'Latencia p95',
        'top_routes' => 'Top rutas',
        'requests_action' => 'Solicitudes',
        'slowest_queries' => 'Queries más lentas',
        'queries_action' => 'Queries',
        'queues' => 'Colas',
        'active_incidents' => 'Incidentes activos',
        'recent_issues' => 'Issues recientes',
        'all_action' => 'Todos',
        'no_open_issues' => 'Sin issues abiertas 🎉',
        'heartbeats' => 'Heartbeats',
        'no_heartbeats' => 'Sin heartbeats registrados',
        'recent_traces' => 'Traces recientes',
        'all_traces_action' => 'Todos',
    ],

    // ── sections/cache.blade.php ─────────────────────────────────────────
    'cache' => [
        'title' => 'Stores de caché',
        'empty' => 'Sin actividad de caché en el período',
        'col_store' => 'Store',
        'col_hits' => 'Hits',
        'col_misses' => 'Misses',
        'col_writes' => 'Escrituras',
        'col_hit_rate' => 'Hit rate',
    ],

    // ── sections/delivery.blade.php ──────────────────────────────────────
    'delivery' => [
        'last_received' => 'Última recepción',
        'never' => 'nunca',
        'mode_label' => 'Modo de entrega',
        'mode_sub' => 'inferido por los intervalos de llegada',
        'batches_label' => 'Batches',
        'events_label' => 'Eventos',
        'last_window' => 'últimos :window min',
        'arrivals_chart_label' => 'Llegadas por minuto · últimos :window min',
        'status_receiving' => 'recibiendo',
        'status_idle' => 'inactivo',
        'recent_arrivals' => 'Llegadas recientes',
        'arrivals_empty' => 'Nada recibido en los últimos :window minutos. Si el child está configurado, revisa el daemon <span class="font-mono">warden:ship</span> o el scheduler.',
        'col_received' => 'Recibido',
        'col_when' => 'Cuándo',
        'col_batches' => 'Batches',
        'col_events' => 'Eventos',
        'mode_no_data' => 'Sin datos',
        'mode_continuous' => 'Continuo · daemon',
        'mode_every_minute' => 'Cada minuto · cron',
        'mode_approx' => '~cada :cads',
    ],

    // ── sections/errors.blade.php ────────────────────────────────────────
    'errors' => [
        'definition_html' => '<span class="font-medium text-slate-200">Errores</span> son respuestas HTTP fallidas (estado 5xx). Se distinguen de <a href=":issues_url" class="text-brand-400 hover:text-brand-300">Issues</a> (excepciones no controladas agrupadas por huella) e <a href=":incidents_url" class="text-brand-400 hover:text-brand-300">Incidentes</a> (alertas abiertas por heartbeat inactivo o issue abierta). Un 5xx generalmente <em>tiene</em> una issue asociada; un 4xx no.',
        'chart_label' => 'Errores en el tiempo · 5xx',
        'routes_title' => 'Rutas con errores',
        'routes_empty' => 'Sin errores 5xx en el período 🎉',
        'recent_title' => 'Solicitudes 5xx recientes',
        'exceptions_title' => 'Excepciones recientes',
        'release_filter' => 'Desde la release',
        'release_all' => 'Todas',
    ],

    // ── sections/host.blade.php ──────────────────────────────────────────
    'host' => [
        'empty' => 'Sin métricas de host en el período. El recorder de host obtiene <code class="text-brand-400">/proc</code> en Linux.',
        'cpu' => 'CPU',
        'memory' => 'Memoria',
        'load_1m' => 'Carga (1m)',
        'disk' => 'Disco',
        'cpu_chart' => 'CPU %',
        'memory_chart' => 'Memoria %',
    ],

    // ── sections/http.blade.php ──────────────────────────────────────────
    'http' => [
        'title' => 'HTTP saliente',
        'empty' => 'Sin solicitudes salientes en el período',
        'col_host' => 'Host',
        'col_calls' => 'Llamadas',
        'col_errors' => 'Errores',
        'col_avg' => 'Promedio',
        'col_max' => 'Máx',
        'recent_title' => 'Llamadas salientes recientes',
    ],

    // ── sections/jobs.blade.php ──────────────────────────────────────────
    'jobs' => [
        'title' => 'Jobs & colas',
        'recent_title' => 'Jobs recientes',
    ],

    // ── sections/logs.blade.php ──────────────────────────────────────────
    'logs' => [
        'title' => 'Logs por nivel',
        'clear_filter' => 'Limpiar filtro',
        'empty' => 'Sin logs en el período',
        'recent_title' => 'Logs recientes',
        'recent_filtered_title' => 'Logs recientes · :level',
    ],

    // ── sections/mail.blade.php ──────────────────────────────────────────
    'mail' => [
        'mailers_title' => 'Mailers',
        'mailers_empty' => 'Sin correos enviados en el período',
        'notifications_title' => 'Notificaciones',
        'notifications_empty' => 'Sin notificaciones en el período',
        'recent_mail_title' => 'Correos recientes',
        'recent_notif_title' => 'Notificaciones recientes',
        'sent_avg' => ':count enviados · :avg promedio',
    ],

    // ── sections/queries.blade.php ───────────────────────────────────────
    'queries' => [
        'slowest_title' => 'Queries más lentas (por promedio)',
        'expensive_title' => 'Queries más costosas (acumulado)',
    ],

    // ── sections/requests.blade.php ──────────────────────────────────────
    'requests' => [
        'throughput' => 'Throughput',
        'errors' => 'Errores',
        'p95_latency' => 'Latencia p95',
        'routes_title' => 'Rutas',
        'recent_title' => 'Solicitudes recientes',
    ],

    // ── sections/schedule.blade.php ──────────────────────────────────────
    'schedule' => [
        'heartbeats_title' => 'Heartbeats',
        'heartbeats_empty' => 'Sin heartbeats registrados aún',
        'tasks_title' => 'Tareas programadas',
        'tasks_empty' => 'Sin ejecuciones de tarea en el período',
        'col_task' => 'Tarea',
        'col_runs' => 'Ejecuciones',
        'col_avg' => 'Promedio',
        'recent_title' => 'Ejecuciones recientes de tareas',
        'interval_ago' => 'cada :intervals · :ago',
    ],

    // ── sections/security.blade.php ──────────────────────────────────────
    'security' => [
        'run_audit_btn' => 'Ejecutar auditoría ahora',
        'empty_title' => 'Sin auditoría de seguridad recibida aún.',
        'empty_hint_html' => 'Ejecuta <code class="text-brand-400">php artisan warden:audit</code> en el child, o configura una frecuencia de auditoría en <span class="text-slate-400">Gestionar proyectos → Auditoría</span> para que el child lo haga automáticamente.',
        'last_audit' => 'Última auditoría :ago',
        'tool_ran' => 'ejecutado',
        'tool_ran_packagist' => 'auditado (API Packagist)',
        'tool_skipped' => 'omitido',
        'reason_no_package_json' => 'sin package.json',
        'reason_npm_not_found' => 'npm no encontrado',
        'reason_composer_not_found' => 'composer no encontrado',
        'reason_network_error' => 'error de red',
        'reason_lock_missing' => 'sin composer.lock',
        'vulnerabilities_title' => 'Vulnerabilidades (:total)',
        'no_vulnerabilities' => 'Sin vulnerabilidades conocidas 🎉',
        'col_severity' => 'Severidad',
        'col_package' => 'Paquete',
        'col_advisory' => 'Aviso',
        'col_affected' => 'Afectado',
        'fix_upgrade' => 'Actualiza a :version o superior',
        'fix_upgrade_above' => 'Actualiza a una versión superior a :version',
        'fix_available' => 'Corrección disponible — ejecuta la actualización del paquete',
        'fix_none' => 'Sin corrección conocida aún',
    ],

    // ── sections/uptime.blade.php ────────────────────────────────────────
    'uptime' => [
        'kpi_suffix' => '· KPI',
        'last_window' => 'últimos :label',
        'definition_html' => 'Disponibilidad = fracción del tiempo sin incidente <span class="text-slate-300">crítico</span> abierto (heartbeat inactivo o issue de alta severidad). Los errores HTTP solos no reducen el uptime — ver <a href=":errors_url" class="text-brand-400 hover:text-brand-300">Errores</a>.',
        'downtime_title' => 'Episodios de downtime · 30d',
        'incidents_action' => 'Incidentes',
        'no_incidents' => 'Sin incidentes críticos en los últimos 30 días 🎉',
        'col_incident' => 'Incidente',
        'col_started' => 'Inicio',
        'col_duration' => 'Duración',
        'col_status' => 'Estado',
        'status_ongoing' => 'en curso',
        'status_resolved' => 'resuelto',
    ],

    // ── partials/event-list.blade.php ────────────────────────────────────
    'event_list' => [
        'default_title' => 'Eventos recientes',
        'empty' => 'Nada capturado aún — genera tráfico (o ejecuta <span class="font-mono text-slate-500">warden:demo</span> en el child).',
        'no_subject' => '(sin asunto)',
        'to' => 'para',
        'via' => 'vía',
    ],

    // ── partials/route-table.blade.php ───────────────────────────────────
    'route_table' => [
        'empty' => 'Sin solicitudes en el período',
        'col_route' => 'Ruta',
        'col_count' => 'Conteo',
        'col_errors' => 'Errores',
        'col_avg' => 'Promedio',
        'col_p95' => 'p95',
    ],

    // ── partials/query-table.blade.php ───────────────────────────────────
    'query_table' => [
        'empty' => 'Sin queries en el período',
        'col_query' => 'Query',
        'col_calls' => 'Llamadas',
        'col_avg' => 'Promedio',
        'col_total' => 'Total',
        'slow_badge' => ':count lenta(s)',
    ],

    // ── partials/queue-table.blade.php ───────────────────────────────────
    'queue_table' => [
        'empty' => 'Sin jobs en el período',
        'col_job' => 'Job',
        'col_processed' => 'Procesados',
        'col_failed' => 'Fallidos',
        'col_avg' => 'Promedio',
    ],

    // ── partials/bars.blade.php & chart.blade.php ────────────────────────
    'chart' => [
        'no_data' => 'sin datos en el período',
    ],

    // ── partials/overview-cards.blade.php ────────────────────────────────
    'overview_cards' => [
        'req_5m' => 'req · 5m',
        'errors' => 'errores',
        'uptime_30d' => '% uptime · 30d',
    ],

    // ── admin/project-edit.blade.php — sección Comportamiento ────────────
    'behaviour' => [
        'title' => 'Comportamiento (avanzado)',
        'intro' => 'Sobreescribe los parámetros de captura del child para este proyecto. El parent los envía al child en su próxima entrega. Deja un campo en blanco para heredar el .env / valor predeterminado del child.',
        'host_interval' => 'Intervalo de métricas de host (s)',
        'host_interval_hint' => 'Con qué frecuencia se muestrea /proc.',
        'sample_request' => 'Tasa de muestreo — requests',
        'sample_request_hint' => 'Fracción 0..1 de requests rastreadas.',
        'sample_job' => 'Tasa de muestreo — jobs',
        'sample_job_hint' => 'Fracción 0..1 de jobs rastreados.',
        'slower_ms' => 'Conservar siempre más lento que (ms)',
        'slower_ms_hint' => 'Fuerza mantener traces por encima de esta latencia, ignorando el muestreo.',
        'recorders' => 'Recorders',
        'recorders_hint' => 'Marca los recorders a habilitar para este proyecto. Deja todos sin marcar para heredar la lista del child.',
    ],

];
