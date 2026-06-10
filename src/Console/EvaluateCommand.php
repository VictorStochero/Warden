<?php

namespace VictorStochero\Warden\Console;

use Illuminate\Console\Command;
use VictorStochero\Warden\Alerting\Evaluator;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Support\Cast;

class EvaluateCommand extends Command
{
    protected $signature = 'warden:evaluate {--project=}';

    protected $description = 'Evaluate issues & heartbeats, open/resolve incidents and fire internal alerts (parent)';

    public function handle(Evaluator $evaluator): int
    {
        if (config('warden.mode') !== 'parent') {
            $this->components->error('warden:evaluate only runs in parent mode.');

            return self::FAILURE;
        }

        $query = Project::query();
        if ($filter = $this->option('project')) {
            $filter = Cast::str($filter);
            $query->where(fn ($q) => $q
                ->where('slug', $filter)
                ->when(ctype_digit($filter), fn ($qq) => $qq->orWhere('id', (int) $filter)));
        }

        foreach ($query->get() as $project) {
            $evaluator->evaluate($project->id);
            $this->components->task("Evaluated project [{$project->slug}]");
        }

        return self::SUCCESS;
    }
}
