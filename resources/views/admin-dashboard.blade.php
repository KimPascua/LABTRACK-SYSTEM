<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#0b1730">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>LABSTRACK — Administrator Dashboard</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="{{ asset('css copy/AdminDashboardFinal.css') }}">

<script>
  /* Restore the collapsed sidebar before first paint (no layout flash). */
  try {
    if (localStorage.getItem('labstrack.sidebar') === 'collapsed') {
      document.documentElement.classList.add('sidebar-collapsed');
    }
  } catch (e) {}
</script>
</head>

@php
    $adminName = session('admin_name', 'Administrator');
    $adminInitials = collect(explode(' ', trim($adminName)))
        ->filter()
        ->map(fn ($word) => strtoupper(substr($word, 0, 1)))
        ->take(2)
        ->implode('') ?: 'A';
@endphp

<body>

<a href="#mainContent" class="skip-link">Skip to main content</a>

<div class="app">

  <!-- =====================================================
       SIDEBAR
  ====================================================== -->
  <aside class="sidebar" id="sidebar" aria-label="Main navigation">

    <div class="sidebar-top">
      <a href="/admin/dashboard" class="brand" title="LABTRACK">
        <img class="brand-logo" src="{{ asset('images/white_logo.png') }}" alt="LABTRACK logo">
        <span class="brand-text">
          <span class="brand-name">LABTRACK</span>
          <span class="brand-tag">Computer Laboratory Monitoring System</span>
        </span>
      </a>

      <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Close navigation">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-label">Main</div>

      <a class="nav-item active" href="/admin/dashboard" aria-current="page" title="Dashboard">
        <i class="fa-solid fa-table-columns"></i><span>Dashboard</span>
      </a>

      <a class="nav-item" href="{{ route('admin.subjects.page') }}" title="Subject Management">
        <i class="fa-solid fa-book-open"></i><span>Subject Management</span>
      </a>

      <a class="nav-item" href="{{ route('admin.schedules') }}" title="Schedule Management">
        <i class="fa-regular fa-calendar-days"></i><span>Schedule Management</span>
      </a>

      <a class="nav-item" href="{{ route('admin.computers') }}" title="Computer Management">
        <i class="fa-solid fa-computer"></i><span>Computer Management</span>
      </a>

      <a class="nav-item" href="{{ route('admin.attendance') }}" title="Attendance Records">
        <i class="fa-solid fa-clipboard-check"></i><span>Attendance Records</span>
      </a>

      <a class="nav-item" href="{{ route('admin.violations') }}" title="Violation Logs">
        <i class="fa-solid fa-triangle-exclamation"></i><span>Violation Logs</span>
      </a>

      <a class="nav-item" href="/admin/reports" title="Reports">
        <i class="fa-solid fa-chart-simple"></i><span>Reports</span>
      </a>

      <a class="nav-item" href="{{ route('admin.announcements') }}" title="Announcement">
        <i class="fa-solid fa-bullhorn"></i><span>Announcement</span>
      </a>

      <div class="nav-label">Account</div>

      <a class="nav-item" href="/admin/settings" title="Settings">
        <i class="fa-solid fa-gear"></i><span>Settings</span>
      </a>

      <button type="button" class="nav-item logout-item" data-action="logout" title="Logout">
        <i class="fa-solid fa-right-from-bracket"></i><span>Logout</span>
      </button>
    </nav>

    <div class="sidebar-foot" title="Monitoring active">
      <span class="live-dot" aria-hidden="true"></span>
      <span class="sidebar-foot-text">Monitoring active</span>
    </div>
  </aside>

  <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>


  <!-- =====================================================
       MAIN
  ====================================================== -->
  <div class="main">

    <header class="topbar">
      <div class="topbar-left">
        <button type="button" class="icon-button hamburger" id="hamburgerBtn"
                aria-label="Toggle navigation" aria-controls="sidebar" aria-expanded="true">
          <i class="fa-solid fa-bars"></i>
        </button>

        <div class="topbar-title">
          <h2 id="pageTitle">Dashboard</h2>
          <span class="topbar-crumb">Admin &middot; Overview</span>
        </div>
      </div>

      <div class="topbar-right">

        <!-- Global search -->
        <div class="search-box" id="globalSearchBox" role="search">
          <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>

          <input type="search"
                 id="globalSearchInput"
                 placeholder="Search students, PCs, logs..."
                 autocomplete="off"
                 aria-label="Search students, PCs and logs"
                 aria-controls="globalSearchResults"
                 aria-expanded="false">

          <kbd class="search-kbd" aria-hidden="true">/</kbd>

          <button type="button" class="search-close" id="searchClose" aria-label="Close search">
            <i class="fa-solid fa-xmark"></i>
          </button>

          <div class="global-search-results" id="globalSearchResults" role="listbox"></div>
        </div>

        <button type="button" class="icon-button search-toggle" id="searchToggle" data-search-trigger aria-label="Open search">
          <i class="fa-solid fa-magnifying-glass"></i>
        </button>

        <!-- Notifications -->
        <div class="notif-wrap" id="notifWrap">
          <button type="button" class="icon-button bell" id="bellBtn"
                  aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
            <i class="fa-regular fa-bell"></i>
            <span class="dot" id="bellDot">3</span>
          </button>

          <div class="notif-menu" role="menu">
            <div class="notif-menu-title">
              Notifications
              <a href="{{ route('admin.violations') }}" class="link">View all</a>
            </div>

            <a class="notif-row unread" href="{{ route('admin.violations') }}" role="menuitem">
              <div class="dot-icon tone-red"><i class="fa-solid fa-ban"></i></div>
              <div>
                <div class="r-title">Unauthorized app blocked — PC-14, CLAB1</div>
                <div class="r-time">8 minutes ago</div>
              </div>
            </a>

            <a class="notif-row unread" href="{{ route('admin.computers') }}" role="menuitem">
              <div class="dot-icon tone-orange"><i class="fa-solid fa-triangle-exclamation"></i></div>
              <div>
                <div class="r-title">PC-22 went offline unexpectedly</div>
                <div class="r-time">22 minutes ago</div>
              </div>
            </a>

            <a class="notif-row unread" href="/admin/reports" role="menuitem">
              <div class="dot-icon tone-blue"><i class="fa-solid fa-circle-check"></i></div>
              <div>
                <div class="r-title">Weekly attendance report is ready</div>
                <div class="r-time">1 hour ago</div>
              </div>
            </a>
          </div>
        </div>

        <!-- Profile -->
        <div class="profile" id="profileMenu">
          <button type="button" class="profile-trigger" id="profileBtn"
                  aria-haspopup="true" aria-expanded="false" aria-label="Account menu">
            <div class="avatar">{{ $adminInitials }}</div>

            <div class="profile-name">
              <div class="name">{{ $adminName }}</div>
              <div class="role">Laboratory Administrator</div>
            </div>

            <i class="fa-solid fa-chevron-down chev" aria-hidden="true"></i>
          </button>

          <div class="profile-menu" role="menu">
            <div class="profile-menu-header">
              <div class="avatar">{{ $adminInitials }}</div>
              <div class="profile-menu-id">
                <div class="name">{{ $adminName }}</div>
                <div class="sub">ID: {{ session('admin_code', 'N/A') }}</div>
              </div>
            </div>

            <a class="profile-menu-item" href="/admin/settings" role="menuitem">
              <i class="fa-regular fa-user"></i> My Profile
            </a>
            <a class="profile-menu-item" href="/admin/settings" role="menuitem">
              <i class="fa-solid fa-gear"></i> Settings
            </a>
            <a class="profile-menu-item" href="{{ route('admin.violations') }}" role="menuitem">
              <i class="fa-regular fa-bell"></i> Notifications
            </a>

            <div class="profile-menu-divider"></div>

            <button type="button" class="profile-menu-item danger" data-action="logout" role="menuitem">
              <i class="fa-solid fa-right-from-bracket"></i> Logout
            </button>
          </div>
        </div>

      </div>
    </header>


    <main class="content" id="mainContent" tabindex="-1">

      <!-- ============ DASHBOARD ============ -->
      <section class="page active" id="page-dashboard">

        <!-- HERO -->
        <section class="hero">
          <div class="hero-copy">
            <span class="hero-eyebrow" id="heroGreeting">Welcome back</span>
            <h1>{{ $adminName }}</h1>
            <p>Here's a live overview of registered students, lab sessions, and today's monitoring activity.</p>

            <div class="hero-meta">
              <button type="button" class="hero-chip" id="refreshBtn" title="Refresh now" aria-label="Refresh dashboard data">
                <span class="live-dot" aria-hidden="true"></span>
                Live &middot; <span id="lastUpdated">connecting…</span>
                <i class="fa-solid fa-rotate-right refresh-icon" aria-hidden="true"></i>
              </button>
            </div>
          </div>

          <div class="hero-clock" aria-live="off">
            <div class="time" id="heroTime">--:--</div>
            <div class="date" id="heroDate">Loading date...</div>
          </div>
        </section>


        <!-- STATS -->
        <section class="grid-4" aria-label="Key figures">

          <article class="stat-card">
            <div class="stat-icon blue"><i class="fa-solid fa-user-graduate"></i></div>
            <div class="stat-body">
              <div class="stat-label">Registered Students</div>
              <span class="stat-value" id="registeredStudents">0</span>
              <div class="stat-sub blue">All enrolled students</div>
            </div>
          </article>

          <article class="stat-card">
            <div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div>
            <div class="stat-body">
              <div class="stat-label">Students Present</div>
              <span class="stat-value" id="studentsPresent">0</span>
              <div class="stat-sub muted"><span id="attendancePercentage">0% of registered</span></div>
            </div>
          </article>

          <article class="stat-card">
            <div class="stat-icon purple"><i class="fa-solid fa-computer"></i></div>
            <div class="stat-body">
              <div class="stat-label">Active Sessions / PC</div>
              <span class="stat-value">
                <span id="activeComputers">0</span><span class="stat-total"> / <span id="totalComputers">0</span></span>
              </span>
              <div class="stat-sub muted">Across all laboratories</div>
            </div>
          </article>

          <article class="stat-card">
            <div class="stat-icon red"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="stat-body">
              <div class="stat-label">Today's Violations</div>
              <span class="stat-value" id="todaysViolations">0</span>
              <div class="stat-sub muted"><span id="unresolvedViolations">0 unresolved</span></div>
            </div>
          </article>

        </section>


        <!-- MONITORING + ACTIVITY -->
        <section class="grid-2">

          <div class="card">
            <div class="card-head">
              <div class="card-title">
                <h3>Live Monitoring</h3>
                <span class="live-pill"><span class="live-dot" aria-hidden="true"></span>Live</span>
              </div>
              <a class="view-all" href="{{ route('admin.computers') }}">
                Open Monitoring <i class="fa-solid fa-arrow-right"></i>
              </a>
            </div>

            <div class="pc-grid dashboard-pc-grid" id="dashMonitorGrid" aria-live="polite">
              @for ($i = 0; $i < 8; $i++)
                <div class="pc-tile skeleton-tile" aria-hidden="true">
                  <span class="skeleton sk-icon"></span>
                  <span class="skeleton sk-line"></span>
                  <span class="skeleton sk-line short"></span>
                </div>
              @endfor
            </div>

            <div class="legend" aria-label="Legend">
              <span><i class="dot" style="background:var(--blue)"></i>In use</span>
              <span><i class="dot" style="background:var(--green)"></i>Idle</span>
              <span><i class="dot" style="background:var(--orange)"></i>Maintenance</span>
              <span><i class="dot" style="background:#a7afbd"></i>Offline / Locked</span>
            </div>
          </div>

          <div class="card">
            <div class="card-head">
              <div class="card-title"><h3>Recent Activities</h3></div>
              <a class="view-all" href="{{ route('admin.violations') }}">
                View All <i class="fa-solid fa-arrow-right"></i>
              </a>
            </div>

            <div id="recentActivityList" class="activity-list" aria-live="polite">
              @for ($i = 0; $i < 4; $i++)
                <div class="list-row" aria-hidden="true">
                  <span class="skeleton sk-square"></span>
                  <div class="row-main">
                    <span class="skeleton sk-line"></span>
                    <span class="skeleton sk-line short"></span>
                  </div>
                </div>
              @endfor
            </div>
          </div>

        </section>


        <!-- STATUS + QUICK ACTIONS -->
        <section class="grid-2">

          <div class="card">
            <div class="card-head">
              <div class="card-title"><h3>System Status</h3></div>
            </div>
            <div class="status-list" id="systemStatusList"></div>
          </div>

          <div class="card">
            <div class="card-head">
              <div class="card-title"><h3>Quick Actions</h3></div>
            </div>

            <div class="quick-grid">
              <a class="quick-btn" href="{{ route('admin.computers') }}">
                <span class="quick-icon tone-blue"><i class="fa-solid fa-power-off"></i></span>
                <span class="q-title">Manage PCs</span>
                <span class="q-desc">Lock, restart, or check lab computers</span>
              </a>

              <a class="quick-btn" href="{{ route('admin.announcements') }}">
                <span class="quick-icon tone-purple"><i class="fa-solid fa-bullhorn"></i></span>
                <span class="q-title">Post Announcement</span>
                <span class="q-desc">Notify all students instantly</span>
              </a>

              <a class="quick-btn" href="/admin/reports">
                <span class="quick-icon tone-green"><i class="fa-solid fa-file-arrow-down"></i></span>
                <span class="q-title">Generate Report</span>
                <span class="q-desc">Export attendance or violation data</span>
              </a>

              <a class="quick-btn" href="{{ route('admin.violations') }}">
                <span class="quick-icon tone-red"><i class="fa-solid fa-shield-halved"></i></span>
                <span class="q-title">Review Violations</span>
                <span class="q-desc">Resolve today's flagged incidents</span>
              </a>
            </div>
          </div>

        </section>
      </section>

    </main>
  </div>
