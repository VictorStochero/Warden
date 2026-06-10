<?php

namespace VictorStochero\Warden\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Projects\ProjectManager;
use VictorStochero\Warden\Support\Cast;

/**
 * Parent-side project management. Creating a project mints the token + secret a
 * child uses to authenticate and sign its batches; the secret is shown once
 * (it is stored encrypted and cannot be recovered later).
 */
class ProjectCommand extends Command
{
    protected $signature = 'warden:project
        {name? : Name of the project to create}
        {--slug= : Custom slug (defaults to a slug of the name)}
        {--delivery=scheduler : Child delivery mode for the printed install command (scheduler|daemon)}
        {--list : List existing projects}';

    protected $description = 'Create or list Warden projects (parent)';

    public function handle(ProjectManager $manager): int
    {
        if (config('warden.mode') !== 'parent') {
            $this->components->error('warden:project only runs in parent mode.');

            return self::FAILURE;
        }

        if ($this->option('list') || ! $this->argument('name')) {
            return $this->listProjects();
        }

        return $this->createProject($manager);
    }

    protected function createProject(ProjectManager $manager): int
    {
        $nameArg = $this->argument('name');
        $name = is_string($nameArg) ? $nameArg : '';

        try {
            $result = $manager->create($name, Cast::str($this->option('slug')) ?: null);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $delivery = Cast::str($this->option('delivery'), 'scheduler');
        $command = $manager->installCommand(
            $result['project']->slug,
            $result['token'],
            $result['secret'],
            Cast::str(url('/')),
            $delivery,
        );

        $this->newLine();
        $this->components->info("Project [{$name}] created.");
        $this->newLine();
        $this->line('  Run this on the <fg=yellow>child</> (the secret is shown only once):');
        $this->newLine();
        $this->line('    <fg=green>'.$command.'</>');
        $this->newLine();

        if ($delivery === 'daemon') {
            $this->line('  Daemon mode: supervise `php artisan warden:ship` (Supervisor / Forge Daemon).');
        } else {
            $this->line('  Scheduler mode: just keep the scheduler cron running — nothing else to do.');
        }

        $this->newLine();

        return self::SUCCESS;
    }

    protected function listProjects(): int
    {
        $projects = Project::query()->orderBy('name')->get();

        if ($projects->isEmpty()) {
            $this->components->warn('No projects yet. Create one: php artisan warden:project "My App"');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Slug', 'Token', 'Active', 'Last seen'],
            $projects->map(fn (Project $p) => [
                $p->id,
                $p->name,
                $p->slug,
                Str::limit($p->token, 8, '…'),
                $p->active ? 'yes' : 'no',
                $p->last_seen_at?->diffForHumans() ?? 'never',
            ])->all()
        );

        return self::SUCCESS;
    }
}
