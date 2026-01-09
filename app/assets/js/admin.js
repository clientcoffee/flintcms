const protectedKeys = ["admin.password", "system.root"];

// Helper function to include CSRF token in all POST requests
function secureFetch(url, options = {}) {
  // Add CSRF token header to all requests
  const headers = options.headers || {};
  if (typeof CSRF_TOKEN !== 'undefined') {
    headers['X-CSRF-Token'] = CSRF_TOKEN;
  }

  return fetch(url, {
    ...options,
    headers
  });
}

// Tab switching
document.querySelectorAll(".admin-nav-link").forEach(link => {
  link.addEventListener("click", (e) => {
    if (link.getAttribute("href").startsWith("/")) return;
    e.preventDefault();
    const tabName = link.dataset.tab;
    document.querySelectorAll(".admin-nav-link").forEach(l => {
      l.classList.remove("active", "bg-gray-100", "text-gray-900");
      l.classList.add("text-gray-600", "hover:bg-gray-50");
    });
    link.classList.add("active", "bg-gray-100", "text-gray-900");
    link.classList.remove("text-gray-600", "hover:bg-gray-50");
    document.querySelectorAll(".admin-tab").forEach(tab => tab.classList.add("hidden"));
    document.getElementById(tabName + "-tab").classList.remove("hidden");
  });
});

// Load settings from API
async function loadSettings() {
  try {
    const response = await fetch("/api/settings");
    const data = await response.json();
    if (data.success) {
      renderSettings(data.settings);
    }
  } catch (error) {
    console.error("Failed to load settings:", error);
  }
}

// Render settings form
function renderSettings(settings) {
  const container = document.getElementById("settings-container");
  container.innerHTML = "";
  for (const [key, value] of Object.entries(settings)) {
    const isProtected = protectedKeys.includes(key);
    const div = document.createElement("div");
    div.innerHTML = `
      <label class="block text-sm font-medium text-gray-700 mb-2">${key}</label>
      <input type="text" name="${key}" value="${value}" ${isProtected ? "disabled" : ""} class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm ${isProtected ? "bg-gray-50 text-gray-500 cursor-not-allowed" : "focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"}">
      ${isProtected ? "<p class=\"text-xs text-gray-500 mt-1\">This setting cannot be modified</p>" : ""}
    `;
    container.appendChild(div);
  }
}

// Save settings
document.getElementById("settings-form").addEventListener("submit", async (e) => {
  e.preventDefault();
  const formData = new FormData(e.target);
  const settings = Object.fromEntries(formData.entries());
  try {
    const response = await secureFetch("/api/settings", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ settings })
    });
    const data = await response.json();
    if (data.success) {
      alert("Settings saved successfully!");
    } else {
      alert("Failed to save settings: " + (data.error || "Unknown error"));
    }
  } catch (error) {
    console.error("Save error:", error);
    alert("Failed to save settings");
  }
});

// Add custom setting
document.getElementById("add-setting-btn").addEventListener("click", () => {
  const key = prompt("Enter setting key (e.g., custom.my_setting):");
  if (!key) return;
  const value = prompt("Enter value:");
  if (value === null) return;
  const container = document.getElementById("settings-container");
  const div = document.createElement("div");
  div.innerHTML = `
    <label class="block text-sm font-medium text-gray-700 mb-2">${key}</label>
    <input type="text" name="${key}" value="${value}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
  `;
  container.appendChild(div);
});

// Clear honeypot IP blocklist
document.getElementById("clear-blocklist-btn")?.addEventListener("click", async () => {
  const confirmed = confirm("Clear all blocked hosts?");
  if (!confirmed) return;
  try {
    const response = await secureFetch("/api/blocklist/clear", { method: "POST" });
    const data = await response.json();
    if (data.success) {
      alert("Blocklist cleared.");
      return;
    }
    alert("Failed to clear blocklist.");
  } catch (error) {
    console.error("Blocklist clear error:", error);
    alert("Failed to clear blocklist.");
  }
});

