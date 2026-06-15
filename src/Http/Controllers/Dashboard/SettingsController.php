<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFactory;
use VictorStochero\Warden\Http\Controllers\Dashboard\Concerns\ResolvesContext;
use VictorStochero\Warden\Models\AlertRule;
use VictorStochero\Warden\Models\AlertSetting;
use VictorStochero\Warden\Support\Cast;

class SettingsController
{
    use ResolvesContext;

    /** Severities the dashboard exposes for the minimum-severity gate. */
    private const SEVERITIES = ['info', 'warning', 'critical'];

    /** Whitelists for the UI-managed alert rules (§5.5). */
    private const METRICS = ['error_rate', 'p95', 'throughput', 'errors', 'slow', 'failed_jobs', 'cache_hit_rate'];

    private const OPS = ['>', '>=', '<', '<='];

    private const WINDOWS = ['15m', '1h', '6h', '24h', '7d'];

    public function index(): View
    {
        return ViewFactory::make('warden::admin.settings', array_merge($this->chrome(), [
            'settings' => AlertSetting::current(),
            'severities' => self::SEVERITIES,
            'rules' => AlertRule::query()->orderBy('name')->get(),
            'metrics' => self::METRICS,
            'ops' => self::OPS,
            'windows' => self::WINDOWS,
            'showRanges' => false,
            'autoRefresh' => false,
        ]));
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $name = trim(Cast::str($request->input('name')));
        $metric = Cast::str($request->input('metric'));
        $op = Cast::str($request->input('op'));
        $window = Cast::str($request->input('window'));
        $severity = Cast::str($request->input('severity'), 'warning');

        // Reject anything outside the whitelists rather than persisting junk.
        if ($name === ''
            || ! in_array($metric, self::METRICS, true)
            || ! in_array($op, self::OPS, true)
            || ! in_array($window, self::WINDOWS, true)
            || ! in_array($severity, self::SEVERITIES, true)) {
            return redirect()->route('warden.admin.settings')->with('warden_error', 'Invalid alert rule.');
        }

        AlertRule::updateOrCreate(['name' => $name], [
            'metric' => $metric,
            'op' => $op,
            'threshold' => (float) Cast::str($request->input('threshold'), '0'),
            'window' => $window,
            'severity' => $severity,
            'enabled' => true,
        ]);

        return redirect()->route('warden.admin.settings')->with('warden_status', 'Alert rule saved.');
    }

    public function deleteRule(int $rule): RedirectResponse
    {
        AlertRule::query()->whereKey($rule)->delete();

        return redirect()->route('warden.admin.settings')->with('warden_status', 'Alert rule removed.');
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
            // #16 — drop anything that isn't a valid e-mail before persisting.
            if ($address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL) !== false) {
                $out[] = $address;
            }
        }

        return array_values(array_unique($out));
    }
}
