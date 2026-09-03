<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Pagination;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    /**
     * Log aktivitas — filter tanggal, user, aksi, dan kata kunci.
     */
    public function index(Request $request): Response
    {
        $logs = ActivityLog::query()
            ->with('user:id,name')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->string('user_id')->toString()))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')->toString()))
            ->when($request->filled('search'), fn ($q) => $q->whereLike('description', '%'.$request->string('search')->toString().'%'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(Pagination::perPage($request, 20))
            ->withQueryString();

        return Inertia::render('admin/aktivitas/Index', [
            'logs' => $logs,
            'filters' => $request->only(['from', 'to', 'user_id', 'action', 'search']),
            'userOptions' => User::query()
                ->whereHas('activityLogs')
                ->orderBy('name')
                ->get(['id', 'name']),
            'actionOptions' => ActivityAction::options(),
        ]);
    }
}