const securityMessage = document.getElementById("security-message");
const setSecurityMessage = (message, tone = "info", link = null) => {
  if (!securityMessage) return;
  securityMessage.classList.remove(
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

  securityMessage.textContent = message;
  if (tone === "success") {
    securityMessage.classList.add("border-emerald-200", "bg-emerald-50", "text-emerald-700");
  } else if (tone === "error") {
    securityMessage.classList.add("border-red-200", "bg-red-50", "text-red-700");
  } else {
    securityMessage.classList.add("border-indigo-200", "bg-indigo-50", "text-indigo-700");
  }

  if (link) {
    const linkWrapper = document.createElement("div");
    linkWrapper.className = "mt-2 text-xs";
    const linkEl = document.createElement("a");
    linkEl.href = link;
    linkEl.textContent = "Open magic link";
    linkEl.className = "text-indigo-600 hover:text-indigo-700 underline break-all";
    linkWrapper.appendChild(linkEl);
    securityMessage.appendChild(linkWrapper);
  }
};

document.getElementById("security-password-reset-btn")?.addEventListener("click", async () => {
  const confirmed = confirm("Send a password reset magic link to the admin email?");
  if (!confirmed) return;
  try {
    setSecurityMessage("Sending reset link...");
    const response = await secureFetch("/api/security/password-reset", { method: "POST" });
    const data = await response.json();
    if (data.success) {
      setSecurityMessage(
        data.message || "Reset link sent.",
        "success",
        data.magic_link || null
      );
      return;
    }
    setSecurityMessage(data.error || "Failed to send reset link.", "error");
  } catch (error) {
    console.error("Password reset error:", error);
    setSecurityMessage("Failed to send reset link.", "error");
  }
});

// Export content functionality
document.getElementById("export-btn")?.addEventListener("click", async () => {
  const btn = document.getElementById("export-btn");
  const status = document.getElementById("export-status");
  const originalHTML = btn.innerHTML;
  
  btn.disabled = true;
  btn.innerHTML = \'<svg class="animate-spin h-4 w-4 mr-2" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Preparing export...\';
  status.classList.remove("hidden");
  status.className = "mt-3 text-sm text-blue-600";
  status.textContent = "Creating archive...";
  
  try {
    const response = await secureFetch("/api/export", { method: "POST" });
    
    if (!response.ok) {
      throw new Error("Export failed");
    }
    
    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "flint-export-" + new Date().toISOString().slice(0,19).replace(/:/g, "-") + ".tar.gz";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    status.className = "mt-3 text-sm text-green-600 font-medium";
    status.textContent = "✓ Export complete! Check your downloads.";
    setTimeout(() => status.classList.add("hidden"), 5000);
  } catch (error) {
    console.error("Export error:", error);
    status.className = "mt-3 text-sm text-red-600 font-medium";
    status.textContent = "✗ Export failed. Please try again.";
  } finally {
    btn.disabled = false;
    btn.innerHTML = originalHTML;
  }
});

// Load settings on page load
loadSettings();

// Update checking functionality
async function checkForUpdates() {
  const btn = document.getElementById("check-updates-btn");
  const banner = document.getElementById("update-banner");
  const originalHTML = btn.innerHTML;
  
  btn.disabled = true;
  btn.innerHTML = \'<svg class="animate-spin h-4 w-4 mr-2 inline" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Checking...\';
  
  try {
    const response = await fetch("/api/updates/check");
    const data = await response.json();
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
    console.error("Update check failed:", error);
    banner.innerHTML = `<div class="bg-red-50 border border-red-200 rounded-lg p-4"><p class="text-red-800 text-sm font-medium">✗ Failed to check for updates. Please try again.</p></div>`;
    banner.classList.remove("hidden");
    setTimeout(() => banner.classList.add("hidden"), 6000);
  } finally {
    btn.disabled = false;
    btn.innerHTML = originalHTML;
  }
}

function showUpdateBanner(data) {
  const banner = document.getElementById("update-banner");
  const autoUpdateMode = data.auto_update_mode;
  let actions = "";
  if (autoUpdateMode === "true") {
    actions = `<button onclick="applyUpdate(\'${data.update.download_url}\')" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">Install Now</button>`;
  } else if (autoUpdateMode === "ask") {
    actions = `<button onclick="applyUpdate(\'${data.update.download_url}\')" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">Install Update</button><a href="${data.update.url}" target="_blank" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-medium">View Release Notes</a>`;
  } else {
    actions = `<a href="${data.update.url}" target="_blank" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">View Release</a>`;
  }
  banner.innerHTML = `<div class="bg-blue-50 border border-blue-200 rounded-lg p-6"><div class="flex items-start justify-between"><div class="flex-1"><h3 class="text-lg font-semibold text-blue-900 mb-2">Update Available: v${data.update.version}</h3><p class="text-blue-800 text-sm mb-4">A new version is available. Your content and themes will be preserved.</p><div class="flex gap-3">${actions}</div></div></div></div>`;
  banner.classList.remove("hidden");
}

async function applyUpdate(downloadUrl) {
  if (!confirm("Install update now? Your site will be briefly unavailable.")) return;
  const banner = document.getElementById("update-banner");
  banner.innerHTML = `<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4"><div class="flex items-center gap-3"><svg class="animate-spin h-5 w-5 text-yellow-600" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><p class="text-yellow-800 text-sm font-medium">Installing update... Please wait.</p></div></div>`;
  try {
    const response = await secureFetch("/api/updates/apply", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ download_url: downloadUrl }) });
    const data = await response.json();
    if (data.success) {
      banner.innerHTML = `<div class="bg-green-50 border border-green-200 rounded-lg p-4"><p class="text-green-800 text-sm font-medium">✓ ${data.message}</p></div>`;
      setTimeout(() => window.location.reload(), 2000);
    } else {
      banner.innerHTML = `<div class="bg-red-50 border border-red-200 rounded-lg p-4"><p class="text-red-800 text-sm font-medium">✗ Update failed: ${data.error}</p></div>`;
    }
  } catch (error) {
    banner.innerHTML = `<div class="bg-red-50 border border-red-200 rounded-lg p-4"><p class="text-red-800 text-sm">Update failed. Please try again.</p></div>`;
  }
}

