<?php

namespace App\Services\Notes;

use App\Models\Note;
use App\Models\NoteSubject;
use App\Services\AiProviderService;

class NoteAiService
{
    public const MODES = ['summary', 'decisions', 'tasks', 'timeline', 'report'];

    public function __construct(private AiProviderService $providers) {}

    public function isConfigured($user): bool
    {
        return $this->providers->isConfigured($user);
    }

    public function resolvedLabel($user): ?string
    {
        $r = $this->providers->resolve($user);

        return $r ? ($r['provider'].' / '.$r['model']) : null;
    }

    /**
     * Single sync completion across provider types. Messages are
     * OpenAI-shaped: [['role'=>'system|user','content'=>'...']].
     *
     * @throws \Exception
     */
    public function complete($user, array $messages, int $maxTokens = 2500): string
    {
        $resolved = $this->providers->resolve($user);

        if (! $resolved) {
            throw new \Exception('No AI provider is configured. Add an API key in AI Settings first.');
        }

        $type = $resolved['type'] ?? 'openai';

        return match ($type) {
            'gemini' => $this->callGemini($resolved, $messages, $maxTokens),
            'anthropic' => $this->callAnthropic($resolved, $messages, $maxTokens),
            default => $this->callOpenAi($resolved, $messages, $maxTokens),
        };
    }

    private function callOpenAi(array $resolved, array $messages, int $maxTokens): string
    {
        $endpoint = $this->providers->endpointFor($resolved['config']['base_url'], 'openai');
        $payload = $this->providers->openAiPayload($messages, $resolved['model']);
        $payload['max_tokens'] = $maxTokens;

        $res = $this->providers->postJson($endpoint, $payload, [
            'Authorization' => 'Bearer '.$resolved['key'],
        ], 90);

        if ($res->failed()) {
            throw new \Exception($this->providers->formatErrorResponse($res));
        }

        $text = trim((string) ($res->json('choices.0.message.content') ?? ''));

        if ($text === '') {
            throw new \Exception('Empty response from provider.');
        }

        return $text;
    }

