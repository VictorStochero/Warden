<?php

return [
    'live' => 'ao vivo',
    'save_changes' => 'Salvar alterações',
    'cancel' => 'Cancelar',

    'signed_in_as' => 'Conectado como :role',
    'role_admin' => 'Admin',
    'role_viewer' => 'Visualizador',
    'sign_in_as_admin' => 'Entrar como admin',
    'sign_out' => 'Sair',
    'self_hosted' => 'Auto-hospedado · zero deps',

    'read_only_notice' => 'Acesso somente leitura — entre como admin para gerenciar projetos, alertas e manutenção.',

    'health' => [
        'green' => 'Saudável',
        'yellow' => 'Degradado',
        'red' => 'Fora do ar / erros',
    ],

    'getting_started' => [
        'title' => 'Primeiros passos',
        'intro' => 'Nenhum app observado ainda — só este parent está se monitorando. Adicione um projeto para começar a observar outro app.',
        'step1' => 'Crie um projeto aqui — o Warden gera o token + secret.',
        'step2' => 'No app que você quer observar, rode o comando de instalação (ou cole as chaves :env) que ele fornecer.',
        'step3' => 'Mantenha o cron do scheduler rodando dos dois lados — :ship entrega os lotes a este parent a cada minuto.',
        'cta' => 'Adicionar um projeto',
    ],
];
