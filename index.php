<?php
session_start();
// Auto-redirect if logged in
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'student';
    $redirect = [
        'admin' => 'admin/dashboard.php',
        'teacher' => 'teacher/dashboard.php',
        'student' => 'student/dashboard.php'
    ];
    header("Location: " . ($redirect[$role] ?? 'student/dashboard.php'));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduMate · Study Smarter Together</title>
    <link rel="apple-touch-icon" sizes="180x180" href="favicon_io/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon_io/favicon-16x16.png">
    <link rel="manifest" href="favicon_io/site.webmanifest">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:wght@600;700&family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=contrast-2">
    <link rel="stylesheet" href="assets/css/interface-refinement.css?v=1">
    <style>
        /* Homepage refinements: keep the existing EduMate palette and type system. */
        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1.12fr) minmax(0, .88fr);
            align-items: center;
            gap: clamp(2.5rem, 6vw, 6rem);
            width: min(100% - 3rem, 1280px);
            margin-inline: auto;
            padding-block: clamp(3rem, 6vw, 5.5rem);
        }
        .hero-content { width: 100%; max-width: 660px; gap: 1.1rem; padding-top: 0; }
        .hero .typewriter {
            max-width: 16ch;
            font-size: clamp(3rem, 5.5vw, 4.65rem);
            line-height: 1.04;
            letter-spacing: -.035em;
            overflow-wrap: break-word;
        }
        .hero-copy { max-width: 52ch; font-size: clamp(1.02rem, 1.35vw, 1.18rem); line-height: 1.75; }
        .study-tabs { display:flex; flex-wrap:wrap; gap:.55rem; }
        .study-tab { min-height:42px; padding:.55rem .95rem; border:1px solid var(--line); border-radius:var(--radius-pill); background:var(--paper); color:var(--muted); font-size:.86rem; font-weight:600; }
        .study-tab.active { background:var(--violet-text); border-color:var(--violet-text); color:var(--paper); }
        .hero-actions { display: flex; flex-wrap: wrap; gap: .8rem; margin-top: .35rem; }
        .hero-actions .play-btn { min-height: 50px; justify-content: center; }
        .streak-row { margin-top:.25rem; }
        .sw-wrapper { width: 100%; max-width: 610px; justify-self: end; }
        .boards-section, .feature-band { width: min(100% - 3rem, 1280px); margin-inline: auto; }
        .boards-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.5rem; }
        .board-card { display: flex; flex-direction: column; height: 100%; }
        .board-card__foot { margin-top: auto; flex-wrap: wrap; gap: .75rem; }
        .feature-band { padding-block: clamp(4rem, 8vw, 7rem); }
        .features-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1.5rem; }
        .features-hero { grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr); gap: clamp(2rem, 5vw, 5rem); align-items: center; margin-bottom:3rem; }
        .features-hero-actions { margin-top: 1.5rem !important; }
        .features-hero-stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.25rem; }
        .fh-divider { display: none; }
        @media (prefers-reduced-motion: reduce) { .hero * { scroll-behavior:auto !important; } }
        @media (max-width: 820px) {
            .hero { grid-template-columns: minmax(0, 1fr); width: min(100% - 2rem, 760px); gap: 2.5rem; }
            .hero-content { max-width: none; }
            .hero .typewriter { max-width: 16ch; font-size: clamp(2.8rem, 9vw, 4.4rem); }
            .sw-wrapper { justify-self: center; }
            .boards-section, .feature-band { width: min(100% - 2rem, 760px); }
            .features-hero { grid-template-columns:minmax(0,1fr); }
            .features-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 560px) {
            .boards-grid, .features-grid, .features-hero-stats { grid-template-columns: minmax(0, 1fr); }
            .hero-actions { align-items: stretch; }
            .hero-actions .play-btn { width: 100%; }
        }
        /* Dose-inspired task grouping and Dusk-inspired restrained emphasis,
           expressed through EduMate's existing palette and type. */
        .hero { align-items: start; padding-block: clamp(3rem, 5vw, 4.5rem); }
        .hero-content { padding-top: clamp(.5rem, 2vw, 1.5rem); }
        .sw-wrapper { max-width: 660px; }
        .sw-decor, .sw-dot, .sw-orb, .sw-topbar__avatars, .sw-topbar__badge, .sw-stats { display: none !important; }
        .study-widget { border: 1px solid var(--line); border-radius: 22px; box-shadow: var(--shadow-soft); }
        .study-widget:hover { transform:none; box-shadow: var(--shadow-soft); }
        .sw-topbar { padding: 1.15rem 1.3rem; border:0; border-bottom:1px solid var(--line); border-radius:0; background:transparent; }
        .sw-topbar__label { font-size: .95rem; }
        .sw-tabs { justify-content: flex-start; gap:1rem; padding:0 1.1rem; border:0; border-bottom:1px solid var(--line); border-radius:0; background:transparent; }
        .sw-tab { flex:0 0 auto; min-height:46px; padding-inline:.2rem; border-radius:0; border-bottom:2px solid transparent; }
        .sw-tab:hover:not(.active) { transform:none; }
        .sw-tab.active { color:var(--violet); background:transparent; border-bottom-color:var(--violet); transform:none; }
        #studyWidget [hidden] { display:none !important; }
        .sw-panel { padding: 1.25rem; }
        .sw-quiz__session { display:flex; justify-content:space-between; gap:1rem; margin:0 0 .85rem; color:var(--muted); font-size:.82rem; font-weight:600; }
        .sw-quiz__progress { height:5px; overflow:hidden; border-radius:var(--radius-pill); background:var(--line); margin-bottom:1.2rem; }
        .sw-quiz__progress span { display:block; height:100%; border-radius:inherit; background:var(--violet); transition:transform .2s ease; transform-origin:left; }
        .sw-quiz__q { font-size:1.15rem; line-height:1.45; }
        .sw-quiz__options { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); }
        .sw-quiz__opt { min-height:48px; text-align:left; }
        .sw-quiz__opt:focus-visible, .sw-tab:focus-visible, .sw-quiz__next:focus-visible, .sw-quiz__restart:focus-visible { outline:3px solid var(--violet); outline-offset:3px; }
        .sw-quiz__feedback { margin-top:.9rem; }
        .sw-quiz__next, .sw-quiz__restart { margin-top:.9rem; }
        .sw-quiz__complete { padding:1.2rem; border:1px solid var(--line); border-radius:16px; background:var(--bg-2); color:var(--ink); }
        .sw-quiz__complete strong { display:block; margin-bottom:.4rem; font-size:1.2rem; }
        .boards-grid { align-items:stretch; }
        .board-card { border:1px solid var(--line); box-shadow:var(--shadow-soft); }
        .features-hero { padding-block:2rem; border-block:1px solid var(--line); }
        .features-hero-actions { margin-top:1.5rem !important; }
        @media (max-width: 560px) {
            .sw-panel { padding:1rem; }
            .sw-quiz__options { grid-template-columns:minmax(0,1fr); }
        }
        @media (prefers-reduced-motion: reduce) {
            .sw-quiz__progress span { transition:none; }
        }
    </style>
