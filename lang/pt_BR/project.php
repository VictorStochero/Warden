<?php

return [

    // ── project.blade.php ────────────────────────────────────────────────
    'subheading' => ':section · visto por último :ago',

    'kpi' => [
        'throughput' => 'Throughput',
        'requests' => 'requisições',
        'error_rate' => 'Taxa de erro',
        'errors' => ':count erros',
        'p95_latency' => 'Latência p95',
        'slow_reqs' => 'Reqs lentas',
        'failed_jobs' => 'Jobs falhos',
        'cache_hit' => 'Cache hit',
        'open_issues' => 'Issues abertas',
        'uptime_30d' => 'Uptime · 30d',
    ],

    // ── sections/overview.blade.php ──────────────────────────────────────
    'overview' => [
        'throughput' => 'Throughput',
        'p95_latency' => 'Latência p95',
        'top_routes' => 'Top rotas',
        'requests_action' => 'Requisições',
        'slowest_queries' => 'Queries mais lentas',
        'queries_action' => 'Queries',
        'queues' => 'Filas',
        'active_incidents' => 'Incidentes ativos',
        'recent_issues' => 'Issues recentes',
        'all_action' => 'Todos',
        'no_open_issues' => 'Nenhuma issue aberta 🎉',
        'heartbeats' => 'Heartbeats',
        'no_heartbeats' => 'Nenhum heartbeat registrado',
        'recent_traces' => 'Traces recentes',
        'all_traces_action' => 'Todos',
    ],

    // ── sections/cache.blade.php ─────────────────────────────────────────
    'cache' => [
        'title' => 'Stores de cache',
        'empty' => 'Nenhuma atividade de cache no período',
        'col_store' => 'Store',
        'col_hits' => 'Hits',
        'col_misses' => 'Misses',
        'col_writes' => 'Escritas',
        'col_hit_rate' => 'Hit rate',
    ],

    // ── sections/delivery.blade.php ──────────────────────────────────────
    'delivery' => [
        'last_received' => 'Último recebimento',
        'never' => 'nunca',
        'mode_label' => 'Modo de entrega',
        'mode_sub' => 'inferido pelos intervalos de chegada',
        'batches_label' => 'Batches',
        'events_label' => 'Eventos',
        'last_window' => 'últimos :window min',
        'arrivals_chart_label' => 'Chegadas por minuto · últimos :window min',
        'status_receiving' => 'recebendo',
        'status_idle' => 'ocioso',
        'recent_arrivals' => 'Chegadas recentes',
        'arrivals_empty' => 'Nada recebido nos últimos :window minutos. Se o child estiver configurado, verifique o daemon <span class="font-mono">warden:ship</span> ou o scheduler.',
        'col_received' => 'Recebido',
        'col_when' => 'Quando',
        'col_batches' => 'Batches',
        'col_events' => 'Eventos',
        'mode_no_data' => 'Sem dados',
        'mode_continuous' => 'Contínuo · daemon',
        'mode_every_minute' => 'A cada minuto · cron',
        'mode_approx' => '~a cada :cads',
    ],

    // ── sections/errors.blade.php ────────────────────────────────────────
    'errors' => [
        'definition_html' => '<span class="font-medium text-slate-200">Erros</span> são respostas HTTP com falha (status 5xx). São distintos de <a href=":issues_url" class="text-brand-400 hover:text-brand-300">Issues</a> (exceções não tratadas agrupadas por fingerprint) e <a href=":incidents_url" class="text-brand-400 hover:text-brand-300">Incidentes</a> (alertas abertos por heartbeat inativo ou issue aberta). Um 5xx geralmente <em>possui</em> uma issue correspondente; um 4xx não.',
        'chart_label' => 'Erros ao longo do tempo · 5xx',
        'routes_title' => 'Rotas com erros',
        'routes_empty' => 'Nenhum erro 5xx no período 🎉',
        'recent_title' => 'Requisições 5xx recentes',
        'exceptions_title' => 'Exceptions recentes',
        'release_filter' => 'Desde a release',
        'release_all' => 'Todas',
        'since_deploy_title' => 'Desde o último deploy',
        'since_deploy_throughput' => 'Requisições',
        'since_deploy_errors' => 'Erros 5xx',
        'since_deploy_error_rate' => 'Taxa de erro',
        'since_deploy_new_issues' => 'Novas issues',
    ],

    // ── sections/host.blade.php ──────────────────────────────────────────
    'host' => [
        'empty' => 'Nenhuma métrica de host no período. O recorder de host coleta <code class="text-brand-400">/proc</code> no Linux.',
        'cpu' => 'CPU',
        'memory' => 'Memória',
        'load_1m' => 'Carga (1m)',
        'disk' => 'Disco',
        'cpu_chart' => 'CPU %',
        'memory_chart' => 'Memória %',
    ],

    // ── sections/http.blade.php ──────────────────────────────────────────
    'http' => [
        'title' => 'HTTP de saída',
        'empty' => 'Nenhuma requisição de saída no período',
        'col_host' => 'Host',
        'col_calls' => 'Chamadas',
        'col_errors' => 'Erros',
        'col_avg' => 'Média',
        'col_max' => 'Máx',
        'recent_title' => 'Chamadas de saída recentes',
    ],

    // ── sections/jobs.blade.php ──────────────────────────────────────────
    'jobs' => [
        'title' => 'Jobs & filas',
        'recent_title' => 'Jobs recentes',
    ],

    // ── sections/logs.blade.php ──────────────────────────────────────────
    'logs' => [
        'title' => 'Logs por nível',
        'clear_filter' => 'Limpar filtro',
        'empty' => 'Nenhum log no período',
        'recent_title' => 'Logs recentes',
        'recent_filtered_title' => 'Logs recentes · :level',
        'search' => 'Buscar',
        'search_placeholder' => 'Buscar nas mensagens de log…',
    ],

    // ── sections/mail.blade.php ──────────────────────────────────────────
    'mail' => [
        'mailers_title' => 'Mailers',
        'mailers_empty' => 'Nenhum e-mail enviado no período',
        'notifications_title' => 'Notificações',
        'notifications_empty' => 'Nenhuma notificação no período',
        'recent_mail_title' => 'E-mails recentes',
        'recent_notif_title' => 'Notificações recentes',
        'sent_avg' => ':count enviados · :avg em média',
    ],

    // ── sections/database.blade.php ─────────────────────────────────────
    'database' => [
        'queries_heading' => 'Queries',
        'cache_heading' => 'Cache',
        'health' => [
            'title' => 'Saúde das queries',
            'empty' => 'Nenhum problema de query detectado nesta janela.',
            'sampled' => 'Analisadas as últimas :count queries.',
            'n_plus_one' => 'Queries N+1',
            'duplicates' => 'Duplicadas exatas',
            'select_star' => 'SELECT *',
            'no_where' => 'UPDATE/DELETE sem WHERE',
            'fat_request' => 'Requests com muitas queries',
            'slow' => 'Queries lentas',
        ],
    ],

    // ── sections/queries.blade.php ───────────────────────────────────────
    'queries' => [
        'slowest_title' => 'Queries mais lentas (por média)',
        'expensive_title' => 'Queries mais custosas (cumulativo)',
    ],

    // ── sections/requests.blade.php ──────────────────────────────────────
    'requests' => [
        'throughput' => 'Throughput',
        'errors' => 'Erros',
        'p95_latency' => 'Latência p95',
        'deploys' => 'Deploys',
        'routes_title' => 'Rotas',
        'recent_title' => 'Requisições recentes',
        'show_panel' => 'Mostrar requisições do painel',
        'hide_panel' => 'Ocultar requisições do painel',
    ],

    // ── sections/schedule.blade.php ──────────────────────────────────────
    'schedule' => [
        'heartbeats_title' => 'Heartbeats',
        'heartbeats_empty' => 'Nenhum heartbeat registrado ainda',
        'tasks_title' => 'Tarefas agendadas',
        'tasks_empty' => 'Nenhuma execução de tarefa no período',
        'col_task' => 'Tarefa',
        'col_runs' => 'Execuções',
        'col_avg' => 'Média',
        'recent_title' => 'Execuções recentes de tarefas',
        'interval_ago' => 'a cada :intervals · :ago',
    ],

    // ── sections/security.blade.php ──────────────────────────────────────
    'security' => [
        'run_audit_btn' => 'Executar auditoria agora',
        'empty_title' => 'Nenhuma auditoria de segurança recebida ainda.',
        'empty_hint_html' => 'Execute <code class="text-brand-400">php artisan warden:audit</code> no child, ou defina uma frequência de auditoria em <span class="text-slate-400">Gerenciar projetos → Auditoria</span> para que o child execute automaticamente.',
        'last_audit' => 'Última auditoria :ago',
        'tool_ran' => 'executado',
        'tool_ran_packagist' => 'auditado (API Packagist)',
        'tool_skipped' => 'ignorado',
        'reason_no_package_json' => 'sem package.json',
        'reason_npm_not_found' => 'npm não encontrado',
        'reason_composer_not_found' => 'composer não encontrado',
        'reason_network_error' => 'falha de rede',
        'reason_lock_missing' => 'sem composer.lock',
        'vulnerabilities_title' => 'Vulnerabilidades (:total)',
        'no_vulnerabilities' => 'Nenhuma vulnerabilidade conhecida 🎉',
        'col_severity' => 'Severidade',
        'col_package' => 'Pacote',
        'col_advisory' => 'Aviso',
        'col_affected' => 'Afetado',
        'fix_upgrade' => 'Atualize para :version ou superior',
        'fix_upgrade_above' => 'Atualize para uma versão acima de :version',
        'fix_available' => 'Correção disponível — rode a atualização do pacote',
        'fix_none' => 'Sem correção conhecida ainda',
    ],

    // ── sections/uptime.blade.php ────────────────────────────────────────
    'uptime' => [
        'kpi_suffix' => '· KPI',
        'last_window' => 'últimos :label',
        'definition_html' => 'Disponibilidade = parcela do tempo sem incidente <span class="text-slate-300">crítico</span> aberto (heartbeat inativo ou issue de alta severidade). Erros HTTP sozinhos não reduzem o uptime — veja <a href=":errors_url" class="text-brand-400 hover:text-brand-300">Erros</a>.',
        'downtime_title' => 'Episódios de downtime · 30d',
        'incidents_action' => 'Incidentes',
        'no_incidents' => 'Nenhum incidente crítico nos últimos 30 dias 🎉',
        'col_incident' => 'Incidente',
        'col_started' => 'Iniciado em',
        'col_duration' => 'Duração',
        'col_status' => 'Status',
        'status_ongoing' => 'em andamento',
        'status_resolved' => 'resolvido',
    ],

    // ── partials/event-list.blade.php ────────────────────────────────────
    'event_list' => [
        'default_title' => 'Eventos recentes',
        'empty' => 'Nada capturado ainda — gere algum tráfego (ou execute <span class="font-mono text-slate-500">warden:demo</span> no child).',
        'no_subject' => '(sem assunto)',
        'to' => 'para',
        'via' => 'via',
    ],

    // ── partials/route-table.blade.php ───────────────────────────────────
    'route_table' => [
        'empty' => 'Nenhuma requisição no período',
        'col_route' => 'Rota',
        'col_count' => 'Contagem',
        'col_errors' => 'Erros',
        'col_avg' => 'Média',
        'col_p95' => 'p95',
    ],

    // ── partials/query-table.blade.php ───────────────────────────────────
    'query_table' => [
        'empty' => 'Nenhuma query no período',
        'col_query' => 'Query',
        'col_calls' => 'Chamadas',
        'col_avg' => 'Média',
        'col_total' => 'Total',
        'slow_badge' => ':count lenta(s)',
    ],

    // ── partials/queue-table.blade.php ───────────────────────────────────
    'queue_table' => [
        'empty' => 'Nenhum job no período',
        'col_job' => 'Job',
        'col_processed' => 'Processados',
        'col_failed' => 'Falhos',
        'col_avg' => 'Média',
    ],

    // ── partials/bars.blade.php & chart.blade.php ────────────────────────
    'chart' => [
        'no_data' => 'sem dados no período',
    ],

    // ── partials/overview-cards.blade.php ────────────────────────────────
    'overview_cards' => [
        'req_5m' => 'req · 5m',
        'errors' => 'erros',
        'uptime_30d' => '% uptime · 30d',
    ],

    // ── admin/project-edit.blade.php — seção Comportamento ───────────────
    'behaviour' => [
        'title' => 'Comportamento (avançado)',
        'intro' => 'Sobrescreve os parâmetros de captura do child para este projeto. O parent os empurra ao child na próxima entrega. Deixe um campo em branco para herdar o .env / padrão do child.',
        'host_interval' => 'Intervalo de métricas de host (s)',
        'host_interval_hint' => 'Com que frequência o /proc é amostrado.',
        'sample_request' => 'Taxa de amostragem — requests',
        'sample_request_hint' => 'Fração 0..1 de requests rastreados.',
        'sample_job' => 'Taxa de amostragem — jobs',
        'sample_job_hint' => 'Fração 0..1 de jobs rastreados.',
        'slower_ms' => 'Sempre manter acima de (ms)',
        'slower_ms_hint' => 'Força manter traces acima desta latência, sobrepondo a amostragem.',
        'query_min_ms' => 'Capturar queries mais lentas que (ms)',
        'query_min_ms_hint' => '0 captura toda query. Um valor positivo descarta as mais rápidas (e desliga a análise de N+1 / queries frequentes).',
        'recorders' => 'Recorders',
        'recorders_hint' => 'Marque os recorders a habilitar para este projeto. Deixe todos desmarcados para herdar a lista do child.',

        // Métricas capturadas (gate de tipo por projeto)
        'metrics' => 'Métricas capturadas',
        'metrics_help' => 'Desmarque uma métrica para parar de salvá-la neste projeto. O child para de capturá-la na próxima entrega, e o parent a descarta no ingest de qualquer forma — então ela não incha o banco. Dados já gravados somem com a retenção, ou limpe agora abaixo.',

        // Privacidade & captura
        'capture' => 'Privacidade & captura',
        'capture_help' => 'Controla quanto dado potencialmente sensível o child captura para este projeto. Desligado por padrão.',
        'capture_pii' => 'Capturar PII',
        'capture_pii_hint' => 'PII = informação pessoal identificável: input da requisição, cookies, parâmetros de rota e valores semelhantes vindos do usuário. Quando desligado, esses dados são descartados antes mesmo de sair do child.',
        'capture_mail_body' => 'Capturar corpo do e-mail',
        'capture_mail_body_hint' => 'Armazena o corpo HTML/texto renderizado de e-mails e notificações enviados. Quando desligado, só os metadados (assunto, destinatários, mailer) são mantidos.',
        'capture_env_locked' => '⚠ ignorado — fixado pelo .env do child',
        'capture_credential_floor' => 'Credenciais são sempre mascaradas. Senhas, tokens de API, secrets e documentos (CPF) são removidos independentemente destes toggles. Esse piso só pode ser reduzido no próprio child via a variável WARDEN_DISABLE_CREDENTIAL_SCRUB — nunca por este dashboard.',
        'capture_pii_confirm' => 'Capturar PII significa que dados do usuário (input da requisição, cookies, parâmetros de rota) serão armazenados. Garanta que isso está de acordo com sua política de privacidade. Ativar a captura de PII?',
    ],

];
