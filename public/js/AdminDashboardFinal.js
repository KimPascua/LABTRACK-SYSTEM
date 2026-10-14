/* =========================================================
   LABSTRACK — ADMIN DASHBOARD
   ========================================================= */

"use strict";

const ROOT = document.documentElement;
const MOBILE_QUERY = window.matchMedia("(max-width: 1024px)");
const REFRESH_MS = 5000;
const SIDEBAR_KEY = "labstrack.sidebar";


/* =========================================================
   ROUTES & TITLES
   ========================================================= */

const pageTitles = {
    dashboard: "Dashboard",
    subjects: "Subject Management",
    schedule: "Schedule Management",
    computers: "Computer Management",
    attendance: "Attendance Records",
    monitoring: "Monitoring Applications",
    violations: "Violation Logs",
    reports: "Reports",
    announcement: "Announcement",
    settings: "Settings"
};

const pageRoutes = {
    dashboard: "/admin/dashboard",
    subjects: "/admin/subjects",
    schedule: "/admin/schedule",
    computers: "/admin/computers",
    attendance: "/admin/attendance",
    monitoring: "/admin/monitoring",
    violations: "/admin/violations",
    reports: "/admin/reports",
    announcement: "/admin/announcement",
    settings: "/admin/settings"
};


/* =========================================================
   HELPERS
   ========================================================= */

function $(id) {
    return document.getElementById(id);
}

function escapeHTML(value) {
    const div = document.createElement("div");
    div.textContent = value ?? "";
    return div.innerHTML;
}

/* Kept for compatibility with other admin pages. */
const escapeDashboardHTML = escapeHTML;
const escapeSearchHTML = escapeHTML;

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute("content") : "";
}

/* Only update text when it changes, and flash the value briefly. */
function setText(id, value) {
    const el = $(id);
    if (!el) return;

    const next = String(value);
    if (el.textContent === next) return;

    el.textContent = next;

    const flashTarget = el.closest(".stat-value") || el;
    flashTarget.classList.remove("is-updated");
    void flashTarget.offsetWidth; // restart animation
    flashTarget.classList.add("is-updated");
}

/* Avoid re-rendering (and flicker) when nothing changed. */
function setHTMLIfChanged(el, html) {
    if (!el) return;
    if (el.dataset.renderKey === html) return;
    el.dataset.renderKey = html;
    el.innerHTML = html;
}

/* Only allow same-origin, relative URLs from the search API. */
function safeUrl(url) {
    if (typeof url !== "string" || !url) return null;
    try {
        const parsed = new URL(url, window.location.origin);
        return parsed.origin === window.location.origin ? parsed.href : null;
    } catch (e) {
        return null;
    }
}

let sessionExpired = false;

function handleSessionExpired() {
    if (sessionExpired) return;
    sessionExpired = true;
    stopAutoRefresh();
    showToast("Your session has expired. Redirecting to sign in…", "warn");

    const loginUrl = (window.adminRoutes && window.adminRoutes.login) || "/admin";
    setTimeout(function () {
        window.location.href = loginUrl;
    }, 1800);
}

async function fetchJSON(url, options) {
    const response = await fetch(url, Object.assign({
        method: "GET",
        headers: {
            "Accept": "application/json",
            "X-Requested-With": "XMLHttpRequest"
        },
        credentials: "same-origin",
        cache: "no-store"
    }, options || {}));

    if (response.status === 401 || response.status === 419) {
        handleSessionExpired();
        throw new Error("Session expired");
    }

    if (!response.ok) {
        throw new Error("Request to " + url + " failed: " + response.status);
    }

    return response.json();
}


/* =========================================================
   PAGE NAVIGATION
   ========================================================= */

function goTo(pageId) {

    if (pageRoutes[pageId]) {
        window.location.href = pageRoutes[pageId];
        return;
    }

    document.querySelectorAll(".page").forEach(function (page) {
        page.classList.remove("active");
    });

    const page = $("page-" + pageId);
    if (page) page.classList.add("active");

    const title = $("pageTitle");
    if (title) title.textContent = pageTitles[pageId] || "Dashboard";

    closeProfile();
    closeNotif();
    closeSidebar();

    window.scrollTo({ top: 0, behavior: "smooth" });
}


