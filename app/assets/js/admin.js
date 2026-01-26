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
  const query = (selector, scope = document) => scope.querySelector(selector);
  const queryAll = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));

  // Escape HTML to prevent injection when rendering API-provided strings.
  const escapeHtml = (value) => {
    const text = String(value ?? "");
    return text
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  };

  // Allow only http(s) and same-origin relative URLs.
  const sanitizeUrl = (value) => {
    if (typeof value !== "string") {
      return "";
    }

    const trimmed = value.trim();
    if (trimmed === "") {
      return "";
    }

    if (trimmed.startsWith("/")) {
      return trimmed;
    }

    try {
      const parsed = new URL(trimmed, window.location.origin);
      if (parsed.protocol === "http:" || parsed.protocol === "https:") {
        return parsed.href;
      }
    } catch (error) {
      // Invalid URL; return empty so callers can skip the link.
    }

    return "";
  };

  // Remove path separators and traversal from filenames.
  const sanitizeFilename = (value) => {
    const text = String(value ?? "").trim();
    if (!text) {
      return "";
    }

    return text.replace(/\0/g, "").replace(/[/\\]/g, "").replace(/\.\./g, "");
  };

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
    const contentType = response.headers.get("Content-Type") || "";
    const text = await response.text();
    let payload = {};

    if (contentType.includes("application/json")) {
      try {
        payload = text ? JSON.parse(text) : {};
      } catch (error) {
        payload = { success: false, error: text || "Invalid JSON response." };
      }
    } else {
      payload = { success: false, error: text || "Request failed." };
    }

    return { response, data: payload };
  };

  // Post JSON bodies with the default headers.
  const postJson = (url, body) => fetchJson(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body)
  });

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

    const toneClasses = {
      success: ["border-emerald-200", "bg-emerald-50", "text-emerald-700"],
      error: ["border-red-200", "bg-red-50", "text-red-700"],
      info: ["border-indigo-200", "bg-indigo-50", "text-indigo-700"]
    };

    element.textContent = text || "";
    element.classList.add(...(toneClasses[tone] || toneClasses.info));

    if (link) {
      const safeLink = sanitizeUrl(link);
      if (!safeLink) {
        return;
      }
      const linkWrapper = document.createElement("div");
      linkWrapper.className = "mt-2 text-xs";
      const linkElement = document.createElement("a");
      linkElement.href = safeLink;
      linkElement.textContent = linkLabel;
      linkElement.className = "text-indigo-600 hover:text-indigo-700 underline break-all";
      linkWrapper.appendChild(linkElement);
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

    const valueElement = document.createElement("p");
    valueElement.className = "text-sm text-gray-600 text-right break-all";
    valueElement.textContent = maskSettingValue(key, value);

    row.appendChild(labelBlock);
    row.appendChild(valueElement);

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
    editor.state.isEditing = enabled;
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
    const link = query(`.admin-nav-link[data-tab="${tabName}"]`);
    if (link) {
      link.dispatchEvent(new MouseEvent("click", { bubbles: true, cancelable: true }));
    }
  };

  // Tab switching logic for the admin sidebar.
  const initTabs = () => {
    const links = queryAll(".admin-nav-link");
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
        queryAll(".admin-tab").forEach((tab) => tab.classList.add("hidden"));

        const target = query(`#${tabName}-tab`);
        if (target) {
          target.classList.remove("hidden");
        }
      });
    });
  };

  // Render editable settings for the Settings tab.
  const renderSettings = (settings) => {
    const container = query("#settings-container");
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
    const container = query("#settings-readonly");
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
      const { data: responseData } = await fetchJson("/api/settings");
      if (responseData.success) {
        settingsState.coreKeys = new Set((responseData.meta && responseData.meta.core_site_keys) || []);
        renderSettings(responseData.editable);
        renderReadonlySettings(responseData.readonly);
      }
    } catch (error) {
      // Silent failure: keep current settings until next refresh.
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
      const { data: responseData } = await postJson("/api/settings", {
        settings,
        removed: Array.from(settingsState.removed)
      });

      if (responseData.success) {
        alert("Settings saved successfully!");
        settingsState.removed.clear();
        loadSettings();
        return;
      }

      alert("Failed to save settings: " + (responseData.error || "Unknown error"));
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
    const form = query("#settings-form");
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

    const container = query("#settings-container");
    if (!container) {
      return;
    }

    settingsState.removed.delete(key);
    container.appendChild(createSettingField(key, value));
  };

  // Wire settings form events.
  const initSettings = () => {
    const form = query("#settings-form");
    const addButton = query("#add-setting-btn");

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
    const container = query("#dashboard-settings-list");
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
    const pagesCount = query("#dashboard-pages-count");
    const blocksCount = query("#dashboard-blocks-count");
    const themeSelect = query("#dashboard-theme-select");
    const themeSave = query("#dashboard-theme-save");
    const statusMessage = query("#dashboard-status");
    const refreshButton = query("#dashboard-refresh-btn");

    const openContent = query("#dashboard-open-content");
    const openPages = query("#dashboard-open-pages");
    const openBlocks = query("#dashboard-open-blocks");
    const openSettingsButton = query("#dashboard-open-settings");

    const loadOverview = async () => {
      if (pagesCount) {
        pagesCount.textContent = "--";
      }
      if (blocksCount) {
        blocksCount.textContent = "--";
      }

      try {
        const { data: responseData } = await fetchJson("/api/admin/overview");
        if (!responseData.success) {
          throw new Error(responseData.error || "Failed to load overview.");
        }

        if (statusMessage) {
          statusMessage.classList.add("hidden");
        }

        const counts = responseData.counts || {};
        if (pagesCount) {
          pagesCount.textContent = counts.pages ?? 0;
        }
        if (blocksCount) {
          blocksCount.textContent = counts.blocks ?? 0;
        }

        if (themeSelect) {
          const themes = Array.isArray(responseData.themes) ? responseData.themes : [];
          themeSelect.innerHTML = "";
          if (themes.length === 0) {
            const option = document.createElement("option");
            option.value = "";
            option.textContent = "No themes found";
            themeSelect.appendChild(option);
          }

          if (themes.length > 0) {
            themes.forEach((theme) => {
              const option = document.createElement("option");
              option.value = theme.name;
              option.textContent = theme.label;
              themeSelect.appendChild(option);
            });
            themeSelect.value = responseData.current_theme || "";
          }
        }

        renderDashboardSettings(responseData.site_settings, responseData.readonly_settings);
      } catch (error) {
        setMessage(statusMessage, { text: "Failed to load dashboard overview.", tone: "error" });
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
          const { data: responseData } = await postJson("/api/settings", {
            settings: { "site.theme": themeSelect.value }
          });

          if (responseData.success) {
            setMessage(statusMessage, {
              text: "Theme updated. Refresh the site to apply changes.",
              tone: "success"
            });
            return;
          }

          setMessage(statusMessage, { text: responseData.error || "Failed to update theme.", tone: "error" });
        } catch (error) {
          setMessage(statusMessage, { text: "Failed to update theme.", tone: "error" });
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

    const statusMessage = query("#advanced-status");
    const clearSessionsButton = query("#clear-sessions-btn");
    const clearMagicLinksButton = query("#clear-magic-links-btn");

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
      setMessage(statusMessage, { text: "Working...", tone: "info" });

      try {
        const { data: responseData } = await fetchJson(endpoint, { method: "POST" });
        if (responseData.success) {
          setMessage(statusMessage, { text: responseData.message || successText, tone: "success" });
          return;
        }

        setMessage(statusMessage, { text: responseData.error || "Action failed.", tone: "error" });
      } catch (error) {
        setMessage(statusMessage, { text: "Action failed.", tone: "error" });
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
    const securityMessage = query("#security-message");
    const resetButton = query("#security-password-reset-btn");
    const clearBlocklistButton = query("#clear-blocklist-btn");
    const blockedHostsList = query("#blocked-hosts-list");
    const blockedHostsToggle = query("#blocked-hosts-toggle");
    const blockedHostsFade = query("#blocked-hosts-fade");

    const renderBlockedHosts = (items) => {
      if (!blockedHostsList) {
        return;
      }

      blockedHostsList.innerHTML = "";
      const maxVisible = 20;
      const hasEntries = Array.isArray(items) && items.length > 0;

      if (!hasEntries) {
        blockedHostsList.innerHTML = "<li class=\"text-sm text-gray-500\">No blocked hosts.</li>";
        blockedHostsToggle?.classList.add("hidden");
        blockedHostsFade?.classList.add("hidden");
        return;
      }

      const rows = items.map((entry, index) => {
        const item = document.createElement("li");
        item.className = "flex flex-col gap-1 rounded-lg border border-gray-200 px-3 py-2";
        if (index >= maxVisible) {
          item.classList.add("hidden");
        }

        const header = document.createElement("div");
        header.className = "flex items-center justify-between gap-2";

        const ip = document.createElement("span");
        ip.className = "text-sm font-semibold text-gray-800";
        ip.textContent = entry.ip || "Unknown";

        const expires = document.createElement("span");
        expires.className = "text-xs text-gray-400";
        expires.textContent = entry.expires_in || "Unknown expiry";

        header.appendChild(ip);
        header.appendChild(expires);

        const reason = document.createElement("p");
        reason.className = "text-xs text-gray-500";
        reason.textContent = entry.reason || "Security block";

        item.appendChild(header);
        item.appendChild(reason);
        blockedHostsList.appendChild(item);
        return item;
      });

      const hasOverflow = rows.length > maxVisible;

      if (blockedHostsToggle) {
        blockedHostsToggle.classList.toggle("hidden", !hasOverflow);
        blockedHostsToggle.textContent = hasOverflow ? `More (${rows.length - maxVisible})` : "";
        blockedHostsToggle.dataset.expanded = "false";
      }

      if (blockedHostsFade) {
        blockedHostsFade.classList.toggle("hidden", !hasOverflow);
      }

      if (blockedHostsToggle) {
        blockedHostsToggle.onclick = () => {
          const expanded = blockedHostsToggle.dataset.expanded === "true";
          const nextState = !expanded;
          blockedHostsToggle.dataset.expanded = nextState ? "true" : "false";
          rows.forEach((row, index) => {
            if (index >= maxVisible) {
              row.classList.toggle("hidden", !nextState);
            }
          });
          blockedHostsToggle.textContent = nextState ? "Less" : `More (${rows.length - maxVisible})`;
          if (blockedHostsFade) {
            blockedHostsFade.classList.toggle("hidden", nextState);
          }
        };
      }
    };

    const loadBlockedHosts = async () => {
      if (!blockedHostsList) {
        return;
      }

      try {
        const { data: responseData } = await fetchJson("/api/blocklist/list");
        if (responseData.success) {
          renderBlockedHosts(responseData.items || []);
          return;
        }
      } catch (error) {
        // Fall through to render an empty list.
      }

      renderBlockedHosts([]);
    };

    if (resetButton) {
      resetButton.addEventListener("click", async () => {
        const confirmed = confirm("Send a password reset magic link to the admin email?");
        if (!confirmed) {
          return;
        }

        try {
          setMessage(securityMessage, { text: "Sending reset link..." });
          const { data: responseData } = await fetchJson("/api/security/password-reset", { method: "POST" });
          if (responseData.success) {
            setMessage(securityMessage, {
              text: responseData.message || "Reset link sent.",
              tone: "success",
              link: responseData.magic_link || null
            });
            return;
          }
          setMessage(securityMessage, { text: responseData.error || "Failed to send reset link.", tone: "error" });
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
          const { data: responseData } = await fetchJson("/api/blocklist/clear", { method: "POST" });
          if (responseData.success) {
            alert("Blocklist cleared.");
            loadBlockedHosts();
            return;
          }
          alert("Failed to clear blocklist.");
        } catch (error) {
          alert("Failed to clear blocklist.");
        }
      });
    }

    loadBlockedHosts();
  };

  const getExportFilename = (response, fallback) => {
    const header = response.headers.get("Content-Disposition") || "";
    const match = header.match(/filename="?([^";]+)"?/i);
    if (match && match[1]) {
      const sanitized = sanitizeFilename(match[1]);
      if (sanitized) {
        return sanitized;
      }
    }

    return fallback;
  };

  // Export content handler for the advanced tab.
  const initExport = () => {
    const button = query("#export-btn");
    const statusMessage = query("#export-status");

    if (!button || !statusMessage) {
      return;
    }

    button.addEventListener("click", async () => {
      const originalHtml = button.innerHTML;

      setButtonState(button, { loading: true, html: `${SPINNER_ICON}Preparing export...` });
      statusMessage.classList.remove("hidden");
      statusMessage.className = "mt-3 text-sm text-blue-600";
      statusMessage.textContent = "Creating archive...";

      try {
        const response = await secureFetch("/api/export", { method: "POST" });
        const contentType = response.headers.get("Content-Type") || "";
        if (!response.ok) {
          let errorMessage = "Export failed";
          if (contentType.includes("application/json")) {
            const responseData = await response.json();
            errorMessage = responseData.error || errorMessage;
          }
          throw new Error(errorMessage);
        }

        if (contentType.includes("application/json")) {
          const responseData = await response.json();
          throw new Error(responseData.error || "Export failed");
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

        statusMessage.className = "mt-3 text-sm text-green-600 font-medium";
        statusMessage.textContent = "✓ Export complete! Check your downloads.";
        setTimeout(() => statusMessage.classList.add("hidden"), 5000);
      } catch (error) {
        statusMessage.className = "mt-3 text-sm text-red-600 font-medium";
        statusMessage.textContent = "✗ Export failed. Please try again.";
      } finally {
        setButtonState(button, { loading: false, html: originalHtml });
      }
    });
  };

  // Build the update banner actions for the current auto-update mode.
  const renderUpdateActions = (updateData) => {
    const autoUpdateMode = updateData.auto_update_mode;
    const updateUrl = sanitizeUrl(updateData.update?.download_url || "");
    const releaseUrl = sanitizeUrl(updateData.update?.url || "");

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

  // Reset banner visibility classes before writing new content.
  const resetUpdateBanner = (banner) => {
    if (!banner) {
      return;
    }

    banner.classList.remove("hidden", "opacity-0", "transition-opacity");
  };

  // Show the update banner after a successful check.
  const showUpdateBanner = (updateData) => {
    const banner = query("#update-banner");
    if (!banner) {
      return;
    }

    resetUpdateBanner(banner);
    const actions = renderUpdateActions(updateData);
    const versionLabel = escapeHtml(updateData.update?.version || "");
    banner.innerHTML = `<div class="bg-blue-50 border border-blue-200 rounded-lg p-6"><div class="flex items-start justify-between"><div class="flex-1"><h3 class="text-lg font-semibold text-blue-900 mb-2">Update Available: v${versionLabel}</h3><p class="text-blue-800 text-sm mb-4">A new version is available. Your content and themes will be preserved.</p><div class="flex gap-3">${actions}</div></div></div></div>`;
  };

  // Check for updates when the button is pressed.
  const checkForUpdates = async () => {
    const button = query("#check-updates-btn");
    const banner = query("#update-banner");
    if (!button || !banner) {
      return;
    }

    const originalHtml = button.innerHTML;
    setButtonState(button, { loading: true, html: `${SPINNER_ICON}Checking...` });

    try {
      const { data: responseData } = await fetchJson("/api/updates/check?force=1");
      if (responseData.error) {
        resetUpdateBanner(banner);
        banner.innerHTML = `<div class="bg-red-50 border border-red-200 rounded-lg p-4"><p class="text-red-800 text-sm font-medium">✗ ${escapeHtml(responseData.error)}</p></div>`;
        setTimeout(() => banner.classList.add("hidden"), 6000);
        return;
      }

      if (responseData.update_available && responseData.update) {
        showUpdateBanner(responseData);
      } else {
        resetUpdateBanner(banner);
        banner.innerHTML = `<div class="bg-green-50 border border-green-200 rounded-lg p-4 transition-all"><p class="text-green-800 text-sm font-medium">✓ You are running the latest version (${escapeHtml(responseData.current_version)})</p></div>`;
        setTimeout(() => {
          banner.classList.add("opacity-0", "transition-opacity");
          setTimeout(() => banner.classList.add("hidden"), 300);
        }, 4000);
      }
    } catch (error) {
      resetUpdateBanner(banner);
      banner.innerHTML = `<div class="bg-red-50 border border-red-200 rounded-lg p-4"><p class="text-red-800 text-sm font-medium">✗ Failed to check for updates. Please try again.</p></div>`;
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

    const safeDownloadUrl = sanitizeUrl(downloadUrl);
    if (!safeDownloadUrl) {
      alert("Invalid update URL.");
      return;
    }

    const banner = query("#update-banner");
    if (!banner) {
      return;
    }

    banner.innerHTML = `<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4"><div class="flex items-center gap-3">${SPINNER_ICON}<p class="text-yellow-800 text-sm font-medium">Installing update... Please wait.</p></div></div>`;

    try {
      const { data: responseData } = await postJson("/api/updates/apply", {
        download_url: safeDownloadUrl
      });

      if (responseData.success) {
        banner.innerHTML = `<div class="bg-green-50 border border-green-200 rounded-lg p-4"><p class="text-green-800 text-sm font-medium">✓ ${escapeHtml(responseData.message)}</p></div>`;
        setTimeout(() => window.location.reload(), 2000);
      } else {
        banner.innerHTML = `<div class="bg-red-50 border border-red-200 rounded-lg p-4"><p class="text-red-800 text-sm font-medium">✗ Update failed: ${escapeHtml(responseData.error)}</p></div>`;
      }
    } catch (error) {
      banner.innerHTML = `<div class="bg-red-50 border border-red-200 rounded-lg p-4"><p class="text-red-800 text-sm">Update failed. Please try again.</p></div>`;
    }
  };

  // Wire update buttons and delegated banner actions.
  const initUpdates = () => {
    const checkButton = query("#check-updates-btn");
    if (checkButton) {
      checkButton.addEventListener("click", checkForUpdates);
    }

    const banner = query("#update-banner");
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
    const container = query("#submissions-container");
    const countElement = query("#submissions-count");

    if (!container || !countElement) {
      return;
    }

    try {
      const { data: responseData } = await fetchJson("/api/submissions");
      if (responseData.success) {
        renderSubmissions(responseData.submissions);
        return;
      }
    } catch (error) {
      // Fall through to render the error state.
    }

    container.innerHTML = "<p class=\"text-red-600 text-sm\">Failed to load submissions.</p>";
  };

  // Render submission cards in the Submissions tab using DOM nodes to avoid XSS.
  const renderSubmissions = (submissions) => {
    const container = query("#submissions-container");
    const countElement = query("#submissions-count");

    if (!container || !countElement) {
      return;
    }

    const items = Array.isArray(submissions) ? submissions : [];
    countElement.textContent = `Total: ${items.length} submission(s)`;

    if (items.length === 0) {
      container.innerHTML = "<p class=\"text-gray-500 text-sm\">No submissions yet.</p>";
      return;
    }

    container.innerHTML = "";
    const fragment = document.createDocumentFragment();

    items.forEach((submission) => {
      const card = document.createElement("div");
      card.className = "border border-gray-200 rounded-lg p-4 bg-gray-50 hover:bg-gray-100 transition-colors";

      const header = document.createElement("div");
      header.className = "flex justify-between items-start mb-2";

      const meta = document.createElement("div");

      const name = document.createElement("p");
      name.className = "font-semibold text-gray-900";
      name.textContent = submission.name || "Unknown";

      const email = document.createElement("p");
      email.className = "text-sm text-gray-600";
      email.textContent = submission.email || "Unknown";

      meta.appendChild(name);
      meta.appendChild(email);

      const date = document.createElement("span");
      date.className = "text-xs text-gray-500";
      const submittedAt = Number(submission.submitted_at) || 0;
      date.textContent = submittedAt ? new Date(submittedAt * 1000).toLocaleString() : "Unknown";

      header.appendChild(meta);
      header.appendChild(date);

      const message = document.createElement("p");
      message.className = "text-sm text-gray-700 whitespace-pre-wrap mb-2";
      message.textContent = submission.message || "";

      card.appendChild(header);
      card.appendChild(message);

      if (submission.submitted_from) {
        const source = document.createElement("p");
        source.className = "text-xs text-gray-500 mt-2";
        const sourceLabel = document.createElement("strong");
        sourceLabel.textContent = "From:";
        source.appendChild(sourceLabel);
        source.appendChild(document.createTextNode(` ${submission.submitted_from}`));
        card.appendChild(source);
      }

      fragment.appendChild(card);
    });

    container.appendChild(fragment);
  };

  // Wire the submissions clear action.
  const initSubmissions = () => {
    const clearButton = query("#clear-submissions-btn");

    if (clearButton) {
      clearButton.addEventListener("click", async () => {
        if (!confirm("Delete all submissions? This cannot be undone.")) {
          return;
        }

        try {
          const { data: responseData } = await fetchJson("/api/submissions/clear", { method: "POST" });
          if (responseData.success) {
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

  // Render installed components list with escaped metadata.
  const renderComponents = (components) => {
    const container = query("#installed-components");
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
        const componentName = escapeHtml(component.name || "");
        const displayName = escapeHtml(component.displayName || component.name || "Component");
        const version = escapeHtml(component.version || "");
        const author = escapeHtml(component.author || "");
        const description = escapeHtml(component.description || "");
        const isEnabled = Boolean(component.enabled);

        const statusBadge = isEnabled
          ? "<span class=\"px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700\">Enabled</span>"
          : "<span class=\"px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600\">Disabled</span>";
        const toggleLabel = isEnabled ? "Disable" : "Enable";
        const toggleClasses = isEnabled
          ? "bg-gray-100 text-gray-700"
          : "bg-indigo-600 text-white";
        const updateButton = component.repo
          ? `<button type="button" data-action="update-component" data-component="${componentName}" class="px-3 py-1 text-sm bg-blue-50 text-blue-700 rounded hover:bg-blue-100">Update</button>`
          : "";

        return `
          <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="flex justify-between items-start mb-2">
              <div>
                <h4 class="font-semibold text-gray-900">${displayName}</h4>
                <p class="text-xs text-gray-500">v${version} by ${author}</p>
              </div>
              ${statusBadge}
            </div>
            <p class="text-sm text-gray-600 mb-3">${description}</p>
            <div class="flex gap-2">
              <button type="button" data-action="toggle-component" data-component="${componentName}" data-enabled="${!isEnabled}" class="px-3 py-1 text-sm rounded ${toggleClasses} hover:opacity-80">
                ${toggleLabel}
              </button>
              ${updateButton}
              <button type="button" data-action="delete-component" data-component="${componentName}" class="px-3 py-1 text-sm bg-red-50 text-red-700 rounded hover:bg-red-100">Delete</button>
            </div>
          </div>
        `;
      })
      .join("");
  };

  // Render available components from the registry with escaped metadata.
  const renderAvailableComponents = (components) => {
    const container = query("#browse-components");
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
        const displayName = escapeHtml(component.displayName || "Component");
        const version = escapeHtml(component.version || "");
        const author = escapeHtml(component.author || "");
        const description = escapeHtml(component.description || "");
        const repo = escapeHtml(component.repo || "");
        const downloads = Number(component.downloads) || 0;
        const stars = Number(component.stars) || 0;

        return `
          <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="flex justify-between items-start mb-2">
              <div>
                <h4 class="font-semibold text-gray-900">${displayName}</h4>
                <p class="text-xs text-gray-500">v${version} by ${author}</p>
              </div>
              <div class="text-xs text-gray-500">
                ⬇ ${downloads} | ★ ${stars}
              </div>
            </div>
            <p class="text-sm text-gray-600 mb-3">${description}</p>
            <button type="button" data-action="install-component" data-repo="${repo}" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
              Install
            </button>
          </div>
        `;
      })
      .join("");
  };

  // Fetch installed components.
  const loadComponents = async () => {
    const container = query("#installed-components");
    if (!container) {
      return;
    }

    try {
      const { data: responseData } = await fetchJson("/api/components");
      if (responseData.success) {
        renderComponents(responseData.components);
        return;
      }
    } catch (error) {
      // Fall through to render the error state.
    }

    container.innerHTML = "<p class=\"text-red-600 text-sm\">Failed to load components.</p>";
  };

  // Fetch available components from the registry API.
  const browseComponents = async () => {
    const button = query("#browse-btn");
    const container = query("#browse-components");

    if (!button || !container) {
      return;
    }

    setButtonState(button, { loading: true, label: "Loading..." });

    try {
      const { data: responseData } = await fetchJson("/api/components/browse");
      if (responseData.success) {
        renderAvailableComponents(responseData.components);
        return;
      }
    } catch (error) {
      // Fall through to render the error state.
    } finally {
      setButtonState(button, { loading: false, label: "Refresh" });
    }

    container.innerHTML = "<p class=\"text-red-600 text-sm\">Failed to load available components.</p>";
  };

  // Install a component from a remote repo.
  const installComponent = async (repo) => {
    const repoName = String(repo || "").trim();
    if (!repoName) {
      alert("Invalid component repository.");
      return;
    }

    if (!confirm(`Install component from ${repoName}?`)) {
      return;
    }

    try {
      const { data: responseData } = await postJson("/api/components/install", {
        repo: repoName
      });

      if (responseData.success) {
        alert(responseData.message);
        loadComponents();
        return;
      }

      alert("Installation failed: " + responseData.error);
    } catch (error) {
      alert("Installation failed");
    }
  };

  // Update an installed component.
  const updateComponent = async (name) => {
    const componentName = String(name || "").trim();
    if (!componentName) {
      alert("Missing component name.");
      return;
    }

    if (!confirm(`Update ${componentName} component?`)) {
      return;
    }

    try {
      const { data: responseData } = await postJson("/api/components/update", {
        name: componentName
      });

      if (responseData.success) {
        alert(responseData.message);
        loadComponents();
        return;
      }

      alert("Update failed: " + responseData.error);
    } catch (error) {
      alert("Update failed");
    }
  };

  // Toggle a component enabled state.
  const toggleComponent = async (name, enabled) => {
    const componentName = String(name || "").trim();
    if (!componentName) {
      alert("Missing component name.");
      return;
    }

    try {
      const { data: responseData } = await postJson("/api/components/toggle", {
        name: componentName,
        enabled
      });

      if (responseData.success) {
        loadComponents();
        return;
      }

      alert("Toggle failed: " + responseData.error);
    } catch (error) {
      alert("Toggle failed");
    }
  };

  // Delete a component.
  const deleteComponent = async (name) => {
    const componentName = String(name || "").trim();
    if (!componentName) {
      alert("Missing component name.");
      return;
    }

    if (!confirm(`Delete ${componentName} component? This cannot be undone.`)) {
      return;
    }

    try {
      const { data: responseData } = await postJson("/api/components/delete", {
        name: componentName
      });

      if (responseData.success) {
        alert(responseData.message);
        loadComponents();
        return;
      }

      alert("Delete failed: " + responseData.error);
    } catch (error) {
      alert("Delete failed");
    }
  };

  // Handle component actions without inline handlers.
  const initComponentActions = () => {
    const refreshButton = query("#refresh-components-btn");
    if (refreshButton) {
      refreshButton.addEventListener("click", loadComponents);
    }

    const browseButton = query("#browse-btn");
    if (browseButton) {
      browseButton.addEventListener("click", browseComponents);
    }

    const installedContainer = query("#installed-components");
    if (installedContainer) {
      installedContainer.addEventListener("click", (event) => {
        const button = event.target.closest("button[data-action]");
        if (!button) {
          return;
        }

        const actionType = button.dataset.action || "";
        const componentName = button.dataset.component || "";

        if (actionType === "toggle-component") {
          const enabled = button.dataset.enabled === "true";
          if (componentName) {
            toggleComponent(componentName, enabled);
          }
          return;
        }

        if (actionType === "update-component" && componentName) {
          updateComponent(componentName);
          return;
        }

        if (actionType === "delete-component" && componentName) {
          deleteComponent(componentName);
        }
      });
    }

    const browseContainer = query("#browse-components");
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

  const formatDirectoryLabel = (value) =>
    value
      .replace(/[-_]+/g, " ")
      .replace(/\b\w/g, (match) => match.toUpperCase());

  const renderFileItem = (item, onFileClick, options = {}) => {
    const enableDragDrop = options.enableDragDrop === true;
    const onMove = typeof options.onMove === "function" ? options.onMove : null;
    const listItem = document.createElement("li");
    const label = item.label || item.path;
    const detailPath = item.file || item.path;
    const status = item.status || "published";
    const isHidden = status === "hidden";
    const isDraft = status === "draft";

    const button = document.createElement("button");
    button.type = "button";
    button.className =
      "w-full text-left px-2 py-2 rounded-md transition-colors";
    if (isHidden || isDraft) {
      button.classList.add("text-gray-400", "hover:text-gray-500");
    } else {
      button.classList.add("text-gray-700", "hover:bg-indigo-50", "hover:text-indigo-700");
    }

    const labelRow = document.createElement("div");
    labelRow.className = "flex flex-wrap items-center gap-2";

    const labelSpan = document.createElement("span");
    labelSpan.className = "text-sm font-medium";
    labelSpan.textContent = label;
    labelRow.appendChild(labelSpan);

    const pathSpan = document.createElement("span");
    pathSpan.className = "text-xs text-gray-400";
    pathSpan.textContent = `(${detailPath})`;
    labelRow.appendChild(pathSpan);

    button.appendChild(labelRow);
    button.dataset.path = item.path;
    button.addEventListener("click", () => onFileClick(item.path, label, button));

    if (enableDragDrop && onMove) {
      button.draggable = true;
      button.addEventListener("dragstart", (event) => {
        event.dataTransfer?.setData("text/plain", item.path || "");
        if (event.dataTransfer) {
          event.dataTransfer.effectAllowed = "move";
        }
        button.classList.add("opacity-60");
      });
      button.addEventListener("dragend", () => {
        button.classList.remove("opacity-60");
      });
    }

    listItem.appendChild(button);
    return listItem;
  };

  const flattenTreeFiles = (items, files = []) => {
    items.forEach((item) => {
      if (item.type === "file") {
        files.push(item);
        return;
      }
      if (Array.isArray(item.children)) {
        flattenTreeFiles(item.children, files);
      }
    });
    return files;
  };

  const buildUrlTree = (pages) => {
    const root = { page: null, children: {} };
    pages.forEach((page) => {
      const rawPath = page.path || "";
      const trimmed = rawPath.replace(/^\/+|\/+$/g, "");
      const segments = trimmed === "" ? [] : trimmed.split("/");
      let node = root;
      segments.forEach((segment) => {
        if (!node.children[segment]) {
          node.children[segment] = { segment, page: null, children: {} };
        }
        node = node.children[segment];
      });
      if (segments.length === 0) {
        root.page = page;
      } else {
        node.page = page;
      }
    });
    return root;
  };

  const directoryFromIndexFile = (filePath) => {
    if (!filePath) {
      return "";
    }
    const normalized = filePath.replace(/\\/g, "/");
    const match = normalized.match(/^(.*)\/index\.(md|mdx)$/i);
    return match ? match[1] : "";
  };

  const buildUrlTreeList = (node, depth, onFileClick, options = {}) => {
    const list = document.createElement("ul");
    list.className =
      depth === 0
        ? "space-y-1 text-sm text-gray-700"
        : "mt-2 space-y-1 border-l border-gray-200 pl-4";

    if (depth === 0 && node.page) {
      list.appendChild(renderFileItem(node.page, onFileClick, options));
    }

    const children = Object.values(node.children);
    children.sort((a, b) => {
      const labelA = a.page?.label || formatDirectoryLabel(a.segment || "");
      const labelB = b.page?.label || formatDirectoryLabel(b.segment || "");
      return labelA.localeCompare(labelB);
    });

    children.forEach((child) => {
      const listItem = child.page
        ? renderFileItem(child.page, onFileClick, options)
        : document.createElement("li");

      if (!child.page) {
        const label = document.createElement("span");
        label.className = "block text-sm font-semibold text-gray-600";
        label.textContent = formatDirectoryLabel(child.segment || "");
        listItem.appendChild(label);
      }

      if (child.page && options.enableDragDrop && typeof options.onMove === "function") {
        const targetPath = directoryFromIndexFile(child.page.file || "");
        if (targetPath !== "") {
          listItem.dataset.dirPath = targetPath;
          listItem.classList.add("rounded-md");
          listItem.addEventListener("dragover", (event) => {
            event.preventDefault();
            listItem.classList.add("bg-indigo-50");
          });
          listItem.addEventListener("dragleave", () => {
            listItem.classList.remove("bg-indigo-50");
          });
          listItem.addEventListener("drop", (event) => {
            event.preventDefault();
            listItem.classList.remove("bg-indigo-50");
            const sourcePath = event.dataTransfer?.getData("text/plain") || "";
            if (sourcePath) {
              options.onMove(sourcePath, targetPath);
            }
          });
        }
      }

      if (Object.keys(child.children).length > 0) {
        listItem.appendChild(buildUrlTreeList(child, depth + 1, onFileClick, options));
      }

      list.appendChild(listItem);
    });

    return list;
  };

  // Build a nested tree list UI from API data.
  const buildTreeList = (items, depth, onFileClick, options = {}) => {
    const enableDragDrop = options.enableDragDrop === true;
    const onMove = typeof options.onMove === "function" ? options.onMove : null;
    const list = document.createElement("ul");
    list.className =
      depth === 0
        ? "space-y-1 text-sm text-gray-700"
        : "mt-2 space-y-1 border-l border-gray-200 pl-4";

    items.forEach((item) => {
      const listItem = document.createElement("li");

      if (item.type === "directory") {
        const directoryPath = item.path ? `/${item.path}` : "/";
        const children = Array.isArray(item.children) ? item.children : [];
        const indexEntry = children.find(
          (child) => child.type === "file" && child.path === directoryPath
        );
        const nonIndexChildren = children.filter((child) => child !== indexEntry);

        if (indexEntry && nonIndexChildren.length === 0) {
          list.appendChild(renderFileItem(indexEntry, onFileClick, options));
          return;
        }

        const hasIndex = Boolean(indexEntry);
        const labelRow = document.createElement(hasIndex ? "button" : "div");
        labelRow.className = "flex flex-wrap items-center gap-2 text-sm font-semibold text-gray-600";

        if (hasIndex) {
          labelRow.type = "button";
          labelRow.classList.add("w-full", "text-left", "hover:text-indigo-700");
          labelRow.addEventListener("click", () =>
            onFileClick(indexEntry.path, indexEntry.label || indexEntry.path, labelRow)
          );
        }

        const labelSpan = document.createElement("span");
        labelSpan.textContent = formatDirectoryLabel(item.name || "");
        labelRow.appendChild(labelSpan);

        const pathSpan = document.createElement("span");
        pathSpan.className = "text-xs text-gray-400 font-normal";
        pathSpan.textContent = `(${directoryPath})`;
        labelRow.appendChild(pathSpan);

        listItem.appendChild(labelRow);

        if (enableDragDrop && onMove) {
          const targetPath = item.path || "";
          listItem.dataset.dirPath = targetPath;
          listItem.classList.add("rounded-md");

          listItem.addEventListener("dragover", (event) => {
            event.preventDefault();
            listItem.classList.add("bg-indigo-50");
          });

          listItem.addEventListener("dragleave", () => {
            listItem.classList.remove("bg-indigo-50");
          });

          listItem.addEventListener("drop", (event) => {
            event.preventDefault();
            listItem.classList.remove("bg-indigo-50");
            const sourcePath = event.dataTransfer?.getData("text/plain") || "";
            if (sourcePath) {
              onMove(sourcePath, targetPath);
            }
          });
        }

        if (nonIndexChildren.length > 0) {
          listItem.appendChild(buildTreeList(nonIndexChildren, depth + 1, onFileClick, options));
        }

        list.appendChild(listItem);
        return;
      }

      if (item.type === "file") {
        list.appendChild(renderFileItem(item, onFileClick, options));
      }
    });

    return list;
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
      const { data: responseData } = await fetchJson(editor.endpoints.list);
      if (responseData.success) {
        editor.list.innerHTML = "";
        if (!Array.isArray(responseData.items) || responseData.items.length === 0) {
          editor.list.innerHTML = `<p class="text-xs text-gray-500">${editor.emptyText}</p>`;
        } else {
          if (editor.treeOptions?.treeMode === "url") {
            const files = flattenTreeFiles(responseData.items);
            const urlTree = buildUrlTree(files);
            const list = buildUrlTreeList(urlTree, 0, (path, label, button) =>
              openEditorItem(editor, path, label, button),
              editor.treeOptions || {}
            );
            editor.list.appendChild(list);
          } else {
            const list = buildTreeList(
              responseData.items,
              0,
              (path, label, button) => openEditorItem(editor, path, label, button),
              editor.treeOptions || {}
            );
            editor.list.appendChild(list);
          }
          if (editor.treeOptions?.enableDragDrop && !editor.state.rootDropBound) {
            editor.list.addEventListener("dragover", (event) => {
              if (event.target.closest("[data-dir-path]")) {
                return;
              }
              event.preventDefault();
            });
            editor.list.addEventListener("drop", (event) => {
              if (event.target.closest("[data-dir-path]")) {
                return;
              }
              event.preventDefault();
              const sourcePath = event.dataTransfer?.getData("text/plain") || "";
              if (sourcePath && typeof editor.treeOptions.onMove === "function") {
                editor.treeOptions.onMove(sourcePath, "");
              }
            });
            editor.state.rootDropBound = true;
          }
        }
        if (typeof editor.onListLoaded === "function") {
          editor.onListLoaded(responseData.items);
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
    editor.state.activeLabel = label;
    editor.state.isDraft = false;
    editor.state.draft = null;
    editor.state.returnState = null;
    if (editor.saveButton) {
      editor.saveButton.textContent = "Save";
    }
    editor.title.textContent = label;
    editor.pathLabel.textContent = path;
    editor.pathLabel.classList.remove("hidden");
    setEditorState(editor, true);

    try {
      const { data: responseData } = await fetchJson(`${editor.endpoints.load}?path=${encodeURIComponent(path)}`);
      if (!responseData.success) {
        alert(editor.loadErrorText);
        return;
      }
      editor.state.currentPath = path;
      editor.state.originalBody = responseData.content;
      editor.editorArea.value = responseData.content;
    } catch (error) {
      alert(editor.loadErrorText);
    }
  };

  const parseDraftContent = (value) => {
    const lines = value.split("\n");
    const title = (lines.shift() || "").trim();
    const body = lines.join("\n").replace(/^\n/, "");
    return { title, body };
  };

  // Persist editor content back to the API.
  const saveEditorContent = async (editor) => {
    if (!editor.editorArea) {
      return;
    }

    if (editor.state.isDraft) {
      const { title, body } = parseDraftContent(editor.editorArea.value);
      const parent = editor.state.parentPath || "";
      if (!title) {
        alert("Add a title before saving.");
        return;
      }

      try {
        const { data: responseData } = await postJson(editor.endpoints.create, {
          title,
          body,
          parent
        });

        if (responseData.success) {
          editor.state.isDraft = false;
          editor.state.draft = null;
          editor.state.returnState = null;
          editor.state.currentPath = responseData.path || "";
          editor.state.originalBody = responseData.content || editor.editorArea.value;
          editor.state.activeLabel = title;
          editor.state.activeButton = setActiveButton(null, editor.state.activeButton);
          if (editor.pathLabel && responseData.path) {
            editor.pathLabel.textContent = responseData.path;
            editor.pathLabel.classList.remove("hidden");
          }
          if (editor.title) {
            editor.title.textContent = title;
          }
          if (editor.saveButton) {
            editor.saveButton.textContent = "Save";
          }
          if (responseData.content) {
            editor.editorArea.value = responseData.content;
          }
          editor.state.loaded = false;
          loadTreeList(editor, true);
          alert(editor.saveSuccessText);
          return;
        }

        alert(editor.saveErrorText);
        return;
      } catch (error) {
        alert(editor.saveErrorText);
        return;
      }
    }

    if (!editor.state.currentPath) {
      return;
    }

    try {
      const { data: responseData } = await postJson(editor.endpoints.save, {
        path: editor.state.currentPath,
        content: editor.editorArea.value
      });

      if (responseData.success) {
        editor.state.originalBody = editor.editorArea.value;
        alert(editor.saveSuccessText);
        return;
      }

      alert(editor.saveErrorText);
    } catch (error) {
      alert(editor.saveErrorText);
    }
  };

  const resetEditorState = (editor) => {
    if (!editor.editorArea) {
      return;
    }

    editor.editorArea.value = editor.state.originalBody || "";
    setEditorState(editor, false);
    editor.state.currentPath = "";
    editor.state.originalBody = "";
    editor.state.activeLabel = "";
    editor.state.isDraft = false;
    editor.state.draft = null;
    editor.state.returnState = null;
    editor.pathLabel?.classList.add("hidden");
    if (editor.title && editor.defaultTitle) {
      editor.title.textContent = editor.defaultTitle;
    }
    if (editor.saveButton) {
      editor.saveButton.textContent = "Save";
    }
    editor.state.activeButton = setActiveButton(null, editor.state.activeButton);
  };

  const updateDraftFromInput = (editor) => {
    if (!editor.editorArea || !editor.state.isDraft || !editor.state.draft) {
      return;
    }

    const value = editor.editorArea.value;
    const { title, body } = parseDraftContent(value);
    editor.state.draft.title = title;
    editor.state.draft.body = body;
    editor.state.draft.hasTyped = value.trim() !== "";
    editor.state.draft.mode = value.includes("\n") ? "body" : "title";
    if (editor.title) {
      editor.title.textContent = title !== "" ? title : "New page";
    }
  };

  // Revert editor changes to the last loaded version.
  const cancelEditorContent = (editor, restoreCallback) => {
    if (!editor.editorArea) {
      return;
    }

    if (editor.state.isDraft && editor.state.draft && !editor.state.draft.hasTyped) {
      resetEditorState(editor);
      if (typeof restoreCallback === "function") {
        restoreCallback();
      }
      return;
    }

    editor.editorArea.value = editor.state.originalBody;
    setEditorState(editor, false);
    editor.state.currentPath = "";
    editor.state.originalBody = "";
    editor.state.activeLabel = "";
    editor.state.isDraft = false;
    editor.state.draft = null;
    editor.state.returnState = null;
    editor.pathLabel?.classList.add("hidden");
    if (editor.title && editor.defaultTitle) {
      editor.title.textContent = editor.defaultTitle;
    }
    if (editor.saveButton) {
      editor.saveButton.textContent = "Save";
    }
    editor.state.activeButton = setActiveButton(null, editor.state.activeButton);
  };

  // Wire editor buttons for a given editor definition.
  const initEditor = (editor) => {
    if (editor.saveButton) {
      editor.saveButton.addEventListener("click", () => saveEditorContent(editor));
    }

    if (editor.cancelButton) {
      editor.cancelButton.addEventListener("click", () =>
        cancelEditorContent(editor, editor.restoreDraft)
      );
    }

    if (editor.editorArea) {
      editor.editorArea.addEventListener("keydown", (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "s") {
          event.preventDefault();
          saveEditorContent(editor);
          return;
        }

        if (event.key === "Escape") {
          cancelEditorContent(editor, editor.restoreDraft);
          return;
        }

        if (editor.state.isDraft && editor.state.draft && event.key === "Enter") {
          if (editor.state.draft.mode === "title") {
            editor.state.draft.mode = "body";
          }
        }
      });

      editor.editorArea.addEventListener("input", () => updateDraftFromInput(editor));
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
    const parentButton = query("#content-parent-btn");
    const parentMenu = query("#content-parent-menu");
    const parentSelect = query("#content-parent-select");

    const contentEditor = {
      list: query("#content-list"),
      editorArea: query("#content-editor-area"),
      placeholder: query("#content-editor-placeholder"),
      saveButton: query("#content-save-btn"),
      cancelButton: query("#content-cancel-btn"),
      title: query("#content-editor-title"),
      pathLabel: query("#content-editor-path"),
      parentButton,
      parentMenu,
      parentSelect,
      defaultTitle: "Select a page",
      endpoints: {
        list: "/api/site/list",
        load: "/api/site",
        save: "/api/save",
        create: "/api/site/create",
        move: "/api/site/move"
      },
      emptyText: "No content found.",
      errorText: "Failed to load content.",
      loadErrorText: "Failed to load content.",
      saveSuccessText: "Content saved.",
      saveErrorText: "Failed to save content.",
      state: {
        loaded: false,
        currentPath: "",
        originalBody: "",
        activeButton: null,
        activeLabel: "",
        isEditing: false,
        isDraft: false,
        draft: null,
        returnState: null,
        parentPath: "",
        rootDropBound: false
      }
    };

    const blockEditor = {
      list: query("#blocks-list"),
      editorArea: query("#block-editor-area"),
      placeholder: query("#block-editor-placeholder"),
      saveButton: query("#block-save-btn"),
      cancelButton: query("#block-cancel-btn"),
      title: query("#block-editor-title"),
      pathLabel: query("#block-editor-path"),
      refreshButton: query("#refresh-blocks-btn"),
      defaultTitle: "Select a block",
      endpoints: {
        list: "/api/blocks/list",
        load: "/api/blocks",
        save: "/api/blocks/save"
      },
      emptyText: "No blocks found.",
      errorText: "Failed to load blocks.",
      loadErrorText: "Failed to load block.",
      saveSuccessText: "Block saved.",
      saveErrorText: "Failed to save block.",
      state: {
        loaded: false,
        currentPath: "",
        originalBody: "",
        activeButton: null,
        activeLabel: "",
        isEditing: false
      }
    };

    const getActiveTabName = () => {
      const activeLink = query(".admin-nav-link.active");
      return activeLink?.dataset.tab || "";
    };

    const captureViewState = () => ({
      activeTab: getActiveTabName(),
      contentPath: contentEditor.state.currentPath,
      contentLabel: contentEditor.state.activeLabel,
      blockPath: blockEditor.state.currentPath,
      blockLabel: blockEditor.state.activeLabel
    });

    const restoreViewState = (state) => {
      if (!state) {
        return;
      }

      if (state.activeTab) {
        openTab(state.activeTab);
      }

      if (state.activeTab === "content" && state.contentPath) {
        setTimeout(() => {
          openEditorItem(contentEditor, state.contentPath, state.contentLabel || state.contentPath, null);
        }, 0);
      }

      if (state.activeTab === "blocks" && state.blockPath) {
        setTimeout(() => {
          openEditorItem(blockEditor, state.blockPath, state.blockLabel || state.blockPath, null);
        }, 0);
      }
    };

    const setParentMenuOpen = (isOpen) => {
      if (!parentMenu || !parentButton) {
        return;
      }
      parentMenu.classList.toggle("hidden", !isOpen);
      parentButton.classList.toggle("text-indigo-600", isOpen);
      parentButton.classList.toggle("bg-indigo-50", isOpen);
    };

    const buildParentOptions = (items) => {
      const options = [{ value: "", label: "/ (Root)" }];
      const walk = (nodes, depth) => {
        nodes.forEach((item) => {
          if (item.type !== "directory") {
            return;
          }
          const prefix = depth > 0 ? `${"— ".repeat(depth)}` : "";
          options.push({
            value: item.path || "",
            label: `${prefix}${item.name}/`
          });
          if (Array.isArray(item.children) && item.children.length > 0) {
            walk(item.children, depth + 1);
          }
        });
      };
      walk(items, 0);
      return options;
    };

    const populateParentSelect = (items) => {
      if (!parentSelect) {
        return;
      }
      const options = buildParentOptions(items);
      parentSelect.innerHTML = "";
      options.forEach((option) => {
        const element = document.createElement("option");
        element.value = option.value;
        element.textContent = option.label;
        parentSelect.appendChild(element);
      });
      if (contentEditor.state.parentPath) {
        parentSelect.value = contentEditor.state.parentPath;
      } else {
        contentEditor.state.parentPath = parentSelect.value || "";
      }
    };

    const moveContentItem = async (sourcePath, destinationPath) => {
      if (!sourcePath || sourcePath === destinationPath) {
        return;
      }
      try {
        const { data: responseData } = await postJson(contentEditor.endpoints.move, {
          source: sourcePath,
          destination: destinationPath
        });
        if (responseData.success) {
          if (contentEditor.state.currentPath === sourcePath && responseData.path) {
            contentEditor.state.currentPath = responseData.path;
            if (contentEditor.pathLabel) {
              contentEditor.pathLabel.textContent = responseData.path;
              contentEditor.pathLabel.classList.remove("hidden");
            }
          }
          contentEditor.state.loaded = false;
          loadTreeList(contentEditor, true);
          return;
        }
        alert(responseData.error || "Failed to move file.");
      } catch (error) {
        alert("Failed to move file.");
      }
    };

    const startNewContentDraft = () => {
      const returnState = captureViewState();
      contentEditor.state.returnState = returnState;

      openTab("content");
      contentEditor.state.isDraft = true;
      contentEditor.state.draft = {
        hasTyped: false,
        mode: "title",
        title: "",
        body: ""
      };
      contentEditor.state.currentPath = "";
      contentEditor.state.originalBody = "";
      contentEditor.state.activeLabel = "";
      contentEditor.state.activeButton = setActiveButton(null, contentEditor.state.activeButton);
      contentEditor.state.parentPath = parentSelect?.value || "";

      setEditorState(contentEditor, true);
      if (contentEditor.title) {
        contentEditor.title.textContent = "New page";
      }
      if (contentEditor.pathLabel) {
        contentEditor.pathLabel.classList.add("hidden");
      }
      if (contentEditor.saveButton) {
        contentEditor.saveButton.textContent = "Create";
      }
      if (contentEditor.editorArea) {
        contentEditor.editorArea.value = "";
        contentEditor.editorArea.focus();
      }
      if (!contentEditor.state.loaded) {
        loadTreeList(contentEditor);
      }
    };

    contentEditor.restoreDraft = () => restoreViewState(contentEditor.state.returnState);
    contentEditor.treeOptions = {
      treeMode: "url",
      enableDragDrop: true,
      onMove: moveContentItem
    };
    contentEditor.onListLoaded = (items) => populateParentSelect(items);

    if (parentButton && parentMenu) {
      parentButton.addEventListener("click", (event) => {
        event.stopPropagation();
        const isOpen = !parentMenu.classList.contains("hidden");
        setParentMenuOpen(!isOpen);
      });

      document.addEventListener("click", () => {
        setParentMenuOpen(false);
      });
    }

    if (parentMenu) {
      parentMenu.addEventListener("click", (event) => {
        event.stopPropagation();
      });
    }

    if (parentSelect) {
      parentSelect.addEventListener("change", () => {
        contentEditor.state.parentPath = parentSelect.value || "";
      });
    }

    initEditor(contentEditor);
    initEditor(blockEditor);

    document.addEventListener("keydown", (event) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "n") {
        event.preventDefault();
        startNewContentDraft();
        return;
      }

      if (event.key !== "Escape") {
        return;
      }

      if (contentEditor.state.isEditing) {
        cancelEditorContent(contentEditor, contentEditor.restoreDraft);
        return;
      }

      if (blockEditor.state.isEditing) {
        cancelEditorContent(blockEditor);
      }
    });

    const contentTab = query("[data-tab='content']");
    if (contentTab) {
      contentTab.addEventListener("click", () => loadTreeList(contentEditor));
    }

    const blocksTab = query("[data-tab='blocks']");
    if (blocksTab) {
      blocksTab.addEventListener("click", () => loadTreeList(blockEditor));
    }
  };

  // Lazy-load heavier tabs on first click.
  const initLazyTabs = () => {
    const submissionsTab = query("[data-tab='submissions']");
    if (submissionsTab) {
      submissionsTab.addEventListener("click", loadSubmissions);
    }

    const componentsTab = query("[data-tab='components']");
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
