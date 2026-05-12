// ===========================
// KEYMANAGER - Popup Script
// ===========================

const KM_BASE_URL      = "http://localhost/project-work";
const KM_LOGIN_URL     = KM_BASE_URL + "/auth/login.php";
const KM_DASHBOARD_URL = KM_BASE_URL + "/dashboard/main.php";
const KM_SETTINGS_URL  = KM_BASE_URL + "/dashboard/settings.php";
const KM_STATUS_URL    = KM_BASE_URL + "/auth/session_status.php";

const AVATAR_COLORS = [
  "#4285F4", "#34A853", "#E50914", "#FF9900",
  "#7B68EE", "#FF6B35", "#333333", "#9B59B6"
];

let activeTabId    = null;
let activeTabUrl   = "";
let allCredentials = [];


// ───────────────── UI UTILS ─────────────────

function setMessage(text) {
  document.getElementById("messageText").textContent = text || "";
}

function normalizeDomainFromUrl(rawUrl) {
  try {
    return new URL(rawUrl).hostname.replace(/^www\./, "").toLowerCase();
  } catch {
    return "";
  }
}

function showView(view) {
  const loggedIn    = document.getElementById("viewLoggedIn");
  const notLoggedIn = document.getElementById("viewNotLoggedIn");

  if (view === "loggedIn") {
    loggedIn.classList.remove("hidden");
    notLoggedIn.classList.add("hidden");
  } else {
    loggedIn.classList.add("hidden");
    notLoggedIn.classList.remove("hidden");
  }
}

function openTab(url) {
  chrome.tabs.create({ url, active: true });
}

function avatarColor(name) {
  let hash = 0;
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash);
  }
  return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
}

function avatarLetter(name) {
  return (name || "?").charAt(0).toUpperCase();
}


// ───────────────── SESSION CHECK ─────────────────

async function checkLoginStatus() {
  try {
    const response = await fetch(KM_STATUS_URL, { credentials: "include" });
    if (!response.ok) return false;
    const data = await response.json();
    return data.logged_in === true;
  } catch {
    return false;
  }
}


// ───────────────── SITE CARD ─────────────────

function updateSiteCard(domain, cred) {
  const nameEl   = document.getElementById("siteName");
  const subEl    = document.getElementById("siteEmail");
  const avatarEl = document.getElementById("siteAvatar");
  const autofill = document.getElementById("autofillBtn");

  if (!domain) {
    nameEl.textContent        = "-";
    subEl.textContent         = "Nessuna credenziale trovata";
    avatarEl.textContent      = "?";
    avatarEl.style.background = "#888888";
    autofill.disabled         = true;
    return;
  }

  if (cred) {
    nameEl.textContent        = cred.site || domain;
    subEl.textContent         = cred.username || "";
    avatarEl.textContent      = avatarLetter(cred.site || domain);
    avatarEl.style.background = avatarColor(cred.site || domain);
    autofill.disabled         = false;
  } else {
    nameEl.textContent        = domain;
    subEl.textContent         = "Nessuna credenziale trovata";
    avatarEl.textContent      = avatarLetter(domain);
    avatarEl.style.background = avatarColor(domain);
    autofill.disabled         = true;
  }
}


// ───────────────── RECENT LIST ─────────────────

