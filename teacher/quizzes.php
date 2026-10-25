<?php
/**
 * EduMate - Teacher: Quizzes (CRUD list)
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dashboard.php';
require_role('teacher');

$user = current_user();
$uid  = (int)$user['id'];
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';
    if (!csrf_check(is_string($token) ? $token : null)) {
        flash_set('error', 'Session expired.');
        header('Location: quizzes.php'); exit;
    }

    if ($action === 'delete') {
        $qidPost = (int)($_POST['quiz_id'] ?? 0);
        $ownQ = $pdo->prepare('SELECT 1 FROM quizzes WHERE id = :id AND created_by = :u');
        $ownQ->execute([':id' => $qidPost, ':u' => $uid]);
        if (!$ownQ->fetch()) flash_set('error', 'Quiz not found.');
        else {
            $pdo->prepare('DELETE FROM quizzes WHERE id = :id AND created_by = :u')->execute([':id' => $qidPost, ':u' => $uid]);
            flash_set('success', 'Quiz removed.');
        }
    }
    header('Location: quizzes.php');
    exit;
}

// List view
$list = $pdo->prepare(
    'SELECT q.*, c.title AS course_title,
            (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS question_count,
            (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = q.id) AS attempt_count
       FROM quizzes q
       JOIN courses c ON c.id = q.course_id
      WHERE q.created_by = :u ORDER BY q.created_at DESC'
);
$list->execute([':u' => $uid]);
$quizzes = $list->fetchAll();

$myCourses = $pdo->prepare('SELECT id, title FROM courses WHERE teacher_id = :u ORDER BY title');
$myCourses->execute([':u' => $uid]);
$myCourses = $myCourses->fetchAll();

render_dashboard_header(
    'Quizzes',
    teacher_nav(),
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
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
        <h2 style="margin:0;">All Quizzes</h2>
        <?php if (!empty($myCourses)): ?>
            <a href="quiz_edit.php" class="btn btn-primary"><ion-icon name="add-outline"></ion-icon> New Quiz</a>
        <?php endif; ?>
    </div>

    <?php if (empty($quizzes)): ?>
        <div class="empty">
            <div class="ico"><ion-icon name="clipboard-outline"></ion-icon></div>
            <h3>No quizzes yet</h3>
            <p>Create a quiz to assess your students.</p>
        </div>
    <?php else: ?>
        <table class="simple">
            <thead><tr><th>Title</th><th>Course</th><th>Questions</th><th>Attempts</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($quizzes as $q): ?>
                    <tr>
                        <td><a href="quiz_edit.php?id=<?php echo (int)$q['id']; ?>" style="font-weight:600;"><?php echo htmlspecialchars($q['title'], ENT_QUOTES); ?></a></td>
                        <td><?php echo htmlspecialchars($q['course_title'], ENT_QUOTES); ?></td>
                        <td><span class="tag"><?php echo (int)$q['question_count']; ?></span></td>
                        <td><span class="tag"><?php echo (int)$q['attempt_count']; ?></span></td>
                        <td style="display: flex; gap: 8px; align-items: center; justify-content: flex-end;">
                            <a class="btn btn-ghost btn-sm" href="quiz_edit.php?id=<?php echo (int)$q['id']; ?>"><ion-icon name="create-outline"></ion-icon> Edit</a>
                            <form method="POST" action="quizzes.php" data-confirm="Delete this quiz? This will also delete all student attempts!" style="display:inline;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="quiz_id" value="<?php echo (int)$q['id']; ?>">
                                <button class="btn btn-ghost btn-sm" type="submit" style="color:#b21f3a;"><ion-icon name="trash-outline"></ion-icon></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php render_dashboard_footer(); ?>
