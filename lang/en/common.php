<?php

return [
    'live' => 'live',
    'save_changes' => 'Save changes',
    'cancel' => 'Cancel',

    'signed_in_as' => 'Signed in as :role',
    'role_admin' => 'Admin',
    'role_viewer' => 'Viewer',
    'sign_in_as_admin' => 'Sign in as admin',
    'sign_out' => 'Sign out',
    'self_hosted' => 'Self-hosted · zero deps',
    'version' => 'Warden :version',

    'read_only_notice' => 'Read-only access — sign in as admin to manage projects, alerts and maintenance.',

    'health' => [
        'green' => 'Healthy',
        'yellow' => 'Degraded',
        'red' => 'Down / errors',
    ],

    'getting_started' => [
        'title' => 'Getting started',
        'intro' => 'No observed apps yet — only this parent is monitoring itself. Add a project to start watching another app.',
        'step1' => 'Create a project here — Warden mints its token + secret.',
        'step2' => 'On the app you want to watch, run the install command (or paste the :env keys) it gives you.',
        'step3' => 'Keep the scheduler cron running on both sides — :ship delivers batches to this parent every minute.',
        'cta' => 'Add a project',
    ],
];
