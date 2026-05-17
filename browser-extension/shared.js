// ===========================
// KEYMANAGER - Shared utilities
// Caricato sia dal popup che dal content script (vedi manifest.json).
// ===========================

// ───────────────── chrome.* helpers ─────────────────

function storageGet(keys) {
  return new Promise((resolve) => chrome.storage.local.get(keys, resolve));
}

function storageSet(payload) {
  return new Promise((resolve) => chrome.storage.local.set(payload, resolve));
}

function storageRemove(keys) {
  return new Promise((resolve) => chrome.storage.local.remove(keys, resolve));
}

function tabsQuery(queryInfo) {
  return new Promise((resolve) => chrome.tabs.query(queryInfo, resolve));
}

function tabsSendMessage(tabId, payload) {
  return new Promise((resolve) => {
    chrome.tabs.sendMessage(tabId, payload, (response) => {
      if (chrome.runtime.lastError) {
        resolve({
          success: false,
          message: chrome.runtime.lastError.message
        });
        return;
      }

      resolve(response || { success: false, message: "Nessuna risposta dalla scheda" });
    });
  });
}

function injectContentScript(tabId) {
  return new Promise((resolve) => {
    chrome.scripting.executeScript(
      {
        target: { tabId },
        files: ["content.js"]
      },
      () => {
        if (chrome.runtime.lastError) {
          resolve({
            success: false,
            message: chrome.runtime.lastError.message
          });
          return;
        }

        resolve({ success: true });
      }
    );
  });
}

function isDashboardUrl(rawUrl) {
  return /\/project-work\/dashboard\/main\.php/i.test(String(rawUrl || ""));
}


// ───────────────── Matching helpers ─────────────────
// Logica unica condivisa tra popup.js e content.js per garantire
// che l'autofill mostri/usi sempre le stesse credenziali del matching.

/**
 * Normalizza un URL o hostname in un hostname senza "www.".
 * Esempio: "https://www.Google.com/path" → "google.com"
 */
function kmNormalizeHost(value) {
  const raw = String(value || "").trim().toLowerCase();
  if (!raw) return "";

  try {
    const parsed = new URL(raw.includes("://") ? raw : "https://" + raw);
    return parsed.hostname.replace(/^www\./, "").toLowerCase();
  } catch {
    return raw.replace(/^www\./, "").toLowerCase();
  }
}

/**
 * Ritorna il dominio radice (es. "sub.google.com" → "google.com").
 */
function kmRootDomain(host) {
  const normalized = kmNormalizeHost(host);
  if (!normalized) return "";
  const parts = normalized.split(".");
  return parts.length <= 2 ? normalized : parts.slice(-2).join(".");
}

/**
 * Estrae l'hostname da una credenziale (priorità: url → service_name → site).
 * Ritorna "" se nessuna informazione contiene un host valido.
 */
function kmHostFromCredential(credential) {
  const urlHost = kmNormalizeHost(credential.url || "");
  if (urlHost) return urlHost;

  const service = String(credential.service_name || credential.site || "");
  const match = service.match(/([a-z0-9.-]+\.[a-z]{2,})/i);
  return match ? kmNormalizeHost(match[1]) : "";
}

/**
 * Calcola quanto bene una credenziale corrisponde alla pagina corrente.
 * 2 = match esatto sull'host, 1 = stesso dominio radice, 0 = nessun match.
 */
function kmCredentialMatchScore(credentialHost, pageHost) {
  if (!credentialHost || !pageHost) return 0;
  if (credentialHost === pageHost) return 2;
  if (kmRootDomain(credentialHost) === kmRootDomain(pageHost)) return 1;
  return 0;
}

/**
 * Ritorna le credenziali corrispondenti al sito corrente, ordinate per match
 * (exact host prima, root domain dopo). Ogni credenziale ha aggiunto:
 *   - match_type: "exact_host" | "root_domain"
 */
function kmGetMatchingCredentials(pageHost, credentials) {
  const normalizedPageHost = kmNormalizeHost(pageHost);
  if (!normalizedPageHost) return [];

  const list = Array.isArray(credentials) ? credentials : [];
  const matched = [];

  list.forEach((credential) => {
    const credHost = kmHostFromCredential(credential);
    const score = kmCredentialMatchScore(credHost, normalizedPageHost);
    if (score <= 0) return;

    matched.push({
      ...credential,
      match_type: score === 2 ? "exact_host" : "root_domain"
    });
  });

  matched.sort((a, b) => {
    if (a.match_type === b.match_type) return 0;
    return a.match_type === "exact_host" ? -1 : 1;
  });

  return matched;
}


// ───────────────── Esportazioni per Jest ─────────────────
// Nel browser `module` non esiste e questo blocco viene ignorato.
/* istanbul ignore next */
if (typeof module !== "undefined") {
  module.exports = {
    storageGet,
    storageSet,
    storageRemove,
    tabsQuery,
    tabsSendMessage,
    injectContentScript,
    isDashboardUrl,
    kmNormalizeHost,
    kmRootDomain,
    kmHostFromCredential,
    kmCredentialMatchScore,
    kmGetMatchingCredentials
  };
}
