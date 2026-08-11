<?php

namespace App\Support;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Pencatat aktivitas (audit log) — dipanggil dari event auth, observer
 * model, dan titik-titik penting di controller.
 */
final class ActivityLogger
{
    /**
     * Catat aktivitas. User diambil dari auth (bisa di-override), IP dari request.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function log(
        ActivityAction $action,
        string $description,
        ?Model $subject = null,
        array $metadata = [],
        ?User $user = null,
    ): void {
        $user ??= auth()->user();
        $request = app()->bound('request') ? app(Request::class) : null;

        ActivityLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
            'ip_address' => $request?->ip(),
        ]);
    }
}
