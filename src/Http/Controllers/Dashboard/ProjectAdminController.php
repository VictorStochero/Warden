<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFactory;
use VictorStochero\Warden\Config\ProjectConfig;
use VictorStochero\Warden\Http\Controllers\Dashboard\Concerns\ResolvesContext;
use VictorStochero\Warden\Models\Group;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Models\Tag;
use VictorStochero\Warden\Projects\ProjectManager;
use VictorStochero\Warden\Support\Cast;

class ProjectAdminController
{
    use ResolvesContext;

    public function index(): View
    {
        return ViewFactory::make('warden::admin.projects', array_merge($this->chrome(), [
            'projects' => Project::query()->with('group', 'tags')->orderBy('name')->get(),
            'showRanges' => false,
            'autoRefresh' => false,
        ]));
    }

    public function store(Request $request, ProjectManager $manager): RedirectResponse
    {
        $name = trim(Cast::str($request->input('name')));

        if ($name === '') {
            return redirect()->route('warden.admin.projects')->with('warden_error', 'Name is required.');
        }

        $slug = Cast::str($request->input('slug'));

        try {
            $result = $manager->create($name, $slug !== '' ? $slug : null);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->route('warden.admin.projects')->with('warden_error', $e->getMessage());
        }

        return redirect()->route('warden.admin.projects')->with('warden_credentials', [
            'name' => $result['project']->name,
            'command' => $manager->installCommand(
                $result['project']->slug,
                $result['token'],
                $result['secret'],
                Cast::str(url('/')),
            ),
            'env' => $manager->envSnippet(
                $result['project']->slug,
                $result['token'],
                $result['secret'],
                Cast::str(url('/')),
            ),
        ]);
    }

    /** Edit a project's details — name, client, contact, group and tags. */
    public function edit(Project $project): View
    {
        return ViewFactory::make('warden::admin.project-edit', array_merge($this->chrome(), [
            'project' => $project->load('group', 'tags'),
            'groups' => Group::query()->orderBy('name')->get(),
            'allTags' => Tag::query()->orderBy('name')->get(),
            'showRanges' => false,
            'autoRefresh' => false,
        ]));
    }

    public function update(Project $project, Request $request, ProjectManager $manager): RedirectResponse
    {
        $name = trim(Cast::str($request->input('name')));

        if ($name === '') {
            return redirect()->route('warden.admin.projects.edit', $project)
                ->with('warden_error', 'Name is required.');
        }

        $intervals = $this->resolveIntervals($request);
        if ($intervals === null) {
            return redirect()->route('warden.admin.projects.edit', $project)
                ->with('warden_error', 'Invalid interval settings.');
        }

        $manager->updateDetails($project, [
            'name' => $name,
            'client' => Cast::str($request->input('client')),
            'contact' => Cast::str($request->input('contact')),
            'group' => Cast::str($request->input('group')),
            'tags' => Cast::str($request->input('tags')),
        ]);

        $project->forceFill(array_merge($intervals, $this->resolveRetention($request), $this->resolveAlertOverride($request)))->save();

        $this->applyBehaviourConfig($project, $request);

        return redirect()->route('warden.admin.projects')
            ->with('warden_status', "{$project->name} updated.");
    }

    /**
     * Merge the sparse behaviour-config document submitted from the edit form
     * into the project's stored config. Unknown knobs are dropped and values are
     * clamped/coerced by ProjectConfig::sanitize. config_version is bumped only
     * when the sanitised document actually differs from what is already stored,
     * so a no-op save never forces an unnecessary push to the child.
     */
    private function applyBehaviourConfig(Project $project, Request $request): void
    {
        $incoming = $request->input('config');

        $document = [];
        foreach (Cast::arr($incoming) as $key => $value) {
            $document[Cast::str($key)] = $value;
        }

        $sanitized = ProjectConfig::sanitize($document);

        $current = is_array($project->config) ? $project->config : [];

        if ($sanitized != $current) { // structural comparison, key order independent
            $project->forceFill([
                'config' => $sanitized === [] ? null : $sanitized,
                'config_version' => Cast::int($project->config_version, 0) + 1,
            ])->save();
        }
    }

    /**
     * Resolve the per-project retention override (§5.12). An empty field means
     * "inherit the global window" (null); a value tightens retention below the
     * global ceiling. Clamped to sane bounds; a non-positive value falls back to
     * inherit.
     *
     * @return array{raw_retention_days: int|null, aggregate_retention_days: int|null}
     */
    private function resolveRetention(Request $request): array
    {
        return [
            'raw_retention_days' => $this->retentionDays($request->input('raw_retention_days'), 365),
            'aggregate_retention_days' => $this->retentionDays($request->input('aggregate_retention_days'), 3650),
        ];
    }

    private function retentionDays(mixed $input, int $max): ?int
    {
        if ($input === null || $input === '') {
            return null;
        }

        $days = Cast::int($input);

        return $days < 1 ? null : min($days, $max);
    }

