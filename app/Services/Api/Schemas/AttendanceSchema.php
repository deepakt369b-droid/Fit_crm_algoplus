<?php

namespace App\Services\Api\Schemas;

use App\Models\Attendance;

/**
 * Single source of truth for Attendance API query rules and serialization.
 */
final class AttendanceSchema
{
    private function __construct() {}

    /**
     * @return array{
     *   searchable: list<string>,
     *   sortable: list<string>,
     *   default_sort: string,
     *   status_column: string|null,
     *   includes: list<string>,
     *   filters: array<string, array{type: string, column: string}>
     * }
     */
    public static function queryRules(): array
    {
        return [
            'searchable' => [],
            'sortable' => ['id', 'recognized_at', 'created_at'],
            'default_sort' => '-recognized_at',
            'status_column' => null,
            'includes' => ['member', 'device'],
            'filters' => [
                'member_id' => ['type' => 'exact', 'column' => 'member_id'],
                'device_id' => ['type' => 'exact', 'column' => 'device_id'],
                'direction' => ['type' => 'exact', 'column' => 'direction'],
                'method' => ['type' => 'exact', 'column' => 'method'],
                'recognized_at' => ['type' => 'datetime_range', 'column' => 'recognized_at'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resource(Attendance $attendance): array
    {
        return [
            'id' => (int) $attendance->id,
            'member_id' => (int) $attendance->member_id,
            'device_id' => $attendance->device_id !== null ? (int) $attendance->device_id : null,
            'direction' => (string) $attendance->direction,
            'method' => (string) $attendance->method,
            'confidence' => $attendance->confidence !== null ? (float) $attendance->confidence : null,
            'recognized_at' => $attendance->recognized_at->toISOString(),
            'source' => (string) $attendance->source,
            'created_at' => $attendance->created_at?->toISOString(),
        ];
    }
}
