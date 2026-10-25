<?php
/**
 * EduMate - Student Dashboard (Overview)
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dashboard.php';
require_role('student');

$sessionUser = current_user();
$uid  = (int)$sessionUser['id'];

// Pull fresh user details from database for accurate class & board
$pdo = db();
$userStmt = $pdo->prepare('SELECT * FROM users WHERE id = :u LIMIT 1');
$userStmt->execute([':u' => $uid]);
$dbUser = $userStmt->fetch(PDO::FETCH_ASSOC);
$user = $dbUser ?: $sessionUser;

// Pull stats
$enrolledStmt    = $pdo->prepare('SELECT COUNT(*) FROM enrollments WHERE student_id = :u');
$enrolledStmt->execute([':u' => $uid]);
$enrolledCount   = (int)$enrolledStmt->fetchColumn();

$lessonsDoneStmt = $pdo->prepare('SELECT COUNT(*) FROM lesson_completions WHERE student_id = :u');
$lessonsDoneStmt->execute([':u' => $uid]);
$lessonsDone     = (int)$lessonsDoneStmt->fetchColumn();

$quizzesStmt = $pdo->prepare(
    'SELECT COUNT(*), COALESCE(AVG(score/NULLIF(total,0))*100, 0)
       FROM quiz_attempts WHERE student_id = :u'
);
$quizzesStmt->execute([':u' => $uid]);
$quizRow = $quizzesStmt->fetch(PDO::FETCH_NUM);
$quizzesCount = (int)($quizRow[0] ?? 0);
$avgScore     = (int)round((float)($quizRow[1] ?? 0));

$streakDays = update_streak($uid);

// Enrolled courses (with progress)
$coursesStmt = $pdo->prepare(
    'SELECT c.id, c.title, c.subject, c.cover_color, c.description,
            e.progress, e.last_active
       FROM enrollments e
       JOIN courses c ON c.id = e.course_id
      WHERE e.student_id = :u
      ORDER BY e.last_active DESC, e.enrolled_at DESC
      LIMIT 6'
);
$coursesStmt->execute([':u' => $uid]);
$courses = $coursesStmt->fetchAll();

// Next assignments from courses the student is enrolled in.
$pendingAssignmentsStmt = $pdo->prepare(
    'SELECT a.id, a.title, a.due_date, c.title AS course_title
       FROM assignments a
       JOIN courses c ON c.id = a.course_id
       JOIN enrollments e ON e.course_id = c.id AND e.student_id = :u
       LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = :u2
      WHERE s.id IS NULL
      ORDER BY CASE WHEN a.due_date IS NULL THEN 1 ELSE 0 END, a.due_date ASC, a.created_at DESC
      LIMIT 4'
);
$pendingAssignmentsStmt->execute([':u' => $uid, ':u2' => $uid]);
$pendingAssignments = $pendingAssignmentsStmt->fetchAll();

// Recent activity (last 6 events for the user)
$actStmt = $pdo->prepare(
    'SELECT icon, title, detail, created_at
       FROM activities WHERE user_id = :u
      ORDER BY created_at DESC LIMIT 6'
);
$actStmt->execute([':u' => $uid]);
$activities = $actStmt->fetchAll();

// Dynamic Banner Text based on student profile
$studentClass = !empty($user['class']) ? (int)$user['class'] : null;
$studentBoard = !empty($user['board']) ? trim((string)$user['board']) : '';

if ($studentClass && $studentBoard) {
    $bannerDetailText = 'Revise prescribed subjects &amp; chapters for Class ' . $studentClass . ' (' . htmlspecialchars($studentBoard, ENT_QUOTES) . ' Board) with 3D flip cards.';
} elseif ($studentClass) {
    $bannerDetailText = 'Revise prescribed subjects &amp; chapters for Class ' . $studentClass . ' with 3D flip cards.';
} elseif ($studentBoard) {
    $bannerDetailText = 'Revise prescribed subjects &amp; chapters for ' . htmlspecialchars($studentBoard, ENT_QUOTES) . ' Board with 3D flip cards.';
} else {
    $bannerDetailText = 'Revise prescribed subjects &amp; chapters tailored for your curriculum with 3D flip cards.';
}

render_dashboard_header(
    'Overview',
    student_nav(),
    'overview',
    ['Home', 'Overview'],
    'Dashboard'
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

<section class="panel student-assignment-panel" aria-labelledby="studentAssignmentsHeading">
    <div class="student-assignment-heading">
        <div>
            <h2 id="studentAssignmentsHeading">Next assignments</h2>
            <p>Work posted to your enrolled courses.</p>
        </div>
        <a class="btn btn-ghost btn-sm" href="assignments.php">All assignments <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon></a>
    </div>
    <?php if (empty($pendingAssignments)): ?>
        <p class="student-assignment-empty">You’re caught up on assignment submissions.</p>
    <?php else: ?>
        <ul class="student-assignment-list">
            <?php foreach ($pendingAssignments as $assignment): ?>
                <li>
                    <a href="assignments.php?id=<?php echo (int)$assignment['id']; ?>">
                        <span class="student-assignment-title"><?php echo htmlspecialchars($assignment['title'], ENT_QUOTES); ?></span>
                        <span class="student-assignment-course"><?php echo htmlspecialchars($assignment['course_title'], ENT_QUOTES); ?></span>
                    </a>
                    <span class="student-assignment-due">
                        <?php echo $assignment['due_date'] ? 'Due ' . htmlspecialchars(date('M j', strtotime($assignment['due_date'])), ENT_QUOTES) : 'No due date'; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="cards">
    <div class="card">
        <div class="ico"><ion-icon name="book-outline"></ion-icon></div>
        <div class="label">Enrolled Courses</div>
        <div class="value"><?php echo $enrolledCount; ?></div>
        <div class="delta">Keep going!</div>
    </div>
    <div class="card s2">
        <div class="ico"><ion-icon name="checkmark-circle-outline"></ion-icon></div>
        <div class="label">Lessons Completed</div>
        <div class="value"><?php echo $lessonsDone; ?></div>
        <div class="delta"><?php echo $avgScore; ?>% avg quiz score</div>
    </div>
    <div class="card s3">
        <div class="ico"><ion-icon name="clipboard-outline"></ion-icon></div>
        <div class="label">Quizzes Taken</div>
        <div class="value"><?php echo $quizzesCount; ?></div>
        <div class="delta"><?php echo $avgScore; ?>% average</div>
    </div>
    <div class="card s4">
        <div class="ico"><ion-icon name="flame-outline"></ion-icon></div>
        <div class="label">Streak Days</div>
        <div class="value"><?php echo $streakDays; ?></div>
        <div class="delta">Keep it going</div>
    </div>
</section>

<!-- AI Flashcards Quick Banner -->
<section class="panel student-revision-panel">
    <div class="student-revision-content">
        <div>
            <h2 class="student-revision-title">
                <ion-icon name="card-outline" aria-hidden="true"></ion-icon> AI Interactive Flashcards
            </h2>
            <p class="student-revision-copy"><?php echo $bannerDetailText; ?></p>
        </div>
        <a href="flashcards.php" class="btn btn-primary">
            <ion-icon name="flash-outline"></ion-icon> Start Revision
        </a>
    </div>
</section>

<style>
.student-revision-panel { border-left: 4px solid var(--gradient-primary); box-shadow: var(--shadow-xs); }
.student-revision-content { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; }
.student-revision-title { display:flex; align-items:center; gap:.65rem; margin:0 0 .4rem !important; }
.student-revision-title ion-icon { color:var(--gradient-primary); }
.student-revision-copy { color:var(--text-secondary); font-size:.9rem; margin:0; }
@media (max-width:560px) { .student-revision-content > .btn { width:100%; } }
</style>

<div class="panel-grid">
    <section class="panel">
        <h2>My Courses <span class="sub">Continue where you left off</span></h2>
        <?php if (empty($courses)): ?>
            <div class="empty">
                <div class="ico"><ion-icon name="book-outline"></ion-icon></div>
                <h3>No courses yet</h3>
                <p>Browse the course catalog to start learning.</p>
                <p style="margin-top:10px"><a class="btn btn-primary btn-sm" href="courses.php">Browse courses</a></p>
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
                            <div class="progress" role="progressbar" aria-label="<?php echo htmlspecialchars($c['title'], ENT_QUOTES); ?> course progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo max(0, min(100, (int)$c['progress'])); ?>"><span style="width: <?php echo max(0, min(100, (int)$c['progress'])); ?>%"></span></div>
                            <div class="meta">
                                <span><ion-icon name="cellular-outline"></ion-icon> <?php echo max(0, min(100, (int)$c['progress'])); ?>% complete</span>
                                <?php if (!empty($c['last_active'])): ?>
                                    <span><ion-icon name="time-outline"></ion-icon> <?php echo htmlspecialchars(date('M j', strtotime($c['last_active'])), ENT_QUOTES); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="footer">
                            <a class="btn btn-primary btn-sm" href="course.php?id=<?php echo (int)$c['id']; ?>">Continue</a>
                            <a class="btn btn-ghost btn-sm" href="course.php?id=<?php echo (int)$c['id']; ?>#lessons">Lessons</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Right Sidebar Stack: Timer + Activity -->
    <div style="display: flex; flex-direction: column; gap: 20px;">
        <!-- Study Focus Timer Section (Native Card Theme) -->
        <style>
            .study-timer-card {
                background: var(--bg-card);
                border: 1px solid var(--border-color);
                border-radius: var(--radius-lg, 16px);
                padding: 16px 20px;
                box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,0.05));
                position: relative;
            }
            .timer-mode-pill {
                font-family: 'Poppins', sans-serif;
                background: var(--bg-secondary);
                color: var(--text-secondary);
                border: 1px solid var(--border-color);
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
                display: inline-flex;
                align-items: center;
                gap: 4px;
            }
            .timer-mode-pill:hover {
                background: var(--border-color);
                color: var(--text-primary);
            }
            .timer-mode-pill.active {
                background: linear-gradient(135deg, #6366f1, #4f46e5);
                color: #ffffff;
                border-color: #4f46e5;
                font-weight: 600;
                box-shadow: 0 3px 10px rgba(99, 102, 241, 0.3);
            }
            .timer-range-slider {
                -webkit-appearance: none;
                width: 100%;
                height: 5px;
                border-radius: 4px;
                background: var(--border-color);
                outline: none;
                cursor: pointer;
            }
            .timer-range-slider::-webkit-slider-thumb {
                -webkit-appearance: none;
                width: 16px;
                height: 16px;
                border-radius: 50%;
                background: #6366f1;
                border: 2px solid var(--bg-card);
                box-shadow: 0 0 8px rgba(99, 102, 241, 0.4);
                cursor: pointer;
            }
        </style>

        <section class="study-timer-card">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <ion-icon name="timer-outline" style="color: #6366f1; font-size: 18px;"></ion-icon>
                    <h4 style="margin: 0; font-size: 15px; font-weight: 700; color: var(--text-primary); font-family: 'Bodoni Moda', serif;">Study Focus Timer</h4>
                </div>
                <div style="font-size: 11px; color: var(--text-secondary);">
                    Sessions Today: <strong id="sessions-count" style="color: #10b981;">0</strong>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 16px;">
                <!-- Left: Compact Clock Ring -->
                <div style="display: flex; align-items: center; justify-content: center; position: relative; width: 90px; height: 90px; flex-shrink: 0;">
                    <svg width="90" height="90" viewBox="0 0 90 90" style="transform: rotate(-90deg);">
                        <circle cx="45" cy="45" r="38" stroke="var(--border-color)" stroke-width="5" fill="none" />
                        <circle id="timer-ring" cx="45" cy="45" r="38" stroke="#6366f1" stroke-width="5" fill="none" stroke-linecap="round" stroke-dasharray="238.7" stroke-dashoffset="0" style="transition: stroke-dashoffset 0.5s ease, stroke 0.3s ease;" />
                    </svg>
                    <div style="position: absolute; text-align: center;">
                        <div id="timer-display" style="font-size: 18px; font-weight: 700; color: var(--text-primary); font-family: 'Bodoni Moda', Georgia, serif; letter-spacing: -0.5px; user-select: none;">25:00</div>
                        <div id="timer-label" style="font-size: 9px; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; margin-top: 1px;">Focus</div>
                    </div>
                </div>

                <!-- Right: Compact Presets, Slider & Controls -->
                <div style="flex: 1; display: flex; flex-direction: column; gap: 8px; min-width: 0;">
                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                        <button type="button" class="timer-mode-pill active" data-time="1500" data-label="Focus"><i class="fa-solid fa-bullseye"></i> 25m</button>
                        <button type="button" class="timer-mode-pill" data-time="300" data-label="Break"><i class="fa-solid fa-mug-hot"></i> 5m</button>
                        <button type="button" class="timer-mode-pill" data-time="900" data-label="Break"><i class="fa-solid fa-umbrella-beach"></i> 15m</button>
                    </div>

                    <div style="display: flex; align-items: center; gap: 8px; background: var(--bg-secondary); padding: 5px 10px; border-radius: 8px; border: 1px solid var(--border-color);">
                        <input type="range" id="timer-slider" min="1" max="120" value="25" class="timer-range-slider" style="flex: 1;">
                        <span style="font-size: 11px; color: var(--text-primary); font-weight: 700; white-space: nowrap;"><span id="slider-val-text">25</span>m</span>
                    </div>

                    <div style="display: flex; gap: 6px;">
                        <button type="button" id="timer-toggle-btn" class="btn" style="flex: 1; background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; font-weight: 600; border: none; padding: 6px 12px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: 12px; box-shadow: 0 3px 8px rgba(99, 102, 241, 0.3);">
                            <ion-icon id="timer-toggle-icon" name="play-outline" style="font-size: 13px;"></ion-icon> <span id="timer-toggle-text">Start</span>
                        </button>
                        <button type="button" id="timer-reset-btn" class="btn" style="background: var(--bg-secondary); color: var(--text-secondary); font-weight: 500; border: 1px solid var(--border-color); padding: 6px 10px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; font-size: 12px;">
                            <ion-icon name="refresh-outline" style="font-size: 13px;"></ion-icon> Reset
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <script>
        (function() {
            let totalSeconds = 1500;
            let remainingSeconds = 1500;
            let timerId = null;
            let isRunning = false;
            let currentLabel = "Focus";

            const display = document.getElementById('timer-display');
            const ring = document.getElementById('timer-ring');
            const label = document.getElementById('timer-label');
            const toggleBtn = document.getElementById('timer-toggle-btn');
            const toggleIcon = document.getElementById('timer-toggle-icon');
            const toggleText = document.getElementById('timer-toggle-text');
            const resetBtn = document.getElementById('timer-reset-btn');
            const modeBtns = document.querySelectorAll('.timer-mode-pill');
            const sessionsCountEl = document.getElementById('sessions-count');

            const timerSlider = document.getElementById('timer-slider');
            const sliderValText = document.getElementById('slider-val-text');

            const CIRCUMFERENCE = 238.7;

            const todayKey = 'edumate_focus_sessions_' + new Date().toISOString().slice(0, 10);
            let focusSessions = parseInt(localStorage.getItem(todayKey) || '0', 10);
            if (sessionsCountEl) sessionsCountEl.textContent = focusSessions;

            function updateDisplay() {
                if (!display || !ring) return;
                const mins = Math.floor(remainingSeconds / 60);
                const secs = remainingSeconds % 60;
                display.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
                
                const progress = remainingSeconds / totalSeconds;
                const offset = CIRCUMFERENCE - (progress * CIRCUMFERENCE);
                ring.style.strokeDashoffset = offset;
            }

            function applyMinutesFromSlider(mins) {
                let val = parseInt(mins, 10);
                if (isNaN(val) || val < 1) val = 1;
                if (val > 120) val = 120;

                pauseTimer();
                totalSeconds = val * 60;
                remainingSeconds = totalSeconds;
                if (sliderValText) sliderValText.textContent = val;
                
                updateDisplay();
            }

            if (timerSlider) {
                timerSlider.addEventListener('input', (e) => {
                    applyMinutesFromSlider(e.target.value);
                });
            }

            function playChime() {
                try {
                    const AudioCtx = window.AudioContext || window.webkitAudioContext;
                    if (!AudioCtx) return;
                    const ctx = new AudioCtx();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(587.33, ctx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.3);
                    gain.gain.setValueAtTime(0.3, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 1.2);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start();
                    osc.stop(ctx.currentTime + 1.2);
                } catch(e) {}
            }

            function startTimer() {
                if (isRunning) return;
                isRunning = true;
                if (toggleIcon) toggleIcon.setAttribute('name', 'pause-outline');
                if (toggleText) toggleText.textContent = 'Pause';
                if (toggleBtn) toggleBtn.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';

                timerId = setInterval(() => {
                    if (remainingSeconds > 0) {
                        remainingSeconds--;
                        updateDisplay();
                    } else {
                        pauseTimer();
                        playChime();
                        if (currentLabel.includes('Focus')) {
                            focusSessions++;
                            localStorage.setItem(todayKey, focusSessions);
                            if (sessionsCountEl) sessionsCountEl.textContent = focusSessions;
                            if (window.showToast) {
                                window.showToast('Great job! Focus session complete. Take a break!', 'success', 'Session Complete');
                            }
                        } else {
                            if (window.showToast) {
                                window.showToast('Break time is over! Ready for your next focus session?', 'info', 'Break Ended');
                            }
                        }
                    }
                }, 1000);
            }

            function pauseTimer() {
                isRunning = false;
                if (timerId) {
                    clearInterval(timerId);
                    timerId = null;
                }
                if (toggleIcon) toggleIcon.setAttribute('name', 'play-outline');
                if (toggleText) toggleText.textContent = 'Start';
                if (toggleBtn) toggleBtn.style.background = 'linear-gradient(135deg, #6366f1, #4f46e5)';
            }

            if (toggleBtn) {
                toggleBtn.addEventListener('click', () => {
                    if (isRunning) {
                        pauseTimer();
                    } else {
                        startTimer();
                    }
                });
            }

            if (resetBtn) {
                resetBtn.addEventListener('click', () => {
                    pauseTimer();
                    remainingSeconds = totalSeconds;
                    if (toggleText) toggleText.textContent = 'Start';
                    updateDisplay();
                });
            }

            modeBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    modeBtns.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');

                    pauseTimer();
                    totalSeconds = parseInt(btn.dataset.time, 10);
                    remainingSeconds = totalSeconds;
                    currentLabel = btn.dataset.label;
                    if (label) label.textContent = currentLabel;
                    if (toggleText) toggleText.textContent = 'Start';

                    const m = Math.ceil(totalSeconds / 60);
                    if (timerSlider) timerSlider.value = m;
                    if (sliderValText) sliderValText.textContent = m;

                    if (ring) {
                        if (currentLabel.includes('Focus')) {
                            ring.style.stroke = '#6366f1';
                        } else {
                            ring.style.stroke = '#10b981';
                        }
                    }

                    updateDisplay();
                });
            });

            updateDisplay();
        })();
        </script>
        </section>

        <!-- Recent Activity -->
        <section class="panel">
            <h2>Recent Activity</h2>
            <?php if (empty($activities)): ?>
                <div class="empty">
                    <div class="ico"><ion-icon name="flash-outline"></ion-icon></div>
                    <h3>No activity yet</h3>
                    <p>Start a lesson to see your progress here.</p>
                </div>
            <?php else: ?>
                <div class="activity">
                    <?php
                    $icons = ['fa-1' => '', 'fa-2' => 's2', 'fa-3' => 's3', 'fa-4' => 's4'];
                    $i = 1;
                    foreach ($activities as $a):
                        $cls = $icons['fa-' . (($i % 4) + 1)] ?? '';
                        $i++;
                    ?>
                        <div class="item">
                            <div class="ico <?php echo $cls; ?>"><ion-icon name="<?php echo htmlspecialchars(dashboard_activity_icon_name((string)$a['icon']), ENT_QUOTES); ?>"></ion-icon></div>
                            <div class="body">
                                <strong><?php echo htmlspecialchars($a['title'], ENT_QUOTES); ?></strong>
                                <?php if (!empty($a['detail'])): ?>
                                    <div style="color:var(--text-secondary); font-size:12px;"><?php echo htmlspecialchars($a['detail'], ENT_QUOTES); ?></div>
                                <?php endif; ?>
                                <div class="when"><?php echo htmlspecialchars(date('M j, g:i A', strtotime($a['created_at'])), ENT_QUOTES); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php render_dashboard_footer(); ?>