// Load and display form submissions
async function loadSubmissions() {
  try {
    const response = await fetch("/api/submissions");
    const data = await response.json();
    if (data.success) {
      renderSubmissions(data.submissions);
    }
  } catch (error) {
    console.error("Failed to load submissions:", error);
    document.getElementById("submissions-container").innerHTML = `<p class="text-red-600 text-sm">Failed to load submissions.</p>`;
  }
}

function renderSubmissions(submissions) {
  const container = document.getElementById("submissions-container");
  const countEl = document.getElementById("submissions-count");
  countEl.textContent = `Total: ${submissions.length} submission(s)`;
  if (submissions.length === 0) {
    container.innerHTML = `<p class="text-gray-500 text-sm">No submissions yet.</p>`;
    return;
  }
  container.innerHTML = submissions.map(sub => {
    const date = new Date(sub.submitted_at * 1000).toLocaleString();
    const fromUrl = sub.submitted_from ? `<p class="text-xs text-gray-500 mt-2"><strong>From:</strong> ${sub.submitted_from}</p>` : "";
    return `
      <div class="border border-gray-200 rounded-lg p-4 bg-gray-50 hover:bg-gray-100 transition-colors">
        <div class="flex justify-between items-start mb-2">
          <div>
            <p class="font-semibold text-gray-900">${sub.name}</p>
            <p class="text-sm text-gray-600">${sub.email}</p>
          </div>
          <span class="text-xs text-gray-500">${date}</span>
        </div>
        <p class="text-sm text-gray-700 whitespace-pre-wrap mb-2">${sub.message}</p>
        ${fromUrl}
      </div>`;
  }).join("");
}

// Clear all submissions
document.getElementById("clear-submissions-btn")?.addEventListener("click", async () => {
  if (!confirm("Delete all submissions? This cannot be undone.")) return;
  try {
    const response = await secureFetch("/api/submissions/clear", { method: "POST" });
    const data = await response.json();
    if (data.success) {
      loadSubmissions();
      alert("All submissions cleared.");
    } else {
      alert("Failed to clear submissions.");
    }
  } catch (error) {
    console.error("Clear error:", error);
    alert("Failed to clear submissions.");
  }
});