/* =========================================================
   SIDEBAR
   (desktop: collapse to an icon rail · mobile: off-canvas drawer)
   ========================================================= */

function isMobileLayout() {
    return MOBILE_QUERY.matches;
}

function syncHamburgerState() {
    const btn = $("hamburgerBtn");
    if (!btn) return;

    const expanded = isMobileLayout()
        ? ROOT.classList.contains("nav-open")
        : !ROOT.classList.contains("sidebar-collapsed");

    btn.setAttribute("aria-expanded", String(expanded));

    /* Bottom tab bar "Menu" button mirrors the drawer state. */
    const menuBtn = $("mobileMenuBtn");
    if (menuBtn) {
        const open = ROOT.classList.contains("nav-open");
        menuBtn.setAttribute("aria-expanded", String(open));
        menuBtn.classList.toggle("is-open", open);
    }
}

function openSidebar() {
    ROOT.classList.add("nav-open");
    syncHamburgerState();

    const firstLink = document.querySelector(".sidebar .nav-item");
    if (firstLink) {
        setTimeout(function () { firstLink.focus({ preventScroll: true }); }, 150);
    }
}

function closeSidebar() {
    if (!ROOT.classList.contains("nav-open")) return;
    ROOT.classList.remove("nav-open");
    syncHamburgerState();
}

function toggleSidebar() {
    if (isMobileLayout()) {
        if (ROOT.classList.contains("nav-open")) {
            closeSidebar();
            const btn = $("hamburgerBtn");
            if (btn) btn.focus();
        } else {
            openSidebar();
        }
        return;
    }

    const collapsed = ROOT.classList.toggle("sidebar-collapsed");
    try {
        localStorage.setItem(SIDEBAR_KEY, collapsed ? "collapsed" : "expanded");
    } catch (e) { /* storage unavailable */ }

    syncHamburgerState();
}

function onViewportChange() {
    if (!isMobileLayout()) {
        ROOT.classList.remove("nav-open");
        closeMobileSearch();
    }
    syncHamburgerState();
}


/* =========================================================
   DROPDOWNS (profile + notifications)
   ========================================================= */

function toggleProfile(event) {
    if (event) event.stopPropagation();
    closeNotif();

    const menu = $("profileMenu");
    if (!menu) return;

    const open = menu.classList.toggle("open");
    const btn = $("profileBtn");
    if (btn) btn.setAttribute("aria-expanded", String(open));
}

function closeProfile() {
    const menu = $("profileMenu");
    if (menu) menu.classList.remove("open");
    const btn = $("profileBtn");
    if (btn) btn.setAttribute("aria-expanded", "false");
}

function toggleNotif(event) {
    if (event) event.stopPropagation();
    closeProfile();

    const wrap = $("notifWrap");
    if (!wrap) return;

    const open = wrap.classList.toggle("open");
    const btn = $("bellBtn");
    if (btn) btn.setAttribute("aria-expanded", String(open));

    /* Mark as seen once opened. */
    const dot = $("bellDot");
    if (open && dot) dot.classList.add("seen");
}

function closeNotif() {
    const wrap = $("notifWrap");
    if (wrap) wrap.classList.remove("open");
    const btn = $("bellBtn");
    if (btn) btn.setAttribute("aria-expanded", "false");
}


/* =========================================================
   LOGOUT MODAL
   ========================================================= */

let logoutReturnFocus = null;

function openLogout() {
    const modal = $("logoutModal");
    if (!modal) return;

    closeProfile();
    closeNotif();
    closeSidebar();

    logoutReturnFocus = document.activeElement;

    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");
    ROOT.classList.add("modal-open");

    const cancel = $("logoutCancel");
    if (cancel) setTimeout(function () { cancel.focus(); }, 50);
}

