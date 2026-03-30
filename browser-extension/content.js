const KM_OVERLAY_ID = "km-autofill-overlay";
let kmOverlayElement = null;

function kmNormalizeHost(value) {
  const raw = String(value || "").trim().toLowerCase();
  if (!raw) {
    return "";
  }

  try {
    const parsed = new URL(raw.includes("://") ? raw : "https://" + raw);
    return parsed.hostname.replace(/^www\./, "").toLowerCase();
  } catch (error) {
    return raw.replace(/^www\./, "").toLowerCase();
  }
}

function kmRootDomain(host) {
  const normalized = kmNormalizeHost(host);
  if (!normalized) {
    return "";
  }

  const parts = normalized.split(".");
  if (parts.length <= 2) {
    return normalized;
  }

  return parts.slice(-2).join(".");
}

function kmHostFromCredential(credential) {
  const urlHost = kmNormalizeHost(credential.url || "");
  if (urlHost) {
    return urlHost;
  }

  const service = String(credential.service_name || "");
  const match = service.match(/([a-z0-9.-]+\.[a-z]{2,})/i);
  return match ? kmNormalizeHost(match[1]) : "";
}

function kmCredentialMatchScore(credentialHost, pageHost) {
  if (!credentialHost || !pageHost) {
    return 0;
  }

  if (credentialHost === pageHost) {
    return 2;
  }

  if (kmRootDomain(credentialHost) === kmRootDomain(pageHost)) {
    return 1;
  }

  return 0;
}

function kmDispatchInputEvents(element) {
  element.dispatchEvent(new Event("input", { bubbles: true }));
  element.dispatchEvent(new Event("change", { bubbles: true }));
}

function kmIsVisible(element) {
  if (!element) {
    return false;
  }

  const style = window.getComputedStyle(element);
  return style.display !== "none" && style.visibility !== "hidden";
}

function kmIsEditableInput(element) {
  if (!(element instanceof HTMLInputElement)) {
    return false;
  }

  if (element.disabled || element.readOnly) {
    return false;
  }

  const type = (element.type || "text").toLowerCase();
  return ["text", "email", "password", "search", "tel", "url"].includes(type);
}

function kmFindPasswordField(anchor) {
  const scope = anchor.form || document;
  const candidates = Array.from(scope.querySelectorAll("input[type='password']"));
  const visible = candidates.filter(kmIsVisible);

  if (anchor.type === "password") {
    return anchor;
  }

  return visible[0] || null;
}

function kmFindUsernameField(anchor, passwordField) {
  const scope = anchor.form || document;
  const candidates = Array.from(scope.querySelectorAll("input[type='text'], input[type='email'], input:not([type])"))
    .filter(kmIsVisible)
    .filter((field) => !field.disabled && !field.readOnly);

  if (candidates.length === 0) {
    return null;
  }

  if (anchor.type === "text" || anchor.type === "email") {
    return anchor;
  }

  if (passwordField) {
    for (let i = candidates.length - 1; i >= 0; i -= 1) {
      const candidate = candidates[i];
      if (candidate.compareDocumentPosition(passwordField) & Node.DOCUMENT_POSITION_FOLLOWING) {
        return candidate;
      }
    }
  }

  return candidates[0];
}

function kmFillCredential(anchor, credential) {
  const passwordField = kmFindPasswordField(anchor);
  const usernameField = kmFindUsernameField(anchor, passwordField);
  let filledCount = 0;

  if (usernameField && typeof credential.username === "string") {
    usernameField.focus();
    usernameField.value = credential.username;
    kmDispatchInputEvents(usernameField);
    filledCount += 1;
  }

  if (passwordField && typeof credential.password === "string") {
    passwordField.focus();
    passwordField.value = credential.password;
    kmDispatchInputEvents(passwordField);
    filledCount += 1;
  }

  return filledCount;
}

function kmHideOverlay() {
  if (kmOverlayElement && kmOverlayElement.parentNode) {
    kmOverlayElement.parentNode.removeChild(kmOverlayElement);
  }
  kmOverlayElement = null;
}