// Load submissions when submissions tab is activated
document.addEventListener("DOMContentLoaded", () => {
  const submissionsTab = document.querySelector(\'[data-tab="submissions"]\');
  submissionsTab?.addEventListener("click", () => {
    loadSubmissions();
  });
  
  const contentTab = document.querySelector(\'[data-tab="content"]\');
  contentTab?.addEventListener("click", () => {
    loadContentList();
  });

  const blocksTab = document.querySelector(\'[data-tab="blocks"]\');
  blocksTab?.addEventListener("click", () => {
    loadBlocksList();
  });

  // Load components when components tab is activated
  const componentsTab = document.querySelector(\'[data-tab="components"]\');
  componentsTab?.addEventListener("click", () => {
    loadComponents();
  });
});

// Load and display installed components
async function loadComponents() {
  try {
    const response = await fetch("/api/components");
    const data = await response.json();
    if (data.success) {
      renderComponents(data.components);
    }
  } catch (error) {
    console.error("Failed to load components:", error);
    document.getElementById("installed-components").innerHTML = `<p class="text-red-600 text-sm">Failed to load components.</p>`;
  }
}

// Render installed components
function renderComponents(components) {
  const container = document.getElementById("installed-components");
  if (!components || components.length === 0) {
    container.innerHTML = `<p class="text-gray-500 text-sm">No components installed.</p>`;
    return;
  }
  
  container.innerHTML = components.map(comp => {
    const statusBadge = comp.enabled
      ? `<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700">Enabled</span>`
      : `<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">Disabled</span>`;
    
    return `
      <div class="bg-white border border-gray-200 rounded-lg p-4">
        <div class="flex justify-between items-start mb-2">
          <div>
            <h4 class="font-semibold text-gray-900">${comp.displayName}</h4>
            <p class="text-xs text-gray-500">v${comp.version} by ${comp.author}</p>
          </div>
          ${statusBadge}
        </div>
        <p class="text-sm text-gray-600 mb-3">${comp.description}</p>
        <div class="flex gap-2">
          <button onclick="toggleComponent(\'${comp.name}\', ${!comp.enabled})" class="px-3 py-1 text-sm rounded ${comp.enabled ? \'bg-gray-100 text-gray-700\' : \'bg-indigo-600 text-white\'} hover:opacity-80">
            ${comp.enabled ? "Disable" : "Enable"}
          </button>
          ${comp.repo ? `<button onclick="updateComponent(\'${comp.name}\')" class="px-3 py-1 text-sm bg-blue-50 text-blue-700 rounded hover:bg-blue-100">Update</button>` : ""}
          <button onclick="deleteComponent(\'${comp.name}\')" class="px-3 py-1 text-sm bg-red-50 text-red-700 rounded hover:bg-red-100">Delete</button>
        </div>
      </div>`;
  }).join("");
}

// Browse available components
async function browseComponents() {
  const btn = document.getElementById("browse-btn");
  const container = document.getElementById("browse-components");
  btn.disabled = true;
  btn.textContent = "Loading...";
  
  try {
    const response = await fetch("/api/components/browse");
    const data = await response.json();
    if (data.success) {
      renderAvailableComponents(data.components);
    }
  } catch (error) {
    container.innerHTML = `<p class="text-red-600 text-sm">Failed to load available components.</p>`;
  } finally {
    btn.disabled = false;
    btn.textContent = "Refresh";
  }
}

// Render available components
function renderAvailableComponents(components) {
  const container = document.getElementById("browse-components");
  if (!components || components.length === 0) {
    container.innerHTML = `<p class="text-gray-500 text-sm">No components available.</p>`;
    return;
  }
  
  container.innerHTML = components.map(comp => `
    <div class="bg-white border border-gray-200 rounded-lg p-4">
      <div class="flex justify-between items-start mb-2">
        <div>
          <h4 class="font-semibold text-gray-900">${comp.displayName}</h4>
          <p class="text-xs text-gray-500">v${comp.version} by ${comp.author}</p>
        </div>
        <div class="text-xs text-gray-500">
          ⬇ ${comp.downloads} | ★ ${comp.stars}
        </div>
      </div>
      <p class="text-sm text-gray-600 mb-3">${comp.description}</p>
      <button onclick="installComponent(\'${comp.repo}\')" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
        Install
      </button>
    </div>
  `).join("");
}

// Install component
async function installComponent(repo) {
  if (!confirm(`Install component from ${repo}?`)) return;
  
  try {
    const response = await secureFetch("/api/components/install", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ repo })
    });
    const data = await response.json();
    if (data.success) {
      alert(data.message);
      loadComponents();
    } else {
      alert("Installation failed: " + data.error);
    }
  } catch (error) {
    alert("Installation failed");
  }
}

// Update component
async function updateComponent(name) {
  if (!confirm(`Update ${name} component?`)) return;
  
  try {
    const response = await secureFetch("/api/components/update", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name })
    });
    const data = await response.json();
    if (data.success) {
      alert(data.message);
      loadComponents();
    } else {
      alert("Update failed: " + data.error);
    }
  } catch (error) {
    alert("Update failed");
  }
}

// Toggle component enabled/disabled
async function toggleComponent(name, enabled) {
  try {
    const response = await secureFetch("/api/components/toggle", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name, enabled })
    });
    const data = await response.json();
    if (data.success) {
      loadComponents();
    } else {
      alert("Toggle failed: " + data.error);
    }
  } catch (error) {
    alert("Toggle failed");
  }
}

