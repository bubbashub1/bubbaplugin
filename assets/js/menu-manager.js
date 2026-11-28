(function () {
  "use strict";

  const MENU_GROUPS = [
    ["main", "Main navigation", "Links shown in the public site header."],
    ["footer_main", "Footer · Main links", "Core family and support destinations."],
    ["footer_tools", "Footer · Explore & tools", "Directory, events and planning tools."],
    ["footer_legal", "Footer · Legal & contact", "Policies and contact information."]
  ];
  const state = { csrf: "", dirty: false };
  const $ = id => document.getElementById(id);
  const root = $("menuManagerLists");
  const status = $("menuManagerStatus");
  const setupPanel = $("menuSetupPanel");
  const editorPanel = $("menuEditorPanel");

  function esc(value) {
    return String(value == null ? "" : value).replace(/[&<>"']/g, char => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    }[char]));
  }

  function setStatus(message, isError) {
    if (!status) return;
    status.textContent = message;
    status.classList.toggle("error", Boolean(isError));
  }

  function itemHtml(item) {
    return '<div class="menu-manager-item">' +
      '<label>Link label<input data-field="label" type="text" maxlength="70" value="' + esc(item.label) + '"></label>' +
      '<label>Destination<input data-field="url" type="text" maxlength="500" value="' + esc(item.url) + '" placeholder="directory.html"></label>' +
      '<label class="menu-visible"><input type="checkbox" data-field="visible" ' + (item.visible !== false ? "checked" : "") + '> Visible</label>' +
      '<div class="menu-item-actions">' +
      '<button type="button" class="button button-soft menu-move-up" aria-label="Move item up">↑</button>' +
      '<button type="button" class="button button-soft menu-move-down" aria-label="Move item down">↓</button>' +
      '<button type="button" class="button button-soft menu-remove">Remove</button>' +
      '</div></div>';
  }

  function render(data) {
    const html = MENU_GROUPS.map(group => {
      const items = Array.isArray(data[group[0]]) ? data[group[0]] : [];
      return '<section class="menu-manager-group" data-menu-key="' + group[0] + '">' +
        '<div class="menu-manager-group-head"><div><h2>' + esc(group[1]) + '</h2><p>' + esc(group[2]) + '</p></div>' +
        '<button type="button" class="button button-soft menu-add" data-key="' + group[0] + '">+ Add link</button></div>' +
        '<div class="menu-manager-items">' + items.map(itemHtml).join("") + '</div></section>';
    }).join("");
    root.innerHTML = html;
    setupPanel.hidden = true;
    editorPanel.hidden = false;
    state.dirty = false;
  }

  function collect() {
    const menus = {};
    root.querySelectorAll(".menu-manager-group").forEach(group => {
      menus[group.dataset.menuKey] = Array.from(group.querySelectorAll(".menu-manager-item")).map((row, index) => ({
        label: row.querySelector('[data-field="label"]').value.trim(),
        url: row.querySelector('[data-field="url"]').value.trim(),
        visible: row.querySelector('[data-field="visible"]').checked,
        order: index + 1
      }));
    });
    return menus;
  }

  async function request(url, options) {
    const response = await fetch(url, Object.assign({
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "application/json" }
    }, options || {}));
    const data = await response.json().catch(() => ({}));
    if (response.status === 401) {
      window.location.href = "/admin-login.html?next=" + encodeURIComponent(location.pathname + location.search);
      throw new Error("Your administrator session has expired.");
    }
    if (!response.ok || data.ok !== true) throw new Error(data.error || ("Request failed (HTTP " + response.status + ")."));
    return data;
  }

  async function verifyAdmin() {
    const response = await fetch("/admin-auth.php?action=check", {
      credentials: "same-origin", cache: "no-store", headers: { Accept: "application/json" }
    });
    if (response.status === 401) {
      window.location.href = "/admin-login.html?next=" + encodeURIComponent(location.pathname + location.search);
      throw new Error("Please sign in to manage navigation.");
    }
    if (!response.ok) throw new Error("Administrator access could not be verified.");
  }

  async function loadMenus(confirmDiscard) {
    if (state.dirty && confirmDiscard && !window.confirm("Discard your unsaved menu edits and reload the saved menus?")) return;
    setStatus("Loading saved navigation…", false);
    try {
      await verifyAdmin();
      const data = await request("/api/menu.php?action=admin");
      state.csrf = data.csrf || "";
      if (data.setup_required) {
        editorPanel.hidden = true;
        setupPanel.hidden = false;
        setStatus("One-time menu storage setup is required. This creates only the existing menu table.", false);
      } else {
        render(data.data || {});
        setStatus("Saved menus loaded. Changes are not saved until you choose Save menus.", false);
      }
    } catch (error) {
      setStatus(error.message || "Navigation could not be loaded.", true);
    }
  }

  async function post(payload) {
    return request("/api/menu.php", {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify(Object.assign({}, payload, { csrf: state.csrf }))
    });
  }

  async function setupMenus() {
    const button = $("setupMenuStorage");
    button.disabled = true;
    setStatus("Setting up menu storage…", false);
    try {
      const data = await post({ action: "setup" });
      state.csrf = data.csrf || state.csrf;
      await loadMenus(false);
      setStatus(data.message || "Menu storage is ready.", false);
    } catch (error) {
      setStatus(error.message || "Menu storage could not be set up.", true);
    } finally {
      button.disabled = false;
    }
  }

  async function saveMenus() {
    const button = $("saveMenus");
    button.disabled = true;
    setStatus("Saving menu changes…", false);
    try {
      const data = await post({ action: "save", menus: collect() });
      state.dirty = false;
      setStatus((data.message || "Menus saved.") + " Public navigation on this deployment uses the saved menus on the next page load.", false);
    } catch (error) {
      setStatus(error.message || "Menu changes could not be saved.", true);
    } finally {
      button.disabled = false;
    }
  }

  if (!root || !status || !setupPanel || !editorPanel) return;

  root.addEventListener("input", () => { state.dirty = true; });
  root.addEventListener("change", () => { state.dirty = true; });
  root.addEventListener("click", event => {
    const add = event.target.closest(".menu-add");
    if (add) {
      const group = root.querySelector('.menu-manager-group[data-menu-key="' + add.dataset.key + '"] .menu-manager-items');
      if (!group) return;
      group.insertAdjacentHTML("beforeend", itemHtml({ label: "New menu item", url: "", visible: true }));
      group.lastElementChild.querySelector('[data-field="label"]').select();
      state.dirty = true;
      return;
    }
    const remove = event.target.closest(".menu-remove");
    if (remove) {
      remove.closest(".menu-manager-item").remove();
      state.dirty = true;
      return;
    }
    const moveUp = event.target.closest(".menu-move-up");
    const moveDown = event.target.closest(".menu-move-down");
    if (moveUp || moveDown) {
      const row = event.target.closest(".menu-manager-item");
      const sibling = moveUp ? row.previousElementSibling : row.nextElementSibling;
      if (sibling) {
        if (moveUp) row.parentElement.insertBefore(row, sibling);
        else row.parentElement.insertBefore(sibling, row);
        state.dirty = true;
      }
    }
  });

  $("setupMenuStorage").addEventListener("click", setupMenus);
  $("saveMenus").addEventListener("click", saveMenus);
  $("reloadMenus").addEventListener("click", () => loadMenus(true));
  const logout = document.querySelector("[data-admin-logout]");
  logout?.addEventListener("click", async () => {
    logout.disabled = true;
    try {
      await fetch("/admin-auth.php?action=logout", { method: "POST", credentials: "same-origin", cache: "no-store" });
    } finally {
      window.location.href = "/admin-login.html";
    }
  });

  void loadMenus(false);
})();