function closeLogout() {
    const modal = $("logoutModal");
    if (!modal || !modal.classList.contains("open")) return;

    modal.classList.remove("open");
    modal.setAttribute("aria-hidden", "true");
    ROOT.classList.remove("modal-open");

    if (logoutReturnFocus && typeof logoutReturnFocus.focus === "function") {
        logoutReturnFocus.focus();
    }
}

function isLogoutOpen() {
    const modal = $("logoutModal");
    return !!(modal && modal.classList.contains("open"));
}


/* =========================================================
   CLOCK & GREETING
   ========================================================= */

function tickClock() {
    const now = new Date();

    const heroTime = $("heroTime");
    const heroDate = $("heroDate");
    const greeting = $("heroGreeting");

    if (heroTime) {
        heroTime.textContent = now.toLocaleTimeString("en-US", {
            hour: "2-digit",
            minute: "2-digit"
        });
    }

    if (heroDate) {
        heroDate.textContent = now.toLocaleDateString("en-US", {
            weekday: "long",
            month: "long",
            day: "numeric",
            year: "numeric"
        });
    }

    if (greeting) {
        const h = now.getHours();
        greeting.textContent =
            h < 12 ? "Good morning" :
            h < 18 ? "Good afternoon" :
                     "Good evening";
    }
}

function markUpdated() {
    const el = $("lastUpdated");
    if (!el) return;
    el.textContent = "updated " + new Date().toLocaleTimeString("en-US", {
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit"
    });
}


/* =========================================================
   DASHBOARD STATS
   ========================================================= */

async function loadDashboardData() {
    try {
        const data = await fetchJSON("/admin/dashboard/data");

        if (!data || !data.success) {
            console.warn((data && data.message) || "Unable to load dashboard data.");
            return false;
        }

        if (data.students) {
            setText("registeredStudents", data.students.registered ?? 0);
        }

        if (data.attendance) {
            setText("studentsPresent", data.attendance.present ?? 0);
            setText("attendancePercentage", (data.attendance.percentage ?? 0) + "% of registered");
        }

        if (data.computers) {
            setText("activeComputers", data.computers.active ?? 0);
            setText("totalComputers", data.computers.total ?? 0);
        }

        if (data.violations) {
            setText("todaysViolations", data.violations.today ?? 0);
            setText("unresolvedViolations", (data.violations.unresolved ?? 0) + " unresolved");
            updateMobileViolationBadge(data.violations.unresolved ?? 0);
        }

        return true;

    } catch (error) {
        console.error("Error loading dashboard data:", error);
        return false;
    }
}


function updateMobileViolationBadge(count) {
    const badge = $("mobileViolationBadge");
    if (!badge) return;

    const n = Number(count) || 0;
    badge.hidden = n <= 0;
    badge.textContent = n > 99 ? "99+" : String(n);
}


/* =========================================================
   LIVE COMPUTER MONITORING
   ========================================================= */

let dashboardComputers = [];

function getDashboardPcState(status) {
    const normalized = String(status || "offline").toLowerCase().replace(/[\s_-]+/g, "");

    if (normalized === "inuse") {
        return { className: "inuse", icon: "fa-user", label: "In Use" };
    }
    if (normalized === "online" || normalized === "idle") {
        return { className: "online", icon: "fa-desktop", label: "Idle" };
    }
    if (normalized === "maintenance" || normalized === "maint") {
        return { className: "maint", icon: "fa-screwdriver-wrench", label: "Maintenance" };
    }
    if (normalized === "locked") {
        return { className: "offline", icon: "fa-lock", label: "Locked" };
    }
    return { className: "offline", icon: "fa-power-off", label: "Offline" };
}

function dashboardMonitorGridHTML(computers) {

    if (!computers || computers.length === 0) {
        return `
            <div class="dashboard-pc-empty">
                <i class="fa-solid fa-computer"></i>
                <span>No computers registered yet.</span>
            </div>
        `;
    }

    return computers.slice(0, 8).map(function (computer) {

        const state = getDashboardPcState(computer.status);
        const pcName = escapeHTML(computer.computer_name || computer.name || "Unnamed PC");
        const lab = escapeHTML(computer.lab || computer.lab_name || "Laboratory");

        return `
            <a class="pc-tile ${state.className}"
               href="/admin/computers"
               title="${pcName} · ${state.label} — open Computer Management">
                <i class="fa-solid ${state.icon}" aria-hidden="true"></i>
                <div class="pc-name">${pcName}</div>
                <div class="pc-state">${state.label}</div>
                <div class="dashboard-pc-lab">${lab}</div>
            </a>
        `;
    }).join("");
}

