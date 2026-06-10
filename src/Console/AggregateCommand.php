<?php

namespace VictorStochero\Warden\Console;

use Illuminate\Console\Command;
use VictorStochero\Warden\Contracts\Aggregator;
use VictorStochero\Warden\Issues\IssueProcessor;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Warden;

class AggregateCommand extends Command
{
    protected $signature = 'warden:aggregate
        {--project= : Limit to a single project id or slug}';

    protected $description = 'Roll raw events into aggregates and group exceptions into issues (parent)';

    /**
     * Event types that get rolled up.
     *
     * @var list<string>
     */
    protected array $types = [
        'request', 'query', 'job', 'cache', 'http', 'mail',
        'notification', 'command', 'schedule', 'log', 'exception', 'host',
    ];

    public function handle(Aggregator $aggregator, IssueProcessor $issues, Warden $warden): int
    {
        if (config('warden.mode') !== 'parent') {
            $this->components->error('warden:aggregate only runs in parent mode.');

            return self::FAILURE;
        }

        foreach ($this->projects() as $project) {
            foreach ($this->types as $type) {
                $aggregator->rollup($project->id, $type);
            }

            $issues->process($project->id);
            $this->components->task("Aggregated project [{$project->slug}]");
        }

        return self::SUCCESS;
    }

    /** @return iterable<Project> */
    protected function projects(): iterable
    {
        $query = Project::query();

        if ($filter = $this->option('project')) {
            $filter = Cast::str($filter);
            $query->where(fn ($q) => $q
                ->where('slug', $filter)
                ->when(ctype_digit($filter), fn ($qq) => $qq->orWhere('id', (int) $filter)));
        }

        return $query->get();
    }
}
