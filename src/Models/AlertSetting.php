<?php

namespace VictorStochero\Warden\Models;

/**
 * Single global row holding the e-mail alert channel configuration. Edited from
 * the dashboard (Settings -> Alerts) and resolved by the Evaluator and the
 * MailAlertChannel. A per-project override lives on the Project model.
 *
 * @property int $id
 * @property bool $email_enabled
 * @property array<int, string>|null $recipients
 * @property string $min_severity
 * @property int $cooldown
 */
class AlertSetting extends WardenModel
{
    protected $table = 'wdn_alert_settings';

    protected $guarded = [];

    protected $casts = [
        'email_enabled' => 'boolean',
        'recipients' => 'array',
        'cooldown' => 'integer',
    ];

    /**
     * Return the single global settings row, creating it with sane defaults the
     * first time. There is never more than one row.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'email_enabled' => false,
            'recipients' => [],
            'min_severity' => 'warning',
            'cooldown' => 300,
        ]);
    }
}