</div>


<!-- =========================================================
     MOBILE BOTTOM NAVIGATION (phones only)
========================================================== -->
<nav class="mobile-nav" aria-label="Quick navigation">
  <a class="mobile-nav-item active" href="/admin/dashboard" aria-current="page">
    <i class="fa-solid fa-table-columns"></i><span>Home</span>
  </a>

  <a class="mobile-nav-item" href="{{ route('admin.computers') }}">
    <i class="fa-solid fa-computer"></i><span>PCs</span>
  </a>

  <a class="mobile-nav-item" href="{{ route('admin.violations') }}">
    <i class="fa-solid fa-triangle-exclamation"></i><span>Violations</span>
    <span class="mobile-nav-badge" id="mobileViolationBadge" hidden>0</span>
  </a>

  <button type="button" class="mobile-nav-item" id="mobileSearchBtn" data-search-trigger aria-label="Search">
    <i class="fa-solid fa-magnifying-glass"></i><span>Search</span>
  </button>

  <button type="button" class="mobile-nav-item" id="mobileMenuBtn"
          aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">
    <i class="fa-solid fa-bars"></i><span>Menu</span>
  </button>
</nav>


<!-- =========================================================
     LOGOUT MODAL
========================================================== -->
<div class="logout-overlay" id="logoutModal" aria-hidden="true">
  <div class="logout-modal" role="dialog" aria-modal="true" aria-labelledby="logoutTitle" aria-describedby="logoutDesc">

    <div class="logout-icon"><i class="fa-solid fa-right-from-bracket"></i></div>

    <h2 id="logoutTitle">Log out of LABSTRACK?</h2>

    <p id="logoutDesc">
      You'll need to sign in again to access the administrator dashboard and lab controls.
    </p>

    <div class="logout-actions">
      <button type="button" class="logout-cancel" id="logoutCancel">Cancel</button>

      <form method="POST" action="{{ route('admin.logout') }}">
        @csrf
        <button type="submit" class="logout-confirm">Logout</button>
      </form>
    </div>
  </div>
</div>


<!-- TOAST -->
<div class="toast" id="toast" role="status" aria-live="polite">
  <i class="fa-solid fa-circle-check"></i>
  <span id="toastMsg"></span>
</div>


<script>
  window.adminRoutes = {
    logout: '/admin/logout',
    login: '/admin',
    dashboard: '/admin/dashboard'
  };
</script>

<script src="{{ asset('js/AdminDashboardFinal.js') }}"></script>
</body>
</html>