// Delete component
async function deleteComponent(name) {
  if (!confirm(`Delete ${name} component? This cannot be undone.`)) return;
  
  try {
    const response = await secureFetch("/api/components/delete", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name })
    });
    const data = await response.json();
    if (data.success) {
      alert(data.message);
      loadComponents();
    } else {
      alert("Delete failed: " + data.error);
    }
  } catch (error) {
    alert("Delete failed");
  }
}

const contentList = document.getElementById("content-list");
const contentEditorArea = document.getElementById("content-editor-area");
const contentEditorPlaceholder = document.getElementById("content-editor-placeholder");
const contentSaveButton = document.getElementById("content-save-btn");
const contentCancelButton = document.getElementById("content-cancel-btn");
const contentEditorTitle = document.getElementById("content-editor-title");
const contentEditorPath = document.getElementById("content-editor-path");
const refreshContentButton = document.getElementById("refresh-content-btn");

let contentListLoaded = false;
let currentContentPath = "";
let originalContentBody = "";
let activeContentButton = null;

const blockList = document.getElementById("blocks-list");
const blockEditorArea = document.getElementById("block-editor-area");
const blockEditorPlaceholder = document.getElementById("block-editor-placeholder");
const blockSaveButton = document.getElementById("block-save-btn");
const blockCancelButton = document.getElementById("block-cancel-btn");
const blockEditorTitle = document.getElementById("block-editor-title");
const blockEditorPath = document.getElementById("block-editor-path");
const refreshBlocksButton = document.getElementById("refresh-blocks-btn");

let blockListLoaded = false;
let currentBlockPath = "";
let originalBlockBody = "";
let activeBlockButton = null;

function setEditorState(editor, placeholder, saveButton, cancelButton, enabled) {
  if (!editor || !placeholder || !saveButton || !cancelButton) {
    return;
  }
  editor.classList.toggle("hidden", !enabled);
  placeholder.classList.toggle("hidden", enabled);
  saveButton.disabled = !enabled;
  cancelButton.disabled = !enabled;
}

function setActiveButton(nextButton, currentButton) {
  if (currentButton) {
    currentButton.classList.remove("bg-indigo-50", "text-indigo-700");
    currentButton.classList.add("text-gray-700");
  }

  if (nextButton) {
    nextButton.classList.add("bg-indigo-50", "text-indigo-700");
    nextButton.classList.remove("text-gray-700");
  }

  return nextButton;
}

async function loadContentList(force = false) {
  if (!contentList) {
    return;
  }

  if (contentListLoaded && !force) {
    return;
  }

  contentList.textContent = "Loading...";
  try {
    const response = await fetch("/api/content/list");
    const data = await response.json();
    if (data.success) {
      contentList.innerHTML = "";
      if (!Array.isArray(data.items) || data.items.length === 0) {
        contentList.innerHTML = `<p class="text-xs text-gray-500">No content found.</p>`;
      } else {
        appendContentTree(data.items, contentList, 0);
      }
      contentListLoaded = true;
      return;
    }
    contentList.innerHTML = `<p class="text-xs text-red-600">Failed to load content.</p>`;
  } catch (error) {
    console.error("Failed to load content list:", error);
    contentList.innerHTML = `<p class="text-xs text-red-600">Failed to load content.</p>`;
  }
}

function appendContentTree(items, container, depth) {
  items.forEach(item => {
    if (item.type === "directory") {
      const header = document.createElement("div");
      header.className = "text-xs uppercase tracking-wide text-gray-400 mt-3";
      header.style.paddingLeft = `${depth * 12}px`;
      header.textContent = item.name;
      container.appendChild(header);
      if (Array.isArray(item.children)) {
        appendContentTree(item.children, container, depth + 1);
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
      button.addEventListener("click", () => {
        openContentEditor(item.path, item.label || item.path, button);
      });
      container.appendChild(button);
    }
  });
}

async function openContentEditor(path, label, button) {
  if (!contentEditorArea || !contentEditorTitle || !contentEditorPath) {
    return;
  }

  activeContentButton = setActiveButton(button, activeContentButton);
  contentEditorTitle.textContent = label;
  contentEditorPath.textContent = path;
  contentEditorPath.classList.remove("hidden");
  setEditorState(contentEditorArea, contentEditorPlaceholder, contentSaveButton, contentCancelButton, true);

  try {
    const response = await fetch(`/api/content?path=${encodeURIComponent(path)}`);
    const data = await response.json();
    if (!data.success) {
      alert("Failed to load content.");
      return;
    }
    currentContentPath = path;
    originalContentBody = data.content;
    contentEditorArea.value = data.content;
  } catch (error) {
    console.error("Failed to load content:", error);
    alert("Failed to load content.");
  }
}

contentSaveButton?.addEventListener("click", async () => {
  if (!contentEditorArea || !currentContentPath) {
    return;
  }

  try {
    const response = await secureFetch("/api/save", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ path: currentContentPath, content: contentEditorArea.value })
    });
    const data = await response.json();
    if (data.success) {
      originalContentBody = contentEditorArea.value;
      alert("Content saved.");
      return;
    }
    alert("Failed to save content.");
  } catch (error) {
    console.error("Content save error:", error);
    alert("Failed to save content.");
  }
});

