<?php

return [

    'projects' => [
        'title' => 'Projetos',
        'heading' => 'Projetos',
        'subheading' => 'Crie e gerencie projetos observados',

        'credentials_shown_once' => 'Credenciais de :name — exibidas somente uma vez',
        'option_a_title' => 'Opção A · comando de instalação',
        'option_a_description' => 'Execute no child — grava os arquivos de config e as chaves do .env automaticamente:',
        'option_b_title' => 'Opção B · chaves do .env',
        'option_b_description' => 'Ou execute <code>warden:install</code> localmente para gerar a config e cole estas chaves diretamente no <code>.env</code> de produção:',

        'add_project' => 'Adicionar projeto',
        'col_name' => 'Nome',
        'col_slug' => 'Slug',
        'col_status' => 'Status',
        'col_last_seen' => 'Última vez visto',
        'col_settings' => 'Configurações',
        'col_actions' => 'Ações',

        'status_active' => 'ativo',
        'status_inactive' => 'inativo',
        'last_seen_never' => 'nunca',

        'label_audit' => 'Auditoria',
        'label_tz' => 'Fuso',
        'tz_auto' => 'Detectando…',
        'run_now' => 'Executar agora',
        'btn_edit' => 'Editar',
        'btn_credentials' => 'Credenciais',
        'btn_rotate' => 'Rotacionar',
        'btn_deactivate' => 'Desativar',
        'btn_activate' => 'Ativar',
        'btn_reset_metrics' => 'Resetar métricas',
        'btn_delete' => 'Excluir',

        'confirm_rotate' => 'Rotacionar credenciais de :name? O token e o segredo atuais param de funcionar imediatamente — o child deverá ser reconfigurado com os novos.',
        'confirm_deactivate' => 'Desativar :name? Batches recebidos serão rejeitados enquanto inativo.',
        'confirm_activate' => 'Ativar :name?',
        'confirm_reset' => 'Resetar TODAS as métricas salvas de :name? Eventos brutos, rollups, issues, incidentes, heartbeats e cursores serão excluídos permanentemente. As credenciais são mantidas.',
        'confirm_delete' => 'Excluir permanentemente :name e TODOS os seus dados (eventos, rollups, issues, incidentes, heartbeats, cursores)? Isso não pode ser desfeito.',
        'deleted' => ':name e todos os seus dados foram excluídos.',
        'cannot_delete_self' => 'O projeto de automonitoramento do parent não pode ser excluído.',

        'no_projects' => 'Nenhum projeto ainda.',

        'modal_add_title' => 'Adicionar projeto',
        'modal_name_label' => 'Nome',
        'modal_name_placeholder' => 'Meu App',
        'modal_slug_label' => 'Slug (opcional)',
        'modal_create_btn' => 'Criar projeto',

        'audit_off' => 'Desligado',
    ],

    'edit' => [
        'title' => 'Editar projeto',
        'heading' => 'Editar projeto',

        'section_details' => 'Detalhes',

        'name_label' => 'Nome',
        'client_label' => 'Cliente',
        'client_placeholder' => 'ex.: Acme Inc.',
        'contact_label' => 'Contato',
        'contact_placeholder' => 'nome ou e-mail',
        'group_label' => 'Grupo',
        'group_placeholder' => 'digite para criar ou selecionar',
        'group_help' => 'Projetos com o mesmo grupo são agrupados na visão geral. Deixe vazio para nenhum.',
        'tags_label' => 'Tags',
        'tags_placeholder' => 'separadas por vírgula, ex.: prod, faturamento',
        'tags_existing' => 'Existentes:',

        'section_intervals' => 'Intervalos',
        'intervals_help' => 'Quando a auditoria de segurança é executada e a janela do KPI de uptime. Os horários estão no fuso do projeto.',

        'audit_frequency_label' => 'Frequência de auditoria',
        'audit_day_of_week' => 'Dia da semana',
        'audit_day_of_month' => 'Dia do mês',
        'audit_hour_label' => 'Hora',
        'audit_any_hour' => 'Qualquer hora',

        'freq_off' => 'Desligado',
        'freq_daily' => 'Diário',
        'freq_weekly' => 'Semanal',
        'freq_monthly' => 'Mensal',

        'weekday_0' => 'Domingo',
        'weekday_1' => 'Segunda-feira',
        'weekday_2' => 'Terça-feira',
        'weekday_3' => 'Quarta-feira',
        'weekday_4' => 'Quinta-feira',
        'weekday_5' => 'Sexta-feira',
        'weekday_6' => 'Sábado',

        'uptime_window_label' => 'Janela de uptime',
        'uptime_24h' => 'Últimas 24 horas',
        'uptime_7d' => 'Últimos 7 dias',
        'uptime_30d' => 'Últimos 30 dias',
        'uptime_help' => 'KPI de disponibilidade em destaque na seção Uptime do projeto.',

        'section_alerts' => 'Alertas',
        'alerts_help' => 'Substitua as configurações globais de alerta por e-mail para este projeto. Deixe os campos em branco para herdar os padrões globais.',

        'alert_override_label' => 'Substituir alertas por e-mail para este projeto',
        'alert_enabled_label' => 'Habilitar alertas por e-mail',
        'recipients_label' => 'Destinatários',
        'recipients_placeholder' => 'deixe em branco para usar os destinatários globais',
        'recipients_help' => 'Separados por vírgula, ponto e vírgula ou nova linha.',
        'min_severity_label' => 'Severidade mínima',
        'min_severity_inherit' => 'Herdar global',
    ],

    'maintenance' => [
        'title' => 'Manutenção',
        'heading' => 'Manutenção',
        'subheading' => 'Acione comandos de manutenção do parent sob demanda',

        'intro' => 'Os comandos rodam na fila. Sem worker? O scheduler já os executa automaticamente.',
        'never_run' => 'nunca executado',
        'last_output' => 'Saída da última execução',
        'no_output' => 'Concluído sem saída.',
        'run_now' => 'Executar agora',

        'confirm_prune' => 'Executar warden:prune? Isso exclui permanentemente eventos brutos e agregados fora da janela de retenção.',

        'dead_letter_title' => 'Batches descartados (dead-letter)',
        'dead_letter_empty' => 'Nenhum — todos os batches foram entregues.',
        'col_batch' => 'Batch',
        'col_reason' => 'Motivo',
        'col_attempts' => 'Tentativas',
        'col_reported' => 'Reportado em',
    ],

    'settings' => [
        'title' => 'Configurações de alerta',
        'heading' => 'Configurações de alerta',
        'subheading' => 'Canal global de alerta por e-mail',

        'section_email' => 'Alertas por e-mail',
        'email_help' => 'Transições de incidentes são enviadas por e-mail através do mailer configurado neste app. Projetos individuais podem substituir esses padrões na página de edição.',
        'email_enabled_label' => 'Habilitar alertas por e-mail',

        'recipients_label' => 'Destinatários',
        'recipients_placeholder' => 'ops@exemplo.com, oncall@exemplo.com',
        'recipients_help' => 'Separados por vírgula, ponto e vírgula ou nova linha.',

        'min_severity_label' => 'Severidade mínima',
        'min_severity_help' => 'Apenas incidentes nesta severidade ou acima são enviados por e-mail.',

        'cooldown_label' => 'Cooldown (segundos)',
        'cooldown_help' => 'Intervalo mínimo entre alertas repetidos para o mesmo incidente.',

        'save_btn' => 'Salvar configurações',
    ],

    'confirm_modal' => [
        'default_message' => 'Tem certeza?',
        'cancel' => 'Cancelar',
        'confirm' => 'Confirmar',
    ],

];
