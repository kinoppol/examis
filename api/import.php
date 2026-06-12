<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';

$user = api_user(['teacher']);
if (req_method() !== 'POST') json_fail('Method not allowed', 405);

$b       = req_body();
$paperId = (int)($b['paper_id'] ?? 0);
$text    = trim((string)($b['text'] ?? ''));
$action  = $b['action'] ?? 'parse'; // 'parse' | 'import'

if (!$text) json_fail('No text provided');

// ── Parse text into question objects ──────────────────────────────────────────
function parseImportText(string $txt): array
{
    $blocks = preg_split('/\n\s*\n/', $txt);
    $out = [];
    foreach ($blocks as $block) {
        $lines = array_filter(array_map('trim', explode("\n", trim($block))));
        $lines = array_values($lines);
        if (!$lines) continue;
        if (!preg_match('/^(\d+)[.).]?\s*(.+)$/', $lines[0], $qm)) continue;
        $choices = [];
        for ($i = 1; $i < count($lines); $i++) {
            $l = $lines[$i]; $correct = false;
            if (str_starts_with($l, '*')) { $correct = true; $l = ltrim(substr($l, 1)); }
            if (preg_match('/^([ก-ฮa-dA-D])[.).]?\s*(.*)$/u', $l, $cm)) {
                $choices[] = ['label' => $cm[1], 'text' => $cm[2], 'correct' => $correct];
            }
        }
        if ($choices) $out[] = ['num' => $qm[1], 'text' => $qm[2], 'choices' => $choices];
    }
    return $out;
}

$parsed = parseImportText($text);
if ($action === 'parse') {
    json_ok(['questions' => $parsed, 'count' => count($parsed)]);
}

// ── Import into DB ────────────────────────────────────────────────────────────
if ($action === 'import') {
    if (!$paperId) json_fail('Missing paper_id');
    $paper = Database::one('SELECT id FROM exam_papers WHERE id = ? AND created_by = ?', [$paperId, $user['id']]);
    if (!$paper) json_fail('Forbidden', 403);

    $maxOrder = (int)Database::value('SELECT COALESCE(MAX(order_num),0) FROM questions WHERE paper_id = ?', [$paperId]);

    $qIns = Database::pdo()->prepare(
        'INSERT INTO questions (paper_id,question_text,order_num,question_type,points) VALUES (?,?,?,\'multiple_choice\',1)'
    );
    $cIns = Database::pdo()->prepare(
        'INSERT INTO choices (question_id,label,choice_text,is_correct,order_num) VALUES (?,?,?,?,?)'
    );

    $imported = 0;
    foreach ($parsed as $q) {
        $qIns->execute([$paperId, $q['text'], $maxOrder + $imported + 1]);
        $qId = (int)Database::pdo()->lastInsertId();
        foreach ($q['choices'] as $oi => $c) {
            $cIns->execute([$qId, $c['label'], $c['text'], $c['correct'] ? 1 : 0, $oi + 1]);
        }
        $imported++;
    }

    Database::run('UPDATE exam_papers SET updated_at = NOW() WHERE id = ?', [$paperId]);
    json_ok(['imported' => $imported]);
}

json_fail('Unknown action');
