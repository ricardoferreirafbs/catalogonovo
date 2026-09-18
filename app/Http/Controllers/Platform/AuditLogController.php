<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __invoke(Request $request)
    {
        $event = trim((string) $request->query('event'));
        $outcome = $request->query('outcome');

        $logs = AuditLog::query()
            ->with(['actor:id,name,email', 'tenant:id,name'])
            ->when($event, fn ($query) => $query->where('event', 'like', "%{$event}%"))
            ->when(in_array($outcome, ['success', 'rejected'], true), fn ($query) => $query->where('metadata->outcome', $outcome))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('platform.audit.index', compact('logs', 'event', 'outcome'));
    }
}
