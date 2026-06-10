<?php

namespace VictorStochero\Warden\Contracts;

use VictorStochero\Warden\Models\Incident;

interface AlertChannel
{
    /**
     * Deliver an incident transition over an internal channel.
     *
     * @param  string  $event  One of: opened | resolved | reminder
     */
    public function send(Incident $incident, string $event): void;
}