async function loadDashboardMonitorGrid() {
    const grid = $("dashMonitorGrid");
    if (!grid) return false;

    try {
        const data = await fetchJSON("/admin/computers/data");
        dashboardComputers = data.computers || [];
        setHTMLIfChanged(grid, dashboardMonitorGridHTML(dashboardComputers));
        return true;

    } catch (error) {
        console.error("Dashboard computer monitoring error:", error);
        setHTMLIfChanged(grid, `
            <div class="dashboard-pc-empty error">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>Unable to load computer status.</span>
            </div>
        `);
        return false;
    }
}


/* =========================================================
   RECENT ACTIVITIES (source: violation logs)
   ========================================================= */

function formatActivityTime(dateValue) {
    if (!dateValue) return "Unknown time";

    const date = new Date(dateValue);
    if (isNaN(date.getTime())) return String(dateValue);

    const diffMinutes = Math.floor((Date.now() - date.getTime()) / 60000);

    if (diffMinutes < 1) return "Just now";
    if (diffMinutes < 60) return diffMinutes + (diffMinutes === 1 ? " minute ago" : " minutes ago");

    const diffHours = Math.floor(diffMinutes / 60);
    if (diffHours < 24) return diffHours + (diffHours === 1 ? " hour ago" : " hours ago");

    const diffDays = Math.floor(diffHours / 24);
    return diffDays + (diffDays === 1 ? " day ago" : " days ago");
}

function getViolationIcon(violation) {
    const text = String(
        violation.violation_type || violation.type || violation.description || violation.reason || ""
    ).toLowerCase();

    if (text.includes("unauthorized") || text.includes("blocked") || text.includes("restricted")) {
        return "fa-ban";
    }
    if (text.includes("warning") || text.includes("violation")) {
        return "fa-triangle-exclamation";
    }
    return "fa-circle-exclamation";
}

function getViolationColor(violation) {
    const status = String(violation.status || "").toLowerCase();
    return (status.includes("resolved") || status.includes("cleared")) ? "green" : "red";
}

function activityRow(icon, tone, title, sub, extra) {
    return `
        <div class="list-row">
            <div class="row-icon tone-${tone}"><i class="fa-solid ${icon}"></i></div>
            <div class="row-main">
                <div class="row-title">${title}</div>
                <div class="row-sub">${sub}</div>
            </div>
            ${extra || ""}
        </div>
    `;
}

async function loadRecentActivities() {
    const container = $("recentActivityList");
    if (!container) return false;

    try {
        const data = await fetchJSON("/admin/violations/data");
        const violations = data.violations || [];

        if (violations.length === 0) {
            setHTMLIfChanged(container, `
                <div class="empty-state">
                    <span class="empty-icon tone-green"><i class="fa-solid fa-circle-check"></i></span>
                    <div class="row-title">No recent violations</div>
                    <div class="row-sub">Everything looks clear.</div>
                </div>
            `);
            return true;
        }

        const html = violations.slice(0, 5).map(function (violation) {

            const pc = violation.computer_name || violation.pc_name || violation.computer || violation.pc || "";
            const student = violation.student_name || violation.fullname || violation.student || "";
            const type = violation.violation_type || violation.type || violation.description ||
                         violation.reason || "Violation detected";
            const timestamp = violation.created_at || violation.violation_time || violation.time || violation.date;
            const color = getViolationColor(violation);

            let title = escapeHTML(type);
            if (pc) title += ' <span class="row-muted">on</span> ' + escapeHTML(pc);

            const sub = (student ? escapeHTML(student) + " · " : "") + escapeHTML(formatActivityTime(timestamp));
            const badge = color === "green"
                ? '<span class="badge green">Resolved</span>'
                : '<span class="badge red">Open</span>';

            return activityRow(getViolationIcon(violation), color, title, sub, badge);

        }).join("");

        setHTMLIfChanged(container, html);
        return true;

    } catch (error) {
        console.error("Error loading recent activities:", error);
        setHTMLIfChanged(container, activityRow(
            "fa-triangle-exclamation",
            "red",
            "Unable to load recent activities",
            "Retrying automatically…"
        ));
        return false;
    }
}


