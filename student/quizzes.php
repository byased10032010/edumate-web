<?php
/**
 * EduMate - Student: Quizzes
 *  - GET ?id=N         -> show a single quiz, allow submission
 *  - GET (no id)       -> list all quizzes
 *  - POST (submit)     -> score it, save attempt, redirect to results
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dashboard.php';
require_role('student');

$user = current_user();
$uid  = (int)$user['id'];
$quizId = (int)($_GET['id'] ?? 0);

// POST: submit answers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit') {
    $token = $_POST['csrf_token'] ?? '';
    $qid   = (int)($_POST['quiz_id'] ?? 0);
    if (!csrf_check(is_string($token) ? $token : null) || $qid <= 0) {
        flash_set('error', 'Invalid submission.');
        header('Location: quizzes.php');
        exit;
    }
    $pdo = db();
    $correct = 0; $total = 0;
    $qs = $pdo->prepare('SELECT id, correct FROM quiz_questions WHERE quiz_id = :q');
    $qs->execute([':q' => $qid]);
    $responses = [];
    foreach ($qs as $q) {
        $total++;
        $given = $_POST['q'][$q['id']] ?? '';
        $responses[$q['id']] = $given;
        if ($given === $q['correct']) $correct++;
    }
    $responsesJson = json_encode($responses);
    $pdo->prepare('INSERT INTO quiz_attempts (student_id, quiz_id, score, total, responses) VALUES (:u, :q, :s, :t, :r)')
        ->execute([':u' => $uid, ':q' => $qid, ':s' => $correct, ':t' => $total, ':r' => $responsesJson]);
    record_activity($uid, 'fa-clipboard-check', "Completed a quiz: scored {$correct}/{$total}", null);
    flash_set($correct === $total ? 'success' : 'info', "You scored {$correct} out of {$total}.");
    header('Location: quiz_result.php?quiz_id=' . $qid);
    exit;
}

if ($quizId > 0) {
    $pdo = db();
    $quizStmt = $pdo->prepare(
        'SELECT q.*, COALESCE(c.title, \'AI Generated Quiz\') AS course_title
           FROM quizzes q LEFT JOIN courses c ON c.id = q.course_id
          WHERE q.id = :q AND (q.course_id IS NOT NULL OR q.created_by = :u)'
    );
    $quizStmt->execute([':q' => $quizId, ':u' => $uid]);
    $quiz = $quizStmt->fetch();
    if (!$quiz) {
        flash_set('error', 'Quiz not found.');
        header('Location: quizzes.php');
        exit;
    }
    $qs = $pdo->prepare('SELECT * FROM quiz_questions WHERE quiz_id = :q ORDER BY sort_order ASC, id ASC');
    $qs->execute([':q' => $quizId]);
    $questions = $qs->fetchAll();

    render_dashboard_header(
        $quiz['title'],
        student_nav(),
        'quizzes',
        ['Home', 'Quizzes', $quiz['title']]
    );
    ?><style>
body[data-page] .sidebar{padding:1.25rem 1rem;gap:.35rem} body[data-page] .sidebar .nav-item{min-height:42px;border-radius:12px} body[data-page] .nav-item.active{background:color-mix(in srgb,var(--gradient-primary) 13%,var(--bg-card));color:var(--text-primary)}
body[data-page] .main{width:min(100%,1680px);margin-inline:auto;padding:clamp(1rem,2.2vw,2rem) clamp(1rem,2.4vw,2.25rem) 3rem} body[data-page] .topbar{align-items:flex-start;padding-bottom:1rem;border-bottom:1px solid var(--border-color);margin-bottom:1.35rem} body[data-page] .topbar h1{font-size:clamp(1.45rem,2vw,1.9rem);line-height:1.25;overflow-wrap:break-word;min-width:0}
body[data-page] .cards{gap:1rem;margin-bottom:1.25rem} body[data-page] .card{min-height:142px;padding:1.25rem} body[data-page] .card .label{font-size:.75rem;letter-spacing:0;text-transform:none} body[data-page] .card .value{font-size:clamp(1.6rem,2.4vw,2rem);line-height:1.15}
body[data-page] .panel-grid{gap:1.1rem} body[data-page] .panel{padding:clamp(1rem,1.8vw,1.5rem)} body[data-page] .panel h2{margin-bottom:1rem;font-size:1.12rem;line-height:1.35} body[data-page] .panel h2 .sub{display:inline-block}
body[data-page] .grid-cards{gap:1rem} body[data-page] .tile{min-width:0;height:100%} body[data-page] .tile .footer{gap:.6rem;flex-wrap:wrap} body[data-page] .btn{min-height:40px;max-width:100%} body[data-page] .form-control{min-height:46px}
body[data-page] .simple th{text-transform:none;letter-spacing:0;font-size:.78rem} body[data-page] :where(a,button,input,select,textarea):focus-visible{outline:3px solid var(--gradient-primary);outline-offset:3px}
@media(max-width:760px){body[data-page] .main{padding:1rem 1rem 2rem} body[data-page] .panel-grid{grid-template-columns:minmax(0,1fr)} body[data-page] .panel-grid>*{min-width:0} body[data-page] .panel{min-width:0;overflow-x:auto} body[data-page] .cards{grid-template-columns:repeat(2,minmax(0,1fr))} body[data-page] table.simple{display:table;width:max-content;min-width:100%}}
@media(max-width:480px){body[data-page] .cards{grid-template-columns:minmax(0,1fr)} body[data-page] .panel{padding:1rem}}
</style>
    <section class="panel">
        <h2><?php echo htmlspecialchars($quiz['title'], ENT_QUOTES); ?>
            <span class="sub"><?php echo htmlspecialchars($quiz['subject'], ENT_QUOTES); ?> · <?php echo count($questions); ?> questions</span>
        </h2>
        <p style="color:var(--text-secondary); margin-bottom:14px;"><?php echo htmlspecialchars($quiz['description'], ENT_QUOTES); ?></p>

        <form method="POST" action="quizzes.php?id=<?php echo $quizId; ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="submit">
            <input type="hidden" name="quiz_id" value="<?php echo $quizId; ?>">
            <div style="display:flex; flex-direction:column; gap:14px;">
                <?php foreach ($questions as $i => $q): ?>
                    <div class="form-group">
                        <label><?php echo ($i + 1) . '. ' . htmlspecialchars($q['prompt'], ENT_QUOTES); ?></label>
                        <div style="display:flex; flex-direction:column; gap:6px;">
                            <?php foreach (['a' => 'option_a', 'b' => 'option_b', 'c' => 'option_c', 'd' => 'option_d'] as $letter => $col): ?>
                                <label style="display:flex; gap:8px; align-items:center; font-weight:500; cursor:pointer; padding:8px 12px; border:1px solid var(--border-color); border-radius:10px;">
                                    <input type="radio" name="q[<?php echo (int)$q['id']; ?>]" value="<?php echo $letter; ?>" required>
                                    <span><?php echo htmlspecialchars($q[$col], ENT_QUOTES); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:16px; display:flex; gap:8px;">
                <button class="btn btn-primary" type="submit"><ion-icon name="send-outline"></ion-icon> Submit answers</button>
                <a class="btn btn-ghost" href="quizzes.php">Back to quizzes</a>
            </div>
        </form>
    </section>
    <?php
    render_dashboard_footer();
    return;
}

// List view
$pdo = db();
$list = $pdo->prepare(
    'SELECT q.*, COALESCE(c.title, \'AI Generated Quiz\') AS course_title,
            (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = q.id AND qa.student_id = :u) AS attempt_count,
            (SELECT MAX(score) FROM quiz_attempts qa2 WHERE qa2.quiz_id = q.id AND qa2.student_id = :u2) AS best_score,
            (SELECT total FROM quiz_attempts qa3 WHERE qa3.quiz_id = q.id AND qa3.student_id = :u3 ORDER BY qa3.attempted_at DESC LIMIT 1) AS last_total
       FROM quizzes q
       LEFT JOIN courses c ON c.id = q.course_id
       LEFT JOIN enrollments e ON e.course_id = c.id AND e.student_id = :u5
      WHERE (e.student_id IS NOT NULL) OR (q.created_by = :u4)
      ORDER BY q.created_at DESC'
);
$list->execute([':u' => $uid, ':u2' => $uid, ':u3' => $uid, ':u4' => $uid, ':u5' => $uid]);
$quizzes = $list->fetchAll();

render_dashboard_header(
    'Quizzes',
    student_nav(),
    'quizzes',
    ['Home', 'Quizzes']
);
?><style>
body[data-page] .sidebar{padding:1.25rem 1rem;gap:.35rem} body[data-page] .sidebar .nav-item{min-height:42px;border-radius:12px} body[data-page] .nav-item.active{background:color-mix(in srgb,var(--gradient-primary) 13%,var(--bg-card));color:var(--text-primary)}
body[data-page] .main{width:min(100%,1680px);margin-inline:auto;padding:clamp(1rem,2.2vw,2rem) clamp(1rem,2.4vw,2.25rem) 3rem} body[data-page] .topbar{align-items:flex-start;padding-bottom:1rem;border-bottom:1px solid var(--border-color);margin-bottom:1.35rem} body[data-page] .topbar h1{font-size:clamp(1.45rem,2vw,1.9rem);line-height:1.25;overflow-wrap:break-word;min-width:0}
body[data-page] .cards{gap:1rem;margin-bottom:1.25rem} body[data-page] .card{min-height:142px;padding:1.25rem} body[data-page] .card .label{font-size:.75rem;letter-spacing:0;text-transform:none} body[data-page] .card .value{font-size:clamp(1.6rem,2.4vw,2rem);line-height:1.15}
body[data-page] .panel-grid{gap:1.1rem} body[data-page] .panel{padding:clamp(1rem,1.8vw,1.5rem)} body[data-page] .panel h2{margin-bottom:1rem;font-size:1.12rem;line-height:1.35} body[data-page] .panel h2 .sub{display:inline-block}
body[data-page] .grid-cards{gap:1rem} body[data-page] .tile{min-width:0;height:100%} body[data-page] .tile .footer{gap:.6rem;flex-wrap:wrap} body[data-page] .btn{min-height:40px;max-width:100%} body[data-page] .form-control{min-height:46px}
body[data-page] .simple th{text-transform:none;letter-spacing:0;font-size:.78rem} body[data-page] :where(a,button,input,select,textarea):focus-visible{outline:3px solid var(--gradient-primary);outline-offset:3px}
@media(max-width:760px){body[data-page] .main{padding:1rem 1rem 2rem} body[data-page] .panel-grid{grid-template-columns:minmax(0,1fr)} body[data-page] .panel-grid>*{min-width:0} body[data-page] .panel{min-width:0;overflow-x:auto} body[data-page] .cards{grid-template-columns:repeat(2,minmax(0,1fr))} body[data-page] table.simple{display:table;width:max-content;min-width:100%}}
@media(max-width:480px){body[data-page] .cards{grid-template-columns:minmax(0,1fr)} body[data-page] .panel{padding:1rem}}
</style>
<section class="panel">
    <h2>All Quizzes <span class="sub">Test what you know</span></h2>
    <?php if (empty($quizzes)): ?>
        <div class="empty"><div class="ico"><ion-icon name="help-circle-outline"></ion-icon></div><h3>No quizzes available</h3><p>Once your teachers publish quizzes, they'll show up here.</p></div>
    <?php else: ?>
        <div class="grid-cards">
            <?php foreach ($quizzes as $q): ?>
                <article class="tile">
                    <div class="cover" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                        <ion-icon name="help-circle-outline"></ion-icon>
                        <span class="pill"><?php echo htmlspecialchars($q['subject'], ENT_QUOTES); ?></span>
                    </div>
                    <div class="body">
                        <h3><?php echo htmlspecialchars($q['title'], ENT_QUOTES); ?></h3>
                        <p><?php echo htmlspecialchars($q['description'], ENT_QUOTES); ?></p>
                        <div class="meta">
                            <span><ion-icon name="book-outline"></ion-icon> <?php echo htmlspecialchars($q['course_title'], ENT_QUOTES); ?></span>
                            <span><ion-icon name="help-circle-outline"></ion-icon> <?php echo (int)$q['attempt_count']; ?> attempt<?php echo $q['attempt_count'] == 1 ? '' : 's'; ?></span>
                            <?php if ($q['best_score'] !== null): ?>
                                <span><ion-icon name="help-circle-outline"></ion-icon> Best: <?php echo (int)$q['best_score']; ?>/<?php echo (int)$q['last_total']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="footer">
                        <a class="btn btn-primary btn-sm" href="quizzes.php?id=<?php echo (int)$q['id']; ?>">
                            <?php echo $q['attempt_count'] > 0 ? 'Retake' : 'Start'; ?>
                        </a>
                        <a class="btn btn-ghost btn-sm" href="quiz_result.php?quiz_id=<?php echo (int)$q['id']; ?>">History</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php render_dashboard_footer(); ?>
