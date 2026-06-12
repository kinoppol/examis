<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';

$user   = api_user(['teacher']);
$method = req_method();

// GET — list questions with choices for a paper
if ($method === 'GET') {
    $paperId = req_int('paper_id');
    if (!$paperId) json_fail('Missing paper_id');

    // Ownership check
    $paper = Database::one('SELECT id FROM exam_papers WHERE id = ? AND created_by = ?', [$paperId, $user['id']]);
    if (!$paper) json_fail('Forbidden or not found', 403);

    $questions = Database::all(
        'SELECT * FROM questions WHERE paper_id = ? ORDER BY order_num',
        [$paperId]
    );
    foreach ($questions as &$q) {
        $q['choices'] = Database::all(
            'SELECT * FROM choices WHERE question_id = ? ORDER BY order_num',
            [$q['id']]
        );
    }
    json_ok(['questions' => $questions]);
}

// POST — add question + choices
if ($method === 'POST') {
    $b       = req_body();
    $paperId = (int)($b['paper_id'] ?? 0);
    if (!$paperId || empty($b['question_text'])) json_fail('Missing fields');

    $paper = Database::one('SELECT id FROM exam_papers WHERE id = ? AND created_by = ?', [$paperId, $user['id']]);
    if (!$paper) json_fail('Forbidden', 403);

    $maxOrder = (int)Database::value('SELECT COALESCE(MAX(order_num),0) FROM questions WHERE paper_id = ?', [$paperId]);

    $qId = Database::insert('questions', [
        'paper_id'        => $paperId,
        'question_type'   => $b['question_type']   ?? 'multiple_choice',
        'question_text'   => $b['question_text'],
        'order_num'       => $maxOrder + 1,
        'points'          => (int)($b['points']   ?? 1),
        'shuffle_choices' => (int)($b['shuffle']  ?? 1),
    ]);

    if (!empty($b['choices']) && is_array($b['choices'])) {
        foreach ($b['choices'] as $oi => $c) {
            Database::insert('choices', [
                'question_id' => $qId,
                'label'       => $c['label'],
                'choice_text' => $c['text'],
                'is_correct'  => (int)($c['correct'] ?? 0),
                'order_num'   => $oi + 1,
            ]);
        }
    }

    // Update paper counts
    Database::run(
        'UPDATE exam_papers SET updated_at = NOW() WHERE id = ?',
        [$paperId]
    );

    json_ok(['id' => $qId], 201);
}

// PUT — update correct answer (correctIdx change from builder UI)
if ($method === 'PUT') {
    $qId = req_int('id');
    $b   = req_body();
    if (!$qId) json_fail('Missing id');

    // Verify ownership via paper
    $owns = Database::one(
        'SELECT q.id FROM questions q JOIN exam_papers p ON p.id = q.paper_id
         WHERE q.id = ? AND p.created_by = ?',
        [$qId, $user['id']]
    );
    if (!$owns) json_fail('Forbidden', 403);

    if (isset($b['correct_choice_id'])) {
        // Reset all then set correct
        Database::run('UPDATE choices SET is_correct = 0 WHERE question_id = ?', [$qId]);
        Database::run('UPDATE choices SET is_correct = 1 WHERE id = ? AND question_id = ?',
            [(int)$b['correct_choice_id'], $qId]);
    }
    if (isset($b['question_text'])) {
        Database::update('questions', ['question_text' => $b['question_text']], 'id = ?', [$qId]);
    }
    json_ok();
}

// DELETE — remove question
if ($method === 'DELETE') {
    $qId = req_int('id');
    if (!$qId) json_fail('Missing id');
    $owns = Database::one(
        'SELECT q.id FROM questions q JOIN exam_papers p ON p.id = q.paper_id WHERE q.id = ? AND p.created_by = ?',
        [$qId, $user['id']]
    );
    if (!$owns) json_fail('Forbidden', 403);
    Database::run('DELETE FROM questions WHERE id = ?', [$qId]);
    json_ok();
}

json_fail('Not found', 404);
