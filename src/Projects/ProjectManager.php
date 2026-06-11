<?php

namespace VictorStochero\Warden\Projects;

use Illuminate\Support\Str;
use VictorStochero\Warden\Facades\Warden;
use VictorStochero\Warden\Models\Group;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Models\Tag;
use VictorStochero\Warden\Support\Cast;

/**
 * Shared project lifecycle used by both the CLI (warden:project) and the
 * parent dashboard. Creating or rotating mints a token + secret; the secret is
 * only returned here (stored encrypted) and must be shown to the operator once.
 */
class ProjectManager
{
    /** @return array{project: Project, token: string, secret: string} */
    public function create(string $name, ?string $slug = null): array
    {
        $slug = Str::slug($slug !== null && $slug !== '' ? $slug : $name);

        if ($slug === '') {
            throw new \InvalidArgumentException('Could not derive a slug — pass a slug explicitly.');
        }

        if (Project::query()->where('slug', $slug)->exists()) {
            throw new \RuntimeException("A project with slug [{$slug}] already exists.");
        }

        $token = Str::random(40);
        $secret = Str::random(64);

        // The mint writes the secret to the DB; suppress so a self-monitoring
        // parent never records the credential INSERT (§18.3) — defense-in-depth
        // on top of the column-correlated binding scrub.
        /** @var Project $project */
        $project = Warden::withoutRecording(fn () => Project::create([
            'name' => $name,
            'slug' => $slug,
            'token' => $token,
            'secret' => $secret,
            'active' => true,
        ]));

        return ['project' => $project, 'token' => $token, 'secret' => $secret];
    }