</head>
<body class="homepage">
    <!-- Page loader -->
    <div class="page-loader" id="pageLoader" aria-hidden="true">
        <div class="page-loader-inner">
            <div class="loader">
                <svg id="pegtopone" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
                    <defs>
                        <filter id="shine-one"><feGaussianBlur stdDeviation="3"/></filter>
                        <mask id="mask-one"><path d="M63,37c-6.7-4-4-27-13-27s-6.3,23-13,27-27,4-27,13,20.3,9,27,13,4,27,13,27,6.3-23,13-27,27-4,27-13-20.3-9-27-13Z" fill="white"/></mask>
                        <radialGradient id="g1-one" cx="50" cy="66" r="30" gradientTransform="translate(0 35) scale(1 0.5)"><stop offset="0%" stop-color="black" stop-opacity="0.3"/><stop offset="50%" stop-color="black" stop-opacity="0.1"/><stop offset="100%" stop-color="black" stop-opacity="0"/></radialGradient>
                        <radialGradient id="g2-one" cx="55" cy="20" r="30"><stop offset="0%" stop-color="white" stop-opacity="0.3"/><stop offset="50%" stop-color="white" stop-opacity="0.1"/><stop offset="100%" stop-color="white" stop-opacity="0"/></radialGradient>
                    </defs>
                    <g><path d="M63,37c-6.7-4-4-27-13-27s-6.3,23-13,27-27,4-27,13,20.3,9,27,13,4,27,13,27,6.3-23,13-27,27-4,27-13-20.3-9-27-13Z" fill="currentColor"/><path d="M63,37c-6.7-4-4-27-13-27s-6.3,23-13,27-27,4-27,13,20.3,9,27,13,4,27,13,27,6.3-23,13-27,27-4,27-13-20.3-9-27-13Z" fill="url(#g1-one)"/><path d="M63,37c-6.7-4-4-27-13-27s-6.3,23-13,27-27,4-27,13,20.3,9,27,13,4,27,13,27,6.3-23,13-27,27-4,27-13-20.3-9-27-13Z" fill="url(#g2-one)"/></g>
                </svg>
                <svg id="pegtoptwo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
                    <defs>
                        <filter id="shine-two"><feGaussianBlur stdDeviation="3"/></filter>
                        <mask id="mask-two"><path d="M63,37c-6.7-4-4-27-13-27s-6.3,23-13,27-27,4-27,13,20.3,9,27,13,4,27,13,27,6.3-23,13-27,27-4,27-13-20.3-9-27-13Z" fill="white"/></mask>
                        <radialGradient id="g1-two" cx="50" cy="66" r="30" gradientTransform="translate(0 35) scale(1 0.5)"><stop offset="0%" stop-color="black" stop-opacity="0.3"/><stop offset="50%" stop-color="black" stop-opacity="0.1"/><stop offset="100%" stop-color="black" stop-opacity="0"/></radialGradient>
                        <radialGradient id="g2-two" cx="55" cy="20" r="30"><stop offset="0%" stop-color="white" stop-opacity="0.3"/><stop offset="50%" stop-color="white" stop-opacity="0.1"/><stop offset="100%" stop-color="white" stop-opacity="0"/></radialGradient>
                    </defs>
                    <g><path d="M63,37c-6.7-4-4-27-13-27s-6.3,23-13,27-27,4-27,13,20.3,9,27,13,4,27,13,27,6.3-23,13-27,27-4,27-13-20.3-9-27-13Z" fill="currentColor"/><path d="M63,37c-6.7-4-4-27-13-27s-6.3,23-13,27-27,4-27,13,20.3,9,27,13,4,27,13,27,6.3-23,13-27,27-4,27-13-20.3-9-27-13Z" fill="url(#g1-two)"/><path d="M63,37c-6.7-4-4-27-13-27s-6.3,23-13,27-27,4-27,13,20.3,9,27,13,4,27,13,27,6.3-23,13-27,27-4,27-13-20.3-9-27-13Z" fill="url(#g2-two)"/></g>
                </svg>
                <svg id="pegtopthree" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
                    <defs>
                        <filter id="shine-three"><feGaussianBlur stdDeviation="3"/></filter>
                        <mask id="mask-three"><path d="M63,37c-6.7-4-4-27-13-27s-6.3,23-13,27-27,4-27,13,20.3,9,27,13,4,27,13,27,6.3-23,13-27,27-4,27-13-20.3-9-27-13Z" fill="white"/></mask>
                        <radialGradient id="g1-three" cx="50" cy="66" r="30" gradientTransform="translate(0 35) scale(1 0.5)"><stop offset="0%" stop-color="black" stop-opacity="0.3"/><stop offset="50%" stop-color="black" stop-opacity="0.1"/><stop offset="100%" stop-color="black" stop-opacity="0"/></radialGradient>
                        <radialGradient id="g2-three" cx="55" cy="20" r="30"><stop offset="0%" stop-color="white" stop-opacity="0.3"/><stop offset="50%" stop-color="white" stop-opacity="0.1"/><stop offset="100%" stop-color="white" stop-opacity="0"/></radialGradient>
                    </defs>
                    <g><path d="M63,37c-6.7-4-4-27-13-27s-6.3,23-13,27-27,4-27,13,20.3,9,27,13,4,27,13,27,6.3-23,13-27,27-4,27-13-20.3-9-27-13Z" fill="currentColor"/><path d="M63,37c-6.7-4-4-27-13-27s-6.3,23-13,27-27,4-27,13,20.3,9,27,13,4,27,13,27,6.3-23,13-27,27-4,27-13-20.3-9-27-13Z" fill="url(#g1-three)"/><path d="M63,37c-6.7-4-4-27-13-27s-6.3,23-13,27-27,4-27,13,20.3,9,27,13,4,27,13,27,6.3-23,13-27,27-4,27-13-20.3-9-27-13Z" fill="url(#g2-three)"/></g>
                </svg>
            </div>
            <span class="page-loader-label">Loading your study space <i class="fa-solid fa-book-open" style="margin-left:6px; color:var(--violet, #6366f1);"></i></span>
        </div>
    </div>

    <!-- Header -->
    <header class="site-header">
        <nav class="navbar" aria-label="Main navigation">
            <a href="index.php" class="logo" aria-label="EduMate home">
                <span class="logo-mark">
                    <img src="favicon_io/apple-touch-icon.png" alt="EduMate Logo" style="width:100%;height:100%;object-fit:cover;">
                </span>
                <span class="logo-wordmark">EduMate</span>
            </a>
            <div class="nav-links">
                <a href="#boards">Boards</a>
                <a href="#features">Features</a>
                <a href="#faq">FAQs</a>
                <a href="#faq">FAQ</a>
            </div>
            <div class="icon-links">
                <label class="theme-switch" aria-label="Toggle dark mode">
                    <input type="checkbox" class="theme-switch__checkbox">
                    <div class="theme-switch__container">
                        <div class="theme-switch__clouds"></div>
                        <div class="theme-switch__stars-container"><svg viewBox="0 0 144 55" fill="none"><path d="M135.831 3.00688C135.055 3.85027 134.111 4.29946 133 4.35447C134.111 4.40947 135.055 4.85867 135.831 5.71123C136.607 6.55462 136.996 7.56303 136.996 8.72727C136.996 7.95722 137.172 7.25134 137.525 6.59129C137.886 5.93124 138.372 5.39954 138.98 5.00535C139.598 4.60199 140.268 4.39114 141 4.35447C139.88 4.2903 138.936 3.85027 138.16 3.00688C137.384 2.16348 136.996 1.16425 136.996 0C136.996 1.16425 136.607 2.16348 135.831 3.00688Z" fill="currentColor"/></svg></div>
                        <div class="theme-switch__circle-container"><div class="theme-switch__sun-moon-container"><div class="theme-switch__moon"><div class="theme-switch__spot"></div><div class="theme-switch__spot"></div><div class="theme-switch__spot"></div></div></div></div>
                    </div>
                </label>
                <a href="login.php" class="icon-button" aria-label="Login"><i class="fas fa-user"></i></a>
                <a href="register.php" class="play-btn">Start free</a>
            </div>
            <button type="button" class="nav-toggle" aria-label="Open menu" aria-expanded="false">
                <span class="nav-toggle-bar"></span><span class="nav-toggle-bar"></span><span class="nav-toggle-bar"></span>
            </button>
        </nav>
    </header>

    <!-- Mobile drawer -->
    <div id="navOverlay" class="nav-overlay" hidden></div>
    <aside id="mobileDrawer" class="mobile-drawer" aria-label="Mobile menu" aria-hidden="true" inert>
        <div class="mobile-drawer-head">
            <a href="index.php" class="logo"><span class="logo-mark"><img src="favicon_io/apple-touch-icon.png" alt="EduMate Logo" style="width:100%;height:100%;object-fit:cover;"></span><span class="logo-wordmark">EduMate</span></a>
            <button type="button" class="mobile-drawer-close" aria-label="Close menu"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <nav class="mobile-drawer-links">
            <a href="#boards"><i class="fa-solid fa-graduation-cap"></i> Boards</a>
            <a href="#features"><i class="fa-solid fa-wand-magic-sparkles"></i> Features</a>
            <a href="#faq"><i class="fa-solid fa-circle-question" aria-hidden="true"></i> Browse FAQs</a>
            <a href="#faq"><i class="fa-solid fa-circle-question"></i> FAQ</a>
        </nav>
        <div class="mobile-drawer-actions">
            <label class="theme-switch theme-switch--mobile" aria-label="Toggle dark mode">
                <input type="checkbox" class="theme-switch__checkbox">
                <div class="theme-switch__container"><div class="theme-switch__clouds"></div><div class="theme-switch__stars-container"><svg viewBox="0 0 144 55" fill="none"><path d="M135.831 3.00688C135.055 3.85027 134.111 4.29946 133 4.35447C134.111 4.40947 135.055 4.85867 135.831 5.71123C136.607 6.55462 136.996 7.56303 136.996 8.72727C136.996 7.95722 137.172 7.25134 137.525 6.59129C137.886 5.93124 138.372 5.39954 138.98 5.00535C139.598 4.60199 140.268 4.39114 141 4.35447C139.88 4.2903 138.936 3.85027 138.16 3.00688C137.384 2.16348 136.996 1.16425 136.996 0C136.996 1.16425 136.607 2.16348 135.831 3.00688Z" fill="currentColor"/></svg></div><div class="theme-switch__circle-container"><div class="theme-switch__sun-moon-container"><div class="theme-switch__moon"><div class="theme-switch__spot"></div><div class="theme-switch__spot"></div><div class="theme-switch__spot"></div></div></div></div></div>
            </label>
            <a href="login.php" class="mobile-drawer-btn mobile-drawer-btn--ghost"><i class="fas fa-user"></i> Sign in</a>
            <a href="register.php" class="mobile-drawer-btn mobile-drawer-btn--primary"><i class="fa-solid fa-rocket"></i> Start studying</a>
        </div>
    </aside>

    <main>
        <!-- Hero Section -->
        <section class="hero" id="signup">
            <div class="hero-content">
                <p class="eyebrow"><i class="fa-solid fa-book-bookmark"></i> Study smarter, together</p>
                <h1 class="typewriter">
                    Master every board,<br>
                    <span class="typewriter-text"></span><span class="typewriter-caret">▌</span>
                </h1>
                <p class="hero-copy"><strong>EduMate</strong> turns your notes into flashcards, brings classmates together in study rooms, and helps you stay focused. Made for students across India’s major school boards.</p>
                <div class="study-tabs">
                    <button class="study-tab active" type="button"><i class="fa-solid fa-layer-group"></i> Flashcards</button>
                    <button class="study-tab" type="button"><i class="fa-solid fa-headphones"></i> Study Rooms</button>
                    <button class="study-tab" type="button"><i class="fa-solid fa-stopwatch"></i> Focus</button>
                </div>
                <div class="hero-actions">
                    <a href="register.php" class="play-btn play-btn--coral"><i class="fa-solid fa-rocket"></i><span>Start studying free</span></a>
                    <a href="#features" class="play-btn play-btn--ghost"><i class="fa-solid fa-circle-play"></i><span>See how it works</span></a>
                </div>
            </div>
            <!-- Study Widget -->
            <div class="sw-wrapper">
                <div class="study-widget" id="studyWidget" aria-label="Interactive study dashboard">
                    <div class="sw-topbar">
                        <div class="sw-topbar__left">
                            <span class="sw-topbar__label">Try a quick question</span>
                        </div>
                    </div>

                    <div class="sw-tabs" role="tablist">
                        <button class="sw-tab active" data-sw-tab="quiz" id="swTabQuiz" aria-controls="sw-panel-quiz" role="tab" aria-selected="true"><i class="fa-solid fa-brain" aria-hidden="true"></i> Questions</button>
                        <button class="sw-tab" data-sw-tab="flashcard" id="swTabFlashcard" aria-controls="sw-panel-flashcard" role="tab" aria-selected="false"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Flashcards</button>
                        <button class="sw-tab" data-sw-tab="focus" id="swTabFocus" aria-controls="sw-panel-focus" role="tab" aria-selected="false"><i class="fa-solid fa-stopwatch" aria-hidden="true"></i> Focus</button>
                    </div>

                    <!-- Flashcard Panel -->
                    <div class="sw-panel" id="sw-panel-flashcard" role="tabpanel" aria-labelledby="swTabFlashcard" hidden>
                        <div class="sw-flashcard" id="swFlashcard" tabindex="0" aria-label="Flip flashcard">
                            <div class="sw-flashcard__inner">
                                <div class="sw-flashcard__face sw-flashcard__face--front">
                                    <span class="sw-fc-label">Question</span>
                                    <p class="sw-fc-text" id="swFcQuestion">What is Newton's Second Law?</p>
                                    <span class="sw-fc-hint">Click to reveal answer <i class="fa-solid fa-wand-magic-sparkles"></i></span>
                                </div>
                                <div class="sw-flashcard__face sw-flashcard__face--back">
                                    <span class="sw-fc-label">Answer</span>
                                    <p class="sw-fc-text" id="swFcAnswer">F = ma — Force equals mass times acceleration.</p>
                                    <span class="sw-fc-hint">Next card →</span>
                                </div>
                            </div>
                        </div>
                        <div class="sw-fc-controls">
                            <button class="sw-fc-btn" id="swFcPrev" aria-label="Previous card"><i class="fa-solid fa-chevron-left"></i></button>
                            <span class="sw-fc-counter" id="swFcCounter">1 / 5</span>
                            <button class="sw-fc-btn" id="swFcNext" aria-label="Next card"><i class="fa-solid fa-chevron-right"></i></button>
                        </div>
                        <div class="sw-fc-progress">
                            <div class="sw-fc-progress__bar" id="swFcProgressBar" style="width:20%"></div>
                        </div>
                    </div>

                    <!-- Quiz Panel -->
                    <div class="sw-panel" id="sw-panel-quiz" role="tabpanel" aria-labelledby="swTabQuiz">
                        <div class="sw-quiz__session"><span id="swQuizCount">Question 1 of 3</span><span id="swQuizScore">0 correct</span></div>
                        <div class="sw-quiz__progress" id="swQuizProgress" role="progressbar" aria-label="Question session progress" aria-valuemin="0" aria-valuemax="3" aria-valuenow="1"><span id="swQuizProgressBar" style="transform:scaleX(.3333)"></span></div>
                        <p class="sw-quiz__q" id="swQuizQ">Which board follows the NCERT syllabus?</p>
                        <div class="sw-quiz__options" id="swQuizOptions">
                            <button class="sw-quiz__opt" data-correct="false">ICSE</button>
                            <button class="sw-quiz__opt" data-correct="true">CBSE</button>
                            <button class="sw-quiz__opt" data-correct="false">WBBSE</button>
                            <button class="sw-quiz__opt" data-correct="false">Maharashtra Board</button>
                        </div>
                        <div class="sw-quiz__feedback" id="swQuizFeedback" hidden></div>
                        <button class="sw-quiz__next play-btn play-btn--ghost" id="swQuizNext" hidden>Next Question <i class="fa-solid fa-arrow-right"></i></button>
                        <div class="sw-quiz__complete" id="swQuizComplete" hidden aria-live="polite"><strong>Session complete</strong><span id="swQuizResult"></span></div>
                        <button class="sw-quiz__restart play-btn play-btn--ghost" id="swQuizRestart" hidden>Try again</button>
                    </div>

                    <!-- Focus Panel -->
                    <div class="sw-panel" id="sw-panel-focus" role="tabpanel" aria-labelledby="swTabFocus" hidden>
                        <div class="sw-pomodoro">
                            <svg class="sw-pom-ring" viewBox="0 0 120 120" aria-hidden="true">
                                <circle class="sw-pom-track" cx="60" cy="60" r="52"/>
                                <circle class="sw-pom-progress" id="swPomProgress" cx="60" cy="60" r="52"/>
                            </svg>
                            <div class="sw-pom-inner">
                                <span class="sw-pom-time" id="swPomTime">25:00</span>
                                <span class="sw-pom-label" id="swPomLabel">Focus</span>
                            </div>
                        </div>
                        <div class="sw-pom-controls">
                            <button class="sw-pom-btn" id="swPomToggle"><i class="fa-solid fa-play"></i> Start</button>
                            <button class="sw-pom-btn sw-pom-btn--ghost" id="swPomReset"><i class="fa-solid fa-rotate-left"></i> Reset</button>
                        </div>
                        <div class="sw-pom-sessions">
                            <span id="swPomSessionDots"></span>
                            <em>Session <span id="swPomSessionNum">1</span> of 4</em>
                        </div>
                    </div>

                    <div class="sw-burst-layer" id="swBurstLayer" aria-hidden="true"></div>
                </div>
            </div>
        </section>

        <!-- Boards Section -->
        <section class="boards-section reveal" id="boards">
            <div class="section-heading">
                <p class="eyebrow" style="--accent:var(--amber);"><i class="fa-solid fa-graduation-cap"></i> Supported Boards</p>
                <h2>Pick your <span class="highlight highlight--mint">board</span>, start studying.</h2>
                <p class="section-sub">EduMate has curated content, past papers, and study rooms tailored to every major Indian curriculum.</p>
            </div>
            <div class="boards-grid">
                <article class="board-card board-card--icse">
                    <div class="board-card__top">
                        <span class="board-emoji"><i class="fa-solid fa-book-bookmark" style="color:var(--brand-blue, #2563eb);"></i></span>
                        <div>
                            <h3 class="board-card__name">ICSE</h3>
                            <span class="board-card__full">Indian Certificate of Secondary Education</span>
                        </div>
                        <span class="board-badge board-badge--blue">Class 1–10</span>
                    </div>
                    <p class="board-card__desc">Comprehensive English-medium curriculum by CISCE. Strong focus on analytical thinking, literature, and science practicals.</p>
                    <ul class="board-card__subjects">
                        <li><i class="fa-solid fa-flask"></i> Physics, Chemistry, Biology</li>
                        <li><i class="fa-solid fa-calculator"></i> Mathematics</li>
                        <li><i class="fa-solid fa-book-open"></i> English Literature &amp; Language</li>
                        <li><i class="fa-solid fa-globe"></i> History &amp; Geography</li>
                    </ul>
                    <div class="board-card__foot">
                        <span><i class="fa-solid fa-file-lines"></i> Past papers</span>
                        <span><i class="fa-solid fa-users"></i> Study rooms</span>
                        <a href="#" class="play-btn play-btn--ghost board-card__cta">Explore ICSE <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </article>

                <article class="board-card board-card--cbse">
                    <div class="board-card__top">
                        <span class="board-emoji"><i class="fa-solid fa-book-bookmark" style="color:var(--green, #10b981);"></i></span>
                        <div>
                            <h3 class="board-card__name">CBSE</h3>
                            <span class="board-card__full">Central Board of Secondary Education</span>
                        </div>
                        <span class="board-badge board-badge--green">Class 1–12</span>
                    </div>
                    <p class="board-card__desc">India's most widely followed national board. NCERT-aligned syllabus with a strong emphasis on competitive exam readiness (JEE, NEET).</p>
                    <ul class="board-card__subjects">
                        <li><i class="fa-solid fa-atom"></i> Science (PCM / PCB)</li>
                        <li><i class="fa-solid fa-calculator"></i> Mathematics &amp; Applied Maths</li>
                        <li><i class="fa-solid fa-landmark"></i> Social Science</li>
                        <li><i class="fa-solid fa-language"></i> Hindi &amp; English Core</li>
                    </ul>
                    <div class="board-card__foot">
                        <span><i class="fa-solid fa-file-lines"></i> Past papers</span>
                        <span><i class="fa-solid fa-users"></i> Study rooms</span>
                        <a href="#" class="play-btn play-btn--mint board-card__cta">Explore CBSE <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </article>

                <article class="board-card board-card--wbbse">
                    <div class="board-card__top">
                        <span class="board-emoji"><i class="fa-solid fa-book-bookmark" style="color:var(--amber, #f59e0b);"></i></span>
                        <div>
                            <h3 class="board-card__name">WBBSE</h3>
                            <span class="board-card__full">West Bengal Board of Secondary Education</span>
                        </div>
                        <span class="board-badge board-badge--amber">Class 5–10</span>
                    </div>
                    <p class="board-card__desc">State board for West Bengal with bilingual (Bengali &amp; English) content. Covers Madhyamik and Higher Secondary levels with regional context.</p>
                    <ul class="board-card__subjects">
                        <li><i class="fa-solid fa-flask"></i> Life Science &amp; Physical Science</li>
                        <li><i class="fa-solid fa-calculator"></i> Mathematics</li>
                        <li><i class="fa-solid fa-pen-nib"></i> Bengali &amp; English</li>
                        <li><i class="fa-solid fa-earth-asia"></i> History &amp; Geography</li>
                    </ul>
                    <div class="board-card__foot">
                        <span><i class="fa-solid fa-file-lines"></i> Past papers</span>
                        <span><i class="fa-solid fa-users"></i> Study rooms</span>
                        <a href="#" class="play-btn play-btn--ghost board-card__cta">Explore WBBSE <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </article>

                <article class="board-card board-card--maha">
                    <div class="board-card__top">
                        <span class="board-emoji"><i class="fa-solid fa-book-bookmark" style="color:var(--coral, #f43f5e);"></i></span>
                        <div>
                            <h3 class="board-card__name">Maharashtra Board</h3>
                            <span class="board-card__full">Maharashtra State Board of Secondary &amp; Higher Secondary Education</span>
                        </div>
                        <span class="board-badge board-badge--coral">Class 1–12</span>
                    </div>
                    <p class="board-card__desc">State board for Maharashtra with SSC (Class 10) and HSC (Class 12) streams. Bilingual Marathi &amp; English medium with strong vocational options.</p>
                    <ul class="board-card__subjects">
                        <li><i class="fa-solid fa-flask"></i> Science &amp; Technology</li>
                        <li><i class="fa-solid fa-calculator"></i> Algebra &amp; Geometry</li>
                        <li><i class="fa-solid fa-pen-nib"></i> Marathi &amp; English</li>
                        <li><i class="fa-solid fa-landmark"></i> History &amp; Political Science</li>
                    </ul>
                    <div class="board-card__foot">
                        <span><i class="fa-solid fa-file-lines"></i> Past papers</span>
                        <span><i class="fa-solid fa-users"></i> Study rooms</span>
                        <a href="#" class="play-btn play-btn--coral board-card__cta">Explore Maha Board <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </article>
            </div>
        </section>

        <!-- Features Section -->
        <section class="feature-band reveal" id="features">
            <div class="features-hero">
                <div class="features-hero-text">
                    <p class="eyebrow">Features</p>
                    <h2>Everything a modern study platform needs.</h2>
                    <p class="section-sub">From flashcards to focus timers, EduMate bundles the essentials every student and study group relies on — built on a foundation of trust.</p>
                    <div class="features-hero-actions" style="margin-top: 3rem;">
                        <a href="register.php" class="btn primary"><i class="fa-solid fa-rocket"></i> Start free</a>
                        <a href="#boards" class="btn secondary"><i class="fa-solid fa-graduation-cap"></i> Browse boards</a>
                    </div>
                </div>
                <div class="features-hero-stats" aria-label="Study tools">
                    <div class="fh-stat"><strong><i class="fa-solid fa-layer-group" aria-hidden="true"></i></strong><em>Flashcards</em></div>
                    <div class="fh-stat"><strong><i class="fa-solid fa-stopwatch" aria-hidden="true"></i></strong><em>Focus sessions</em></div>
                    <div class="fh-stat"><strong><i class="fa-solid fa-users" aria-hidden="true"></i></strong><em>Group study</em></div>
                    <div class="fh-stat"><strong><i class="fa-solid fa-note-sticky" aria-hidden="true"></i></strong><em>Shared notes</em></div>
                </div>
            </div>

            <div class="features-grid">
                <article class="fx-card fx-card--flashcards">
                    <div class="fx-card-head">
                        <span class="fx-icon fx-icon--violet"><i class="fa-solid fa-layer-group"></i></span>
                        <div class="fx-card-titles"><h3>Smart Flashcards</h3><span class="fx-sub">Master concepts with adaptive spaced repetition.</span></div>
                    </div>
                    <div class="fx-flashcard">
                        <div class="fx-flashcard-inner">
                            <div class="fx-flashcard-face fx-flashcard-front"><span class="fx-fc-label">Question</span><strong>What is mitochondria?</strong><em class="fx-fc-counter">3 / 12</em></div>
                            <div class="fx-flashcard-face fx-flashcard-back"><span class="fx-fc-label fx-fc-label--back">Answer</span><strong>Powerhouse of the cell</strong><em>Tap to reveal</em></div>
                        </div>
                    </div>
                    <div class="fx-foot fx-foot--center"><span><i class="fa-solid fa-rotate"></i> Spaced Rep.</span><span><i class="fa-solid fa-bullseye"></i> Targeted</span></div>
                </article>

                <article class="fx-card fx-card--focus">
                    <div class="fx-card-head">
                        <span class="fx-icon fx-icon--teal"><i class="fa-solid fa-stopwatch"></i></span>
                        <div class="fx-card-titles"><h3>Focus Timer</h3><span class="fx-sub">Pomodoro sessions with break reminders.</span></div>
                    </div>
                    <div class="fx-timer-ring">
                        <div class="fx-timer-inner">
                            <strong>25:00</strong>
                            <em>Focus session</em>
                        </div>
                    </div>
                    <div class="fx-timer-actions"><span class="fx-timer-pill"><i class="fa-solid fa-play"></i> Start</span><span class="fx-timer-pill"><i class="fa-solid fa-mug-hot"></i> 5 min</span><span class="fx-timer-pill"><i class="fa-solid fa-mug-saucer"></i> 15 min</span></div>
                </article>

                <article class="fx-card fx-card--notes">
                    <div class="fx-card-head">
                        <span class="fx-icon fx-icon--amber"><i class="fa-solid fa-note-sticky"></i></span>
                        <div class="fx-card-titles"><h3>Collaborative Notes</h3><span class="fx-sub">Write, share, and revise notes with peers.</span></div>
                    </div>
                    <ul class="fx-notes-list">
                        <li><i class="fa-solid fa-bookmark"></i> Chapter 4: Cell Biology</li>
                        <li><i class="fa-solid fa-bookmark"></i> Key formulas to remember</li>
                        <li><i class="fa-solid fa-bookmark"></i> Practice exam questions</li>
                    </ul>
                    <div class="fx-foot fx-foot--center"><span><i class="fa-solid fa-pen-to-square"></i> Edit</span><span><i class="fa-solid fa-share-from-square"></i> Share</span></div>
                </article>
            </div>

            <div class="pricing-calculator-card study-plan-card">
                <div class="calc-header"><i class="fa-solid fa-bullseye text-violet"></i><div><h3>Build Your Weekly Study Plan</h3><p>Slide to choose how many hours per day you can study — we'll recommend a Pomodoro rhythm, daily XP target, and a weekly streak goal tailored just for you.</p></div></div>
                <div class="calc-body">
                    <div class="calc-slider-group">
                        <label for="studyHoursRange" class="calc-label">Daily Study Hours: <strong id="studyHoursDisplay" class="text-violet">2 hrs / day</strong></label>
                        <input type="range" id="studyHoursRange" min="1" max="8" value="2" step="1" class="calc-slider">
                        <div class="slider-marks"><span>1 hr</span><span>2 hrs</span><span>3 hrs</span><span>4 hrs</span><span>5 hrs</span><span>6 hrs</span><span>7 hrs</span><span>8 hrs</span></div>
                    </div>
                    <div class="calc-results">
                        <div class="calc-result-stat"><span>Pomodoro Rhythm</span><strong id="planPomodoro">4 × 25 min</strong></div>
                        <div class="calc-result-stat"><span>Daily XP Target</span><strong id="planXP">200 XP</strong></div>
                        <div class="calc-result-stat"><span>Weekly Goal</span><strong id="planGoal">12 sessions</strong></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="faq-band reveal" id="faq" aria-label="Frequently asked questions">
            <div class="faq-card">
                <div class="faq-decor"><span class="faq-orb faq-orb--violet"></span><span class="faq-orb faq-orb--teal"></span></div>
                <div class="faq-content">
                    <header class="faq-header">
                        <p class="eyebrow"><i class="fa-solid fa-circle-question"></i> Frequently Asked Questions</p>
                        <h2>Got questions? We've got answers.</h2>
                        <p class="section-sub">Type a question, a keyword, or browse the topics below. The list filters instantly so you can find what you need in seconds.</p>
                    </header>
                    <form class="faq-search" role="search" onsubmit="return false;" aria-label="Search FAQ">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" id="faqSearchInput" data-faq-search placeholder="Type your question here — e.g. boards, flashcards, study rooms, AI doubt solver…" autocomplete="off" aria-controls="faqList">
                        <button type="button" class="faq-clear" data-faq-clear aria-label="Clear search"><i class="fa-solid fa-xmark"></i></button>
                    </form>
                    <div class="faq-tags" role="tablist">
                        <button type="button" class="faq-tag active" data-faq-tag="all">All</button>
                        <button type="button" class="faq-tag" data-faq-tag="general"><i class="fa-solid fa-graduation-cap"></i> General</button>
                        <button type="button" class="faq-tag" data-faq-tag="ai"><i class="fa-solid fa-brain"></i> AI Doubt Solver</button>
                        <button type="button" class="faq-tag" data-faq-tag="study-rooms"><i class="fa-solid fa-users"></i> Study Rooms</button>
                        <button type="button" class="faq-tag" data-faq-tag="pricing"><i class="fa-solid fa-tag"></i> Pricing</button>
                    </div>
                    <div class="faq-list" id="faqList" data-faq-list role="region" aria-live="polite">
                        <details class="faq-item" data-faq-item data-tags="general"><summary><span class="faq-accent"></span><span class="faq-q">What is EduMate and who is it built for?</span><span class="faq-toggle"><span class="faq-toggle-icon"></span></span></summary><div class="faq-a"><p>EduMate is an AI-powered study companion built specifically for Class 5–10 students studying under CBSE, ICSE, and state boards. It combines smart flashcards, a pomodoro focus timer, collaborative study rooms, and an AI tutor to help students master their syllabus.</p></div></details>
                        <details class="faq-item" data-faq-item data-tags="general"><summary><span class="faq-accent"></span><span class="faq-q">Which school boards are supported?</span><span class="faq-toggle"><span class="faq-toggle-icon"></span></span></summary><div class="faq-a"><p>We support major Indian boards, including CBSE, ICSE, WBBSE (West Bengal), and Maharashtra State Board. Our quizzes, practice papers, and concept cards are curated according to their respective syllabi.</p></div></details>
                        <details class="faq-item" data-faq-item data-tags="ai"><summary><span class="faq-accent"></span><span class="faq-q">How does the AI doubt-solver work?</span><span class="faq-toggle"><span class="faq-toggle-icon"></span></span></summary><div class="faq-a"><p>The AI assistant is trained on school textbook curricula. You can ask it to explain difficult concepts, solve science or math doubts, provide definitions, or generate custom mock questions 24/7.</p></div></details>
                        <details class="faq-item" data-faq-item data-tags="study-rooms"><summary><span class="faq-accent"></span><span class="faq-q">What are EduMate Study Rooms?</span><span class="faq-toggle"><span class="faq-toggle-icon"></span></span></summary><div class="faq-a"><p>Study Rooms are collaborative areas where you can invite friends and classmates. You can share custom flashcards, study together using a shared Pomodoro focus timer, test each other with live quizzes, or just chat.</p></div></details>
                        <details class="faq-item" data-faq-item data-tags="pricing"><summary><span class="faq-accent"></span><span class="faq-q">Is EduMate free?</span><span class="faq-toggle"><span class="faq-toggle-icon"></span></span></summary><div class="faq-a"><p>Yes! EduMate is completely free to get started. You get access to smart flashcards, focus timers, and basic AI assistant queries. Our Pro plan offers unlimited AI questions and premium board practice papers.</p></div></details>
                    </div>
                    <p class="faq-empty" data-faq-empty hidden><i class="fa-solid fa-face-meh"></i> No questions match your search keywords. Try a different keyword or <button type="button" class="faq-empty-reset" data-faq-clear>clear the search</button>.</p>
                    <div class="faq-cta"><div><strong>Still curious?</strong><span>Our support team replies within minutes, day or night.</span></div><div class="faq-cta-actions"><a href="#" class="btn small-btn"><i class="fa-solid fa-headset"></i> Contact support</a><a href="#" class="btn small-btn"><i class="fa-solid fa-comments"></i> Open chat</a></div></div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="footer-top">
            <div class="footer-brand">
                <a href="index.php" class="logo footer-logo">
                    <span class="logo-mark">
                        <img src="favicon_io/apple-touch-icon.png" alt="EduMate Logo" style="width:100%;height:100%;object-fit:cover;">
                    </span>
                    <span class="logo-wordmark footer-logo__wordmark">EduMate</span>
                </a>
                <p class="footer-tagline">Calm, secure study chat for friends, communities, and the people you actually learn with.</p>
                <div class="footer-social"><a href="#" aria-label="X"><i class="fa-brands fa-x-twitter" aria-hidden="true"></i></a><a href="#" aria-label="GitHub"><i class="fa-brands fa-github" aria-hidden="true"></i></a><a href="#" aria-label="Discord"><i class="fa-brands fa-discord" aria-hidden="true"></i></a><a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram" aria-hidden="true"></i></a></div>
            </div>
            <nav class="footer-cols">
                <div class="footer-col"><h4>Explore</h4><ul><li><a href="#boards">Study boards</a></li><li><a href="#features">Features</a></li><li><a href="#faq">FAQs</a></li></ul></div>
                <div class="footer-col"><h4>Your account</h4><ul><li><a href="register.php">Create an account</a></li><li><a href="login.php">Sign in</a></li></ul></div>
                <div class="footer-col"><h4>Get help</h4><ul><li><a href="#faq">Browse FAQs</a></li><li><a href="#faq">Find answers</a></li></ul></div>
            </nav>
        </div>
        <div class="footer-bottom"><span class="footer-copy">© 2026 EduMate Labs Inc. · Made with care for clearer conversations.</span><div class="footer-status"><span class="footer-status-dot"></span> All systems operational</div></div>
    </footer>

    <button type="button" id="scrollTopBtn" class="scroll-top-btn" aria-label="Scroll to top"><span class="scroll-top-icon"><i class="fa-solid fa-arrow-up"></i></span></button>

    <script src="script.js?v=question-session-1"></script>
</body>
</html>
