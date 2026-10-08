<?php

namespace App\Services\AiTooling;

/**
 * Declarative argument schemas for every Lina tool (Schema Validation stage).
 *
 * This stage runs on NORMALIZED args (after alias mapping) and checks shape
 * only: required/optional, types, enums, ids, dates, string lengths and the
 * total payload budget. Business rules, ownership and state live in later
 * stages — a schema pass never touches the database.
 *
 * Accepted value sets intentionally mirror what the business validators
 * (AiToolService) canonicalize — e.g. routine frequency synonyms — so the
 * schema can never reject something the business layer would accept.
 */
final class ToolSchema
{
    /** Hard cap on the JSON-encoded args payload (48 KB). */
    public const MAX_PAYLOAD_BYTES = 49152;

    /**
     * Max nesting depth of the args structure. Legit plans nest as
     * args > projects[] > tasks[] > subtasks[] (+ wrappers), so the cap
     * must clear that with headroom; the byte budget is the real guard.
     */
    public const MAX_DEPTH = 12;

    public const FREQUENCIES = [
        'daily', 'weekly', 'monthly', 'every_n_days',
        // Synonyms the business validator canonicalizes.
        'everyday', 'every_day', 'each_day',
        'every_week', 'every_month',
        'every_other_day', 'everyotherday', 'every_2_days', 'everyndays', 'every_n_day',
    ];

