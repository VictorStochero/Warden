<?php

return [
    // Reduced-capture banner (lean / custom projects)
    'badge' => 'Lean',
    'reduced_title' => 'Lean capture is on',
    'reduced_body' => 'To keep the database small, some recorders are off: :list.',
    'query_note' => 'Queries are captured only above :ms ms — N+1 and frequent-query analysis stay off until full query capture is enabled.',
    'sample_note' => 'Requests are sampled at :pct% (errors and slow requests are always kept).',
    'manage' => 'Manage capture',
    'dismiss_session' => 'Dismiss',

    // Lean opt-in notice (existing projects, capture_profile = null)
    'optin_title' => 'Reduce database growth?',
    'optin_body' => 'This project captures everything. Switch to the lean profile to keep only high-signal data (requests sampled, slow queries only, noisy recorders off). You can fine-tune it any time.',
    'optin_migrate' => 'Switch to lean',
    'optin_keep' => 'Keep full capture',

    // Flash messages
    'migrated' => ':name switched to the lean capture profile.',
    'kept_full' => ':name kept on full capture.',
];