    /**
     * Resolve the per-project alert override. When the override toggle is off,
     * all three columns are nulled so the project inherits the global settings.
     * Empty recipients/severity also fall back to global.
     *
     * @return array{alert_email_enabled: bool|null, alert_recipients: list<string>|null, alert_min_severity: string|null}
     */
    private function resolveAlertOverride(Request $request): array
    {
        if (! $request->boolean('alert_override')) {
            return ['alert_email_enabled' => null, 'alert_recipients' => null, 'alert_min_severity' => null];
        }

        $recipients = $this->parseRecipients($request->input('alert_recipients'));

        $severity = Cast::str($request->input('alert_min_severity'));
        $severity = in_array($severity, ['info', 'warning', 'critical'], true) ? $severity : null;

        return [
            'alert_email_enabled' => $request->boolean('alert_email_enabled'),
            'alert_recipients' => $recipients === [] ? null : $recipients,
            'alert_min_severity' => $severity,
        ];
    }

    /**
     * Split a comma/semicolon/newline-separated string into a clean address list.
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

    /**
     * Validate and normalise the "Intervals" section — audit schedule (frequency,
     * day, hour) and the uptime KPI window. Returns the column => value map to
     * persist, or null when any field is out of range.
     *
     * @return array{audit_frequency: string, audit_day: int|null, audit_hour: int|null, uptime_window: string}|null
     */
    private function resolveIntervals(Request $request): ?array
    {
        $frequency = Cast::str($request->input('audit_frequency'), 'off');
        if (! in_array($frequency, ['off', 'daily', 'weekly', 'monthly'], true)) {
            return null;
        }

        $dayInput = $request->input('audit_day');
        $day = ($dayInput === null || $dayInput === '') ? null : Cast::int($dayInput);

        $hourInput = $request->input('audit_hour');
        $hour = ($hourInput === null || $hourInput === '') ? null : Cast::int($hourInput);

        if ($hour !== null && ($hour < 0 || $hour > 23)) {
            return null;
        }

        $window = Cast::str($request->input('uptime_window'), '30d');
        if (! in_array($window, ['24h', '7d', '30d'], true)) {
            return null;
        }

        if ($frequency === 'off') {
            return ['audit_frequency' => 'off', 'audit_day' => null, 'audit_hour' => null, 'uptime_window' => $window];
        }

        if ($frequency === 'weekly') {
            $day = $day === null ? null : max(0, min(6, $day));
        } elseif ($frequency === 'monthly') {
            $day = $day === null ? null : max(1, min(31, $day));
        } else {
            $day = null; // daily has no day component
        }

        return [
            'audit_frequency' => $frequency,
            'audit_day' => $day,
            'audit_hour' => $hour,
            'uptime_window' => $window,
        ];
    }

    public function rotate(Project $project, ProjectManager $manager): RedirectResponse
    {
        $rotated = $manager->rotate($project);

        return redirect()->route('warden.admin.projects')->with('warden_credentials', [
            'name' => $project->name,
            'command' => $manager->installCommand(
                $project->slug,
                $rotated['token'],
                $rotated['secret'],
                Cast::str(url('/')),
            ),
            'env' => $manager->envSnippet(
                $project->slug,
                $rotated['token'],
                $rotated['secret'],
                Cast::str(url('/')),
            ),
        ]);
    }

    /**
     * Re-show the child credentials for an existing project — same token + secret
     * (the secret is stored encrypted, so it can be decrypted and displayed again
     * for the .env), without rotating. Lets an operator recover the setup snippet
     * they only saw once at creation.
     */
    public function credentials(Project $project, ProjectManager $manager): RedirectResponse
    {
        $url = Cast::str(url('/'));

        return redirect()->route('warden.admin.projects')->with('warden_credentials', [
            'name' => $project->name,
            'command' => $manager->installCommand($project->slug, $project->token, $project->secret, $url),
            'env' => $manager->envSnippet($project->slug, $project->token, $project->secret, $url),
        ]);
    }

    /** Permanently delete a project and all of its data. */
    public function destroy(Project $project, ProjectManager $manager): RedirectResponse
    {
        $selfSlug = Cast::str(config('warden.parent.self_project'), 'parent');

        if ($project->slug === $selfSlug) {
            return redirect()->route('warden.admin.projects')
                ->with('warden_error', Cast::str(__('warden::admin.projects.cannot_delete_self')));
        }

        $name = $project->name;
        $manager->delete($project);

        return redirect()->route('warden.admin.projects')
            ->with('warden_status', Cast::str(__('warden::admin.projects.deleted', ['name' => $name])));
    }

    public function toggle(Project $project, ProjectManager $manager): RedirectResponse
    {
        $active = ! $project->active;
        $manager->setActive($project, $active);

        return redirect()->route('warden.admin.projects')
            ->with('warden_status', $project->name.' '.($active ? 'activated' : 'deactivated').'.');
    }

    public function reset(Project $project, ProjectManager $manager): RedirectResponse
    {
        $deleted = $manager->resetMetrics($project);
        $total = array_sum($deleted);

        return redirect()->route('warden.admin.projects')
            ->with('warden_status', "Metrics for {$project->name} reset — {$total} rows cleared.");
    }

    /** Request an immediate audit; the child runs it on its next ship. */
    public function auditNow(Project $project): RedirectResponse
    {
        $project->forceFill(['audit_requested_at' => now()])->save();

        return redirect()->back()
            ->with('warden_status', "{$project->name}: audit requested — the child will run it on its next delivery.");
    }
}
