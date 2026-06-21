<?php

return [
    // Banner de captura reduzida (projetos lean / custom)
    'badge' => 'Enxuto',
    'reduced_title' => 'Captura enxuta ativa',
    'reduced_body' => 'Para manter o banco pequeno, alguns recorders estão desligados: :list.',
    'query_note' => 'Queries são capturadas só acima de :ms ms — a análise de N+1 e queries frequentes fica desligada até ativar a captura completa de query.',
    'sample_note' => 'Requests são amostradas a :pct% (erros e requests lentas são sempre mantidos).',
    'manage' => 'Gerenciar captura',
    'dismiss_session' => 'Dispensar',

    // Aviso opt-in de migração (projetos existentes, capture_profile = null)
    'optin_title' => 'Reduzir o crescimento do banco?',
    'optin_body' => 'Este projeto captura tudo. Mude para o perfil enxuto para manter só dados de alto sinal (requests amostradas, só queries lentas, recorders ruidosos desligados). Você pode ajustar quando quiser.',
    'optin_migrate' => 'Mudar para enxuto',
    'optin_keep' => 'Manter captura completa',

    // Mensagens flash
    'migrated' => ':name migrado para o perfil de captura enxuto.',
    'kept_full' => ':name mantido em captura completa.',
];
