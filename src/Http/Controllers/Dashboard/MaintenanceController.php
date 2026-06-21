<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFactory;
use VictorStochero\Warden\Http\Controllers\Dashboard\Concerns\ResolvesContext;
use VictorStochero\Warden\Maintenance\RunMaintenanceJob;
use VictorStochero\Warden\Models\CommandRun;
use VictorStochero\Warden\Models\DeadLetter;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Updates\VersionCheck;

class MaintenanceController
{
    use ResolvesContext;

    public function index(VersionCheck $check): View
    {
        $runs = CommandRun::query()->orderByDesc('id')->get()->unique('command')->keyBy('command');

        return ViewFactory::make('warden::admin.maintenance', array_merge($this->chrome(), [
            'commands' => RunMaintenanceJob::ALLOWED,
            'descriptions' => RunMaintenanceJob::DESCRIPTIONS,
            'runs' => $runs,
            'showRanges' => false,
            'autoRefresh' => false,
            'deadLetters' => DeadLetter::query()->orderByDesc('id')->limit(50)->get(),
            'versionCheck' => [
                'enabled' => $check->enabled(),
                'notice' => $check->notice(),
                'env_locked' => getenv('WARDEN_VERSION_CHECK') !== false,
            ],
        ]));
    }

    public function run(Request $request): RedirectResponse
    {
        $command = Cast::str($request->input('command'));

        if (! in_array($command, RunMaintenanceJob::ALLOWED, true)) {
            return redirect()->route('warden.admin.maintenance')->with('warden_error', 'Unknown command.');
        }

        $run = CommandRun::create(['command' => $command, 'status' => 'queued', 'queued_at' => now()]);

        RunMaintenanceJob::dispatch($command, (int) $run->id);

        return redirect()->route('warden.admin.maintenance')->with('warden_status', "Queued warden:{$command}.");
    }

    /** Dismiss the new-version banner for the currently-advertised version. */
    public function dismissVersion(Request $request, VersionCheck $check): RedirectResponse
    {
        $version = Cast::str($request->input('version'));

        if ($version !== '') {
            $check->dismiss($version);
        }

        return redirect()->back()->with('warden_status', Cast::str(__('warden::version.dismissed')));
    }

    /** Toggle the new-version check on/off from the dashboard (.env still wins). */
    public function toggleVersionCheck(Request $request, VersionCheck $check): RedirectResponse
    {
        $check->setEnabled($request->boolean('enabled'));

        return redirect()->route('warden.admin.maintenance')->with('warden_status', Cast::str(__('warden::version.toggled')));
    }
}
