<?php
/**
 * EduMate - Student: My Courses
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dashboard.php';
require_role('student');

$user = current_user();
$uid  = (int)$user['id'];

// Handle enroll / unenroll POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';
    $courseId = (int)($_POST['course_id'] ?? 0);
    if (!csrf_check(is_string($token) ? $token : null) || $courseId <= 0) {
        flash_set('error', 'Invalid request.');
    } elseif ($action === 'enroll') {
        try {
            db()->prepare(
                'INSERT IGNORE INTO enrollments (student_id, course_id) VALUES (:u, :c)'
            )->execute([':u' => $uid, ':c' => $courseId]);
            record_activity($uid, 'fa-book-open', 'Enrolled in a new course', null);
            flash_set('success', 'Enrolled! You can find the course in your dashboard.');
        } catch (Throwable $e) {
            flash_set('error', 'Could not enroll: ' . $e->getMessage());
        }
    } elseif ($action === 'unenroll') {
        try {
            db()->prepare(
                'DELETE FROM enrollments WHERE student_id = :u AND course_id = :c'
            )->execute([':u' => $uid, ':c' => $courseId]);
            flash_set('success', 'You have been unenrolled from the course.');
        } catch (Throwable $e) {
            flash_set('error', 'Could not unenroll. Please try again.');
        }
    }
    header('Location: courses.php');
    exit;
}

$filter = $_GET['filter'] ?? 'enrolled';
if (!in_array($filter, ['enrolled', 'all', 'available'], true)) {
    $filter = 'enrolled';
}

$pdo = db();

// Fetch student details for default filters
$uStmt = $pdo->prepare('SELECT class, board FROM users WHERE id = :id');
$uStmt->execute([':id' => $uid]);
$uInfo = $uStmt->fetch();
$studentClass = $uInfo['class'] ?? null;
$studentBoard = $uInfo['board'] ?? null;

$filterClass = isset($_GET['class']) ? ($_GET['class'] === 'all' ? 'all' : (int)$_GET['class']) : 'default';
$filterBoard = isset($_GET['board']) ? ($_GET['board'] === 'all' ? 'all' : trim((string)$_GET['board'])) : 'default';

$selectedClass = ($filterClass === 'default') ? $studentClass : (($filterClass === 'all') ? null : $filterClass);
$selectedBoard = ($filterBoard === 'default') ? $studentBoard : (($filterBoard === 'all') ? null : $filterBoard);

$whereClause = '';
$params = [':u' => $uid];

if ($selectedClass !== null) {
    $whereClause .= ' AND c.class = :class';
    $params[':class'] = $selectedClass;
}
if ($selectedBoard !== null && $selectedBoard !== '') {
    $whereClause .= ' AND c.board = :board';
    $params[':board'] = $selectedBoard;
}

if ($filter === 'enrolled') {
    $stmt = $pdo->prepare(
        'SELECT c.*, e.progress, e.last_active, t.full_name AS teacher_name, 1 AS is_enrolled
           FROM enrollments e
           JOIN courses c ON c.id = e.course_id
           JOIN users t    ON t.id = c.teacher_id
          WHERE e.student_id = :u AND c.is_published = 1' . $whereClause . '
          ORDER BY e.last_active DESC, e.enrolled_at DESC'
    );
    $stmt->execute($params);
} elseif ($filter === 'available') {
    $stmt = $pdo->prepare(
        'SELECT c.*, 0 AS progress, NULL AS last_active, t.full_name AS teacher_name, 0 AS is_enrolled
           FROM courses c
           JOIN users t ON t.id = c.teacher_id
          WHERE c.is_published = 1
            AND c.id NOT IN (SELECT course_id FROM enrollments WHERE student_id = :u)' . $whereClause . '
          ORDER BY c.created_at DESC'
    );
    $stmt->execute($params);
} else { // all
    $stmt = $pdo->prepare(
        'SELECT c.*, COALESCE(e.progress, 0) AS progress, e.last_active, t.full_name AS teacher_name, (e.course_id IS NOT NULL) AS is_enrolled
           FROM courses c
           JOIN users t ON t.id = c.teacher_id
           LEFT JOIN enrollments e ON e.course_id = c.id AND e.student_id = :u
          WHERE c.is_published = 1' . $whereClause . '
          ORDER BY c.created_at DESC'
    );
    $stmt->execute($params);
}
$courses = $stmt->fetchAll();

render_dashboard_header(
    'My Courses',
    student_nav(),
    'courses',
    ['Home', 'My Courses']
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
    <form method="GET" action="courses.php" class="course-filters">
        <strong>Filter courses</strong>
        <select name="filter" class="form-control" data-auto-submit>
            <option value="enrolled"  <?php echo $filter === 'enrolled'  ? 'selected' : ''; ?>>My Enrolled Courses</option>
            <option value="available" <?php echo $filter === 'available' ? 'selected' : ''; ?>>Available to Join</option>
            <option value="all"       <?php echo $filter === 'all'       ? 'selected' : ''; ?>>All Courses</option>
        </select>
        
        <select name="class" class="form-control" data-auto-submit>
            <option value="all" <?php echo $filterClass === 'all' ? 'selected' : ''; ?>>All Classes</option>
            <?php for ($c = 5; $c <= 10; $c++): ?>
                <option value="<?php echo $c; ?>" <?php echo (($filterClass !== 'all' && $filterClass !== 'default' && (int)$filterClass === $c) || ($filterClass === 'default' && $studentClass === $c)) ? 'selected' : ''; ?>>Class <?php echo $c; ?></option>
            <?php endfor; ?>
        </select>

        <select name="board" class="form-control" data-auto-submit>
            <option value="all" <?php echo $filterBoard === 'all' ? 'selected' : ''; ?>>All Boards</option>
            <?php foreach (get_all_boards() as $b): ?>
                <option value="<?php echo htmlspecialchars($b, ENT_QUOTES); ?>" <?php echo (($filterBoard !== 'all' && $filterBoard !== 'default' && $filterBoard === $b) || ($filterBoard === 'default' && $studentBoard === $b)) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b, ENT_QUOTES); ?></option>
            <?php endforeach; ?>
        </select>

        <span class="course-count"><?php echo count($courses); ?> course<?php echo count($courses) === 1 ? '' : 's'; ?></span>
    </form>

    <?php if (empty($courses)): ?>
        <div class="empty">
            <div class="ico"><ion-icon name="book-outline"></ion-icon></div>
            <h3>Nothing here yet</h3>
            <p>Try switching the filter to "Available to Join".</p>
        </div>
    <?php else: ?>
        <div class="grid-cards">
            <?php foreach ($courses as $c): ?>
                <article class="tile">
                    <div class="cover" style="background:linear-gradient(135deg, <?php echo htmlspecialchars($c['cover_color'], ENT_QUOTES); ?>, #1a1a2e);">
                        <ion-icon name="book-outline"></ion-icon>
                        <span class="pill"><?php echo htmlspecialchars($c['subject'], ENT_QUOTES); ?></span>
                    </div>
                    <div class="body">
                        <h3><?php echo htmlspecialchars($c['title'], ENT_QUOTES); ?></h3>
                        <p><?php echo htmlspecialchars($c['description'], ENT_QUOTES); ?></p>
                        <div class="meta">
                            <span><ion-icon name="help-circle-outline"></ion-icon> <?php echo htmlspecialchars($c['teacher_name'], ENT_QUOTES); ?></span>
                            <span><ion-icon name="cellular-outline"></ion-icon> <?php echo htmlspecialchars(ucfirst($c['level']), ENT_QUOTES); ?></span>
                            <?php if ($c['class']): ?>
                                <span><ion-icon name="school-outline"></ion-icon> Class <?php echo (int)$c['class']; ?></span>
                            <?php endif; ?>
                            <?php if ($c['board']): ?>
                                <span><ion-icon name="business-outline"></ion-icon> <?php echo htmlspecialchars($c['board'], ENT_QUOTES); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ((int)$c['progress'] > 0 || $filter !== 'available'): ?>
                            <div class="progress"><span style="width: <?php echo (int)$c['progress']; ?>%"></span></div>
                            <div class="meta"><span><?php echo (int)$c['progress']; ?>% complete</span></div>
                        <?php endif; ?>
                    </div>
                    <div class="footer">
                        <?php if (!empty($c['is_enrolled'])): ?>
                            <a class="btn btn-primary btn-sm" href="course.php?id=<?php echo (int)$c['id']; ?>">Open</a>
                            <form method="POST" action="courses.php" data-confirm="Unenroll from this course?" style="display:inline;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="unenroll">
                                <input type="hidden" name="course_id" value="<?php echo (int)$c['id']; ?>">
                                <button class="btn btn-ghost btn-sm" type="submit">Unenroll</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="courses.php" style="display:inline;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="enroll">
                                <input type="hidden" name="course_id" value="<?php echo (int)$c['id']; ?>">
                                <button class="btn btn-primary btn-sm" type="submit">Enroll</button>
                            </form>
                            <a class="btn btn-ghost btn-sm" href="course.php?id=<?php echo (int)$c['id']; ?>">Preview</a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<style>
.course-filters { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)) auto; align-items:center; gap:.75rem; margin-bottom:1.25rem; }
.course-filters > strong { grid-column:1 / -1; color:var(--text-secondary); font-size:.9rem; }
.course-filters .form-control { width:100%; min-width:0; }
.course-count { justify-self:end; color:var(--text-light); font-size:.85rem; white-space:nowrap; }
@media (max-width:760px) { .course-filters { grid-template-columns:repeat(2, minmax(0, 1fr)); } .course-count { justify-self:start; } }
@media (max-width:480px) { .course-filters { grid-template-columns:minmax(0, 1fr); } }
</style>

<?php render_dashboard_footer(); ?>
