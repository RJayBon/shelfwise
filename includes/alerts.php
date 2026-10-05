<?php
declare(strict_types=1);

/**
 * Compute due-date alerts for the signed-in user.
 * Each loan+status fires at most once per session.
 */
function compute_alerts(PDO $pdo, int $user_id): array
{
    $seen   = $_SESSION['alerted'] ?? [];
    $alerts = [];

    $s = $pdo->prepare(
        "SELECT l.loan_id, l.due_at, UNIX_TIMESTAMP(l.due_at)*1000 AS due_ts, b.title,
                CASE WHEN l.due_at < NOW() THEN 'overdue' ELSE 'due-soon' END AS st
         FROM loans l JOIN books b ON b.book_id = l.book_id
         WHERE l.user_id = ? AND l.status = 'active'
           AND l.due_at < NOW() + INTERVAL " . DUE_SOON_DAYS . " DAY
         ORDER BY l.due_at ASC
         LIMIT 5"
    );
    $s->execute([$user_id]);

    foreach ($s->fetchAll() as $l) {
        $key = $l['loan_id'] . ':' . $l['st'];
        if (($seen[$l['loan_id']] ?? null) === $key) continue;
        $seen[$l['loan_id']] = $key;

        $alerts[] = [
            'type'    => $l['st'] === 'overdue' ? 'error' : 'warning',
            'title'   => $l['st'] === 'overdue' ? 'Overdue book' : 'Due soon',
            'message' => '"' . $l['title'] . '" is due ' . date('M j, Y', (int)($l['due_ts'] / 1000)) . '.',
        ];
    }

    $_SESSION['alerted'] = $seen;
    return $alerts;
}