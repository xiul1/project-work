// ===========================
// KEYMANAGER - Popup Script
// Gestisce la UI del popup: controllo login, sincronizzazione, stato sito corrente.
// ===========================

// URL del progetto (base locale XAMPP)
const KM_BASE_URL        = "http://localhost/project-work";
const KM_LOGIN_URL       = KM_BASE_URL + "/auth/login.php";
const KM_DASHBOARD_URL   = KM_BASE_URL + "/dashboard/main.php";
const KM_STATUS_URL      = KM_BASE_URL + "/auth/session_status.php";

let activeTabId  = null;
let activeTabUrl = "";


// ===========================
// UTILITÀ UI
// ===========================

/** Mostra un messaggio temporaneo in fondo al popup. */
function setMessage(text) {
  document.getElementById("messageText").textContent = text || "";
}

/** Formatta un timestamp ISO in data/ora leggibile. */
function formatDateTime(value) {
  if (!value) return "mai";

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return date.toLocaleString();
}

/** Normalizza un URL in hostname senza "www.". */
function normalizeDomainFromUrl(rawUrl) {
  try {
    const parsed = new URL(rawUrl);
    return parsed.hostname.replace(/^www\./, "").toLowerCase();
  } catch {
    return "";
  }
}

/**
 * Mostra una vista e nasconde l'altra.
 * @param {"loggedIn"|"notLoggedIn"} view
 */
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

/** Apre una nuova scheda (o ne porta una esistente in primo piano) con l'URL indicato. */
function openTab(url) {
  chrome.tabs.create({ url, active: true });
}


// ===========================
// CONTROLLO SESSIONE LOGIN
// ===========================

/**
 * Controlla se l'utente è loggato nella web app KeyManager.
 * Interroga l'endpoint PHP auth/session_status.php.
 * @returns {Promise<boolean>}
 */
async function checkLoginStatus() {
  try {
    const response = await fetch(KM_STATUS_URL, { credentials: "include" });

    if (!response.ok) return false;

    const data = await response.json();
    return data.logged_in === true;
  } catch {
    // Impossibile raggiungere il server (XAMPP spento, ecc.)
    return false;
  }
}


// ===========================
// LOGICA POPUP PRINCIPALE
// ===========================

/** Legge la tab attiva e aggiorna activeTabId / activeTabUrl. */
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

/** Aggiorna i dati nella vista "loggato": cache count, ultima sync, dominio. */
async function refreshStatus() {
  await refreshActiveTab();

  const domain = normalizeDomainFromUrl(activeTabUrl);
  document.getElementById("domainText").textContent = domain || "-";

  const cache       = await storageGet(["km_credentials_cache", "km_last_sync"]);
  const credentials = Array.isArray(cache.km_credentials_cache) ? cache.km_credentials_cache : [];

  document.getElementById("cacheCount").textContent = String(credentials.length);
  document.getElementById("lastSync").textContent   = formatDateTime(cache.km_last_sync || "");

  const meta       = await runtimeSendMessage({ type: "km_get_sync_meta" });
  const baseStatus = meta.success && meta.lastError
    ? "Ultimo errore sync: " + meta.lastError
    : "";

  if (!activeTabId) {
    document.getElementById("statusText").textContent = baseStatus || "Scheda attiva non disponibile.";
    return;
  }

  if (isDashboardUrl(activeTabUrl)) {
    document.getElementById("statusText").textContent = baseStatus || "Dashboard rilevata: puoi sincronizzare ora.";
  } else {
    document.getElementById("statusText").textContent = baseStatus || "Auto-sync attiva. Puoi sincronizzare anche manualmente.";
  }
}

/** Invia un messaggio al background script. */
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

/** Sincronizzazione diretta dalla tab attiva (se è la dashboard). */
async function syncDirectFromActiveTab() {
  await refreshActiveTab();

  if (!activeTabId || !isDashboardUrl(activeTabUrl)) {
    return {
      success: false,
      message: "Apri /dashboard/main.php nella tab attiva e riprova."
    };
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
    return {
      success: false,
      message: (response && response.message) || "Sincronizzazione diretta fallita"
    };
  }

  const credentials = Array.isArray(response.credentials) ? response.credentials : [];

  await storageSet({
    km_credentials_cache: credentials,
    km_last_sync:         new Date().toISOString(),
    km_last_sync_error:   ""
  });

  return { success: true, count: credentials.length };
}

/** Avvia la sincronizzazione tramite background o direttamente dalla tab. */
async function syncFromDashboard() {
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
  setMessage("Sincronizzazione completata (" + String(response.count || 0) + " credenziali).");
}

/** Svuota la cache locale delle credenziali. */
async function clearCache() {
  await storageRemove(["km_credentials_cache", "km_last_sync"]);
  await refreshStatus();
  setMessage("Cache locale svuotata.");
}


// ===========================
// INIZIALIZZAZIONE
// ===========================

/**
 * Punto d'ingresso: controlla se l'utente è loggato e mostra la vista corretta.
 */
async function init() {
  const loggedIn = await checkLoginStatus();

  if (loggedIn) {
    showView("loggedIn");
    await refreshStatus();

    // Bottoni visibili solo nella vista "loggato"
    document.getElementById("syncBtn").addEventListener("click", syncFromDashboard);
    document.getElementById("clearBtn").addEventListener("click", clearCache);
    document.getElementById("refreshBtn").addEventListener("click", refreshStatus);
    document.getElementById("dashboardBtn").addEventListener("click", () => openTab(KM_DASHBOARD_URL));
  } else {
    showView("notLoggedIn");

    // Bottone nella vista "non loggato"
    document.getElementById("loginBtn").addEventListener("click", () => openTab(KM_LOGIN_URL));
  }
}

init();
