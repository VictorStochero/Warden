<?php

namespace VictorStochero\Warden\Models;

/**
 * A UI-managed threshold alert rule (§5.5), evaluated alongside the
 * config-defined rules by the Evaluator.
 *
 * @property int $id
 * @property string $name
 * @property string $metric
 * @property string $op
 * @property float $threshold
 * @property string $window
 * @property string $severity
 * @property bool $enabled
 */
class AlertRule extends WardenModel
{
    protected $table = 'wdn_alert_rules';

    protected $guarded = [];

    protected $casts = [
        'threshold' => 'float',
        'enabled' => 'boolean',
    ];
}
