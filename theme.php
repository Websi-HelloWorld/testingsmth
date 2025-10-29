<?php
/**
 * Zentrales Theme / Darkmode Include
 * Nutzung:
 *   require_once 'theme.php';
 *   echo render_theme_head('Seitentitel');
 *   echo render_theme_toggle(); (direkt nach <body>)
 */
function render_theme_head($title, $options = array()) {
  $extra = isset($options['extra']) ? $options['extra'] : '';
    return <<<HTML
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>{$title}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
    // Tailwind Dark Mode via class
    window.__applyInitialTheme = function(){
      try {
        const pref = localStorage.getItem('theme');
        if (pref === 'dark' || (!pref && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
          document.documentElement.classList.add('dark');
        }
      } catch(e) {}
    };
    window.__applyInitialTheme();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = { darkMode: 'class' };
    </script>
    <style>
      :root { color-scheme: light dark; }
      html, body { height: 100%; }
      
      /* Fixed background that never scrolls */
      body { 
        font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, 'Apple Color Emoji', 'Segoe UI Emoji';
        transition: background-color .35s, color .35s; 
        background: radial-gradient(1200px 600px at 10% -10%, rgba(59,130,246,.12), transparent 50%),
                    radial-gradient(1000px 500px at 110% 10%, rgba(99,102,241,.12), transparent 50%),
                    linear-gradient(180deg, rgba(255,255,255,0.6), rgba(255,255,255,0));
        background-attachment: fixed; /* Background bleibt beim Scrollen fix */
        margin: 0;
        padding: 0;
        padding-top: 80px; /* Platz für fixed header */
      }
      .dark body, body.dark { 
        background: radial-gradient(1200px 600px at 10% -10%, rgba(37,99,235,.25), transparent 50%),
                    radial-gradient(1000px 500px at 110% 10%, rgba(79,70,229,.25), transparent 50%),
                    linear-gradient(180deg, rgba(17,24,39,.8), rgba(17,24,39,.6));
        background-attachment: fixed; /* Background bleibt beim Scrollen fix */
      }

      /* Fixed header that never scrolls */
      header {
        position: fixed !important;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1000;
        height: 80px;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
      }

      /* Subtle grain overlay - also fixed */
      body::before {
        content: '';
        position: fixed; inset: 0; pointer-events: none;
        background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><g fill="none" fill-opacity=".05"><path fill="#000" d="M0 0h1v1H0z"/></g></svg>');
        opacity: .18;
        z-index: -1;
      }

      /* Glass surface */
      .glass {
        background: rgba(255,255,255,0.65);
        -webkit-backdrop-filter: blur(8px);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(148,163,184,.35);
      }
      .dark .glass {
        background: rgba(17,24,39,0.55);
        border-color: rgba(148,163,184,.25);
      }

      /* Card helper */
      .card {
        background: rgba(255,255,255,0.85);
        border: 1px solid rgba(148,163,184,.35);
        box-shadow: 0 8px 24px rgba(15,23,42,.06);
      }
      .dark .card {
        background: rgba(17,24,39,0.7);
        border-color: rgba(148,163,184,.25);
        box-shadow: 0 10px 28px rgba(0,0,0,.35);
      }

      /* Buttons */
      .btn { 
        display:inline-flex; align-items:center; gap:.5rem; 
        padding:.55rem 1rem; border-radius:.65rem; font-weight:600; 
        transition: transform .12s ease, box-shadow .2s ease, background .2s ease; 
      }
      .btn:active { transform: translateY(1px); }
      .btn-primary { 
        color: #fff; 
        background-image: linear-gradient(135deg, #3b82f6, #6366f1);
        box-shadow: 0 6px 16px rgba(59,130,246,.25);
      }
      .btn-primary:hover { box-shadow: 0 10px 22px rgba(59,130,246,.35); }

      /* Additional button variants */
      .btn-danger {
        color: #fff;
        background-image: linear-gradient(135deg, #ef4444, #dc2626);
        box-shadow: 0 6px 16px rgba(239,68,68,.25);
      }
      .btn-danger:hover { box-shadow: 0 10px 22px rgba(239,68,68,.35); }

      .btn-outline {
        color: #1f2937;
        background: transparent;
        border: 1px solid rgba(148,163,184,.45);
      }
      .dark .btn-outline { color: #e5e7eb; border-color: rgba(148,163,184,.35); }
      .btn-outline:hover { background: rgba(148,163,184,.08); }
      .dark .btn-outline:hover { background: rgba(148,163,184,.12); }

      /* Focus styles for accessibility */
      .btn:focus-visible { outline: 2px solid #3b82f6; outline-offset: 2px; }
      .btn-danger:focus-visible { outline-color: #ef4444; }

      /* Titles */
      .section-title { 
        font-weight: 800; letter-spacing: -.01em; 
        background: linear-gradient(90deg, #000000, #3b82f6);
        -webkit-background-clip: text; background-clip: text; color: transparent;
      }
      .dark .section-title {
        font-weight: 800; letter-spacing: -.01em; 
        background: linear-gradient(90deg, #ffffffff, #3b82f6);
        -webkit-background-clip: text; background-clip: text; color: transparent;
      }

      /* Cute hover */
      .hover-raise { transition: transform .18s ease, box-shadow .2s ease; }
      .hover-raise:hover { transform: translateY(-2px); box-shadow: 0 14px 30px rgba(0,0,0,.12); }
      .dark .hover-raise:hover { box-shadow: 0 18px 34px rgba(0,0,0,.35); }

      /* Animations */
      @keyframes fadeUp { from { opacity: 0; transform: translateY(6px);} to { opacity: 1; transform: translateY(0);} }
      .fade-up { animation: fadeUp .5s ease-out both; }

      /* Theme toggle */
      .theme-toggle-btn { 
        transition: background-color .25s, color .25s, transform .15s, box-shadow .2s; 
        box-shadow: 0 4px 14px rgba(15,23,42,.12);
      }
      .theme-toggle-btn:active { transform: scale(.92); }
    </style>
    {$extra}
HTML;
}

function render_theme_toggle($inline = false) {
    $baseClass = 'theme-toggle-btn px-3 py-2 rounded-lg text-sm font-semibold bg-white/70 dark:bg-gray-800/70 text-gray-700 dark:text-gray-200 hover:bg-white/90 dark:hover:bg-gray-800/90 focus:outline-none focus:ring-2 focus:ring-blue-500 transition glass';
    $wrapperClass = $inline ? '' : 'fixed top-3 right-3 z-50 shadow';
    return <<<HTML
    <button id="themeToggle" type="button" aria-label="Theme umschalten" class="$baseClass $wrapperClass">🌙</button>
    <script>
      (function(){
        const btn = document.getElementById('themeToggle');
        if(!btn) return;
        function setIcon(){
          const dark = document.documentElement.classList.contains('dark');
          btn.textContent = dark ? '☀️' : '🌙';
          btn.setAttribute('aria-label', dark ? 'Zum hellen Theme wechseln' : 'Zum dunklen Theme wechseln');
        }
        setIcon();
        btn.addEventListener('click',()=>{
          const isDark = document.documentElement.classList.toggle('dark');
          try { localStorage.setItem('theme', isDark ? 'dark':'light'); } catch(e) {}
          setIcon();
        });
      })();
    </script>
HTML;
}