contentCancelButton?.addEventListener("click", () => {
  if (!contentEditorArea) {
    return;
  }
  contentEditorArea.value = originalContentBody;
});

refreshContentButton?.addEventListener("click", () => {
  contentListLoaded = false;
  loadContentList(true);
});

async function loadBlocksList(force = false) {
  if (!blockList) {
    return;
  }

  if (blockListLoaded && !force) {
    return;
  }

  blockList.textContent = "Loading...";
  try {
    const response = await fetch("/api/blocks/list");
    const data = await response.json();
    if (data.success) {
      blockList.innerHTML = "";
      if (!Array.isArray(data.items) || data.items.length === 0) {
        blockList.innerHTML = `<p class="text-xs text-gray-500">No blocks found.</p>`;
      } else {
        appendBlockTree(data.items, blockList, 0);
      }
      blockListLoaded = true;
      return;
    }
    blockList.innerHTML = `<p class="text-xs text-red-600">Failed to load blocks.</p>`;
  } catch (error) {
    console.error("Failed to load blocks list:", error);
    blockList.innerHTML = `<p class="text-xs text-red-600">Failed to load blocks.</p>`;
  }
}

function appendBlockTree(items, container, depth) {
  items.forEach(item => {
    if (item.type === "directory") {
      const header = document.createElement("div");
      header.className = "text-xs uppercase tracking-wide text-gray-400 mt-3";
      header.style.paddingLeft = `${depth * 12}px`;
      header.textContent = item.name;
      container.appendChild(header);
      if (Array.isArray(item.children)) {
        appendBlockTree(item.children, container, depth + 1);
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
      button.addEventListener("click", () => {
        openBlockEditor(item.path, item.label || item.path, button);
      });
      container.appendChild(button);
    }
  });
}

async function openBlockEditor(path, label, button) {
  if (!blockEditorArea || !blockEditorTitle || !blockEditorPath) {
    return;
  }

  activeBlockButton = setActiveButton(button, activeBlockButton);
  blockEditorTitle.textContent = label;
  blockEditorPath.textContent = path;
  blockEditorPath.classList.remove("hidden");
  setEditorState(blockEditorArea, blockEditorPlaceholder, blockSaveButton, blockCancelButton, true);

  try {
    const response = await fetch(`/api/blocks?path=${encodeURIComponent(path)}`);
    const data = await response.json();
    if (!data.success) {
      alert("Failed to load block.");
      return;
    }
    currentBlockPath = path;
    originalBlockBody = data.content;
    blockEditorArea.value = data.content;
  } catch (error) {
    console.error("Failed to load block:", error);
    alert("Failed to load block.");
  }
}

blockSaveButton?.addEventListener("click", async () => {
  if (!blockEditorArea || !currentBlockPath) {
    return;
  }

  try {
    const response = await secureFetch("/api/blocks/save", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ path: currentBlockPath, content: blockEditorArea.value })
    });
    const data = await response.json();
    if (data.success) {
      originalBlockBody = blockEditorArea.value;
      alert("Block saved.");
      return;
    }
    alert("Failed to save block.");
  } catch (error) {
    console.error("Block save error:", error);
    alert("Failed to save block.");
  }
});

blockCancelButton?.addEventListener("click", () => {
  if (!blockEditorArea) {
    return;
  }
  blockEditorArea.value = originalBlockBody;
});

refreshBlocksButton?.addEventListener("click", () => {
  blockListLoaded = false;
  loadBlocksList(true);
});