/* =========================================================
   SYSTEM STATUS
   ========================================================= */

const systemStatus = [
    { name: "Monitoring Server",      state: "ok",   val: "Operational" },
    { name: "Attendance Sync",        state: "ok",   val: "Operational" },
    { name: "Lab Network — CLAB1–3",  state: "ok",   val: "Stable" },
    { name: "Lab Network — CLAB4",    state: "warn", val: "Degraded" },
    { name: "Backup Service",         state: "ok",   val: "Last backup 2h ago" }
];

function renderSystemStatus() {
    const container = $("systemStatusList");
    if (!container) return;

    container.innerHTML = systemStatus.map(function (s) {
        const state = ["ok", "warn", "err"].includes(s.state) ? s.state : "ok";
        return `
            <div class="status-item">
                <span class="status-dot ${state}" aria-hidden="true"></span>
                <span class="status-name">${escapeHTML(s.name)}</span>
                <span class="status-val status-${state}">${escapeHTML(s.val)}</span>
            </div>
        `;
    }).join("");
}


/* =========================================================
   TOAST
   ========================================================= */

let toastTimer;

function showToast(message, type) {
    const toast = $("toast");
    const toastMsg = $("toastMsg");
    if (!toast || !toastMsg) return;

    toastMsg.textContent = message;
    toast.classList.toggle("warn", type === "warn");
    toast.classList.add("show");

    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () {
        toast.classList.remove("show");
    }, 2600);
}


/* =========================================================
   GLOBAL SEARCH
   ========================================================= */

let globalSearchTimer = null;
let globalSearchRequest = null;
let searchActiveIndex = -1;

function setSearchExpanded(expanded) {
    const input = $("globalSearchInput");
    if (input) input.setAttribute("aria-expanded", String(expanded));
}

function hideSearchResults() {
    const container = $("globalSearchResults");
    if (!container) return;
    container.classList.remove("show");
    setSearchExpanded(false);
    searchActiveIndex = -1;
}

function showSearchContainer(html) {
    const container = $("globalSearchResults");
    if (!container) return;
    container.innerHTML = html;
    container.classList.add("show");
    setSearchExpanded(true);
}

function renderGlobalSearchResults(results) {

    if (!results || results.length === 0) {
        showSearchContainer(`
            <div class="global-search-empty">
                <i class="fa-solid fa-magnifying-glass"></i>
                <span>No results found</span>
            </div>
        `);
        return;
    }

    searchActiveIndex = -1;

    const allowedTypes = ["student", "computer", "violation"];

    showSearchContainer(results.map(function (result, index) {
        const url = safeUrl(result.url) || "#";
        const type = allowedTypes.includes(result.type) ? result.type : "";

        return `
            <a class="global-search-result"
               href="${escapeHTML(url)}"
               role="option"
               id="search-opt-${index}"
               data-index="${index}">
                <div class="global-search-icon ${type}">
                    <i class="fa-solid ${escapeHTML(result.icon || "fa-magnifying-glass")}"></i>
                </div>
                <div class="global-search-text">
                    <div class="global-search-title">${escapeHTML(result.title)}</div>
                    <div class="global-search-subtitle">${escapeHTML(result.subtitle)}</div>
                </div>
                <i class="fa-solid fa-arrow-turn-down global-search-enter" aria-hidden="true"></i>
            </a>
        `;
    }).join(""));
}

