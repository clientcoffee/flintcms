(() => {
  "use strict";

  // Constants for settings display and shared UI text.
  const LABEL_ACRONYMS = {
    api: "API",
    css: "CSS",
    html: "HTML",
    id: "ID",
    ip: "IP",
    smtp: "SMTP",
    url: "URL"
  };
  const SENSITIVE_KEY_PATTERN = /(password|secret|token|api_key|smtp_pass|smtp_password)/i;
  const SPINNER_ICON = "<svg class=\"animate-spin h-4 w-4 mr-2\" viewBox=\"0 0 24 24\"><circle class=\"opacity-25\" cx=\"12\" cy=\"12\" r=\"10\" stroke=\"currentColor\" stroke-width=\"4\" fill=\"none\"></circle><path class=\"opacity-75\" fill=\"currentColor\" d=\"M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z\"></path></svg>";
  // Track non-removable site keys and pending deletions.
  const settingsState = {
    coreKeys: new Set(),
    removed: new Set()
  };

  // DOM helpers keep selectors terse.
  const qs = (selector, scope = document) => scope.querySelector(selector);

  // DOM helpers keep selectors terse.
  const qsa = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));

  // Attach CSRF header on state-changing requests.
  const secureFetch = (url, options = {}) => {
    const headers = options.headers || {};
    if (typeof CSRF_TOKEN !== "undefined") {
      headers["X-CSRF-Token"] = CSRF_TOKEN;
    }
    return fetch(url, { ...options, headers });
  };

  // Fetch JSON while preserving the raw Response for status checks.
  const fetchJson = async (url, options = {}) => {
    const response = await secureFetch(url, options);
    const data = await response.json();
    return { response, data };
  };

  // Swap button state and label during async work.
  const setButtonState = (button, state) => {
    if (!button) {
      return;
    }

    const { loading, label, html } = state;
    button.disabled = Boolean(loading);
    if (typeof html === "string") {
      button.innerHTML = html;
      return;
    }
    if (typeof label === "string") {
      button.textContent = label;
    }
  };

  // Display tone-aware status messages in-place.
  const setMessage = (element, options) => {
    if (!element) {
      return;
    }

    const { text, tone = "info", link, linkLabel = "Open magic link" } = options;
    element.classList.remove(
      "hidden",
      "border-emerald-200",
      "bg-emerald-50",
      "text-emerald-700",
      "border-red-200",
      "bg-red-50",
      "text-red-700",
      "border-indigo-200",
      "bg-indigo-50",
      "text-indigo-700"
    );

    element.textContent = text || "";
    if (tone === "success") {
      element.classList.add("border-emerald-200", "bg-emerald-50", "text-emerald-700");
    } else if (tone === "error") {
      element.classList.add("border-red-200", "bg-red-50", "text-red-700");
    } else {
      element.classList.add("border-indigo-200", "bg-indigo-50", "text-indigo-700");
    }

    if (link) {
      const linkWrapper = document.createElement("div");
      linkWrapper.className = "mt-2 text-xs";
      const linkEl = document.createElement("a");
      linkEl.href = link;
      linkEl.textContent = linkLabel;
      linkEl.className = "text-indigo-600 hover:text-indigo-700 underline break-all";
      linkWrapper.appendChild(linkEl);
      element.appendChild(linkWrapper);
    }
  };

  // Format config keys into friendly labels.
  const formatSettingLabel = (key) => {
    const segments = String(key || "").split(".");
    return segments
      .map((segment) => {
        return segment
          .split(/[_-]+/)
          .map((word) => {
            const lower = word.toLowerCase();
            if (LABEL_ACRONYMS[lower]) {
              return LABEL_ACRONYMS[lower];
            }
            return lower.charAt(0).toUpperCase() + lower.slice(1);
          })
          .join(" ");
      })
      .join(" ");
  };

  // Mask sensitive values for display-only settings.
  const maskSettingValue = (key, value) => {
    if (SENSITIVE_KEY_PATTERN.test(key)) {
      return "******";
    }

    return value === "" ? "—" : value;
  };

  // Normalize new site setting keys.
  const normalizeSiteKey = (raw) => {
    const trimmed = String(raw || "").trim();
    if (!trimmed) {
      return "";
    }
    const withoutPrefix = trimmed.replace(/^site\./i, "");
    return withoutPrefix.replace(/\s+/g, "_");
  };

  // Build an editable setting field element.
  const createSettingField = (key, value) => {
    const wrapper = document.createElement("div");
    wrapper.className = "rounded-xl border border-gray-200 bg-white p-4 space-y-3";

    const header = document.createElement("div");
    header.className = "flex items-start justify-between gap-4";

    const labelBlock = document.createElement("div");
    const label = document.createElement("label");
    label.className = "block text-sm font-medium text-gray-700";
    label.textContent = formatSettingLabel(key);

    const meta = document.createElement("p");
    meta.className = "text-xs text-gray-400";
    meta.textContent = key;

    labelBlock.appendChild(label);
    labelBlock.appendChild(meta);
    header.appendChild(labelBlock);

    if (!settingsState.coreKeys.has(key)) {
      const removeButton = document.createElement("button");
      removeButton.type = "button";
      removeButton.className = "text-xs text-red-600 hover:text-red-700 font-semibold";
      removeButton.textContent = "Remove";
      removeButton.addEventListener("click", () => {
        if (!confirm(`Remove ${key}?`)) {
          return;
        }
        settingsState.removed.add(key);
        wrapper.remove();
      });
      header.appendChild(removeButton);
    }

    const input = document.createElement("input");
    input.type = "text";
    input.name = key;
    input.value = value;
    input.className = "w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200";

    wrapper.appendChild(header);
    wrapper.appendChild(input);

    return wrapper;
  };

  // Build a read-only settings row element.
  const createReadonlyRow = (key, value) => {
    const row = document.createElement("div");
    row.className = "flex items-start justify-between gap-4 border-b border-gray-100 pb-3";

    const labelBlock = document.createElement("div");
    const label = document.createElement("p");
    label.className = "text-sm font-medium text-gray-700";
    label.textContent = formatSettingLabel(key);

    const meta = document.createElement("p");
    meta.className = "text-xs text-gray-400";
    meta.textContent = key;

    labelBlock.appendChild(label);
    labelBlock.appendChild(meta);

    const valueEl = document.createElement("p");
    valueEl.className = "text-sm text-gray-600 text-right break-all";
    valueEl.textContent = maskSettingValue(key, value);

    row.appendChild(labelBlock);
    row.appendChild(valueEl);

    return row;
  };

  // Toggle editor UI visibility and button state.
  const setEditorState = (editor, enabled) => {
    if (!editor.editorArea || !editor.placeholder || !editor.saveButton || !editor.cancelButton) {
      return;
    }

    editor.editorArea.classList.toggle("hidden", !enabled);
    editor.placeholder.classList.toggle("hidden", enabled);
    editor.saveButton.disabled = !enabled;
    editor.cancelButton.disabled = !enabled;
  };

  // Toggle the active button styling in tree lists.
  const setActiveButton = (nextButton, currentButton) => {
    if (currentButton) {
      currentButton.classList.remove("bg-indigo-50", "text-indigo-700");
      currentButton.classList.add("text-gray-700");
    }

    if (nextButton) {
      nextButton.classList.add("bg-indigo-50", "text-indigo-700");
      nextButton.classList.remove("text-gray-700");
    }

    return nextButton;
  };

  // Open a named tab programmatically for cross-tab shortcuts.
  const openTab = (tabName) => {
    const link = qs(`.admin-nav-link[data-tab="${tabName}"]`);
    if (link) {
      link.dispatchEvent(new MouseEvent("click", { bubbles: true, cancelable: true }));
    }
  };

  // Tab switching logic for the admin sidebar.
  const initTabs = () => {
    const links = qsa(".admin-nav-link");
    if (links.length === 0) {
      return;
    }

    links.forEach((link) => {
      link.addEventListener("click", (event) => {
        const href = link.getAttribute("href") || "";
        if (href.startsWith("/")) {
          return;
        }

        event.preventDefault();
        const tabName = link.dataset.tab;
        if (!tabName) {
          return;
        }

        links.forEach((item) => {
          item.classList.remove("active", "bg-gray-100", "text-gray-900");
          item.classList.add("text-gray-600", "hover:bg-gray-50");
        });

        link.classList.add("active", "bg-gray-100", "text-gray-900");
        link.classList.remove("text-gray-600", "hover:bg-gray-50");
        qsa(".admin-tab").forEach((tab) => tab.classList.add("hidden"));

        const target = qs(`#${tabName}-tab`);
        if (target) {
          target.classList.remove("hidden");
        }
      });
    });
  };

  // Render editable settings for the Settings tab.
  const renderSettings = (settings) => {
    const container = qs("#settings-container");
    if (!container) {
      return;
    }

    container.innerHTML = "";
    settingsState.removed.clear();

    const entries = Object.entries(settings || {});
    if (entries.length === 0) {
      container.innerHTML = "<p class=\"text-sm text-gray-500\">No site settings yet.</p>";
      return;
    }

    entries.forEach(([key, value]) => {
      container.appendChild(createSettingField(key, value));
    });
  };

  // Render read-only config values.
  const renderReadonlySettings = (settings) => {
    const container = qs("#settings-readonly");
    if (!container) {
      return;
    }

    container.innerHTML = "";
    const entries = Object.entries(settings || {});
    if (entries.length === 0) {
      container.innerHTML = "<p class=\"text-sm text-gray-500\">No read-only settings found.</p>";
      return;
    }

    entries.forEach(([key, value]) => {
      container.appendChild(createReadonlyRow(key, value));
    });
  };

  // Fetch settings payload for the Settings tab.
  const loadSettings = async () => {
    try {
      const { data } = await fetchJson("/api/settings");
      if (data.success) {
        settingsState.coreKeys = new Set((data.meta && data.meta.core_site_keys) || []);
        renderSettings(data.editable);
        renderReadonlySettings(data.readonly);
      }
    } catch (error) {
    }
  };

  // Persist settings payload for site.* keys.
  const saveSettings = async (event) => {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const settings = {};
    formData.forEach((value, key) => {
      settings[key] = value;
    });

    try {
      const { data } = await fetchJson("/api/settings", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          settings,
          removed: Array.from(settingsState.removed)
        })
      });

      if (data.success) {
        alert("Settings saved successfully!");
        settingsState.removed.clear();
        loadSettings();
        return;
      }

      alert("Failed to save settings: " + (data.error || "Unknown error"));
    } catch (error) {
      alert("Failed to save settings");
    }
  };

  // Add a new site.* key to the form.
  const addSiteSetting = () => {
    const rawKey = prompt("Enter site setting key (example: tagline):");
    if (!rawKey) {
      return;
    }

    const normalized = normalizeSiteKey(rawKey);
    if (!normalized || !/^[A-Za-z0-9._-]+$/.test(normalized)) {
      alert("Setting keys can only include letters, numbers, dots, dashes, or underscores.");
      return;
    }

    const key = `site.${normalized}`;
    const form = qs("#settings-form");
    if (form) {
      const existing = Array.from(form.querySelectorAll("input[name]")).find((input) => input.name === key);
      if (existing) {
        alert("That setting already exists.");
        existing.focus();
        return;
      }
    }

    const value = prompt("Enter value:");
    if (value === null) {
      return;
    }

    const container = qs("#settings-container");
    if (!container) {
      return;
    }

    settingsState.removed.delete(key);
    container.appendChild(createSettingField(key, value));
  };

  // Wire settings form events.
  const initSettings = () => {
    const form = qs("#settings-form");
    const addButton = qs("#add-setting-btn");

    if (form) {
      form.addEventListener("submit", saveSettings);
      loadSettings();
    }

    if (addButton) {
      addButton.addEventListener("click", addSiteSetting);
    }
  };

  // Render the dashboard settings snapshot.
  const renderDashboardSettings = (siteSettings, readonlySettings) => {
    const container = qs("#dashboard-settings-list");
    if (!container) {
      return;
    }

    container.innerHTML = "";
    const entries = [
      ...Object.entries(siteSettings || {}),
      ...Object.entries(readonlySettings || {})
    ];

    if (entries.length === 0) {
      container.innerHTML = "<p class=\"text-sm text-gray-500\">No settings found.</p>";
      return;
    }

    entries.forEach(([key, value]) => {
      container.appendChild(createReadonlyRow(key, value));
    });
  };

  // Load and render dashboard overview widgets.
  const initDashboard = () => {
    const pagesCount = qs("#dashboard-pages-count");
    const blocksCount = qs("#dashboard-blocks-count");
    const themeSelect = qs("#dashboard-theme-select");
    const themeSave = qs("#dashboard-theme-save");
    const status = qs("#dashboard-status");
    const refreshButton = qs("#dashboard-refresh-btn");

    const openContent = qs("#dashboard-open-content");
    const openPages = qs("#dashboard-open-pages");
    const openBlocks = qs("#dashboard-open-blocks");
    const openSettingsButton = qs("#dashboard-open-settings");

    const loadOverview = async () => {
      if (pagesCount) {
        pagesCount.textContent = "--";
      }
      if (blocksCount) {
        blocksCount.textContent = "--";
      }

      try {
        const { data } = await fetchJson("/api/admin/overview");
        if (!data.success) {
          throw new Error(data.error || "Failed to load overview.");
        }

        if (status) {
          status.classList.add("hidden");
        }

        const counts = data.counts || {};
        if (pagesCount) {
          pagesCount.textContent = counts.pages ?? 0;
        }
        if (blocksCount) {
          blocksCount.textContent = counts.blocks ?? 0;
        }

        if (themeSelect) {
          const themes = Array.isArray(data.themes) ? data.themes : [];
          themeSelect.innerHTML = "";
          if (themes.length === 0) {
            const option = document.createElement("option");
            option.value = "";
            option.textContent = "No themes found";
            themeSelect.appendChild(option);
          } else {
            themes.forEach((theme) => {
              const option = document.createElement("option");
              option.value = theme.name;
              option.textContent = theme.label;
              themeSelect.appendChild(option);
            });
            themeSelect.value = data.current_theme || "";
          }
        }

        renderDashboardSettings(data.site_settings, data.readonly_settings);
      } catch (error) {
        setMessage(status, { text: "Failed to load dashboard overview.", tone: "error" });
      }
    };

    if (refreshButton) {
      refreshButton.addEventListener("click", loadOverview);
    }

    if (openContent) {
      openContent.addEventListener("click", () => openTab("content"));
    }

    if (openPages) {
      openPages.addEventListener("click", () => openTab("content"));
    }

    if (openBlocks) {
      openBlocks.addEventListener("click", () => openTab("blocks"));
    }

    if (openSettingsButton) {
      openSettingsButton.addEventListener("click", () => openTab("settings"));
    }

    if (themeSave && themeSelect) {
      themeSave.addEventListener("click", async () => {
        if (!themeSelect.value) {
          return;
        }

        const originalLabel = themeSave.textContent;
        setButtonState(themeSave, { loading: true, label: "Saving..." });

        try {
          const { data } = await fetchJson("/api/settings", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ settings: { "site.theme": themeSelect.value } })
          });

          if (data.success) {
            setMessage(status, {
              text: "Theme updated. Refresh the site to apply changes.",
              tone: "success"
            });
            return;
          }

          setMessage(status, { text: data.error || "Failed to update theme.", tone: "error" });
        } catch (error) {
          setMessage(status, { text: "Failed to update theme.", tone: "error" });
        } finally {
          setButtonState(themeSave, { loading: false, label: originalLabel });
        }
      });
    }

    loadOverview();
  };

  // Wire Advanced tab actions.
  const initAdvanced = () => {
    initExport();

    const status = qs("#advanced-status");
    const clearSessionsButton = qs("#clear-sessions-btn");
    const clearMagicLinksButton = qs("#clear-magic-links-btn");

    // Shared helper for Advanced tab actions that need confirmation + status.
    const runAction = async (button, endpoint, confirmText, successText) => {
      if (!button) {
        return;
      }

      if (!confirm(confirmText)) {
        return;
      }

      const originalLabel = button.textContent;
      setButtonState(button, { loading: true, label: "Working..." });
      setMessage(status, { text: "Working...", tone: "info" });

      try {
        const { data } = await fetchJson(endpoint, { method: "POST" });
        if (data.success) {
          setMessage(status, { text: data.message || successText, tone: "success" });
          return;
        }

        setMessage(status, { text: data.error || "Action failed.", tone: "error" });
      } catch (error) {
        setMessage(status, { text: "Action failed.", tone: "error" });
      } finally {
        setButtonState(button, { loading: false, label: originalLabel });
      }
    };

    if (clearSessionsButton) {
      clearSessionsButton.addEventListener("click", () =>
        runAction(
          clearSessionsButton,
          "/api/admin/clear-sessions",
          "Clear all session files? This will log everyone out.",
          "Sessions cleared."
        )
      );
    }

    if (clearMagicLinksButton) {
      clearMagicLinksButton.addEventListener("click", () =>
        runAction(
          clearMagicLinksButton,
          "/api/admin/clear-magic-links",
          "Clear magic link tokens and cooldown markers?",
          "Magic links cleared."
        )
      );
    }
  };

  // Initialize security section actions.
  const initSecurity = () => {
    const securityMessage = qs("#security-message");
    const resetButton = qs("#security-password-reset-btn");
    const clearBlocklistButton = qs("#clear-blocklist-btn");

    if (resetButton) {
      resetButton.addEventListener("click", async () => {
        const confirmed = confirm("Send a password reset magic link to the admin email?");
        if (!confirmed) {
          return;
        }

        try {
          setMessage(securityMessage, { text: "Sending reset link..." });
          const { data } = await fetchJson("/api/security/password-reset", { method: "POST" });
          if (data.success) {
            setMessage(securityMessage, {
              text: data.message || "Reset link sent.",
              tone: "success",
              link: data.magic_link || null
            });
            return;
          }
          setMessage(securityMessage, { text: data.error || "Failed to send reset link.", tone: "error" });
        } catch (error) {
          setMessage(securityMessage, { text: "Failed to send reset link.", tone: "error" });
        }
      });
    }

    if (clearBlocklistButton) {
      clearBlocklistButton.addEventListener("click", async () => {
        const confirmed = confirm("Clear all blocked hosts?");
        if (!confirmed) {
          return;
        }

        try {
          const { data } = await fetchJson("/api/blocklist/clear", { method: "POST" });
          if (data.success) {
            alert("Blocklist cleared.");
            return;
          }
          alert("Failed to clear blocklist.");
        } catch (error) {
          alert("Failed to clear blocklist.");
        }
      });
    }
  };

  const getExportFilename = (response, fallback) => {
    const header = response.headers.get("Content-Disposition") || "";
    const match = header.match(/filename="?([^";]+)"?/i);
    if (match && match[1]) {
      return match[1];
    }

    return fallback;
  };

  // Export content handler for the advanced tab.
  const initExport = () => {
    const button = qs("#export-btn");
    const status = qs("#export-status");

    if (!button || !status) {
      return;
    }

    button.addEventListener("click", async () => {
      const originalHtml = button.innerHTML;

      setButtonState(button, { loading: true, html: `${SPINNER_ICON}Preparing export...` });
      status.classList.remove("hidden");
      status.className = "mt-3 text-sm text-blue-600";
      status.textContent = "Creating archive...";

      try {
        const response = await secureFetch("/api/export", { method: "POST" });
        const contentType = response.headers.get("Content-Type") || "";
        if (!response.ok) {
          let errorMessage = "Export failed";
          if (contentType.includes("application/json")) {
            const data = await response.json();
            errorMessage = data.error || errorMessage;
          }
          throw new Error(errorMessage);
        }

        if (contentType.includes("application/json")) {
          const data = await response.json();
          throw new Error(data.error || "Export failed");
        }

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const anchor = document.createElement("a");
        anchor.href = url;
        const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, "-");
        const fallback = contentType.includes("zip")
          ? `flint-export-${timestamp}.zip`
          : `flint-export-${timestamp}.tar.gz`;
        anchor.download = getExportFilename(response, fallback);
        document.body.appendChild(anchor);
        anchor.click();
        document.body.removeChild(anchor);
        window.URL.revokeObjectURL(url);

        status.className = "mt-3 text-sm text-green-600 font-medium";
        status.textContent = "✓ Export complete! Check your downloads.";
        setTimeout(() => status.classList.add("hidden"), 5000);
      } catch (error) {
        status.className = "mt-3 text-sm text-red-600 font-medium";
        status.textContent = "✗ Export failed. Please try again.";
      } finally {
        setButtonState(button, { loading: false, html: originalHtml });
      }
    });
  };

  // Build the update banner actions for the current auto-update mode.
  const renderUpdateActions = (data) => {
    const autoUpdateMode = data.auto_update_mode;
    const updateUrl = data.update?.download_url;
    const releaseUrl = data.update?.url;

    if (!updateUrl || !releaseUrl) {
      return "";
    }

    if (autoUpdateMode === "true") {
      return `<button type="button" data-action="apply-update" data-update-url="${updateUrl}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">Install Now</button>`;
    }

    if (autoUpdateMode === "ask") {
      return `<button type="button" data-action="apply-update" data-update-url="${updateUrl}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">Install Update</button><a href="${releaseUrl}" target="_blank" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-medium">View Release Notes</a>`;
    }

    return `<a href="${releaseUrl}" target="_blank" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">View Release</a>`;
  };

  // Show the update banner after a successful check.
  const showUpdateBanner = (data) => {
    const banner = qs("#update-banner");
    if (!banner) {
      return;
    }

    const actions = renderUpdateActions(data);
    banner.innerHTML = `<div class="bg-blue-50 border border-blue-200 rounded-lg p-6"><div class="flex items-start justify-between"><div class="flex-1"><h3 class="text-lg font-semibold text-blue-900 mb-2">Update Available: v${data.update.version}</h3><p class="text-blue-800 text-sm mb-4">A new version is available. Your content and themes will be preserved.</p><div class="flex gap-3">${actions}</div></div></div></div>`;
    banner.classList.remove("hidden");
  };

  // Check for updates when the button is pressed.
  const checkForUpdates = async () => {
    const button = qs("#check-updates-btn");
    const banner = qs("#update-banner");
    if (!button || !banner) {
      return;
    }

    const originalHtml = button.innerHTML;
    setButtonState(button, { loading: true, html: `${SPINNER_ICON}Checking...` });

    try {
      const { data } = await fetchJson("/api/updates/check");
      if (data.update_available && data.update) {
        showUpdateBanner(data);
      } else {
        banner.innerHTML = `<div class="bg-green-50 border border-green-200 rounded-lg p-4 transition-all"><p class="text-green-800 text-sm font-medium">✓ You are running the latest version (${data.current_version})</p></div>`;
        banner.classList.remove("hidden");
        setTimeout(() => {
          banner.classList.add("opacity-0", "transition-opacity");
          setTimeout(() => banner.classList.add("hidden"), 300);
        }, 4000);
      }
    } catch (error) {
      banner.innerHTML = `<div class="bg-red-50 border border-red-200 rounded-lg p-4"><p class="text-red-800 text-sm font-medium">✗ Failed to check for updates. Please try again.</p></div>`;
      banner.classList.remove("hidden");
      setTimeout(() => banner.classList.add("hidden"), 6000);
    } finally {
      setButtonState(button, { loading: false, html: originalHtml });
    }
  };

  // Apply an update after confirmation.
  const applyUpdate = async (downloadUrl) => {
    if (!confirm("Install update now? Your site will be briefly unavailable.")) {
      return;
    }

    const banner = qs("#update-banner");
    if (!banner) {
      return;
    }

    banner.innerHTML = `<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4"><div class="flex items-center gap-3">${SPINNER_ICON}<p class="text-yellow-800 text-sm font-medium">Installing update... Please wait.</p></div></div>`;

    try {
      const { data } = await fetchJson("/api/updates/apply", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ download_url: downloadUrl })
      });

      if (data.success) {
        banner.innerHTML = `<div class="bg-green-50 border border-green-200 rounded-lg p-4"><p class="text-green-800 text-sm font-medium">✓ ${data.message}</p></div>`;
        setTimeout(() => window.location.reload(), 2000);
      } else {
        banner.innerHTML = `<div class="bg-red-50 border border-red-200 rounded-lg p-4"><p class="text-red-800 text-sm font-medium">✗ Update failed: ${data.error}</p></div>`;
      }
    } catch (error) {
      banner.innerHTML = `<div class="bg-red-50 border border-red-200 rounded-lg p-4"><p class="text-red-800 text-sm">Update failed. Please try again.</p></div>`;
    }
  };

  // Wire update buttons and delegated banner actions.
  const initUpdates = () => {
    const checkButton = qs("#check-updates-btn");
    if (checkButton) {
      checkButton.addEventListener("click", checkForUpdates);
    }

    const banner = qs("#update-banner");
    if (!banner) {
      return;
    }

    banner.addEventListener("click", (event) => {
      const button = event.target.closest("button[data-action=\"apply-update\"]");
      if (!button) {
        return;
      }

      const updateUrl = button.dataset.updateUrl || "";
      if (updateUrl) {
        applyUpdate(updateUrl);
      }
    });
  };

  // Fetch and render form submissions.
  const loadSubmissions = async () => {
    const container = qs("#submissions-container");
    const countEl = qs("#submissions-count");

    if (!container || !countEl) {
      return;
    }

    try {
      const { data } = await fetchJson("/api/submissions");
      if (data.success) {
        renderSubmissions(data.submissions);
        return;
      }
    } catch (error) {
    }

    container.innerHTML = "<p class=\"text-red-600 text-sm\">Failed to load submissions.</p>";
  };

  // Render submission cards in the Submissions tab.
  const renderSubmissions = (submissions) => {
    const container = qs("#submissions-container");
    const countEl = qs("#submissions-count");

    if (!container || !countEl) {
      return;
    }

    const items = Array.isArray(submissions) ? submissions : [];
    countEl.textContent = `Total: ${items.length} submission(s)`;

    if (items.length === 0) {
      container.innerHTML = "<p class=\"text-gray-500 text-sm\">No submissions yet.</p>";
      return;
    }

    container.innerHTML = items
      .map((submission) => {
        const date = new Date(submission.submitted_at * 1000).toLocaleString();
        const fromUrl = submission.submitted_from
          ? `<p class="text-xs text-gray-500 mt-2"><strong>From:</strong> ${submission.submitted_from}</p>`
          : "";
        return `
          <div class="border border-gray-200 rounded-lg p-4 bg-gray-50 hover:bg-gray-100 transition-colors">
            <div class="flex justify-between items-start mb-2">
              <div>
                <p class="font-semibold text-gray-900">${submission.name}</p>
                <p class="text-sm text-gray-600">${submission.email}</p>
              </div>
              <span class="text-xs text-gray-500">${date}</span>
            </div>
            <p class="text-sm text-gray-700 whitespace-pre-wrap mb-2">${submission.message}</p>
            ${fromUrl}
          </div>
        `;
      })
      .join("");
  };

  // Wire the submissions clear action.
  const initSubmissions = () => {
    const clearButton = qs("#clear-submissions-btn");

    if (clearButton) {
      clearButton.addEventListener("click", async () => {
        if (!confirm("Delete all submissions? This cannot be undone.")) {
          return;
        }

        try {
          const { data } = await fetchJson("/api/submissions/clear", { method: "POST" });
          if (data.success) {
            loadSubmissions();
            alert("All submissions cleared.");
            return;
          }
          alert("Failed to clear submissions.");
        } catch (error) {
          alert("Failed to clear submissions.");
        }
      });
    }
  };

  // Render installed components list.
  const renderComponents = (components) => {
    const container = qs("#installed-components");
    if (!container) {
      return;
    }

    const list = Array.isArray(components) ? components : [];
    if (list.length === 0) {
      container.innerHTML = "<p class=\"text-gray-500 text-sm\">No components installed.</p>";
      return;
    }

    container.innerHTML = list
      .map((component) => {
        const statusBadge = component.enabled
          ? "<span class=\"px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700\">Enabled</span>"
          : "<span class=\"px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600\">Disabled</span>";
        const toggleLabel = component.enabled ? "Disable" : "Enable";
        const toggleClasses = component.enabled
          ? "bg-gray-100 text-gray-700"
          : "bg-indigo-600 text-white";
        const updateButton = component.repo
          ? `<button type="button" data-action="update-component" data-component="${component.name}" class="px-3 py-1 text-sm bg-blue-50 text-blue-700 rounded hover:bg-blue-100">Update</button>`
          : "";

        return `
          <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="flex justify-between items-start mb-2">
              <div>
                <h4 class="font-semibold text-gray-900">${component.displayName}</h4>
                <p class="text-xs text-gray-500">v${component.version} by ${component.author}</p>
              </div>
              ${statusBadge}
            </div>
            <p class="text-sm text-gray-600 mb-3">${component.description}</p>
            <div class="flex gap-2">
              <button type="button" data-action="toggle-component" data-component="${component.name}" data-enabled="${!component.enabled}" class="px-3 py-1 text-sm rounded ${toggleClasses} hover:opacity-80">
                ${toggleLabel}
              </button>
              ${updateButton}
              <button type="button" data-action="delete-component" data-component="${component.name}" class="px-3 py-1 text-sm bg-red-50 text-red-700 rounded hover:bg-red-100">Delete</button>
            </div>
          </div>
        `;
      })
      .join("");
  };

  // Render available components from the registry.
  const renderAvailableComponents = (components) => {
    const container = qs("#browse-components");
    if (!container) {
      return;
    }

    const list = Array.isArray(components) ? components : [];
    if (list.length === 0) {
      container.innerHTML = "<p class=\"text-gray-500 text-sm\">No components available.</p>";
      return;
    }

    container.innerHTML = list
      .map((component) => {
        return `
          <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="flex justify-between items-start mb-2">
              <div>
                <h4 class="font-semibold text-gray-900">${component.displayName}</h4>
                <p class="text-xs text-gray-500">v${component.version} by ${component.author}</p>
              </div>
              <div class="text-xs text-gray-500">
                ⬇ ${component.downloads} | ★ ${component.stars}
              </div>
            </div>
            <p class="text-sm text-gray-600 mb-3">${component.description}</p>
            <button type="button" data-action="install-component" data-repo="${component.repo}" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
              Install
            </button>
          </div>
        `;
      })
      .join("");
  };

  // Fetch installed components.
  const loadComponents = async () => {
    const container = qs("#installed-components");
    if (!container) {
      return;
    }

    try {
      const { data } = await fetchJson("/api/components");
      if (data.success) {
        renderComponents(data.components);
        return;
      }
    } catch (error) {
    }

    container.innerHTML = "<p class=\"text-red-600 text-sm\">Failed to load components.</p>";
  };

  // Fetch available components from the registry API.
  const browseComponents = async () => {
    const button = qs("#browse-btn");
    const container = qs("#browse-components");

    if (!button || !container) {
      return;
    }

    setButtonState(button, { loading: true, label: "Loading..." });

    try {
      const { data } = await fetchJson("/api/components/browse");
      if (data.success) {
        renderAvailableComponents(data.components);
        return;
      }
    } catch (error) {
    } finally {
      setButtonState(button, { loading: false, label: "Refresh" });
    }

    container.innerHTML = "<p class=\"text-red-600 text-sm\">Failed to load available components.</p>";
  };

  // Install a component from a remote repo.
  const installComponent = async (repo) => {
    if (!confirm(`Install component from ${repo}?`)) {
      return;
    }

    try {
      const { data } = await fetchJson("/api/components/install", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ repo })
      });

      if (data.success) {
        alert(data.message);
        loadComponents();
        return;
      }

      alert("Installation failed: " + data.error);
    } catch (error) {
      alert("Installation failed");
    }
  };

  // Update an installed component.
  const updateComponent = async (name) => {
    if (!confirm(`Update ${name} component?`)) {
      return;
    }

    try {
      const { data } = await fetchJson("/api/components/update", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name })
      });

      if (data.success) {
        alert(data.message);
        loadComponents();
        return;
      }

      alert("Update failed: " + data.error);
    } catch (error) {
      alert("Update failed");
    }
  };

  // Toggle a component enabled state.
  const toggleComponent = async (name, enabled) => {
    try {
      const { data } = await fetchJson("/api/components/toggle", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name, enabled })
      });

      if (data.success) {
        loadComponents();
        return;
      }

      alert("Toggle failed: " + data.error);
    } catch (error) {
      alert("Toggle failed");
    }
  };

  // Delete a component.
  const deleteComponent = async (name) => {
    if (!confirm(`Delete ${name} component? This cannot be undone.`)) {
      return;
    }

    try {
      const { data } = await fetchJson("/api/components/delete", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name })
      });

      if (data.success) {
        alert(data.message);
        loadComponents();
        return;
      }

      alert("Delete failed: " + data.error);
    } catch (error) {
      alert("Delete failed");
    }
  };

  // Handle component actions without inline handlers.
  const initComponentActions = () => {
    const refreshButton = qs("#refresh-components-btn");
    if (refreshButton) {
      refreshButton.addEventListener("click", loadComponents);
    }

    const browseButton = qs("#browse-btn");
    if (browseButton) {
      browseButton.addEventListener("click", browseComponents);
    }

    const installedContainer = qs("#installed-components");
    if (installedContainer) {
      installedContainer.addEventListener("click", (event) => {
        const button = event.target.closest("button[data-action]");
        if (!button) {
          return;
        }

        const action = button.dataset.action || "";
        const name = button.dataset.component || "";

        if (action === "toggle-component") {
          const enabled = button.dataset.enabled === "true";
          if (name) {
            toggleComponent(name, enabled);
          }
          return;
        }

        if (action === "update-component" && name) {
          updateComponent(name);
          return;
        }

        if (action === "delete-component" && name) {
          deleteComponent(name);
        }
      });
    }

    const browseContainer = qs("#browse-components");
    if (browseContainer) {
      browseContainer.addEventListener("click", (event) => {
        const button = event.target.closest("button[data-action=\"install-component\"]");
        if (!button) {
          return;
        }

        const repo = button.dataset.repo || "";
        if (repo) {
          installComponent(repo);
        }
      });
    }
  };

  // Build a nested tree list UI from API data.
  const renderTreeList = (items, container, depth, onFileClick) => {
    items.forEach((item) => {
      if (item.type === "directory") {
        const header = document.createElement("div");
        header.className = "text-xs uppercase tracking-wide text-gray-400 mt-3";
        header.style.paddingLeft = `${depth * 12}px`;
        header.textContent = item.name;
        container.appendChild(header);

        if (Array.isArray(item.children)) {
          renderTreeList(item.children, container, depth + 1, onFileClick);
        }
        return;
      }

      if (item.type === "file") {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "w-full text-left px-2 py-2 rounded-md text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition-colors";
        button.style.paddingLeft = `${depth * 12 + 8}px`;

        const label = item.label || item.path;
        const labelSpan = document.createElement("span");
        labelSpan.className = "block text-sm";
        labelSpan.textContent = label;

        const pathSpan = document.createElement("span");
        pathSpan.className = "block text-xs text-gray-400";
        pathSpan.textContent = item.path;

        button.appendChild(labelSpan);
        button.appendChild(pathSpan);
        button.dataset.path = item.path;
        button.addEventListener("click", () => onFileClick(item.path, label, button));
        container.appendChild(button);
      }
    });
  };

  // Load a tree list from the API.
  const loadTreeList = async (editor, force = false) => {
    if (!editor.list) {
      return;
    }

    if (editor.state.loaded && !force) {
      return;
    }

    editor.list.textContent = "Loading...";

    try {
      const { data } = await fetchJson(editor.endpoints.list);
      if (data.success) {
        editor.list.innerHTML = "";
        if (!Array.isArray(data.items) || data.items.length === 0) {
          editor.list.innerHTML = `<p class="text-xs text-gray-500">${editor.emptyText}</p>`;
        } else {
          renderTreeList(data.items, editor.list, 0, (path, label, button) =>
            openEditorItem(editor, path, label, button)
          );
        }
        editor.state.loaded = true;
        return;
      }

      editor.list.innerHTML = `<p class="text-xs text-red-600">${editor.errorText}</p>`;
    } catch (error) {
      editor.list.innerHTML = `<p class="text-xs text-red-600">${editor.errorText}</p>`;
    }
  };

  // Load a selected file into the editor.
  const openEditorItem = async (editor, path, label, button) => {
    if (!editor.editorArea || !editor.title || !editor.pathLabel) {
      return;
    }

    editor.state.activeButton = setActiveButton(button, editor.state.activeButton);
    editor.title.textContent = label;
    editor.pathLabel.textContent = path;
    editor.pathLabel.classList.remove("hidden");
    setEditorState(editor, true);

    try {
      const { data } = await fetchJson(`${editor.endpoints.load}?path=${encodeURIComponent(path)}`);
      if (!data.success) {
        alert(editor.loadErrorText);
        return;
      }
      editor.state.currentPath = path;
      editor.state.originalBody = data.content;
      editor.editorArea.value = data.content;
    } catch (error) {
      alert(editor.loadErrorText);
    }
  };

  // Persist editor content back to the API.
  const saveEditorContent = async (editor) => {
    if (!editor.editorArea || !editor.state.currentPath) {
      return;
    }

    try {
      const { data } = await fetchJson(editor.endpoints.save, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ path: editor.state.currentPath, content: editor.editorArea.value })
      });

      if (data.success) {
        editor.state.originalBody = editor.editorArea.value;
        alert(editor.saveSuccessText);
        return;
      }

      alert(editor.saveErrorText);
    } catch (error) {
      alert(editor.saveErrorText);
    }
  };

  // Revert editor changes to the last loaded version.
  const cancelEditorContent = (editor) => {
    if (!editor.editorArea) {
      return;
    }

    editor.editorArea.value = editor.state.originalBody;
  };

  // Wire editor buttons for a given editor definition.
  const initEditor = (editor) => {
    if (editor.saveButton) {
      editor.saveButton.addEventListener("click", () => saveEditorContent(editor));
    }

    if (editor.cancelButton) {
      editor.cancelButton.addEventListener("click", () => cancelEditorContent(editor));
    }

    if (editor.refreshButton) {
      editor.refreshButton.addEventListener("click", () => {
        editor.state.loaded = false;
        loadTreeList(editor, true);
      });
    }
  };

  // Initialize content and block editors.
  const initEditors = () => {
    const contentEditor = {
      list: qs("#content-list"),
      editorArea: qs("#content-editor-area"),
      placeholder: qs("#content-editor-placeholder"),
      saveButton: qs("#content-save-btn"),
      cancelButton: qs("#content-cancel-btn"),
      title: qs("#content-editor-title"),
      pathLabel: qs("#content-editor-path"),
      refreshButton: qs("#refresh-content-btn"),
      endpoints: {
        list: "/api/site/list",
        load: "/api/site",
        save: "/api/save"
      },
      emptyText: "No content found.",
      errorText: "Failed to load content.",
      errorLog: "Failed to load content list:",
      loadErrorText: "Failed to load content.",
      loadErrorLog: "Failed to load content:",
      saveSuccessText: "Content saved.",
      saveErrorText: "Failed to save content.",
      saveErrorLog: "Content save error:",
      state: {
        loaded: false,
        currentPath: "",
        originalBody: "",
        activeButton: null
      }
    };

    const blockEditor = {
      list: qs("#blocks-list"),
      editorArea: qs("#block-editor-area"),
      placeholder: qs("#block-editor-placeholder"),
      saveButton: qs("#block-save-btn"),
      cancelButton: qs("#block-cancel-btn"),
      title: qs("#block-editor-title"),
      pathLabel: qs("#block-editor-path"),
      refreshButton: qs("#refresh-blocks-btn"),
      endpoints: {
        list: "/api/blocks/list",
        load: "/api/blocks",
        save: "/api/blocks/save"
      },
      emptyText: "No blocks found.",
      errorText: "Failed to load blocks.",
      errorLog: "Failed to load blocks list:",
      loadErrorText: "Failed to load block.",
      loadErrorLog: "Failed to load block:",
      saveSuccessText: "Block saved.",
      saveErrorText: "Failed to save block.",
      saveErrorLog: "Block save error:",
      state: {
        loaded: false,
        currentPath: "",
        originalBody: "",
        activeButton: null
      }
    };

    initEditor(contentEditor);
    initEditor(blockEditor);

    const contentTab = qs("[data-tab='content']");
    if (contentTab) {
      contentTab.addEventListener("click", () => loadTreeList(contentEditor));
    }

    const blocksTab = qs("[data-tab='blocks']");
    if (blocksTab) {
      blocksTab.addEventListener("click", () => loadTreeList(blockEditor));
    }
  };

  // Lazy-load heavier tabs on first click.
  const initLazyTabs = () => {
    const submissionsTab = qs("[data-tab='submissions']");
    if (submissionsTab) {
      submissionsTab.addEventListener("click", loadSubmissions);
    }

    const componentsTab = qs("[data-tab='components']");
    if (componentsTab) {
      componentsTab.addEventListener("click", loadComponents);
    }
  };

  // Initialize all admin UI behaviors once the DOM is ready.
  const init = () => {
    initTabs();
    initDashboard();
    initSettings();
    initAdvanced();
    initSecurity();
    initSubmissions();
    initEditors();
    initLazyTabs();
    initUpdates();
    initComponentActions();
  };

  // Register DOM ready listener to guarantee elements exist.
  document.addEventListener("DOMContentLoaded", init);

  // Keep globals clean: actions are wired through event listeners.
})();