function kmCreateOverlay(anchor, credentials) {
  kmHideOverlay();

  if (!credentials || credentials.length === 0) {
    return;
  }

  const rect = anchor.getBoundingClientRect();
  const overlay = document.createElement("div");
  overlay.id = KM_OVERLAY_ID;
  overlay.style.position = "fixed";
  overlay.style.zIndex = "2147483647";
  overlay.style.left = Math.round(rect.left) + "px";
  overlay.style.top = Math.round(rect.bottom + 6) + "px";
  overlay.style.minWidth = Math.max(Math.round(rect.width), 260) + "px";
  overlay.style.maxWidth = "360px";
  overlay.style.maxHeight = "220px";
  overlay.style.overflowY = "auto";
  overlay.style.background = "#ffffff";
  overlay.style.border = "1px solid #c8d4e3";
  overlay.style.borderRadius = "8px";
  overlay.style.boxShadow = "0 8px 24px rgba(0,0,0,0.15)";
  overlay.style.padding = "6px";

  credentials.forEach((credential) => {
    const row = document.createElement("button");
    row.type = "button";
    row.style.display = "block";
    row.style.width = "100%";
    row.style.textAlign = "left";
    row.style.border = "0";
    row.style.background = "transparent";
    row.style.padding = "8px";
    row.style.borderRadius = "6px";
    row.style.cursor = "pointer";
    row.style.fontFamily = "Arial, sans-serif";

    const service = credential.service_name || "Servizio";
    const username = credential.username || "";

    row.textContent = service + " | " + username;
    row.addEventListener("mouseenter", () => {
      row.style.background = "#eef5ff";
    });
    row.addEventListener("mouseleave", () => {
      row.style.background = "transparent";
    });
    row.addEventListener("mousedown", (event) => {
      event.preventDefault();
    });
    row.addEventListener("click", () => {
      kmFillCredential(anchor, credential);
      kmHideOverlay();
    });

    overlay.appendChild(row);
  });

  document.body.appendChild(overlay);
  kmOverlayElement = overlay;
}

async function kmGetCachedCredentials() {
  const result = await new Promise((resolve) => {
    chrome.storage.local.get(["km_credentials_cache"], resolve);
  });
  return Array.isArray(result.km_credentials_cache) ? result.km_credentials_cache : [];
}

async function kmGetSuggestionsForPage() {
  const host = kmNormalizeHost(window.location.hostname);

  if (!host) {
    return [];
  }

  const credentials = await kmGetCachedCredentials();
  const matched = [];

  credentials.forEach((credential) => {
    const credentialHost = kmHostFromCredential(credential);
    const score = kmCredentialMatchScore(credentialHost, host);

    if (score <= 0) {
      return;
    }

    matched.push({
      ...credential,
      match_type: score === 2 ? "exact_host" : "root_domain"
    });
  });

  matched.sort((a, b) => {
    if (a.match_type === b.match_type) {
      return 0;
    }
    return a.match_type === "exact_host" ? -1 : 1;
  });

  return matched;
}

async function kmHandleInputFocus(event) {
  const target = event.target;

  if (!kmIsEditableInput(target)) {
    return;
  }

  const suggestions = await kmGetSuggestionsForPage();
  kmCreateOverlay(target, suggestions);
}

function kmIsDashboardPage() {
  return /\/project-work\/dashboard\/main\.php/i.test(window.location.pathname);
}

async function kmFetchPasswordForCredential(credentialId, csrfToken) {
  const payload = new FormData();
  payload.append("id", String(credentialId));
  payload.append("csrf_token", csrfToken);

  const response = await fetch("credential/get_password.php", {
    method: "POST",
    body: payload,
    credentials: "same-origin"
  });

  const data = await response.json();

  if (!response.ok || !data.success) {
    throw new Error(data.message || "Errore recupero password");
  }

  return data.password;
}

async function kmCollectCredentialsFromDashboard() {
  if (!kmIsDashboardPage()) {
    throw new Error("Questa pagina non è la dashboard.");
  }

  const csrfInput = document.querySelector("#addCredentialForm input[name='csrf_token']")
    || document.querySelector("input[name='csrf_token']");

  if (!csrfInput || !csrfInput.value) {
    throw new Error("Token CSRF non trovato in dashboard.");
  }

  const rows = Array.from(document.querySelectorAll("#credentialsTableBody tr[data-id]"));
  const result = [];

  for (const row of rows) {
    const id = Number(row.dataset.id || 0);
    if (!Number.isFinite(id) || id <= 0) {
      continue;
    }

    const serviceName = row.dataset.service || "";
    const username = row.dataset.username || "";
    const url = row.dataset.url || "";
    const notes = row.dataset.notes || "";

    let password = "";
    try {
      password = await kmFetchPasswordForCredential(id, csrfInput.value);
    } catch (error) {
      continue;
    }

    result.push({
      id,
      service_name: serviceName,
      username,
      url,
      notes,
      password
    });
  }

  return result;
}

document.addEventListener("focusin", (event) => {
  kmHandleInputFocus(event).catch(() => {});
});

document.addEventListener("mousedown", (event) => {
  const target = event.target;
  if (kmOverlayElement && target instanceof Node && !kmOverlayElement.contains(target)) {
    kmHideOverlay();
  }
});

window.addEventListener("scroll", kmHideOverlay, true);
window.addEventListener("resize", kmHideOverlay);

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (!message || !message.type) {
    sendResponse({ success: false, message: "Messaggio non valido" });
    return;
  }

  if (message.type === "km_collect_credentials_from_dashboard") {
    kmCollectCredentialsFromDashboard()
      .then((credentials) => {
        sendResponse({
          success: true,
          credentials
        });
      })
      .catch((error) => {
        sendResponse({
          success: false,
          message: error && error.message ? error.message : "Errore sincronizzazione"
        });
      });
    return true;
  }

  sendResponse({ success: false, message: "Tipo messaggio non gestito" });
});