async function performGlobalSearch(query) {

    if (!query.trim()) {
        hideSearchResults();
        return;
    }

    showSearchContainer(`
        <div class="global-search-loading">
            <i class="fa-solid fa-spinner fa-spin"></i>
            <span>Searching…</span>
        </div>
    `);

    if (globalSearchRequest) globalSearchRequest.abort();
    globalSearchRequest = new AbortController();

    try {
        const data = await fetchJSON(
            "/admin/global-search?q=" + encodeURIComponent(query),
            { signal: globalSearchRequest.signal }
        );

        if (!data.success) {
            throw new Error(data.message || "Search failed.");
        }

        renderGlobalSearchResults(data.results || []);

    } catch (error) {
        if (error.name === "AbortError") return;

        console.error("Global search error:", error);
        showSearchContainer(`
            <div class="global-search-empty error">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>Search is unavailable right now.</span>
            </div>
        `);
    }
}

function openGlobalSearchResult(url) {
    const target = safeUrl(url);
    const input = $("globalSearchInput");
    if (input) input.value = "";
    hideSearchResults();
    if (target) window.location.href = target;
}

function moveSearchSelection(step) {
    const container = $("globalSearchResults");
    const input = $("globalSearchInput");
    if (!container) return;

    const items = container.querySelectorAll(".global-search-result");
    if (!items.length) return;

    searchActiveIndex = (searchActiveIndex + step + items.length) % items.length;

    items.forEach(function (item, i) {
        const active = i === searchActiveIndex;
        item.classList.toggle("is-active", active);
        item.setAttribute("aria-selected", String(active));
        if (active) {
            item.scrollIntoView({ block: "nearest" });
            if (input) input.setAttribute("aria-activedescendant", item.id);
        }
    });
}

function openMobileSearch() {
    ROOT.classList.add("search-open");
    const input = $("globalSearchInput");
    if (input) setTimeout(function () { input.focus(); }, 30);
}

function closeMobileSearch() {
    if (!ROOT.classList.contains("search-open")) return;
    ROOT.classList.remove("search-open");
    hideSearchResults();
    const toggle = $("searchToggle");
    if (toggle && isMobileLayout()) toggle.focus();
}

function initGlobalSearch() {
    const input = $("globalSearchInput");
    const searchBox = $("globalSearchBox");
    const results = $("globalSearchResults");

    if (!input || !searchBox || !results) return;

    input.addEventListener("input", function () {
        const query = this.value.trim();
        clearTimeout(globalSearchTimer);
        globalSearchTimer = setTimeout(function () {
            performGlobalSearch(query);
        }, 250);
    });

    input.addEventListener("focus", function () {
        if (this.value.trim() && results.innerHTML.trim()) {
            results.classList.add("show");
            setSearchExpanded(true);
        }
    });

    input.addEventListener("keydown", function (event) {

        if (event.key === "ArrowDown") {
            event.preventDefault();
            moveSearchSelection(1);
            return;
        }

        if (event.key === "ArrowUp") {
            event.preventDefault();
            moveSearchSelection(-1);
            return;
        }

        if (event.key === "Escape") {
            event.stopPropagation();
            if (this.value) {
                this.value = "";
                hideSearchResults();
            } else {
                closeMobileSearch();
                this.blur();
            }
            return;
        }

        if (event.key === "Enter" && results.classList.contains("show")) {
            event.preventDefault();
            const items = results.querySelectorAll(".global-search-result");
            const chosen = items[searchActiveIndex] || items[0];
            if (chosen) openGlobalSearchResult(chosen.getAttribute("href"));
        }
    });

    results.addEventListener("click", function (event) {
        const item = event.target.closest(".global-search-result");
        if (!item) return;
        event.preventDefault();
        openGlobalSearchResult(item.getAttribute("href"));
    });

    const toggle = $("searchToggle");
    if (toggle) toggle.addEventListener("click", openMobileSearch);

    const close = $("searchClose");
    if (close) close.addEventListener("click", closeMobileSearch);

    /* Press "/" anywhere to jump to search. */
    document.addEventListener("keydown", function (event) {
        const tag = (event.target.tagName || "").toLowerCase();
        const typing = tag === "input" || tag === "textarea" || tag === "select" || event.target.isContentEditable;

        if (event.key === "/" && !typing && !isLogoutOpen()) {
            event.preventDefault();
            if (isMobileLayout() && window.matchMedia("(max-width: 768px)").matches) {
                openMobileSearch();
            } else {
                input.focus();
            }
        }
    });
}


