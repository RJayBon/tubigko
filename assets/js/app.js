/* TubigKo — client-side UI (nav, search/filter, modals, tabs, cart, live notifications, password toggle). */
(function () {
  "use strict";

  var CART_KEY = "tubigko:cart";

  // -------- Inject critical password-toggle CSS (self-contained) --------
  (function injectPwStyles() {
    if (document.getElementById("tubigko-pw-styles")) return;
    var css =
      '.pw-wrap{position:relative !important;display:block !important;width:100% !important;}' +
      '.pw-wrap input{width:100% !important;}' +
      '.pw-toggle{' +
        'position:absolute !important;' +
        'top:50% !important;right:.5rem !important;' +
        'transform:translateY(-50%) !important;' +
        'width:2rem !important;height:2rem !important;' +
        'display:inline-flex !important;align-items:center !important;justify-content:center !important;' +
        'background:transparent;border:0;border-radius:8px;padding:0;margin:0;line-height:0;' +
        'color:#5b7480;cursor:pointer;' +
        'transition:color .15s, background .15s;' +
      '}' +
      '.pw-toggle:hover{color:#075064;background:#dff2f6;}' +
      '.pw-toggle:focus-visible{outline:2px solid #17a3bd;outline-offset:2px;}' +
      '.pw-toggle.is-visible{color:#0a6b83;}' +
      '.pw-toggle svg{display:block;width:18px;height:18px;pointer-events:none;}';
    var style = document.createElement("style");
    style.id = "tubigko-pw-styles";
    style.appendChild(document.createTextNode(css));
    document.head.appendChild(style);
  })();

  // -------- Mobile sidebar --------
  var menuToggle = document.getElementById("menuToggle");
  var sidebar = document.getElementById("sidebar");
  if (menuToggle && sidebar) {
    menuToggle.addEventListener("click", function () {
      sidebar.classList.toggle("is-open");
    });
    document.addEventListener("click", function (e) {
      if (sidebar.classList.contains("is-open") && !sidebar.contains(e.target) && e.target !== menuToggle) {
        sidebar.classList.remove("is-open");
      }
    });
  }

  // -------- Modals --------
  window.openModal = function (id) {
    var m = document.getElementById(id);
    if (m) m.hidden = false;
  };
  window.closeModal = function (id) {
    var m = document.getElementById(id);
    if (m) m.hidden = true;
  };
  document.addEventListener("click", function (e) {
    if (e.target.classList && e.target.classList.contains("modal")) e.target.hidden = true;
  });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      document.querySelectorAll(".modal:not([hidden])").forEach(function (m) { m.hidden = true; });
    }
  });

  // -------- "View details" from data-detail JSON --------
  document.querySelectorAll("[data-detail]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var data;
      try { data = JSON.parse(btn.getAttribute("data-detail")); } catch (err) { return; }
      var title = document.getElementById("detailTitle");
      var body  = document.getElementById("detailBody");
      if (!body) return;
      if (title && data.__title) title.textContent = data.__title;
      var rows = "";
      Object.keys(data).forEach(function (key) {
        if (key === "__title") return;
        var v = data[key] === null || data[key] === undefined ? "" : String(data[key]);
        rows += '<div class="cart-line"><span>' + escapeHtml(key) + '</span><strong>' + escapeHtml(v) + '</strong></div>';
      });
      body.innerHTML = rows || '<p class="empty">No details available.</p>';
      openModal("detailModal");
    });
  });

  // -------- Tabs --------
  document.querySelectorAll("[data-tabs]").forEach(function (group) {
    var groupName = group.getAttribute("data-tabs");
    var buttons = group.querySelectorAll("[data-tab]");
    var panels  = document.querySelectorAll('[data-tabpanel="' + groupName + '"]');
    buttons.forEach(function (btn) {
      btn.addEventListener("click", function () {
        var target = btn.getAttribute("data-tab");
        buttons.forEach(function (b) { b.classList.toggle("is-active", b === btn); });
        panels.forEach(function (p) { p.hidden = p.getAttribute("data-panel") !== target; });
      });
    });
  });

  // -------- Table search + filter (idempotent) --------
  var wiredTables = new WeakSet();
  function wireTable(tableId) {
    var table = document.getElementById(tableId);
    if (!table || wiredTables.has(table)) return;
    wiredTables.add(table);

    var searchInput  = document.querySelector('[data-search="' + tableId + '"]');
    var filterSelect = document.querySelector('[data-filter="' + tableId + '"]');
    var emptyRow     = table.querySelector("[data-empty]");

    function apply() {
      var term = searchInput ? searchInput.value.trim().toLowerCase() : "";
      var filterVal = filterSelect ? filterSelect.value : "";
      var visible = 0;
      table.querySelectorAll("tbody tr:not([data-empty])").forEach(function (row) {
        var text = row.textContent.toLowerCase();
        var matchesText = !term || text.indexOf(term) !== -1;
        var matchesFilter = !filterVal || row.getAttribute("data-filter-value") === filterVal;
        var show = matchesText && matchesFilter;
        row.hidden = !show;
        if (show) visible++;
      });
      if (emptyRow) emptyRow.hidden = visible !== 0;
    }
    if (searchInput)  searchInput.addEventListener("input", apply);
    if (filterSelect) filterSelect.addEventListener("change", apply);
  }
  document.querySelectorAll("[data-search]").forEach(function (el) { wireTable(el.getAttribute("data-search")); });
  document.querySelectorAll("[data-filter]").forEach(function (el) { wireTable(el.getAttribute("data-filter")); });

  // -------- CSV export + print --------
  window.exportTable = function (tableId, filename) {
    var table = document.getElementById(tableId);
    if (!table) return;
    var rows = [];
    table.querySelectorAll("tr").forEach(function (tr) {
      if (tr.hidden) return;
      var cols = [];
      tr.querySelectorAll("th, td").forEach(function (cell) {
        if (cell.classList.contains("no-print")) return;
        cols.push('"' + cell.textContent.replace(/\s+/g, " ").trim().replace(/"/g, '""') + '"');
      });
      if (cols.length) rows.push(cols.join(","));
    });
    var csv = rows.join("\r\n");
    var blob = new Blob(["\ufeff" + csv], { type: "text/csv;charset=utf-8;" });
    var link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = (filename || "export") + ".csv";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  window.printPage = function () { window.print(); };

  // -------- Show/Hide password toggles --------
  document.querySelectorAll("[data-toggle-password]").forEach(function (input) {
    if (input.dataset.toggleWired === "1") return;
    input.dataset.toggleWired = "1";

    var wrap = document.createElement("div");
    wrap.className = "pw-wrap";
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    input.style.paddingRight = "2.75rem";

    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "pw-toggle";
    btn.setAttribute("aria-label", "Show password");
    btn.setAttribute("tabindex", "-1");
    btn.innerHTML =
      '<svg class="pw-toggle__eye" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">' +
        '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ' +
          'd="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>' +
        '<circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/>' +
      '</svg>' +
      '<svg class="pw-toggle__eye-off" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" style="display:none">' +
        '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ' +
          'd="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>' +
        '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ' +
          'd="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>' +
        '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ' +
          'd="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>' +
        '<line x1="1" y1="1" x2="23" y2="23" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>' +
      '</svg>';

    btn.addEventListener("click", function (ev) {
      ev.preventDefault();
      ev.stopPropagation();

      var wasFocused = document.activeElement === input;
      var selStart = input.selectionStart;
      var selEnd   = input.selectionEnd;
      if (selStart === null || selEnd === null) {
        var len = input.value.length;
        selStart = selEnd = len;
      }

      var show = input.type === "password";
      input.type = show ? "text" : "password";

      btn.setAttribute("aria-label", show ? "Hide password" : "Show password");
      btn.classList.toggle("is-visible", show);
      btn.querySelector(".pw-toggle__eye").style.display     = show ? "none" : "";
      btn.querySelector(".pw-toggle__eye-off").style.display = show ? "" : "none";

      if (!wasFocused) {
        input.focus();
        var end = input.value.length;
        try { input.setSelectionRange(end, end); } catch (e) {}
        return;
      }

      function restoreCaret() {
        try {
          input.focus();
          input.setSelectionRange(selStart, selEnd);
        } catch (e) {}
      }
      restoreCaret();
      if (window.requestAnimationFrame) requestAnimationFrame(restoreCaret);
      setTimeout(restoreCaret, 0);
    });

    btn.addEventListener("mousedown", function (ev) { ev.preventDefault(); });

    wrap.appendChild(btn);
  });

  // -------- Shopping cart --------
  // Clear cart if navigated with ?clear_cart=1 (post-checkout redirect).
  if (/[?&]clear_cart=1(?:&|$)/.test(window.location.search)) {
    try { localStorage.removeItem(CART_KEY); } catch (e) {}
  }

  function getCart() {
    try { return JSON.parse(localStorage.getItem(CART_KEY)) || []; } catch (e) { return []; }
  }
  function saveCart(cart) {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
    renderCart();
  }
  function addToCart(item) {
    var cart = getCart();
    var existing = cart.find(function (c) { return String(c.id) === String(item.id); });
    if (existing) existing.qty += 1;
    else cart.push({ id: item.id, name: item.name, price: Number(item.price), qty: 1 });
    saveCart(cart);
  }
  function changeQty(id, delta) {
    var cart = getCart();
    var line = cart.find(function (c) { return String(c.id) === String(id); });
    if (!line) return;
    line.qty += delta;
    if (line.qty <= 0) cart = cart.filter(function (c) { return String(c.id) !== String(id); });
    saveCart(cart);
  }
  function removeFromCart(id) {
    saveCart(getCart().filter(function (c) { return String(c.id) !== String(id); }));
  }

  function renderCart() {
    var lines = document.getElementById("cartLines");
    var totalEl = document.getElementById("cartTotal");
    var countEl = document.getElementById("cartCount");
    if (!lines) return;
    var cart = getCart();

    if (!cart.length) {
      lines.innerHTML = '<p class="empty">No gallons selected yet.</p>';
    } else {
      lines.innerHTML = cart.map(function (item) {
        return (
          '<div class="cart-line">' +
            '<span>' + escapeHtml(item.name) + ' <br><small class="muted">' + peso(item.price) + ' each</small></span>' +
            '<span style="display:flex;align-items:center;gap:.4rem">' +
              '<button type="button" class="icon-btn" data-qty-minus="' + item.id + '" aria-label="Decrease">&minus;</button>' +
              '<strong>' + item.qty + '</strong>' +
              '<button type="button" class="icon-btn" data-qty-plus="' + item.id + '" aria-label="Increase">+</button>' +
              '<button type="button" class="icon-btn" data-remove="' + item.id + '" aria-label="Remove">&times;</button>' +
            '</span>' +
          '</div>'
        );
      }).join("");
    }

    var total = cart.reduce(function (s, c) { return s + c.price * c.qty; }, 0);
    var count = cart.reduce(function (n, c) { return n + c.qty; }, 0);
    if (totalEl) totalEl.textContent = peso(total);
    if (countEl) countEl.textContent = String(count);

    lines.querySelectorAll("[data-qty-plus]").forEach(function (b) {
      b.addEventListener("click", function () { changeQty(b.getAttribute("data-qty-plus"), 1); });
    });
    lines.querySelectorAll("[data-qty-minus]").forEach(function (b) {
      b.addEventListener("click", function () { changeQty(b.getAttribute("data-qty-minus"), -1); });
    });
    lines.querySelectorAll("[data-remove]").forEach(function (b) {
      b.addEventListener("click", function () { removeFromCart(b.getAttribute("data-remove")); });
    });
  }

  document.querySelectorAll("[data-add-gallon]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var item;
      try { item = JSON.parse(btn.getAttribute("data-add-gallon")); } catch (e) { return; }
      addToCart(item);
      var original = btn.textContent;
      btn.textContent = "Added!";
      setTimeout(function () { btn.textContent = original; }, 900);
    });
  });

  var checkoutForm = document.getElementById("checkoutForm");
  if (checkoutForm) {
    checkoutForm.addEventListener("submit", function (e) {
      var cart = getCart();
      if (!cart.length) {
        e.preventDefault();
        alert("Your cart is empty. Please select at least one gallon first.");
        window.location.href = "gallons.php";
        return;
      }
      var field = document.getElementById("cartJsonField");
      if (field) {
        field.value = JSON.stringify(cart.map(function (c) {
          return { id: c.id, qty: c.qty };
        }));
      }
    });
  }

  if (document.getElementById("cartLines")) renderCart();

  // -------- Helpers --------
  function peso(n) {
    return "\u20B1" + Number(n || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  // ---------------------------------------------------------------
  // Live notifications (polling — every page, every tab)
  // ---------------------------------------------------------------
  (function liveNotifications() {
    var apiMeta  = document.querySelector('meta[name="tubigko-notif-api"]');
    var userMeta = document.querySelector('meta[name="tubigko-user"]');
    var roleMeta = document.querySelector('meta[name="tubigko-role"]');
    if (!apiMeta || !userMeta) return;

    var apiUrl = apiMeta.getAttribute("content");
    var userId = userMeta.getAttribute("content");
    var role   = roleMeta ? roleMeta.getAttribute("content") : "customer";
    if (!apiUrl || !userId || userId === "0") return;

    var isNotificationsPage = /notifications\.php$/.test(window.location.pathname);

    var POLL_MS  = 5000;
    var STOREKEY = "tubigko:notif:lastId:" + userId;
    var lastId   = parseInt(sessionStorage.getItem(STOREKEY) || "0", 10) || 0;
    var inFlight = false;
    var timerId  = null;

    var list = document.querySelector("[data-notif-list]");
    if (list) {
      var domMax = parseInt(list.getAttribute("data-notif-max-id") || "0", 10) || 0;
      if (domMax > lastId) {
        lastId = domMax;
        sessionStorage.setItem(STOREKEY, String(lastId));
      }
    }

    function setBadge(n) {
      var badges = document.querySelectorAll(".notif-count");
      if (badges.length === 0) {
        var bell = document.querySelector('a[href$="notifications.php"]');
        if (bell) {
          var span = document.createElement("span");
          span.className = "notif-count";
          bell.appendChild(span);
          badges = [span];
        }
      }
      badges.forEach(function (el) {
        if (n > 0) {
          el.textContent = n > 9 ? "9+" : String(n);
          el.hidden = false;
          el.style.display = "";
        } else {
          el.textContent = "";
          el.hidden = true;
          el.style.display = "none";
        }
      });
    }

    function setPageUnread(n) {
      var el = document.querySelector("[data-notif-unread]");
      if (el) el.textContent = String(n);
    }

    function iconFor(type) {
      if (type === "Delivery") return "\uD83D\uDE9A";
      if (type === "Payment")  return "\uD83D\uDCB3";
      if (type === "Promo")    return "\uD83C\uDF81";
      return "\uD83D\uDD14";
    }

    function ensureStack() {
      var s = document.querySelector(".toast-stack");
      if (s) return s;
      s = document.createElement("div");
      s.className = "toast-stack";
      document.body.appendChild(s);
      return s;
    }

    function showToast(item) {
      var stack = ensureStack();
      var el = document.createElement("div");
      el.className = "toast toast--" + String(item.type || "announcement").toLowerCase();
      el.innerHTML =
        '<div class="toast__icon">' + iconFor(item.type) + "</div>" +
        '<div class="toast__body"><h5></h5><p></p></div>' +
        '<button class="toast__close" type="button" aria-label="Dismiss">&times;</button>';
      el.querySelector("h5").textContent = item.title || "Notification";
      el.querySelector("p").textContent  = item.message || "";
      el.querySelector(".toast__close").addEventListener("click", function (ev) {
        ev.stopPropagation();
        el.remove();
      });

      el.addEventListener("click", function (ev) {
        if (ev.target.classList.contains("toast__close")) return;
        var base = apiUrl.replace(/\/api\/notifications\.php.*$/, "");
        var folder = role === "admin" ? "admin" : "customer";
        window.location.href = base + "/" + folder + "/notifications.php";
      });

      stack.appendChild(el);
      setTimeout(function () { if (el.parentNode) el.remove(); }, 8000);
    }

    function buildArticle(item) {
      var article = document.createElement("article");
      article.className = "notif is-unread";
      article.setAttribute("data-notif-id", String(item.db_id));
      article.innerHTML =
        '<div class="notif__icon">' + iconFor(item.type) + "</div>" +
        "<div><h4></h4><p></p><small></small></div>";
      article.querySelector("h4").textContent = item.title || "";
      article.querySelector("p").textContent  = item.message || "";

      var small = article.querySelector("small");
      small.appendChild(document.createTextNode(item.date + " \u00B7 "));
      var badge = document.createElement("span");
      badge.className = "badge";
      badge.textContent = item.type || "Announcement";
      small.appendChild(badge);

      return article;
    }

    function reconcile(activeIds) {
      if (!list) return;
      var activeSet = {};
      activeIds.forEach(function (id) { activeSet[String(id)] = true; });

      list.querySelectorAll("[data-notif-id]").forEach(function (node) {
        var id = node.getAttribute("data-notif-id");
        if (!activeSet[id]) node.remove();
      });

      var hasAny = list.querySelector("[data-notif-id]") || list.querySelector(".notif");
      var empty  = list.querySelector(".empty");
      if (!hasAny && !empty) {
        var p = document.createElement("p");
        p.className = "empty";
        p.textContent = "No notifications yet.";
        list.appendChild(p);
      } else if (hasAny && empty) {
        empty.remove();
      }
    }

    function poll() {
      if (inFlight) return;
      inFlight = true;

      var url = apiUrl + (apiUrl.indexOf("?") >= 0 ? "&" : "?") + "since_id=" + encodeURIComponent(lastId);
      if (isNotificationsPage) url += "&mark_read=1";

      fetch(url, {
        credentials: "same-origin",
        cache: "no-store",
        headers: { "Accept": "application/json", "X-Requested-With": "fetch" }
      })
        .then(function (r) {
          if (r.status === 401 || r.status === 403) { stop(); throw new Error("auth"); }
          if (!r.ok) throw new Error("http " + r.status);
          return r.json();
        })
        .then(function (data) {
          if (!data || !data.ok) return;

          setBadge(data.unread || 0);
          setPageUnread(data.unread || 0);

          var items = Array.isArray(data.items) ? data.items : [];

          // Server returns oldest-first; prepend each so newest ends up on top.
          items.forEach(function (it) {
            if (it.db_id <= lastId) return;
            if (list) {
              var exists = list.querySelector('[data-notif-id="' + it.db_id + '"]');
              if (!exists) {
                var empty = list.querySelector(".empty");
                if (empty) empty.remove();
                list.insertBefore(buildArticle(it), list.firstChild);
              }
            }
            showToast(it);
          });

          if (Array.isArray(data.active_ids)) {
            reconcile(data.active_ids);
          }

          var cursor = Number(data.cursor || 0);
          if (cursor > lastId) {
            lastId = cursor;
            sessionStorage.setItem(STOREKEY, String(lastId));
          }
        })
        .catch(function (err) {
          if (window.console && err.message !== "auth") {
            console.warn("[tubigko] notification poll failed:", err.message);
          }
        })
        .finally(function () { inFlight = false; });
    }

    function start() { if (timerId) return; poll(); timerId = setInterval(poll, POLL_MS); }
    function stop()  { if (timerId) { clearInterval(timerId); timerId = null; } }

    window.addEventListener("focus", poll);
    window.addEventListener("online", poll);

    start();
  })();
})();