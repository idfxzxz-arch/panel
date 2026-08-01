<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Harbor Control' }} — Harbor Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: dark;
            --bg: #06080c;
            --bg2: #090c12;
            --panel: #0c1018;
            --panel2: #111722;
            --panel3: #161d2a;
            --line: #1c2433;
            --line2: #263040;
            --line3: #303c50;
            --text: #e8edf4;
            --text2: #bcc6d4;
            --muted: #6b7a90;
            --muted2: #4e5b6e;
            --red: #f03847;
            --red2: #c92a38;
            --red3: #8b1d28;
            --red-glow: #f0384733;
            --green: #42d392;
            --green2: #2ea876;
            --green-glow: #42d39233;
            --amber: #f5a623;
            --amber2: #d4891a;
            --amber-glow: #f5a62333;
            --blue: #4dabf7;
            --blue2: #3390d4;
            --blue-glow: #4dabf733;
            --purple: #a78bfa;
            --purple-glow: #a78bfa33;
            --sidebar-w: 240px;
            --sidebar-collapsed: 64px;
            --topbar-h: 56px;
            --radius: 6px;
            --radius-sm: 4px;
            --radius-lg: 10px;
            --transition: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.3);
            --shadow: 0 4px 16px rgba(0,0,0,0.4);
            --shadow-lg: 0 8px 32px rgba(0,0,0,0.5);
            --shadow-glow: 0 0 20px var(--red-glow);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            min-height: 100%;
            background: var(--bg);
            color: var(--text);
            font: 13px/1.6 'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        body {
            background-image:
                radial-gradient(ellipse 80% 50% at 50% -20%, rgba(240,56,71,0.08) 0, transparent 60%),
                radial-gradient(ellipse 60% 40% at 80% 80%, rgba(77,171,247,0.04) 0, transparent 60%);
            background-attachment: fixed;
        }
        body::before {
            content: '';
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background-image:
                linear-gradient(rgba(255,255,255,0.015) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.015) 1px, transparent 1px);
            background-size: 60px 60px;
            mask-image: radial-gradient(ellipse 70% 60% at 50% 30%, #000 0%, transparent 70%);
            -webkit-mask-image: radial-gradient(ellipse 70% 60% at 50% 30%, #000 0%, transparent 70%);
            animation: grid-drift 25s linear infinite;
        }
        @keyframes grid-drift {
            from { transform: translate(0, 0); }
            to { transform: translate(60px, 60px); }
        }
        a { color: inherit; text-decoration: none; }
        button, input, select, textarea { font: inherit; }
        ::selection { background: var(--red3); color: #fff; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--line2); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--line3); }

        /* ===== SHELL LAYOUT ===== */
        .shell { display: flex; min-height: 100vh; }

        /* ===== SIDEBAR ===== */
        .sidebar {
            position: fixed; inset: 0 auto 0 0; z-index: 30;
            width: var(--sidebar-w);
            background: var(--bg2);
            border-right: 1px solid var(--line);
            display: flex; flex-direction: column;
            transition: width var(--transition), transform var(--transition);
            overflow: hidden;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed); }
        .sidebar.collapsed .logo-text,
        .sidebar.collapsed .nav-label,
        .sidebar.collapsed .nav-link span,
        .sidebar.collapsed .sidebar-foot .avatar-detail,
        .sidebar.collapsed .sidebar-foot .avatar-dot { display: none; }
        .sidebar.collapsed .nav-link { justify-content: center; padding: 10px; }
        .sidebar.collapsed .nav-link svg { width: 18px; height: 18px; }
        .sidebar.collapsed .logo { justify-content: center; }
        .sidebar.collapsed .sidebar-foot { padding: 12px 8px; }
        .sidebar.collapsed .sidebar-foot .avatar { justify-content: center; }

        .sidebar-header {
            padding: 16px 16px 12px;
            border-bottom: 1px solid var(--line);
            display: flex; align-items: center; gap: 10px;
        }
        .logo {
            display: flex; align-items: center; gap: 10px;
            font-weight: 800; font-size: 14px; letter-spacing: 0.5px;
            flex: 1; min-width: 0;
        }
        .logo-mark {
            display: grid; place-items: center;
            width: 28px; height: 28px; flex-shrink: 0;
            border: 1.5px solid var(--red);
            background: linear-gradient(145deg, #57131d, #160a0e);
            transform: rotate(45deg); border-radius: 3px;
            transition: transform var(--transition);
        }
        .logo-mark span {
            transform: rotate(-45deg);
            color: #ff5261; font-size: 12px; font-weight: 900;
        }
        .logo-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-toggle {
            display: grid; place-items: center;
            width: 28px; height: 28px; flex-shrink: 0;
            border: 1px solid var(--line2); border-radius: var(--radius-sm);
            background: var(--panel2); color: var(--muted);
            cursor: pointer; transition: all var(--transition);
        }
        .sidebar-toggle:hover { color: #fff; border-color: var(--line3); background: var(--panel3); }
        .sidebar-toggle svg { width: 14px; height: 14px; transition: transform var(--transition); }
        .sidebar.collapsed .sidebar-toggle svg { transform: rotate(180deg); }

        .sidebar-nav {
            flex: 1; overflow-y: auto; overflow-x: hidden;
            padding: 8px 10px;
        }
        .nav-section { margin-bottom: 4px; }
        .nav-section-header {
            display: flex; align-items: center; gap: 6px;
            padding: 8px 10px; cursor: pointer;
            color: var(--muted2); font-size: 9px;
            letter-spacing: 1.6px; text-transform: uppercase;
            font-weight: 700; user-select: none;
            transition: color var(--transition);
        }
        .nav-section-header:hover { color: var(--muted); }
        .nav-section-header svg { width: 10px; height: 10px; transition: transform var(--transition); }
        .nav-section.collapsed .nav-section-header svg { transform: rotate(-90deg); }
        .nav-section.collapsed .nav-section-body { display: none; }
        .sidebar.collapsed .nav-section-header { justify-content: center; padding: 8px; }
        .sidebar.collapsed .nav-section-header span { display: none; }
        .sidebar.collapsed .nav-section-header svg { width: 12px; height: 12px; }
        .sidebar.collapsed .nav-section-body { display: none !important; }

        .nav-link {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 10px; border-radius: var(--radius-sm);
            color: var(--muted); font-size: 12px; font-weight: 500;
            margin: 1px 0; position: relative;
            transition: all var(--transition);
            border-left: 2px solid transparent;
        }
        .nav-link:hover {
            color: #fff; background: linear-gradient(90deg, rgba(240,56,71,0.12), transparent 80%);
            border-left-color: rgba(240,56,71,0.5);
        }
        .nav-link.active {
            color: #fff; background: linear-gradient(90deg, rgba(240,56,71,0.18), transparent 80%);
            border-left-color: var(--red);
            font-weight: 600;
        }
        .nav-link svg { width: 16px; height: 16px; flex-shrink: 0; }
        .nav-link .nav-badge {
            margin-left: auto; font-size: 9px; padding: 2px 6px;
            border-radius: 10px; background: var(--red3); color: #ff8a94;
            font-weight: 600;
        }

        .sidebar-foot {
            padding: 12px 14px; border-top: 1px solid var(--line);
            display: flex; align-items: center; gap: 10px;
        }
        .avatar {
            display: flex; align-items: center; gap: 9px;
            flex: 1; min-width: 0;
        }
        .avatar-dot {
            display: grid; place-items: center;
            width: 30px; height: 30px; flex-shrink: 0;
            border-radius: 50%;
            background: linear-gradient(135deg, #252d39, #1a212c);
            border: 1.5px solid var(--line2);
            color: #fff; font-size: 10px; font-weight: 700;
        }
        .avatar-detail { min-width: 0; }
        .avatar-detail .name {
            font-size: 11px; font-weight: 600; white-space: nowrap;
            overflow: hidden; text-overflow: ellipsis;
        }
        .avatar-detail .role {
            font-size: 9px; color: var(--muted);
        }

        /* ===== MAIN AREA ===== */
        .main {
            flex: 1; margin-left: var(--sidebar-w);
            min-height: 100vh; display: flex; flex-direction: column;
            transition: margin-left var(--transition);
        }
        .sidebar.collapsed ~ .main { margin-left: var(--sidebar-collapsed); }

        /* ===== TOPBAR ===== */
        .topbar {
            height: var(--topbar-h);
            display: flex; align-items: center; gap: 16px;
            padding: 0 20px;
            border-bottom: 1px solid var(--line);
            background: rgba(9, 12, 18, 0.85);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            position: sticky; top: 0; z-index: 20;
        }
        .topbar-left { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
        .breadcrumb {
            display: flex; align-items: center; gap: 8px;
            color: var(--muted2); font-size: 11px; font-weight: 500;
            white-space: nowrap;
        }
        .breadcrumb strong { color: var(--text2); font-weight: 700; }
        .breadcrumb svg { width: 12px; height: 12px; color: var(--muted2); }
        .search-box {
            position: relative; max-width: 320px; width: 100%;
        }
        .search-box input {
            width: 100%; padding: 8px 12px 8px 34px;
            border: 1px solid var(--line2); border-radius: 20px;
            background: var(--panel); color: var(--text);
            font-size: 12px; outline: none;
            transition: all var(--transition);
        }
        .search-box input:focus {
            border-color: var(--red2); box-shadow: 0 0 0 3px var(--red-glow);
            background: var(--panel2);
        }
        .search-box input::placeholder { color: var(--muted2); }
        .search-box svg {
            position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
            width: 14px; height: 14px; color: var(--muted2); pointer-events: none;
        }
        .search-results {
            display: none; position: absolute; top: 100%; left: 0; right: 0;
            margin-top: 6px; background: var(--panel2); border: 1px solid var(--line2);
            border-radius: var(--radius); box-shadow: var(--shadow-lg);
            max-height: 300px; overflow-y: auto; z-index: 50;
        }
        .search-results.active { display: block; }
        .search-result-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 14px; cursor: pointer;
            border-bottom: 1px solid var(--line);
            transition: background var(--transition);
        }
        .search-result-item:hover { background: var(--panel3); }
        .search-result-item:last-child { border-bottom: 0; }
        .search-result-item .sr-icon {
            display: grid; place-items: center;
            width: 28px; height: 28px; border-radius: var(--radius-sm);
            background: var(--panel); border: 1px solid var(--line2);
            font-size: 9px; font-weight: 700; color: var(--red);
        }
        .search-result-item .sr-info { flex: 1; min-width: 0; }
        .search-result-item .sr-name { font-size: 12px; font-weight: 600; }
        .search-result-item .sr-meta { font-size: 10px; color: var(--muted); }
        .search-empty { padding: 20px; text-align: center; color: var(--muted); font-size: 11px; }

        .topbar-right { display: flex; align-items: center; gap: 8px; }
        .topbar-status {
            display: flex; align-items: center; gap: 6px;
            font-size: 10px; color: var(--green); font-weight: 600;
            letter-spacing: 0.5px;
        }
        .topbar-status .dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--green); box-shadow: 0 0 8px var(--green-glow);
            animation: pulse-dot 2s infinite;
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        .icon-btn {
            display: grid; place-items: center;
            width: 32px; height: 32px;
            border: 1px solid var(--line2); border-radius: var(--radius-sm);
            background: var(--panel2); color: var(--muted);
            cursor: pointer; transition: all var(--transition);
            position: relative;
        }
        .icon-btn:hover { color: #fff; border-color: var(--line3); background: var(--panel3); }
        .icon-btn svg { width: 15px; height: 15px; }
        .icon-btn .indicator {
            position: absolute; top: 4px; right: 4px;
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--red);
        }
        /* Default button base — so bare <button> tags also look good */
        button {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            padding: 8px 14px; border-radius: var(--radius-sm);
            font-size: 11px; font-weight: 600; letter-spacing: 0.3px;
            cursor: pointer; border: 1px solid var(--line2);
            background: var(--panel2); color: var(--text2);
            transition: all var(--transition); white-space: nowrap;
        }
        button:hover {
            background: var(--panel3); border-color: var(--line3); color: #fff;
        }
        button:active { transform: scale(0.97); }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            padding: 8px 14px; border-radius: var(--radius-sm);
            font-size: 11px; font-weight: 600; letter-spacing: 0.3px;
            cursor: pointer; border: 1px solid transparent;
            transition: all var(--transition); white-space: nowrap;
        }
        .btn:active { transform: scale(0.97); }
        .btn-primary {
            background: linear-gradient(180deg, #ef3c4c, #c92a38);
            border-color: #e43949; color: #fff;
            box-shadow: 0 2px 8px rgba(240,56,71,0.25);
        }
        .btn-primary:hover {
            background: linear-gradient(180deg, #f04755, #d42f3e);
            box-shadow: 0 4px 16px rgba(240,56,71,0.35);
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: var(--panel2); border-color: var(--line2);
            color: var(--text2);
        }
        .btn-secondary:hover {
            background: var(--panel3); border-color: var(--line3);
            color: #fff;
        }
        .btn-danger {
            background: linear-gradient(180deg, #3d141c, #2c1016);
            border-color: #7c2531; color: #ff8390;
        }
        .btn-danger:hover {
            background: linear-gradient(180deg, #4d1822, #35141b);
            border-color: #9c2d3b;
        }
        .btn-ghost {
            background: transparent; border-color: transparent;
            color: var(--muted);
        }
        .btn-ghost:hover { color: #fff; background: var(--panel2); }
        .btn-sm { padding: 5px 10px; font-size: 10px; }
        .btn-lg { padding: 10px 20px; font-size: 13px; }

        /* ===== CONTENT ===== */
        .content {
            flex: 1; padding: 24px 24px 40px;
            max-width: 1440px; width: 100%; margin: 0 auto;
            animation: page-enter 0.35s ease-out;
        }
        @keyframes page-enter {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .page-head {
            display: flex; justify-content: space-between;
            align-items: flex-start; gap: 16px; margin-bottom: 24px;
        }
        .page-head-left { flex: 1; }
        .eyebrow {
            display: inline-flex; align-items: center; gap: 8px;
            margin: 0 0 6px; color: var(--red);
            font-size: 9px; letter-spacing: 2px; text-transform: uppercase;
            font-weight: 700;
        }
        .eyebrow::before {
            content: ''; width: 12px; height: 1px;
            background: var(--red); opacity: 0.6;
        }
        .page-head h1 {
            font-size: 22px; font-weight: 800; letter-spacing: 0.2px;
            margin: 0; line-height: 1.2;
        }
        .page-head .subtitle {
            margin-top: 4px; color: var(--muted); font-size: 12px;
        }
        .muted { color: var(--muted); }

        /* ===== CARDS ===== */
        .card {
            background: linear-gradient(155deg, rgba(16,22,32,0.9), rgba(10,14,21,0.95));
            border: 1px solid var(--line); border-radius: var(--radius);
            padding: 18px; margin-bottom: 14px;
            box-shadow: var(--shadow-sm);
            position: relative; overflow: hidden;
            transition: border-color var(--transition), box-shadow var(--transition), transform var(--transition);
        }
        .card::before {
            content: ''; position: absolute; inset: 0 0 auto; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.04), transparent);
            pointer-events: none;
        }
        .card-interactive { cursor: pointer; }
        .card-interactive:hover {
            border-color: var(--line3);
            box-shadow: var(--shadow), 0 0 20px rgba(240,56,71,0.04);
            transform: translateY(-1px);
        }
        .card-accent {
            border-left: 3px solid var(--red);
        }
        .card-glass {
            background: rgba(12, 16, 24, 0.7);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.06);
        }
        .card-title {
            display: flex; justify-content: space-between; align-items: center;
            font-size: 9px; letter-spacing: 1.4px; color: var(--muted);
            font-weight: 700; text-transform: uppercase;
            margin-bottom: 14px;
        }
        .card-title a { color: var(--red); font-weight: 600; }
        .card-title .live {
            color: var(--green); font-size: 8px; letter-spacing: 1px;
            display: flex; align-items: center; gap: 5px;
        }
        .card-title .live::before {
            content: ''; width: 5px; height: 5px; border-radius: 50%;
            background: var(--green); box-shadow: 0 0 6px var(--green-glow);
            animation: pulse-dot 2s infinite;
        }

        /* ===== GRID ===== */
        .grid { display: grid; gap: 14px; }
        .grid-2 { grid-template-columns: repeat(2, 1fr); }
        .grid-3 { grid-template-columns: repeat(3, 1fr); }
        .grid-4 { grid-template-columns: repeat(4, 1fr); }
        .grid-auto { grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }

        /* ===== BADGES ===== */
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 8px; border-radius: 3px;
            font-size: 9px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.6px; white-space: nowrap;
        }
        .badge::before {
            content: ''; width: 5px; height: 5px; border-radius: 50%;
            background: var(--muted);
        }
        .badge-running::before, .badge-succeeded::before, .badge-active::before {
            background: var(--green); box-shadow: 0 0 6px var(--green-glow);
        }
        .badge-failed::before, .badge-error::before {
            background: var(--red); box-shadow: 0 0 6px var(--red-glow);
        }
        .badge-pending::before, .badge-deploying::before, .badge-queued::before {
            background: var(--amber); box-shadow: 0 0 6px var(--amber-glow);
        }
        .badge-running, .badge-succeeded, .badge-active {
            background: rgba(66,211,146,0.12); color: var(--green);
            border: 1px solid rgba(66,211,146,0.25);
        }
        .badge-failed, .badge-error {
            background: rgba(240,56,71,0.12); color: var(--red);
            border: 1px solid rgba(240,56,71,0.25);
        }
        .badge-pending, .badge-deploying, .badge-queued {
            background: rgba(245,166,35,0.12); color: var(--amber);
            border: 1px solid rgba(245,166,35,0.25);
        }
        .badge-stopped {
            background: rgba(107,122,144,0.12); color: var(--muted);
            border: 1px solid rgba(107,122,144,0.25);
        }

        /* ===== FORMS ===== */
        .form-group { margin-bottom: 14px; }
        label {
            display: block; color: var(--text2); font-weight: 600;
            font-size: 11px; margin-bottom: 5px;
        }
        input, select, textarea {
            display: block; width: 100%;
            padding: 9px 11px; border-radius: var(--radius-sm);
            border: 1px solid var(--line2); background: var(--bg);
            color: var(--text); outline: none;
            transition: all var(--transition);
        }
        input:focus, select:focus, textarea:focus {
            border-color: var(--red2);
            box-shadow: 0 0 0 3px var(--red-glow);
            background: var(--panel);
        }
        input::placeholder, textarea::placeholder { color: var(--muted2); }
        select { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7a90' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; padding-right: 32px; }
        textarea { resize: vertical; min-height: 80px; }

        /* ===== TABLES ===== */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--line); }
        th {
            font-size: 9px; color: var(--muted2); text-transform: uppercase;
            letter-spacing: 1px; font-weight: 700; white-space: nowrap;
        }
        td { font-size: 12px; color: var(--text2); }
        tr:hover td { background: rgba(255,255,255,0.015); }
        code {
            font: 11px/1.5 'JetBrains Mono', ui-monospace, SFMono-Regular, monospace;
            background: rgba(0,0,0,0.3); color: var(--text2);
            padding: 2px 6px; border-radius: 3px;
        }
        pre {
            white-space: pre-wrap; word-break: break-word;
            background: rgba(0,0,0,0.4); border: 1px solid var(--line);
            padding: 14px; border-radius: var(--radius-sm);
            max-height: 500px; overflow: auto;
            color: var(--text2); font: 12px/1.6 'JetBrains Mono', monospace;
        }

        /* ===== ACTIONS ===== */
        .actions { display: flex; gap: 7px; flex-wrap: wrap; align-items: center; }
        .actions form { margin: 0; }

        /* ===== TOAST NOTIFICATIONS ===== */
        .toast-container {
            position: fixed; top: 20px; right: 20px; z-index: 100;
            display: flex; flex-direction: column; gap: 8px;
            pointer-events: none;
        }
        .toast {
            pointer-events: auto;
            display: flex; align-items: flex-start; gap: 10px;
            padding: 12px 16px; border-radius: var(--radius);
            background: var(--panel2); border: 1px solid var(--line2);
            box-shadow: var(--shadow-lg);
            min-width: 320px; max-width: 440px;
            animation: toast-in 0.3s ease-out;
            transition: all 0.3s ease-out;
        }
        .toast.removing {
            opacity: 0; transform: translateX(100%);
        }
        @keyframes toast-in {
            from { opacity: 0; transform: translateX(100%); }
            to { opacity: 1; transform: translateX(0); }
        }
        .toast-success { border-left: 3px solid var(--green); }
        .toast-error { border-left: 3px solid var(--red); }
        .toast-warning { border-left: 3px solid var(--amber); }
        .toast-info { border-left: 3px solid var(--blue); }
        .toast-icon { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
        .toast-success .toast-icon { color: var(--green); }
        .toast-error .toast-icon { color: var(--red); }
        .toast-warning .toast-icon { color: var(--amber); }
        .toast-info .toast-icon { color: var(--blue); }
        .toast-body { flex: 1; min-width: 0; }
        .toast-title { font-size: 12px; font-weight: 700; margin-bottom: 2px; }
        .toast-msg { font-size: 11px; color: var(--muted); }
        .toast-close {
            flex-shrink: 0; cursor: pointer; color: var(--muted2);
            font-size: 16px; line-height: 1; background: none; border: 0;
            padding: 0; transition: color var(--transition);
        }
        .toast-close:hover { color: #fff; }

        /* ===== MODAL ===== */
        .modal-overlay {
            display: none; position: fixed; inset: 0; z-index: 60;
            background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
            align-items: center; justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: var(--panel2); border: 1px solid var(--line2);
            border-radius: var(--radius-lg); box-shadow: var(--shadow-lg);
            padding: 24px; max-width: 500px; width: 90%;
            animation: modal-in 0.2s ease-out;
        }
        @keyframes modal-in {
            from { opacity: 0; transform: scale(0.95) translateY(-10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal h2 { font-size: 16px; margin-bottom: 12px; }
        .modal-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 20px; }

        /* ===== KEYBOARD SHORTCUTS PANEL ===== */
        .shortcuts-panel {
            display: none; position: fixed; inset: 0; z-index: 70;
            background: rgba(0,0,0,0.7); backdrop-filter: blur(6px);
            align-items: center; justify-content: center;
        }
        .shortcuts-panel.active { display: flex; }
        .shortcuts-card {
            background: var(--panel2); border: 1px solid var(--line2);
            border-radius: var(--radius-lg); box-shadow: var(--shadow-lg);
            padding: 28px; max-width: 560px; width: 90%;
            animation: modal-in 0.2s ease-out;
        }
        .shortcuts-card h2 { font-size: 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .shortcuts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .shortcut-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--line); }
        .shortcut-item:last-child { border-bottom: 0; }
        .shortcut-keys { display: flex; gap: 4px; }
        .kbd {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 24px; height: 22px; padding: 0 6px;
            border-radius: 3px; background: var(--panel3); border: 1px solid var(--line2);
            font-size: 10px; font-weight: 600; color: var(--text2);
            font-family: 'JetBrains Mono', monospace;
        }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center; padding: 40px 20px; color: var(--muted);
        }
        .empty-state svg { width: 48px; height: 48px; margin-bottom: 12px; opacity: 0.4; }
        .empty-state h3 { font-size: 14px; margin-bottom: 6px; color: var(--text2); }
        .empty-state p { font-size: 11px; margin-bottom: 16px; }

        /* ===== SECTION TITLE ===== */
        .section-title {
            font-size: 9px; letter-spacing: 1.4px; color: var(--red);
            font-weight: 700; text-transform: uppercase;
            padding-bottom: 10px; margin-bottom: 16px;
            border-bottom: 1px solid var(--line);
        }

        /* ===== MOBILE MENU ===== */
        .mobile-menu-btn {
            display: none; place-items: center;
            width: 32px; height: 32px;
            border: 1px solid var(--line2); border-radius: var(--radius-sm);
            background: var(--panel2); color: var(--muted);
            cursor: pointer;
        }
        .mobile-menu-btn svg { width: 18px; height: 18px; }
        .sidebar-overlay {
            display: none; position: fixed; inset: 0; z-index: 25;
            background: rgba(0,0,0,0.5);
        }
        .sidebar-overlay.active { display: block; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .grid-4 { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            :root { --sidebar-w: 0px; }
            .sidebar {
                transform: translateX(-100%);
                width: 260px !important;
            }
            .sidebar.mobile-open {
                transform: translateX(0);
                width: 260px !important;
            }
            .sidebar.collapsed { width: 260px !important; }
            .sidebar.collapsed .logo-text,
            .sidebar.collapsed .nav-label,
            .sidebar.collapsed .nav-link span,
            .sidebar.collapsed .sidebar-foot .avatar-detail { display: block !important; }
            .sidebar.collapsed .nav-link { justify-content: flex-start; padding: 9px 10px; }
            .sidebar.collapsed .nav-link svg { width: 16px; height: 16px; }
            .sidebar.collapsed .nav-section-body { display: block !important; }
            .sidebar.collapsed .nav-section-header { justify-content: flex-start; padding: 8px 10px; }
            .sidebar.collapsed .nav-section-header span { display: inline; }
            .main { margin-left: 0 !important; }
            .mobile-menu-btn { display: grid; }
            .content { padding: 16px 14px 30px; }
            .page-head { flex-direction: column; align-items: stretch; }
            .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
            .search-box { max-width: none; }
            .topbar { padding: 0 12px; gap: 10px; }
            .topbar-status { display: none; }
            .shortcuts-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) {
            .toast { min-width: auto; max-width: calc(100vw - 32px); }
            .toast-container { right: 8px; left: 8px; }
        }

        /* ===== PAGE TRANSITION & ANIMATIONS ===== */
        .page-transition-overlay-dashboard {
            position: fixed;
            inset: 0;
            background-color: var(--bg);
            z-index: 99999;
            transform: translateY(0); /* Starts covering the screen */
            transition: transform 0.6s cubic-bezier(0.7, 0, 0.3, 1);
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .page-transition-overlay-dashboard.is-entered {
            transform: translateY(-100%); /* Slides up to reveal */
        }
        .transition-logo {
            width: 60px;
            height: 60px;
            opacity: 1;
            transform: scale(1);
            transition: all 0.4s ease;
        }
        .page-transition-overlay-dashboard.is-entered .transition-logo {
            opacity: 0;
            transform: scale(0.8);
        }

        .animate-fade-in {
            opacity: 0;
            animation: fadeIn 0.6s ease forwards;
        }
        .animate-slide-up {
            opacity: 0;
            transform: translateY(20px);
            animation: slideUp 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
        }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }

        @keyframes fadeIn {
            to { opacity: 1; }
        }
        @keyframes slideUp {
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
@auth
<!-- Full screen transition overlay for Dashboard Enter -->
<div class="page-transition-overlay-dashboard" id="dashboardTransitionOverlay">
    <div class="logo-mark transition-logo">
        <span>H</span>
    </div>
</div>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        setTimeout(() => {
            const overlay = document.getElementById('dashboardTransitionOverlay');
            if (overlay) overlay.classList.add('is-entered');
        }, 50);
    });
</script>

<div class="shell animate-fade-in delay-1">
    {{-- Sidebar Overlay (mobile) --}}
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    {{-- Sidebar --}}
    <aside class="sidebar animate-slide-up delay-2" id="sidebar">
        <div class="sidebar-header">
            <a class="logo" href="{{ route('projects.index') }}">
                <span class="logo-mark"><span>H</span></span>
                <span class="logo-text">HARBOR CTRL</span>
            </a>
            <button class="sidebar-toggle" onclick="toggleSidebar()" title="Toggle sidebar (Ctrl+B)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section" id="navCommand">
                <div class="nav-section-header" onclick="toggleNavSection('navCommand')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                    <span>Command Center</span>
                </div>
                <div class="nav-section-body">
                    <a class="nav-link {{ request()->routeIs('projects.index') ? 'active' : '' }}" href="{{ route('projects.index') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                        <span>Overview</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('applications.*') || request()->routeIs('projects.show') ? 'active' : '' }}" href="{{ route('applications.index') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                        <span>Applications</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('projects.create') ? 'active' : '' }}" href="{{ route('projects.create') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                        <span>New Deployment</span>
                    </a>
                </div>
            </div>

            <div class="nav-section" id="navInfra">
                <div class="nav-section-header" onclick="toggleNavSection('navInfra')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                    <span>Infrastructure</span>
                </div>
                <div class="nav-section-body">
                    <a class="nav-link {{ request()->routeIs('domains.*') ? 'active' : '' }}" href="{{ route('domains.index') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/></svg>
                        <span>Domains</span>
                    </a>

                    <a class="nav-link {{ request()->routeIs('monitoring.*') ? 'active' : '' }}" href="{{ route('monitoring.index') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19V5M4 19h16M7 15l4-5 3 3 5-7"/></svg>
                        <span>Monitoring</span>
                    </a>
                </div>
            </div>

            <div class="nav-section" id="navConfig">
                <div class="nav-section-header" onclick="toggleNavSection('navConfig')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                    <span>Configuration</span>
                </div>
                <div class="nav-section-body">
                    <a class="nav-link {{ request()->routeIs('integrations.*') ? 'active' : '' }}" href="{{ route('integrations.index') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1l2-1.5-2-3.4-2.4 1A7 7 0 0 0 15 6l-.3-2.6h-4L10.4 6a7 7 0 0 0-1.5.9l-2.4-1-2 3.4L6.5 11a7 7 0 0 0 0 2l-2 1.5 2 3.4 2.4-1A7 7 0 0 0 10.4 18l.3 2.6h4L15 18a7 7 0 0 0 1.5-.9l2.4 1 2-3.4-2-1.5a7 7 0 0 0 .1-1z"/></svg>
                        <span>Integrations</span>
                    </a>
                </div>
            </div>
        </nav>

        <div class="sidebar-foot">
            <div class="avatar">
                <span class="avatar-dot">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</span>
                <div class="avatar-detail">
                    <div class="name">{{ auth()->user()->name }}</div>
                    <div class="role">Administrator</div>
                </div>
            </div>
        </div>
    </aside>

    {{-- Main Content --}}
    <section class="main animate-slide-up delay-3">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu-btn" onclick="toggleMobileSidebar()" title="Menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
                </button>
                <div class="breadcrumb">
                    <strong>HARBOR</strong>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                    <span>{{ strtoupper($title ?? 'CONTROL') }}</span>
                </div>
                <div class="search-box">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                    <input type="text" id="globalSearch" placeholder="Search applications, deployments... (Ctrl+K)" autocomplete="off">
                    <div class="search-results" id="searchResults"></div>
                </div>
            </div>
            <div class="topbar-right">
                <span class="topbar-status"><span class="dot"></span>ONLINE</span>
                <button class="icon-btn" onclick="toggleShortcuts()" title="Keyboard shortcuts (?)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                </button>
                <form method="post" action="{{ route('logout') }}" style="margin:0">
                    @csrf
                    <button class="btn btn-secondary btn-sm">LOGOUT</button>
                </form>
            </div>
        </header>

        <main class="content">
            {{-- Toast container --}}
            <div class="toast-container" id="toastContainer"></div>

            {{-- Flash messages converted to toasts via JS --}}
            @if(session('success'))
                <script>document.addEventListener('DOMContentLoaded', () => { showToast('success', 'Success', @json(session('success'))); });</script>
            @endif
            @if(session('error'))
                <script>document.addEventListener('DOMContentLoaded', () => { showToast('error', 'Error', @json(session('error'))); });</script>
            @endif
            @if($errors->any())
                <script>document.addEventListener('DOMContentLoaded', () => { showToast('error', 'Error', @json(implode(', ', $errors->all()))); });</script>
            @endif

            @yield('content')
        </main>
    </section>
</div>

{{-- Keyboard Shortcuts Panel --}}
<div class="shortcuts-panel" id="shortcutsPanel" onclick="if(event.target===this) toggleShortcuts()">
    <div class="shortcuts-card">
        <h2>⌨️ Keyboard Shortcuts</h2>
        <div class="shortcuts-grid">
            <div class="shortcut-item"><span>Toggle Sidebar</span><span class="shortcut-keys"><kbd class="kbd">Ctrl</kbd>+<kbd class="kbd">B</kbd></span></div>
            <div class="shortcut-item"><span>Global Search</span><span class="shortcut-keys"><kbd class="kbd">Ctrl</kbd>+<kbd class="kbd">K</kbd></span></div>
            <div class="shortcut-item"><span>Go to Overview</span><span class="shortcut-keys"><kbd class="kbd">G</kbd> <kbd class="kbd">O</kbd></span></div>
            <div class="shortcut-item"><span>Go to Applications</span><span class="shortcut-keys"><kbd class="kbd">G</kbd> <kbd class="kbd">A</kbd></span></div>
            <div class="shortcut-item"><span>Go to Domains</span><span class="shortcut-keys"><kbd class="kbd">G</kbd> <kbd class="kbd">D</kbd></span></div>

            <div class="shortcut-item"><span>Go to Monitoring</span><span class="shortcut-keys"><kbd class="kbd">G</kbd> <kbd class="kbd">M</kbd></span></div>
            <div class="shortcut-item"><span>Go to Integrations</span><span class="shortcut-keys"><kbd class="kbd">G</kbd> <kbd class="kbd">I</kbd></span></div>
            <div class="shortcut-item"><span>New Deployment</span><span class="shortcut-keys"><kbd class="kbd">N</kbd></span></div>
            <div class="shortcut-item"><span>Close Panel / Modal</span><span class="shortcut-keys"><kbd class="kbd">Esc</kbd></span></div>
        </div>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="toggleShortcuts()">Close</button>
        </div>
    </div>
</div>

<script>
// ===== TOAST NOTIFICATIONS =====
function showToast(type, title, message) {
    const container = document.getElementById('toastContainer');
    const icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <span class="toast-icon">${icons[type] || 'ℹ'}</span>
        <div class="toast-body">
            <div class="toast-title">${title}</div>
            <div class="toast-msg">${message}</div>
        </div>
        <button class="toast-close" onclick="dismissToast(this.parentElement)">×</button>
    `;
    container.appendChild(toast);
    setTimeout(() => dismissToast(toast), 5000);
}

function dismissToast(toast) {
    toast.classList.add('removing');
    setTimeout(() => toast.remove(), 300);
}

// ===== SIDEBAR =====
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('collapsed');
    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
}

function toggleMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('mobile-open');
    overlay.classList.toggle('active');
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.remove('mobile-open');
    overlay.classList.remove('active');
}

// Restore sidebar state
document.addEventListener('DOMContentLoaded', () => {
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        document.getElementById('sidebar').classList.add('collapsed');
    }
});

// ===== NAV SECTIONS =====
function toggleNavSection(sectionId) {
    const section = document.getElementById(sectionId);
    section.classList.toggle('collapsed');
    localStorage.setItem(`navSection_${sectionId}`, section.classList.contains('collapsed'));
}

// Restore nav section states
document.addEventListener('DOMContentLoaded', () => {
    ['navCommand', 'navInfra', 'navConfig'].forEach(id => {
        if (localStorage.getItem(`navSection_${id}`) === 'true') {
            const el = document.getElementById(id);
            if (el) el.classList.add('collapsed');
        }
    });
});

// ===== GLOBAL SEARCH =====
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('globalSearch');
    const searchResults = document.getElementById('searchResults');

    if (!searchInput) return;

    const searchData = [
        @php
            $searchItems = [];
            foreach (($projects ?? []) as $project) {
                $searchItems[] = [
                    'type' => 'app',
                    'name' => $project->name,
                    'meta' => ($project->primaryDomain?->domain ?? 'No domain') . ' · ' . $project->status,
                    'url' => route('projects.show', $project),
                    'icon' => strtoupper(substr($project->name, 0, 2)),
                ];
            }
            foreach (($deployments ?? []) as $deployment) {
                $searchItems[] = [
                    'type' => 'deploy',
                    'name' => '#' . $deployment->id . ' ' . ($deployment->project->name ?? ''),
                    'meta' => $deployment->status . ' · ' . $deployment->created_at->format('d M H:i'),
                    'url' => route('deployments.show', $deployment),
                    'icon' => '#' . $deployment->id,
                ];
            }
        @endphp
        @json($searchItems)
    ];

    // Also add static nav items
    const navItems = [
        { type: 'nav', name: 'Overview Dashboard', meta: 'Command Center', url: '{{ route('projects.index') }}', icon: '◆' },
        { type: 'nav', name: 'Applications', meta: 'Workload Inventory', url: '{{ route('applications.index') }}', icon: '☰' },
        { type: 'nav', name: 'New Deployment', meta: 'Deploy Project', url: '{{ route('projects.create') }}', icon: '+' },
        { type: 'nav', name: 'Domains', meta: 'Edge Routing', url: '{{ route('domains.index') }}', icon: '🌐' },

        { type: 'nav', name: 'Monitoring', meta: 'Observability', url: '{{ route('monitoring.index') }}', icon: '📈' },
        { type: 'nav', name: 'Integrations', meta: 'GitHub & Cloudflare', url: '{{ route('integrations.index') }}', icon: '⚙' },
    ];
    const allSearchData = [...navItems, ...searchData];

    searchInput.addEventListener('input', () => {
        const query = searchInput.value.toLowerCase().trim();
        if (!query) {
            searchResults.classList.remove('active');
            return;
        }
        const filtered = allSearchData.filter(item =>
            item.name.toLowerCase().includes(query) ||
            item.meta.toLowerCase().includes(query)
        ).slice(0, 8);

        if (filtered.length === 0) {
            searchResults.innerHTML = '<div class="search-empty">No results found</div>';
        } else {
            searchResults.innerHTML = filtered.map(item => `
                <a class="search-result-item" href="${item.url}">
                    <span class="sr-icon">${item.icon}</span>
                    <div class="sr-info">
                        <div class="sr-name">${item.name}</div>
                        <div class="sr-meta">${item.meta}</div>
                    </div>
                </a>
            `).join('');
        }
        searchResults.classList.add('active');
    });

    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            searchResults.classList.remove('active');
            searchInput.blur();
        }
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.search-box')) {
            searchResults.classList.remove('active');
        }
    });
});

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', (e) => {
    // Don't trigger shortcuts when typing in inputs
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
        if (e.key === 'Escape') e.target.blur();
        return;
    }

    const key = e.key.toLowerCase();

    // Ctrl+B: Toggle sidebar
    if (e.ctrlKey && key === 'b') {
        e.preventDefault();
        toggleSidebar();
    }

    // Ctrl+K: Focus search
    if (e.ctrlKey && key === 'k') {
        e.preventDefault();
        document.getElementById('globalSearch')?.focus();
    }

    // G + letter: Go to pages
    if (key === 'g') {
        const gotoHandler = (e2) => {
            const k2 = e2.key.toLowerCase();
            const routes = {
                'o': '{{ route('projects.index') }}',
                'a': '{{ route('applications.index') }}',
                'd': '{{ route('domains.index') }}',

                'm': '{{ route('monitoring.index') }}',
                'i': '{{ route('integrations.index') }}',
            };
            if (routes[k2]) {
                e2.preventDefault();
                window.location.href = routes[k2];
            }
            document.removeEventListener('keydown', gotoHandler);
        };
        document.addEventListener('keydown', gotoHandler, { once: true });
        setTimeout(() => document.removeEventListener('keydown', gotoHandler), 1500);
    }

    // N: New deployment
    if (key === 'n') {
        e.preventDefault();
        window.location.href = '{{ route('projects.create') }}';
    }

    // ?: Toggle shortcuts panel
    if (key === '?') {
        e.preventDefault();
        toggleShortcuts();
    }

    // Escape: Close panels
    if (key === 'escape') {
        closeSidebar();
        document.getElementById('shortcutsPanel')?.classList.remove('active');
        document.getElementById('searchResults')?.classList.remove('active');
    }
});

// ===== SHORTCUTS PANEL =====
function toggleShortcuts() {
    document.getElementById('shortcutsPanel').classList.toggle('active');
}

// ===== AUTO-REFRESH (optional, only on overview) =====
@if(request()->routeIs('projects.index'))
    let autoRefreshTimer = setTimeout(() => window.location.reload(), 60000);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            clearTimeout(autoRefreshTimer);
        } else {
            autoRefreshTimer = setTimeout(() => window.location.reload(), 60000);
        }
    });
@endif
</script>

@else
{{-- Guest layout (login) --}}
<main class="content" style="display:flex;align-items:center;justify-content:center;min-height:100vh">
    <div class="toast-container" id="toastContainer"></div>
    @if($errors->any())
        <script>document.addEventListener('DOMContentLoaded', () => { showToast('error', 'Error', @json(implode(', ', $errors->all()))); });</script>
    @endif
    @yield('content')
</main>
@endauth
</body>
</html>