/* =========================================================
   GLOBAL CLICK & KEYBOARD
   ========================================================= */

document.addEventListener("click", function (event) {

    const profileMenu = $("profileMenu");
    const notifWrap = $("notifWrap");
    const searchBox = $("globalSearchBox");
    const searchToggle = $("searchToggle");

    if (profileMenu && !profileMenu.contains(event.target)) closeProfile();
    if (notifWrap && !notifWrap.contains(event.target)) closeNotif();

    if (searchBox && !searchBox.contains(event.target)) {
        hideSearchResults();
        const isSearchTrigger =
            (searchToggle && searchToggle.contains(event.target)) ||
            event.target.closest("[data-search-trigger]");
        if (!isSearchTrigger) {
            if (ROOT.classList.contains("search-open")) {
                ROOT.classList.remove("search-open");
            }
        }
    }

    /* Any [data-action="logout"] trigger. */
    const logoutTrigger = event.target.closest('[data-action="logout"]');
    if (logoutTrigger) {
        event.preventDefault();
        openLogout();
    }
});

document.addEventListener("keydown", function (event) {

    if (event.key === "Tab" && isLogoutOpen()) {
        trapFocus(event, document.querySelector(".logout-modal"));
        return;
    }

    if (event.key === "Tab" && ROOT.classList.contains("nav-open") && isMobileLayout()) {
        trapFocus(event, $("sidebar"));
        return;
    }

    if (event.key !== "Escape") return;

    if (isLogoutOpen()) { closeLogout(); return; }

    if (ROOT.classList.contains("nav-open")) {
        closeSidebar();
        const btn = $("hamburgerBtn");
        if (btn) btn.focus();
        return;
    }

    closeProfile();
    closeNotif();
    closeMobileSearch();

    if (typeof closeSubjectModal === "function") closeSubjectModal();
    if (typeof closeBlockAppModal === "function") closeBlockAppModal();
});

function trapFocus(event, container) {
    if (!container) return;

    const focusable = container.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    if (!focusable.length) return;

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}


/* =========================================================
   AUTO REFRESH
   (pauses while the tab is hidden; no overlapping requests)
   ========================================================= */

let refreshTimer = null;
let refreshing = false;

async function refreshDashboard() {
    if (refreshing || document.hidden || sessionExpired) return;
    refreshing = true;

    try {
        const results = await Promise.all([
            loadDashboardData(),
            loadDashboardMonitorGrid(),
            loadRecentActivities()
        ]);

        if (results.some(Boolean)) markUpdated();

    } finally {
        refreshing = false;
    }
}

function startAutoRefresh() {
    stopAutoRefresh();
    refreshTimer = setInterval(refreshDashboard, REFRESH_MS);
}

function stopAutoRefresh() {
    if (refreshTimer) clearInterval(refreshTimer);
    refreshTimer = null;
}

document.addEventListener("visibilitychange", function () {
    if (!document.hidden) refreshDashboard();
});


/* =========================================================
   MANUAL REFRESH (tap the "Live" chip)
   ========================================================= */

async function manualRefresh() {
    const btn = $("refreshBtn");
    if (!btn || btn.classList.contains("is-spinning")) return;

    btn.classList.add("is-spinning");
    btn.disabled = true;

    try {
        /* Wait for any refresh already in progress, then run a fresh one. */
        while (refreshing) {
            await new Promise(function (r) { setTimeout(r, 100); });
        }
        await refreshDashboard();
        if (!sessionExpired) showToast("Dashboard updated");
    } finally {
        btn.classList.remove("is-spinning");
        btn.disabled = false;
    }
}


/* =========================================================
   MOBILE: BOTTOM TAB BAR + SWIPE GESTURES
   ========================================================= */

