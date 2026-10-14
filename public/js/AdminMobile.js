/* =========================================================
   LABSTRACK — SHARED MOBILE LAYER
   Load LAST on every admin page (after the page's own JS).

   Adds, on tablets and phones only:
   - slide-out sidebar (hamburger, "Menu" tab, swipe from left edge)
   - search icon that opens the page's own search box
   - bottom tab bar: Home · PCs · Violations · Search · Menu
   Page functions are not changed; desktop behaves exactly as before.
   ========================================================= */

(function () {
    "use strict";

    var ROOT = document.documentElement;
    var DRAWER_MQ = window.matchMedia("(max-width: 1024px)");
    var PHONE_MQ = window.matchMedia("(max-width: 768px)");
    var BADGE_MS = 30000;

    var routes = window.adminRoutes || {};

    function routeOr(key, fallback) {
        var value = routes[key];
        return (typeof value === "string" && value && value.indexOf("{{") === -1)
            ? value
            : fallback;
    }

    var LINKS = {
        dashboard: routeOr("dashboard", "/admin/dashboard"),
        computers: routeOr("computers", "/admin/computers"),
        violations: routeOr("violations", "/admin/violations")
    };

    var searchBox = null;
    var searchInput = null;


    /* -------------------------------------------------------
       HELPERS
    ------------------------------------------------------- */

    function isDrawer() { return DRAWER_MQ.matches; }
    function isPhone() { return PHONE_MQ.matches; }

    function onMediaChange(mq, fn) {
        if (mq.addEventListener) mq.addEventListener("change", fn);
        else if (mq.addListener) mq.addListener(fn); // older Safari
    }

    function closeMenus() {
        try {
            if (typeof window.closeProfile === "function") window.closeProfile();
            else {
                var p = document.getElementById("profileMenu");
                if (p) p.classList.remove("open");
            }
            if (typeof window.closeNotif === "function") window.closeNotif();
            else {
                var n = document.getElementById("notifWrap");
                if (n) n.classList.remove("open");
            }
        } catch (e) { /* page helpers missing — ignore */ }
    }

    function samePath(url) {
        try {
            var a = new URL(url, window.location.origin).pathname.replace(/\/+$/, "");
            var b = window.location.pathname.replace(/\/+$/, "");
            return a === b;
        } catch (e) {
            return false;
        }
    }


    /* -------------------------------------------------------
       DRAWER
    ------------------------------------------------------- */

    function syncState() {
        var open = ROOT.classList.contains("m-nav-open");

        var menuTab = document.getElementById("mTabMenu");
        if (menuTab) {
            menuTab.classList.toggle("is-open", open);
            menuTab.setAttribute("aria-expanded", String(open));
        }

        var ham = document.getElementById("hamburgerBtn");
        if (ham && isDrawer()) ham.setAttribute("aria-expanded", String(open));
    }

    function openDrawer() {
        if (!isDrawer()) return;
        closeSearch(false);
        closeMenus();
        ROOT.classList.add("m-nav-open");
        syncState();
    }

    function closeDrawer() {
        if (!ROOT.classList.contains("m-nav-open")) return;
        ROOT.classList.remove("m-nav-open");
        syncState();
    }

    function toggleDrawer() {
        if (ROOT.classList.contains("m-nav-open")) closeDrawer();
        else openDrawer();
    }

    function buildDrawer() {
        var sidebar = document.querySelector(".app .sidebar");
        if (!sidebar) return;

        sidebar.id = sidebar.id || "sidebar";

        var close = document.createElement("button");
        close.type = "button";
        close.className = "m-sidebar-close";
        close.setAttribute("aria-label", "Close menu");
        close.innerHTML = '<i class="fa-solid fa-xmark"></i>';
        close.addEventListener("click", closeDrawer);
        sidebar.insertBefore(close, sidebar.firstChild);

        var overlay = document.createElement("div");
        overlay.className = "m-overlay";
        overlay.setAttribute("aria-hidden", "true");
        overlay.addEventListener("click", closeDrawer);
        document.body.appendChild(overlay);

        /* Close the drawer after tapping any item in it */
        sidebar.addEventListener("click", function (e) {
            if (!isDrawer()) return;
            if (e.target.closest(".nav-item, .profile-menu-item, a")) closeDrawer();
        });

        var ham = document.getElementById("hamburgerBtn");
        if (ham) ham.setAttribute("aria-controls", sidebar.id);
    }

    /*
     * Hamburger, same as the dashboard:
     * - desktop: collapse the sidebar to an icon rail (remembered)
     * - tablet/phone: open the slide-out drawer
     * Runs in the capture phase so the page's old toggle never fires.
     */
    var SIDEBAR_KEY = "labstrack.sidebar";

    function toggleCollapse() {
        var collapsed = ROOT.classList.toggle("sidebar-collapsed");
        try {
            localStorage.setItem(SIDEBAR_KEY, collapsed ? "collapsed" : "expanded");
        } catch (e) { /* storage unavailable */ }

        var ham = document.getElementById("hamburgerBtn");
        if (ham) ham.setAttribute("aria-expanded", String(!collapsed));
    }

    try {
        if (localStorage.getItem(SIDEBAR_KEY) === "collapsed") {
            ROOT.classList.add("sidebar-collapsed");
        }
    } catch (e) { /* storage unavailable */ }

    document.addEventListener("click", function (e) {
        var ham = e.target.closest && e.target.closest("#hamburgerBtn");
        if (!ham) return;
        e.preventDefault();
        e.stopImmediatePropagation();

        var app = document.querySelector(".app");
        if (app) app.classList.remove("sidebar-closed");

        if (isDrawer()) toggleDrawer();
        else toggleCollapse();
    }, true);


    /* -------------------------------------------------------
       SHELL — make the page's sidebar + topbar match the dashboard
    ------------------------------------------------------- */

    var CRUMBS = [
        [/subject|schedule/, "Academics"],
        [/computer|monitoring/, "Laboratory"],
        [/attendance|violation|report/, "Records"],
        [/announcement/, "Communication"],
        [/setting/, "Account"]
    ];

    function crumbForPage() {
        var path = window.location.pathname.toLowerCase();
        for (var i = 0; i < CRUMBS.length; i++) {
            if (CRUMBS[i][0].test(path)) return "Admin · " + CRUMBS[i][1];
        }
        return "Admin";
    }

    function enhanceShell() {
        var sidebar = document.querySelector(".app .sidebar");

        if (sidebar) {
            /* Logout buttons get the dashboard's red sidebar style */
            sidebar.querySelectorAll("button, a").forEach(function (el) {
                var handler = el.getAttribute("onclick") || "";
                if (handler.indexOf("openLogout") !== -1) {
                    el.className = "nav-item logout-item";
                }
            });

            /* Wrap each label in a <span> so the icon rail can hide it */
            sidebar.querySelectorAll(".nav-item").forEach(function (item) {
                item.setAttribute("type", item.getAttribute("type") || "button");

                Array.prototype.slice.call(item.childNodes).forEach(function (node) {
                    if (node.nodeType !== 3 || !node.textContent.trim()) return;
                    var span = document.createElement("span");
                    var text = node.textContent.trim();
                    span.textContent = text === "Setting" ? "Settings" : text;
                    item.replaceChild(span, node);
                });

                var label = item.textContent.trim();
                if (label && !item.title) item.title = label;
            });

            /* "Main" heading above the first group, like the dashboard */
            var firstItem = sidebar.querySelector(".nav-item");
            var group = firstItem && firstItem.parentElement;
            if (group && group !== sidebar) {
                var prev = group.previousElementSibling;
                if (!prev || !prev.classList.contains("nav-label")) {
                    var label = document.createElement("div");
                    label.className = "nav-label m-first";
                    label.textContent = "Main";
                    group.parentNode.insertBefore(label, group);
                }
            }

            /* "Monitoring active" footer */
            if (!sidebar.querySelector(".sidebar-foot")) {
                var foot = document.createElement("div");
                foot.className = "sidebar-foot";
                foot.title = "Monitoring active";
                foot.innerHTML =
                    '<span class="m-live-dot" aria-hidden="true"></span>' +
                    '<span class="sidebar-foot-text">Monitoring active</span>';
                sidebar.appendChild(foot);
            }
        }

        /* Page title + small crumb line under it */
        var title = document.querySelector(".app .topbar #pageTitle, .app .topbar h2");
        if (title && !title.closest(".topbar-title")) {
            title.textContent = title.textContent.trim();

            var wrap = document.createElement("div");
            wrap.className = "topbar-title";
            title.parentNode.insertBefore(wrap, title);
            wrap.appendChild(title);

            var crumb = document.createElement("span");
            crumb.className = "topbar-crumb";
            crumb.textContent = crumbForPage();
            wrap.appendChild(crumb);
        }

        var ham = document.getElementById("hamburgerBtn");
        if (ham) {
            ham.setAttribute("aria-label", "Toggle navigation");
            if (!isDrawer()) {
                ham.setAttribute("aria-expanded", String(!ROOT.classList.contains("sidebar-collapsed")));
            }
        }

        /* Notification dot clears once the menu is opened */
        var bell = document.querySelector(".app .bell");
        if (bell) {
            bell.addEventListener("click", function () {
                var dot = bell.querySelector(".dot");
                if (dot) dot.classList.add("seen");
            });
        }
    }


    /* -------------------------------------------------------
       SEARCH (phones)
    ------------------------------------------------------- */

    function openSearch() {
        if (!searchBox) return;
        closeDrawer();
        closeMenus();
        ROOT.classList.add("m-search-open");
        setTimeout(function () {
            if (searchInput) searchInput.focus();
        }, 30);
    }

    /* clear = true empties the box and lets the page refresh its list */
    function closeSearch(clear) {
        if (!ROOT.classList.contains("m-search-open")) return;

        if (clear && searchInput && searchInput.value) {
            searchInput.value = "";
            searchInput.dispatchEvent(new Event("input", { bubbles: true }));
        }

        ROOT.classList.remove("m-search-open");

        var results = searchBox && searchBox.querySelector(".global-search-results");
        if (results) results.classList.remove("show");

        if (searchInput) searchInput.blur();
    }

    function buildSearch() {
        searchBox = document.querySelector(".topbar .search-box");
        if (!searchBox) return;

        searchInput = searchBox.querySelector("input");
        if (searchInput) {
            searchInput.setAttribute("enterkeyhint", "search");
            if (!searchInput.getAttribute("aria-label")) {
                searchInput.setAttribute("aria-label", searchInput.placeholder || "Search");
            }
        }

        /* "/" hint on desktop, like the dashboard */
        var kbd = document.createElement("kbd");
        kbd.className = "m-kbd";
        kbd.setAttribute("aria-hidden", "true");
        kbd.textContent = "/";
        var results = searchBox.querySelector(".global-search-results");
        searchBox.insertBefore(kbd, results || null);

        var toggle = document.createElement("button");
        toggle.type = "button";
        toggle.className = "m-search-toggle";
        toggle.setAttribute("aria-label", "Search");
        toggle.setAttribute("data-m-search", "");
        toggle.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i>';
        toggle.addEventListener("click", openSearch);
        searchBox.parentNode.insertBefore(toggle, searchBox.nextSibling);

        var close = document.createElement("button");
        close.type = "button";
        close.className = "m-search-close";
        close.setAttribute("aria-label", "Close search");
        close.innerHTML = '<i class="fa-solid fa-xmark"></i>';
        close.addEventListener("click", function (e) {
            e.stopPropagation();
            closeSearch(true);
        });
        searchBox.appendChild(close);
    }

    /* Tap outside an empty search box closes it */
    document.addEventListener("click", function (e) {
        if (!ROOT.classList.contains("m-search-open") || !searchBox) return;
        if (searchBox.contains(e.target)) return;
        if (e.target.closest("[data-m-search]")) return;
        if (searchInput && searchInput.value.trim()) return;
        closeSearch(false);
    });


    /* -------------------------------------------------------
       BOTTOM TAB BAR (phones)
    ------------------------------------------------------- */

    function buildTabbar() {
        var items = [
            { href: LINKS.dashboard, icon: "fa-table-columns", label: "Home" },
            { href: LINKS.computers, icon: "fa-computer", label: "PCs" },
            { href: LINKS.violations, icon: "fa-triangle-exclamation", label: "Violations", badge: true }
        ];

        var html = items.map(function (item) {
            var active = samePath(item.href);
            return '<a class="m-tab' + (active ? ' active" aria-current="page' : '') + '" href="' + item.href + '">' +
                '<i class="fa-solid ' + item.icon + '"></i><span>' + item.label + '</span>' +
                (item.badge ? '<span class="m-tab-badge" id="mViolationBadge" hidden>0</span>' : '') +
                '</a>';
        }).join("");

        var columns = 4;

        if (searchBox) {
            columns = 5;
            html += '<button type="button" class="m-tab" id="mTabSearch" data-m-search aria-label="Search">' +
                '<i class="fa-solid fa-magnifying-glass"></i><span>Search</span></button>';
        }

        html += '<button type="button" class="m-tab" id="mTabMenu" aria-label="Open menu" aria-expanded="false">' +
            '<i class="fa-solid fa-bars"></i><span>Menu</span></button>';

        var nav = document.createElement("nav");
        nav.className = "m-tabbar";
        nav.setAttribute("aria-label", "Quick navigation");
        nav.style.gridTemplateColumns = "repeat(" + columns + ", minmax(0, 1fr))";
        nav.innerHTML = html;
        document.body.appendChild(nav);

        var searchTab = document.getElementById("mTabSearch");
        if (searchTab) {
            searchTab.addEventListener("click", function () {
                window.scrollTo({ top: 0, behavior: "smooth" });
                openSearch();
            });
        }

        var menuTab = document.getElementById("mTabMenu");
        if (menuTab) {
            menuTab.addEventListener("click", function () {
                closeSearch(false);
                toggleDrawer();
            });
        }
    }

    /* Unresolved violations count on the Violations tab */
    function refreshBadge() {
        var badge = document.getElementById("mViolationBadge");
        if (!badge || document.hidden || !isPhone()) return;

        fetch("/admin/dashboard/data", {
            headers: { "Accept": "application/json", "X-Requested-With": "XMLHttpRequest" },
            credentials: "same-origin",
            cache: "no-store"
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.violations) return;
                var n = Number(data.violations.unresolved) || 0;
                badge.hidden = n <= 0;
                badge.textContent = n > 99 ? "99+" : String(n);
            })
            .catch(function () { /* badge is optional */ });
    }


    /* -------------------------------------------------------
       HIDE TAB BAR WHILE TYPING (keyboard open)
    ------------------------------------------------------- */

    var TYPING_SELECTOR =
        'input:not([type="checkbox"]):not([type="radio"]):not([type="button"]):not([type="submit"]),' +
        'select, textarea, [contenteditable="true"]';

    document.addEventListener("focusin", function (e) {
        if (isPhone() && e.target.matches && e.target.matches(TYPING_SELECTOR)) {
            ROOT.classList.add("m-typing");
        }
    });

    document.addEventListener("focusout", function () {
        setTimeout(function () {
            var el = document.activeElement;
            if (!el || !el.matches || !el.matches(TYPING_SELECTOR)) {
                ROOT.classList.remove("m-typing");
            }
        }, 60);
    });


    /* -------------------------------------------------------
       SWIPE GESTURES
    ------------------------------------------------------- */

    function initSwipe() {
        var sidebar = document.querySelector(".app .sidebar");

        /* Swipe left on the open drawer to close it */
        if (sidebar) {
            var sx = 0, sy = 0, tracking = false;

            sidebar.addEventListener("touchstart", function (e) {
                if (!ROOT.classList.contains("m-nav-open")) return;
                sx = e.touches[0].clientX;
                sy = e.touches[0].clientY;
                tracking = true;
            }, { passive: true });

            sidebar.addEventListener("touchend", function (e) {
                if (!tracking) return;
                tracking = false;
                var dx = e.changedTouches[0].clientX - sx;
                var dy = e.changedTouches[0].clientY - sy;
                if (dx < -60 && Math.abs(dx) > Math.abs(dy)) closeDrawer();
            }, { passive: true });
        }

        /* Swipe right from the left screen edge to open it */
        var ex = 0, ey = 0, edge = false;

        document.addEventListener("touchstart", function (e) {
            if (!isDrawer() || ROOT.classList.contains("m-nav-open")) return;
            var t = e.touches[0];
            if (t.clientX > 24) return;
            ex = t.clientX;
            ey = t.clientY;
            edge = true;
        }, { passive: true });

        document.addEventListener("touchend", function (e) {
            if (!edge) return;
            edge = false;
            var dx = e.changedTouches[0].clientX - ex;
            var dy = e.changedTouches[0].clientY - ey;
            if (dx > 70 && Math.abs(dx) > Math.abs(dy) * 1.5) openDrawer();
        }, { passive: true });
    }


    /* -------------------------------------------------------
       KEYBOARD + VIEWPORT CHANGES
    ------------------------------------------------------- */

    document.addEventListener("keydown", function (e) {

        /* Press "/" anywhere to jump to search */
        if (e.key === "/" && searchInput) {
            var t = e.target;
            var tag = (t.tagName || "").toLowerCase();
            var typing = tag === "input" || tag === "textarea" || tag === "select" || t.isContentEditable;
            if (!typing) {
                e.preventDefault();
                if (isPhone()) openSearch();
                else searchInput.focus();
            }
            return;
        }

        if (e.key !== "Escape") return;
        closeDrawer();
        if (searchInput && !searchInput.value) closeSearch(false);
    });

    onMediaChange(DRAWER_MQ, function () {
        if (!isDrawer()) closeDrawer();
        syncState();
    });

    onMediaChange(PHONE_MQ, function () {
        if (!isPhone()) {
            ROOT.classList.remove("m-search-open", "m-typing");
        } else {
            refreshBadge();
        }
    });


    /* -------------------------------------------------------
       INIT
    ------------------------------------------------------- */

    function init() {
        enhanceShell();
        buildDrawer();
        buildSearch();
        buildTabbar();
        initSwipe();
        syncState();

        refreshBadge();
        setInterval(refreshBadge, BADGE_MS);
        document.addEventListener("visibilitychange", function () {
            if (!document.hidden) refreshBadge();
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();