    private function callGemini(array $resolved, array $messages, int $maxTokens): string
    {
        $system = '';
        $contents = [];
        foreach ($messages as $m) {
            if (($m['role'] ?? '') === 'system') {
                $system .= (string) ($m['content'] ?? '')."\n";
                continue;
            }
            $contents[] = [
                'role' => ($m['role'] ?? 'user') === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => (string) ($m['content'] ?? '')]],
            ];
        }

        $body = [
            'contents' => $contents,
            'generationConfig' => ['maxOutputTokens' => $maxTokens, 'temperature' => 0.3],
        ];
        if (trim($system) !== '') {
            $body['systemInstruction'] = ['parts' => [['text' => trim($system)]]];
        }

        $url = rtrim($resolved['config']['base_url'], '/').'/'.$resolved['model'].':generateContent?key='.$resolved['key'];
        $res = $this->providers->postJson($url, $body, [], 90);

        if ($res->failed()) {
            throw new \Exception($this->providers->formatErrorResponse($res));
        }

        $parts = $res->json('candidates.0.content.parts') ?? [];
        $text = trim(implode("\n", array_map(fn ($p) => (string) ($p['text'] ?? ''), is_array($parts) ? $parts : [])));

        if ($text === '') {
            throw new \Exception('Empty response from provider.');
        }

        return $text;
    }

    private function callAnthropic(array $resolved, array $messages, int $maxTokens): string
    {
        $system = '';
        $out = [];
        foreach ($messages as $m) {
            if (($m['role'] ?? '') === 'system') {
                $system .= (string) ($m['content'] ?? '')."\n";
                continue;
            }
            $out[] = ['role' => $m['role'] === 'assistant' ? 'assistant' : 'user', 'content' => (string) ($m['content'] ?? '')];
        }

        $payload = ['model' => $resolved['model'], 'max_tokens' => $maxTokens, 'messages' => $out];
        if (trim($system) !== '') {
            $payload['system'] = trim($system);
        }

        $res = $this->providers->postJson($resolved['config']['base_url'], $payload, [
            'x-api-key' => $resolved['key'],
            'anthropic-version' => '2023-06-01',
        ], 90);

        if ($res->failed()) {
            throw new \Exception($this->providers->formatErrorResponse($res));
        }

        $blocks = $res->json('content') ?? [];
        $text = trim(implode("\n", array_map(fn ($b) => (string) ($b['text'] ?? ''), is_array($blocks) ? $blocks : [])));

        if ($text === '') {
            throw new \Exception('Empty response from provider.');
        }

        return $text;
    }

    // ── Note context ─────────────────────────────────────────────

    public function contextFor($notes, int $maxChars = 60000): string
    {
        $parts = [];
        $used = 0;
        foreach ($notes as $note) {
            $block = '### '.$note->title.'  ['.$note->kind.' · '.($note->effectiveDate()?->toDateString() ?? '?').']'."\n"
                .($note->summary ? ('> '.$note->summary."\n") : '')
                .((string) $note->content);
            $len = mb_strlen($block);
            if ($used + $len > $maxChars) {
                break;
            }
            $parts[] = $block;
            $used += $len;
        }

        return implode("\n\n---\n\n", $parts);
    }

    // ── Extraction ───────────────────────────────────────────────

    /**
     * Extract tasks / decisions / summary from one note.
     * Falls back to checkbox parsing when AI is not configured.
     */
    public function extractFromNote($user, Note $note): array
    {
        if (! $this->isConfigured($user)) {
            return array_merge($this->ruleBasedExtract($note), ['ai' => false]);
        }

        $prompt = 'You read ONE personal note (may be Persian, English or mixed). '
            .'Return ONLY valid JSON, no markdown fences, no extra text, with keys: '
            .'"summary" (2-3 sentence string, same language as the note), '
            .'"tasks" (array of {title, due_date (YYYY-MM-DD or null), priority (low|medium|high)}), '
            .'"decisions" (array of {title, detail}), '
            .'"questions" (array of open-question strings). '
            .'Keep tasks concrete and action-shaped. Empty arrays when nothing found.';

        try {
            $raw = $this->complete($user, [
                ['role' => 'system', 'content' => $prompt],
                ['role' => 'user', 'content' => '# '.$note->title."\n\n".(string) $note->content],
            ], 2000);
            $data = $this->parseJson($raw);
        } catch (\Exception $e) {
            return array_merge($this->ruleBasedExtract($note), ['ai' => false, 'error' => $e->getMessage()]);
        }

        return [
            'ai' => true,
            'summary' => (string) ($data['summary'] ?? ''),
            'tasks' => $this->cleanTasks($data['tasks'] ?? []),
            'decisions' => $this->cleanDecisions($data['decisions'] ?? []),
            'questions' => array_values(array_filter(array_map('strval', (array) ($data['questions'] ?? [])))),
        ];
    }

    private function ruleBasedExtract(Note $note): array
    {
        $tasks = [];
        foreach (preg_split('/\r?\n/', (string) $note->content) ?: [] as $line) {
            if (preg_match('/^\s*-\s*\[\s\]\s*(.+)$/u', $line, $m)) {
                $tasks[] = ['title' => trim($m[1]), 'due_date' => null, 'priority' => 'medium'];
            }
        }

        return [
            'summary' => $note->summary ?: $note->excerpt,
            'tasks' => $tasks,
            'decisions' => $note->kind === Note::KIND_DECISION
                ? [['title' => $note->title, 'detail' => $note->excerpt]] : [],
            'questions' => [],
        ];
    }

    private function cleanTasks($tasks): array
    {
        $out = [];
        foreach ((array) $tasks as $t) {
            $title = trim((string) (is_array($t) ? ($t['title'] ?? '') : $t));
            if ($title === '') {
                continue;
            }
            $due = is_array($t) ? ($t['due_date'] ?? null) : null;
            try {
                $due = $due ? \Carbon\Carbon::parse($due)->toDateString() : null;
            } catch (\Exception) {
                $due = null;
            }
            $priority = is_array($t) ? strtolower((string) ($t['priority'] ?? 'medium')) : 'medium';
            if (! in_array($priority, ['low', 'medium', 'high'], true)) {
                $priority = 'medium';
            }
            $out[] = ['title' => mb_substr($title, 0, 255), 'due_date' => $due, 'priority' => $priority];
            if (count($out) >= 20) {
                break;
            }
        }

        return $out;
    }

    private function cleanDecisions($decisions): array
    {
        $out = [];
        foreach ((array) $decisions as $d) {
            $title = trim((string) (is_array($d) ? ($d['title'] ?? '') : $d));
            if ($title === '') {
                continue;
            }
            $out[] = [
                'title' => mb_substr($title, 0, 255),
                'detail' => mb_substr(trim((string) (is_array($d) ? ($d['detail'] ?? '') : '')), 0, 1000),
            ];
            if (count($out) >= 20) {
                break;
            }
        }

        return $out;
    }

    private function parseJson(string $raw): array
    {
        $raw = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($raw)) ?? '');
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $raw = substr($raw, $start, $end - $start + 1);
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    // ── Subject summary ──────────────────────────────────────────

    public function summarizeSubject($user, NoteSubject $subject, $notes): string
    {
        $context = $this->contextFor($notes, 50000);

        return $this->complete($user, [
            ['role' => 'system', 'content' => 'You summarise everything known about one entity (person, organisation or topic) from personal notes. '
                .'Answer in the same language as the notes (Persian if the notes are Persian). '
                .'Use short markdown sections: وضعیت فعلی / نکات مهم / تصمیم‌ها / مسائل باز / اقدام بعدی. Be factual, no invention.'],
            ['role' => 'user', 'content' => 'Entity: '.$subject->name.' ('.$subject->type.")\n\n".$context],
        ], 2000);
    }

    // ── Collection analysis ──────────────────────────────────────

    public function analyzeCollection($user, $notes, string $mode): string
    {
        $mode = in_array($mode, self::MODES, true) ? $mode : 'summary';
        $context = $this->contextFor($notes);

        $instructions = [
            'summary' => 'Write a concise executive summary of these notes (same language as the notes). Sections: خلاصه / نکات کلیدی / روند زمانی.',
            'decisions' => 'List every decision found in these notes plus open questions. Sections: تصمیم‌ها / سوالات باز.',
            'tasks' => 'Extract every actionable task as a checklist with owner/deadline when mentioned. Sections: اقدام‌های بعدی / ددلاین‌ها.',
            'timeline' => 'Reconstruct a chronological timeline of events from these notes, oldest first, one line per event with date.',
            'report' => 'Write a structured report: خلاصه مدیریتی / یافته‌ها / ریسک‌ها و فرصت‌ها / پیشنهاد قدم بعدی.',
        ];

        return $this->complete($user, [
            ['role' => 'system', 'content' => $instructions[$mode].' Answer in the same language as the notes. Markdown only.'],
            ['role' => 'user', 'content' => $context === '' ? '(no notes)' : $context],
        ], 3000);
    }
}
