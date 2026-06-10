<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFactory;
use VictorStochero\Warden\Http\Controllers\Dashboard\Concerns\ResolvesContext;
use VictorStochero\Warden\Models\AlertSetting;
use VictorStochero\Warden\Support\Cast;

class SettingsController
{
    use ResolvesContext;

    /** Severities the dashboard exposes for the minimum-severity gate. */
    private const SEVERITIES = ['info', 'warning', 'critical'];

    public function index(): View
    {
        return ViewFactory::make('warden::admin.settings', array_merge($this->chrome(), [
            'settings' => AlertSetting::current(),
            'severities' => self::SEVERITIES,
            'showRanges' => false,
            'autoRefresh' => false,
        ]));
    }

    public function update(Request $request): RedirectResponse
    {
        $minSeverity = Cast::str($request->input('min_severity'), 'warning');
        if (! in_array($minSeverity, self::SEVERITIES, true)) {
            $minSeverity = 'warning';
        }

        $cooldown = max(0, Cast::int($request->input('cooldown'), 300));

        $settings = AlertSetting::current();
        $settings->email_enabled = $request->boolean('email_enabled');
        $settings->recipients = $this->parseRecipients($request->input('recipients'));
        $settings->min_severity = $minSeverity;
        $settings->cooldown = $cooldown;
        $settings->save();

        return redirect()->route('warden.admin.settings')
            ->with('warden_status', 'Alert settings saved.');
    }

    /**
     * Split a comma/newline-separated textarea into a clean list of addresses.
     *
     * @return list<string>
     */
    private function parseRecipients(mixed $input): array
    {
        $raw = preg_split('/[\s,;]+/', Cast::str($input));

        $out = [];
        foreach ($raw === false ? [] : $raw as $address) {
            $address = trim($address);
            if ($address !== '') {
                $out[] = $address;
            }
        }

        return array_values(array_unique($out));
    }
}
