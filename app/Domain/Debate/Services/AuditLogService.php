<?php

namespace App\Domain\Debate\Services;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogService
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function log(
        ?User $actor,
        string $entityType,
        int $entityId,
        string $action,
        ?string $reason = null,
        array $metadata = [],
    ): AuditLog {
        return AuditLog::query()->create([
            'actor_user_id' => $actor?->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'reason' => $reason,
            'metadata_json' => $metadata,
        ]);
    }
}