function initMobileNav() {

    const searchBtn = $("mobileSearchBtn");
    if (searchBtn) {
        searchBtn.addEventListener("click", function () {
            closeSidebar();
            closeProfile();
            closeNotif();
            window.scrollTo({ top: 0, behavior: "smooth" });
            openMobileSearch();
        });
    }

    const menuBtn = $("mobileMenuBtn");
    if (menuBtn) {
        menuBtn.addEventListener("click", function () {
            closeMobileSearch();
            closeProfile();
            closeNotif();
            toggleSidebar();
        });
    }

    /* Swipe left on the drawer to close it. */
    const sidebar = $("sidebar");
    let startX = 0, startY = 0, tracking = false;

    if (sidebar) {
        sidebar.addEventListener("touchstart", function (e) {
            if (!ROOT.classList.contains("nav-open")) return;
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            tracking = true;
        }, { passive: true });

        sidebar.addEventListener("touchend", function (e) {
            if (!tracking) return;
            tracking = false;
            const dx = e.changedTouches[0].clientX - startX;
            const dy = e.changedTouches[0].clientY - startY;
            if (dx < -60 && Math.abs(dx) > Math.abs(dy)) closeSidebar();
        }, { passive: true });
    }

    /* Swipe right from the left screen edge to open it. */
    let edgeX = 0, edgeY = 0, edgeTracking = false;

    document.addEventListener("touchstart", function (e) {
        if (!isMobileLayout() || ROOT.classList.contains("nav-open") || isLogoutOpen()) return;
        const t = e.touches[0];
        if (t.clientX > 24) return;
        edgeX = t.clientX;
        edgeY = t.clientY;
        edgeTracking = true;
    }, { passive: true });

    document.addEventListener("touchend", function (e) {
        if (!edgeTracking) return;
        edgeTracking = false;
        const dx = e.changedTouches[0].clientX - edgeX;
        const dy = e.changedTouches[0].clientY - edgeY;
        if (dx > 70 && Math.abs(dx) > Math.abs(dy) * 1.5) openSidebar();
    }, { passive: true });
}


/* =========================================================
   INITIALIZATION
   ========================================================= */

function initDashboard() {

    initMobileNav();

    const refreshBtn = $("refreshBtn");
    if (refreshBtn) refreshBtn.addEventListener("click", manualRefresh);

    tickClock();
    setInterval(tickClock, 15000);

    renderSystemStatus();
    initGlobalSearch();

    refreshDashboard();
    startAutoRefresh();

    /* Sidebar */
    const hamburger = $("hamburgerBtn");
    if (hamburger) hamburger.addEventListener("click", toggleSidebar);

    const sidebarClose = $("sidebarClose");
    if (sidebarClose) sidebarClose.addEventListener("click", closeSidebar);

    const overlay = $("sidebarOverlay");
    if (overlay) overlay.addEventListener("click", closeSidebar);

    /* Close the drawer after choosing a link on mobile. */
    document.querySelectorAll(".sidebar .nav-item").forEach(function (item) {
        item.addEventListener("click", function () {
            if (isMobileLayout() && !item.matches('[data-action="logout"]')) closeSidebar();
        });
    });

    if (MOBILE_QUERY.addEventListener) {
        MOBILE_QUERY.addEventListener("change", onViewportChange);
    } else if (MOBILE_QUERY.addListener) {
        MOBILE_QUERY.addListener(onViewportChange); // older Safari
    }
    syncHamburgerState();

    /* Dropdowns */
    const profileBtn = $("profileBtn");
    if (profileBtn) profileBtn.addEventListener("click", toggleProfile);

    const bellBtn = $("bellBtn");
    if (bellBtn) bellBtn.addEventListener("click", toggleNotif);

    /* Logout modal */
    const cancel = $("logoutCancel");
    if (cancel) cancel.addEventListener("click", closeLogout);

    const logoutModal = $("logoutModal");
    if (logoutModal) {
        logoutModal.addEventListener("click", function (event) {
            if (event.target === this) closeLogout();
        });
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initDashboard);
} else {
    initDashboard();
}