    /**
     * Field spec per tool. Each field:
     *   type: string|int|bool|array|scalar|date|time
     *   required: bool | ['anyOf' => [...other fields...]]
     *   enum: allowed values | max: chars/items | min/max: numeric bounds
     */
    public static function schemas(): array
    {
        $id = ['type' => 'int', 'min' => 1];
        $optStr = fn (int $max) => ['type' => 'string', 'max' => $max];
        $reqStr = fn (int $max) => ['type' => 'string', 'required' => true, 'max' => $max];

        return [
            'task_create' => [
                'title' => $reqStr(255),
                'project_id' => $id,
                'project' => $optStr(255),
                'parent_id' => $id,
                'due_date' => ['type' => 'date'],
                'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                'status' => ['type' => 'string', 'enum' => ['to_do', 'in_progress', 'on_hold', 'in_review', 'completed']],
                'description' => $optStr(2000),
            ],
            'task_update' => [
                'id' => $id,
                'task' => $optStr(255),
                'project' => $optStr(255),
                'project_id' => $id,
                'title' => $optStr(255),
                'due_date' => ['type' => 'date'],
                'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                'status' => ['type' => 'string', 'enum' => ['to_do', 'in_progress', 'on_hold', 'in_review', 'completed']],
                'description' => $optStr(2000),
                '__anyOf' => [['id', 'task']],
            ],
            'task_complete' => [
                'id' => $id,
                'task' => $optStr(255),
                'project' => $optStr(255),
                'project_id' => $id,
                '__anyOf' => [['id', 'task']],
            ],
            'task_delete' => [
                'id' => $id,
                'task' => $optStr(255),
                'project' => $optStr(255),
                'project_id' => $id,
                '__anyOf' => [['id', 'task']],
            ],
            'reminder_create' => [
                'title' => $reqStr(255),
                'date' => ['type' => 'date'],
                'time' => ['type' => 'time'],
                'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent']],
                'description' => $optStr(2000),
                'location' => $optStr(255),
            ],
            'reminder_complete' => ['id' => $id + ['required' => true]],
            'reminder_delete' => ['id' => $id + ['required' => true]],
            'note_create' => [
                'title' => $reqStr(255),
                'content' => $reqStr(20000),
                'category' => $optStr(100),
            ],
            'note_update' => [
                'id' => $id + ['required' => true],
                'title' => $optStr(255),
                'content' => $optStr(20000),
                'category' => $optStr(100),
            ],
            'note_delete' => ['id' => $id + ['required' => true]],
            'project_create' => [
                'name' => $reqStr(255),
                'description' => $optStr(2000),
                'status' => ['type' => 'string', 'enum' => ['not_started', 'in_progress', 'completed', 'closed']],
                'parent' => $optStr(255),
                'parent_id' => $id,
            ],
            'project_add_member' => [
                'project_id' => $id,
                'project' => $optStr(255),
                'user' => $reqStr(255),
                'role' => ['type' => 'string', 'enum' => ['member', 'viewer', 'editor']],
            ],
            'note_link' => [
                'note_id' => $id,
                'note' => $optStr(255),
                'target_type' => ['type' => 'string', 'required' => true, 'enum' => ['project', 'task', 'note']],
                'target_id' => $id,
                'target' => $optStr(255),
            ],
            'report_generate' => [
                'range' => ['type' => 'string', 'enum' => ['today', 'week', 'month']],
            ],
            'checklist_add' => [
                'task_id' => $id,
                'task' => $optStr(255),
                'project' => $optStr(255),
                'project_id' => $id,
                'name' => $reqStr(255),
            ],
            'checklist_toggle' => ['id' => $id + ['required' => true]],
            'routine_create' => [
                'title' => $reqStr(255),
                'frequency' => ['type' => 'string', 'enum' => self::FREQUENCIES],
                // Business also accepts a single weekday string (canonicalized later).
                'days' => ['type' => ['string', 'array']],
                'month_days' => ['type' => 'array'],
                'every_n_days' => ['type' => 'int', 'min' => 2, 'max' => 60],
                'time_period' => $optStr(50),
                'description' => $optStr(2000),
                'behavior_type' => ['type' => 'string', 'enum' => ['build', 'avoid']],
                'count_violations' => ['type' => 'bool'],
                'tracking_mode' => ['type' => 'string', 'enum' => ['none', 'value', 'sets']],
                'value_kind' => ['type' => 'string', 'enum' => ['number', 'weight', 'time', 'reps', 'percent']],
                'value_unit' => $optStr(20),
                'value_label' => $optStr(100),
                'steps' => ['type' => 'array', 'max' => 20],
            ],
            'routine_log' => [
                'routine' => $optStr(255),
                'routine_id' => $id,
                'date' => ['type' => 'date'],
                'value' => ['type' => 'scalar', 'required' => true],
                'item' => $optStr(255),
                'item_id' => $id,
                'set_no' => ['type' => 'int', 'min' => 1, 'max' => 20],
            ],
            'routine_complete' => [
                'id' => $id + ['required' => true],
                'date' => ['type' => 'date'],
            ],
            'routine_delete' => ['id' => $id + ['required' => true]],
            'plan_propose' => [
                'title' => $reqStr(255),
                'project' => ['type' => 'array'],
                'subprojects' => ['type' => 'array', 'max' => 3],
                'projects' => ['type' => 'array', 'max' => 5],
                'reminders' => ['type' => 'array', 'max' => 10],
                'notes' => ['type' => 'array', 'max' => 10],
                'routines' => ['type' => 'array', 'max' => 7],
            ],
            'workout_plan_propose' => [
                'title' => $reqStr(255),
                'week_number' => ['type' => 'int', 'min' => 1, 'max' => 99],
                'start_date' => ['type' => 'date'],
                'goal' => $optStr(500),
                'description' => $optStr(2000),
                'rules' => ['type' => 'array', 'max' => 30],
                'days' => ['type' => 'array', 'max' => 7],
            ],
        ];
    }

    /**
     * Envelope check on RAW (pre-normalization) args: array shape, depth,
     * total payload budget. Returns decoded array on success.
     */
    public static function envelope($raw): array
    {
        $args = is_string($raw) ? (json_decode($raw, true)) : $raw;
        if (! is_array($args)) {
            return ToolError::fail(ToolError::VALIDATION_ERROR, 'Tool arguments must be a JSON object.');
        }
        if (self::depth($args) > self::MAX_DEPTH) {
            return ToolError::fail(ToolError::VALIDATION_ERROR, 'Tool arguments are nested too deeply.');
        }
        $bytes = self::payloadBytes($args);
        if ($bytes < 0) {
            return ToolError::fail(ToolError::VALIDATION_ERROR, 'Tool arguments could not be encoded.');
        }
        if ($bytes > self::MAX_PAYLOAD_BYTES) {
            return ToolError::fail(
                ToolError::PAYLOAD_TOO_LARGE,
                'Tool arguments are too large ('.number_format($bytes).' bytes, max '.number_format(self::MAX_PAYLOAD_BYTES).'). Split the request into smaller calls.'
            );
        }

        return ToolError::ok(['args' => $args, 'bytes' => $bytes]);
    }

    public static function payloadBytes(array $args): int
    {
        $json = json_encode($args, JSON_UNESCAPED_UNICODE);

        return $json === false ? -1 : strlen($json);
    }

    private static function depth(array $args, int $level = 1): int
    {
        $max = $level;
        foreach ($args as $v) {
            if (is_array($v)) {
                $max = max($max, self::depth($v, $level + 1));
            }
        }

        return $max;
    }

    /**
     * Shape check on NORMALIZED args against the tool's schema.
     * Unknown keys are ignored (business validators own them).
     */
    public static function check(string $tool, array $args): array
    {
        $schemas = self::schemas();
        if (! isset($schemas[$tool])) {
            return ToolError::fail(ToolError::UNKNOWN_TOOL, "Unknown tool: {$tool}.");
        }
        $schema = $schemas[$tool];

        foreach ($schema as $field => $spec) {
            if ($field === '__anyOf') {
                continue;
            }
            $present = array_key_exists($field, $args) && $args[$field] !== null;
            if (! empty($spec['required']) && ! $present) {
                return ToolError::fail(ToolError::VALIDATION_ERROR, "Missing required argument: {$field}.", ['field' => $field]);
            }
            if (! $present) {
                continue;
            }
            $err = self::checkField($tool, $field, $args[$field], $spec);
            if ($err !== null) {
                return $err;
            }
        }

        foreach ($schema['__anyOf'] ?? [] as $group) {
            $hit = false;
            foreach ($group as $f) {
                if (array_key_exists($f, $args) && $args[$f] !== null && trim((string) $args[$f]) !== '') {
                    $hit = true;
                    break;
                }
            }
            if (! $hit) {
                return ToolError::fail(
                    ToolError::VALIDATION_ERROR,
                    'One of ['.implode(', ', $group)."] is required for {$tool}.",
                    ['field' => $group[0]]
                );
            }
        }

        return ToolError::ok();
    }

    private static function checkField(string $tool, string $field, $value, array $spec): ?array
    {
        $fail = fn (string $msg) => ToolError::fail(ToolError::VALIDATION_ERROR, "{$tool}.{$field}: {$msg}.", ['field' => $field]);
        $types = (array) ($spec['type'] ?? 'string');

        // Union types (e.g. days accepts a single weekday string or an array).
        if (count($types) > 1) {
            foreach ($types as $t) {
                if (self::checkSingleType($value, $t) === null) {
                    return self::checkEnum($tool, $field, $value, $spec);
                }
            }

            return $fail('must be one of types ['.implode(', ', $types).']');
        }

        $type = $types[0];

        switch ($type) {
            case 'string':
                if (! is_string($value)) {
                    return $fail('must be a string');
                }
                if (trim($value) === '' && ! empty($spec['required']) && empty($spec['allowEmpty'])) {
                    return $fail('must not be empty');
                }
                if (isset($spec['max']) && mb_strlen($value) > $spec['max']) {
                    return $fail('too long (max '.$spec['max'].' chars)');
                }
                break;
            case 'int':
                if (! self::isIntLike($value)) {
                    return $fail('must be an integer');
                }
                $num = (int) $value;
                if (isset($spec['min']) && $num < $spec['min']) {
                    return $fail('must be >= '.$spec['min']);
                }
                if (isset($spec['max']) && $num > $spec['max']) {
                    return $fail('must be <= '.$spec['max']);
                }
                break;
            case 'bool':
                if (! is_bool($value) && ! in_array($value, [0, 1, '0', '1'], true)) {
                    return $fail('must be true/false');
                }
                break;
            case 'array':
                if (! is_array($value)) {
                    return $fail('must be an array');
                }
                if (isset($spec['max']) && count($value) > $spec['max']) {
                    return $fail('too many items (max '.$spec['max'].')');
                }
                break;
            case 'scalar':
                if (! is_scalar($value) || is_bool($value)) {
                    return $fail('must be a number or string');
                }
                break;
            case 'date':
                if (! is_string($value) || strtotime($value) === false) {
                    return $fail('must be a valid date');
                }
                break;
            case 'time':
                if (! is_string($value) || ! preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', trim($value))) {
                    return $fail('must be HH:MM (24h)');
                }
                break;
            default:
                return $fail('has an unknown schema type');
        }

        return self::checkEnum($tool, $field, $value, $spec);
    }

    private static function checkEnum(string $tool, string $field, $value, array $spec): ?array
    {
        if (isset($spec['enum']) && ! in_array($value, $spec['enum'], true)) {
            return ToolError::fail(
                ToolError::VALIDATION_ERROR,
                "{$tool}.{$field}: must be one of [".implode(', ', $spec['enum']).'].',
                ['field' => $field]
            );
        }

        return null;
    }

    private static function checkSingleType($value, string $type): ?string
    {
        return match ($type) {
            'string' => is_string($value) ? null : 'must be a string',
            'array' => is_array($value) ? null : 'must be an array',
            'int' => self::isIntLike($value) ? null : 'must be an integer',
            'bool' => (is_bool($value) || in_array($value, [0, 1, '0', '1'], true)) ? null : 'must be true/false',
            default => 'has an unknown schema type',
        };
    }

    private static function isIntLike($value): bool
    {
        if (is_int($value)) {
            return true;
        }

        return is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1;
    }
}
