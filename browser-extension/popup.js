let activeTabId = null;
let activeTabUrl = "";

function setMessage(text) {
  document.getElementById("messageText").textContent = text || "";
}

function normalizeDomainFromUrl(rawUrl) {
  try {
    const parsed = new URL(rawUrl);
    return parsed.hostname.replace(/^www\./, "").toLowerCase();
  } catch (error) {
    return "";
  }
}

function formatDateTime(value) {
  if (!value) {
    return "mai";
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleString();
}

function runtimeSendMessage(payload) {
  return new Promise((resolve) => {
    chrome.runtime.sendMessage(payload, (response) => {
      if (chrome.runtime.lastError) {
        resolve({
          success: false,
          message: chrome.runtime.lastError.message
        });
        return;
      }

      resolve(response || { success: false, message: "Nessuna risposta dal runtime" });
    });
  });
}

async function refreshActiveTab() {
  const tabs = await tabsQuery({ active: true, currentWindow: true });
  const tab = tabs && tabs[0] ? tabs[0] : null;

  if (!tab || typeof tab.id !== "number") {
    activeTabId = null;
    activeTabUrl = "";
    return;
  }

  activeTabId = tab.id;
  activeTabUrl = tab.url || "";
}

async function refreshStatus() {
  await refreshActiveTab();

  const domain = normalizeDomainFromUrl(activeTabUrl);
  document.getElementById("domainText").textContent = domain || "-";

  const cache = await storageGet(["km_credentials_cache", "km_last_sync"]);
  const credentials = Array.isArray(cache.km_credentials_cache) ? cache.km_credentials_cache : [];

  document.getElementById("cacheCount").textContent = String(credentials.length);
  document.getElementById("lastSync").textContent = formatDateTime(cache.km_last_sync || "");

  const meta = await runtimeSendMessage({ type: "km_get_sync_meta" });
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
    km_last_sync: new Date().toISOString(),
    km_last_sync_error: ""
  });

  return {
    success: true,
    count: credentials.length
  };
}

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

async function clearCache() {
  await storageRemove(["km_credentials_cache", "km_last_sync"]);
  await refreshStatus();
  setMessage("Cache locale svuotata.");
}

document.getElementById("syncBtn").addEventListener("click", syncFromDashboard);
document.getElementById("clearBtn").addEventListener("click", clearCache);
document.getElementById("refreshBtn").addEventListener("click", refreshStatus);

refreshStatus();
