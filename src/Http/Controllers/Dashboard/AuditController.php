<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Http\Controllers\Dashboard\Concerns\ResolvesContext;

/**
 * The audit trail (§5.7): a read-only list of who did what in the dashboard,
 * gated by manageWarden.
 */
class AuditController
{
    use ResolvesContext;

    public function index(DashboardRepository $repo): View
    {
        return ViewFactory::make('warden::admin.audit', array_merge($this->chrome(), [
            'entries' => $repo->auditLog(200),
            'showRanges' => false,
            'autoRefresh' => false,
        ]));
    }
}