const COPY_SVG = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>`;

function buildRecentList(credentials, query) {
  const list = document.getElementById("recentList");
  list.innerHTML = "";

  const filtered = query
    ? credentials.filter(c => {
        const q = query.toLowerCase();
        return (c.site || "").toLowerCase().includes(q) ||
               (c.username || "").toLowerCase().includes(q);
      })
    : credentials;

  const items = filtered.slice(0, 4);

  if (items.length === 0) {
    const empty = document.createElement("div");
    empty.className = "km-recent-item";
    empty.style.color = "#888888";
    empty.style.justifyContent = "center";
    empty.style.cursor = "default";
    empty.textContent = query ? "Nessun risultato" : "Nessuna credenziale in cache";
    list.appendChild(empty);
    return;
  }

  items.forEach(cred => {
    const row = document.createElement("div");
    row.className = "km-recent-item";

    const av = document.createElement("div");
    av.className = "km-avatar";
    av.textContent = avatarLetter(cred.site || "");
    av.style.background = avatarColor(cred.site || "");

    const info = document.createElement("div");
    info.className = "km-recent-info";

    const name = document.createElement("div");
    name.className = "km-recent-name";
    name.textContent = cred.site || "-";

    const sub = document.createElement("div");
    sub.className = "km-recent-sub";
    sub.textContent = cred.username || "";

    info.appendChild(name);
    info.appendChild(sub);

    const copyBtn = document.createElement("button");
    copyBtn.className = "km-copy-btn";
    copyBtn.title = "Copia username";
    copyBtn.innerHTML = COPY_SVG;
    copyBtn.addEventListener("click", async (e) => {
      e.stopPropagation();
      if (cred.username) {
        await navigator.clipboard.writeText(cred.username);
        setMessage("Username copiato!");
        setTimeout(() => setMessage(""), 1500);
      }
    });

    row.appendChild(av);
    row.appendChild(info);
    row.appendChild(copyBtn);
    list.appendChild(row);
  });
}


// ───────────────── ACTIVE TAB ─────────────────

async function refreshActiveTab() {
  const tabs = await tabsQuery({ active: true, currentWindow: true });
  const tab  = tabs && tabs[0] ? tabs[0] : null;

  if (!tab || typeof tab.id !== "number") {
    activeTabId  = null;
    activeTabUrl = "";
    return;
  }

  activeTabId  = tab.id;
  activeTabUrl = tab.url || "";
}

async function refreshStatus() {
  await refreshActiveTab();

  const domain = normalizeDomainFromUrl(activeTabUrl);
  const cache  = await storageGet(["km_credentials_cache"]);
  allCredentials = Array.isArray(cache.km_credentials_cache) ? cache.km_credentials_cache : [];

  const matched = domain
    ? allCredentials.find(c => {
        const site = (c.site || "").toLowerCase();
        return site.includes(domain) || domain.includes(site);
      })
    : null;

  updateSiteCard(domain, matched || null);
  buildRecentList(allCredentials, "");
}


// ───────────────── SYNC ─────────────────

function runtimeSendMessage(payload) {
  return new Promise((resolve) => {
    chrome.runtime.sendMessage(payload, (response) => {
      if (chrome.runtime.lastError) {
        resolve({ success: false, message: chrome.runtime.lastError.message });
        return;
      }
      resolve(response || { success: false, message: "Nessuna risposta dal runtime" });
    });
  });
}

async function syncDirectFromActiveTab() {
  await refreshActiveTab();

  if (!activeTabId || !isDashboardUrl(activeTabUrl)) {
    return { success: false, message: "Apri /dashboard/main.php nella tab attiva e riprova." };
  }

  let response = await tabsSendMessage(activeTabId, {
    type: "km_collect_credentials_from_dashboard"
  });

  if (
    !response.success &&
    typeof response.message === "string" &&
    response.message.includes("Receiving end does not exist")
  ) {
    const injection = await injectContentScript(activeTabId);
    if (!injection.success) {
      return {
        success: false,
        message: "Impossibile collegarsi alla pagina: " + (injection.message || "errore injection")
      };
    }
    response = await tabsSendMessage(activeTabId, {
      type: "km_collect_credentials_from_dashboard"
    });
  }

  if (!response || !response.success) {
    return { success: false, message: (response && response.message) || "Sincronizzazione diretta fallita" };
  }

  const credentials = Array.isArray(response.credentials) ? response.credentials : [];
  await storageSet({
    km_credentials_cache: credentials,
    km_last_sync:         new Date().toISOString(),
    km_last_sync_error:   ""
  });
  return { success: true, count: credentials.length };
}

async function syncFromDashboard() {
  setMessage("Sincronizzazione in corso...");

  let response = await runtimeSendMessage({ type: "km_sync_now" });

  if (
    !response.success &&
    typeof response.message === "string" &&
    response.message.includes("Receiving end does not exist")
  ) {
    response = await syncDirectFromActiveTab();
  }

  if (!response || !response.success) {
    setMessage((response && response.message) || "Sincronizzazione fallita.");
    return;
  }

  await refreshStatus();
  setMessage("Sincronizzato (" + String(response.count || 0) + " credenziali).");
  setTimeout(() => setMessage(""), 2000);
}


// ───────────────── AUTOFILL ─────────────────

async function triggerAutofill() {
  if (!activeTabId) {
    setMessage("Nessuna tab attiva.");
    return;
  }

  const domain = normalizeDomainFromUrl(activeTabUrl);
  const cred   = allCredentials.find(c => {
    const site = (c.site || "").toLowerCase();
    return site.includes(domain) || domain.includes(site);
  });

  if (!cred) {
    setMessage("Nessuna credenziale per questo sito.");
    return;
  }

  const response = await tabsSendMessage(activeTabId, {
    type: "km_autofill",
    credential: cred
  });

  if (!response || !response.success) {
    setMessage("Autofill non riuscito.");
  } else {
    window.close();
  }
}


// ───────────────── INIT ─────────────────

async function init() {
  const loggedIn = await checkLoginStatus();

  if (loggedIn) {
    showView("loggedIn");
    await refreshStatus();

    document.getElementById("syncBtn").addEventListener("click", syncFromDashboard);
    document.getElementById("settingsBtn").addEventListener("click", () => openTab(KM_SETTINGS_URL));
    document.getElementById("autofillBtn").addEventListener("click", triggerAutofill);
    document.getElementById("openVaultBtn").addEventListener("click", () => openTab(KM_DASHBOARD_URL));
    document.getElementById("addNewBtn").addEventListener("click", () => openTab(KM_DASHBOARD_URL));
    document.getElementById("generateBtn").addEventListener("click", () => openTab(KM_DASHBOARD_URL));
    document.getElementById("searchInput").addEventListener("input", (e) => {
      buildRecentList(allCredentials, e.target.value);
    });
  } else {
    showView("notLoggedIn");
    document.getElementById("loginBtn").addEventListener("click", () => openTab(KM_LOGIN_URL));
  }
}

init();