    /**
     * Ensure the parent's self-monitoring project exists (Frente 1). Idempotent:
     * firstOrCreate keyed by slug. The token/secret columns are NOT NULL, so we
     * mint random values even though self-monitoring never uses the HTTP channel.
     */
    public function ensureSelfProject(string $slug, ?string $name = null): Project
    {
        $slug = Str::slug($slug);

        if ($slug === '') {
            throw new \InvalidArgumentException('Self project slug is empty.');
        }

        // Mints token/secret on first create — suppressed like create()/rotate().
        /** @var Project $project */
        $project = Warden::withoutRecording(fn () => Project::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name ?? $this->defaultSelfName($slug),
                'token' => Str::random(40),
                'secret' => Str::random(64),
                'active' => true,
            ],
        ));

        $this->syncSelfTimezone($project);

        return $project;
    }

    /**
     * Auto-detect the self project's display timezone from the parent's own
     * app.timezone — the self-monitoring equivalent of what the ingest endpoint
     * does for remote children. Idempotent: only a valid IANA identifier that
     * differs from the stored value is written.
     */
    private function syncSelfTimezone(Project $project): void
    {
        $tz = Cast::str(config('app.timezone'));

        if ($tz === '' || $tz === Cast::str($project->timezone) || ! in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            return;
        }

        $project->forceFill(['timezone' => $tz])->save();
    }

    /**
     * Default display name for the self-monitoring project: the host app's name
     * (APP_NAME), falling back to a headline of the slug when it is unset. Only
     * applied at creation — an operator's later rename is never clobbered.
     */
    private function defaultSelfName(string $slug): string
    {
        $appName = trim(Cast::str(config('app.name')));

        return $appName !== '' ? $appName : Str::headline($slug);
    }

    /** @return array{token: string, secret: string} */
    public function rotate(Project $project): array
    {
        $token = Str::random(40);
        $secret = Str::random(64);

        // Suppress the credential UPDATE from a self-monitoring parent (§18.3).
        Warden::withoutRecording(fn () => $project->update(['token' => $token, 'secret' => $secret]));

        return ['token' => $token, 'secret' => $secret];
    }

    public function setActive(Project $project, bool $active): void
    {
        $project->update(['active' => $active]);
    }

    /**
     * Update a project's editable details — name, light CRM metadata, its group
     * (resolved/created by name) and its tags (resolved/created by name, then
     * synced). Empty group/tags clear the association.
     *
     * @param  array{name?: string|null, client?: string|null, contact?: string|null, group?: string|null, tags?: string|array<int, string>|null}  $data
     */
    public function updateDetails(Project $project, array $data): void
    {
        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name !== '') {
                $project->name = $name;
            }
        }

        $project->client = $this->nullableStr($data['client'] ?? null);
        $project->contact = $this->nullableStr($data['contact'] ?? null);

        $groupName = $this->nullableStr($data['group'] ?? null);
        $project->group_id = $groupName !== null ? $this->resolveGroup($groupName)->id : null;

        $project->save();

        $tagIds = array_map(fn (string $name): int => $this->resolveTag($name)->id, $this->parseTags($data['tags'] ?? null));
        $project->tags()->sync($tagIds);
    }

    private function resolveGroup(string $name): Group
    {
        $slug = Str::slug($name);

        return Group::query()->firstOrCreate(['slug' => $slug], ['name' => $name]);
    }

    private function resolveTag(string $name): Tag
    {
        $slug = Str::slug($name);

        return Tag::query()->firstOrCreate(['slug' => $slug], ['name' => $name]);
    }

    /**
     * @param  string|array<int, string>|null  $tags
     * @return array<int, string>
     */
    private function parseTags(string|array|null $tags): array
    {
        $list = is_array($tags) ? $tags : explode(',', (string) $tags);

        $clean = [];
        foreach ($list as $tag) {
            $tag = trim($tag);
            if ($tag !== '' && Str::slug($tag) !== '') {
                $clean[Str::slug($tag)] = $tag; // dedupe by slug
            }
        }

        return array_values($clean);
    }

    private function nullableStr(?string $value): ?string
    {
        $value = trim($value ?? '');

        return $value !== '' ? $value : null;
    }

    /**
     * Wipe every project-scoped metric — raw events, rollups, issues, incidents,
     * heartbeats and consolidation cursors — so a project can start fresh (useful
     * for testing). The project row and its credentials are kept.
     *
     * @return array<string, int> rows deleted, keyed by table
     */
    public function resetMetrics(Project $project): array
    {
        $connection = $project->getConnection();

        $tables = [
            'wdn_events', 'wdn_aggregates', 'wdn_issues',
            'wdn_incidents', 'wdn_heartbeats', 'wdn_cursors',
        ];

        $deleted = [];

        foreach ($tables as $table) {
            $deleted[$table] = $connection->table($table)->where('project_id', $project->id)->delete();
        }

        return $deleted;
    }

    /**
     * Permanently remove a project and everything scoped to it — raw events,
     * rollups, issues, incidents, heartbeats, cursors and tag links — then the
     * project row itself. Shared groups are left untouched.
     *
     * @return array<string, int> rows deleted per metric table (project row excluded)
     */
    public function delete(Project $project): array
    {
        $deleted = $this->resetMetrics($project);

        $project->tags()->detach();
        $project->delete();

        return $deleted;
    }

    public function installCommand(
        string $slug,
        string $token,
        string $secret,
        string $parentUrl,
        string $delivery = 'scheduler',
    ): string {
        $parts = [
            'php artisan warden:install --child',
            '--parent-url='.$parentUrl,
            '--project='.$slug,
            '--token='.$token,
            '--secret='.$secret,
        ];

        if ($delivery === 'daemon') {
            $parts[] = '--delivery=daemon';
        }

        return implode(' ', $parts);
    }

    /**
     * The raw .env block for the child — an alternative to running the install
     * command (e.g. paste straight into a production .env after generating the
     * config files locally).
     */
    public function envSnippet(
        string $slug,
        string $token,
        string $secret,
        string $parentUrl,
        string $delivery = 'scheduler',
    ): string {
        $lines = [
            'WARDEN_MODE=child',
            'WARDEN_PARENT_URL='.$parentUrl,
            'WARDEN_PROJECT='.$slug,
            'WARDEN_TOKEN='.$token,
            'WARDEN_SECRET='.$secret,
        ];

        if ($delivery === 'daemon') {
            $lines[] = 'WARDEN_DELIVERY=daemon';
        }

        return implode("\n", $lines);
    }
}
