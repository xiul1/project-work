// ===========================
// KEYMANAGER - Content Script
// Autofill intelligente e autonomo delle credenziali
// ===========================

// --- Costanti ---

const KM_OVERLAY_ID = "km-autofill-overlay";

// Parole chiave che identificano un campo username/email
const KM_USERNAME_HINTS = [
  "user", "username", "login", "email", "mail",
  "account", "userid", "user_id", "utente"
];

// Parole chiave che escludono un campo (OTP, PIN, ecc.)
const KM_EXCLUDED_INPUT_HINTS = [
  "otp", "pin", "totp", "2fa", "one-time-code", "onetime",
  "passcode", "verification", "verify", "securitycode",
  "authcode", "smscode", "captcha", "token"
];

// Ritardo (ms) prima di eseguire l'auto-fill autonomo (evita conflitti con SPA)
const KM_AUTOFILL_DELAY_MS = 600;

// Elemento DOM del dropdown di suggerimenti
let kmOverlayElement = null;

// Timer per il debounce dell'auto-fill
let kmScanTimeout = null;

// MutationObserver per rilevare nuovi form (SPA, dialoghi, ecc.)
let kmDomObserver = null;


// ===========================
// UTILITÀ URL E DOMINIO
// ===========================

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
 * Estrae l'hostname da una credenziale (priorità: url → service_name).
 */
function kmHostFromCredential(credential) {
  const urlHost = kmNormalizeHost(credential.url || "");
  if (urlHost) return urlHost;

  const service = String(credential.service_name || "");
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


// ===========================
// RILEVAMENTO CAMPI INPUT
// ===========================

/**
 * Verifica se un elemento è un input modificabile (non disabilitato, non readonly).
 */
function kmIsEditableInput(element) {
  if (!(element instanceof HTMLInputElement)) return false;
  if (element.disabled || element.readOnly) return false;
  const type = (element.type || "text").toLowerCase();
  return ["text", "email", "password", "search", "tel", "url"].includes(type);
}

/**
 * Verifica se un elemento è visibile sullo schermo.
 */
function kmIsVisible(element) {
  if (!element) return false;
  const style = window.getComputedStyle(element);
  return style.display !== "none" && style.visibility !== "hidden";
}

/**
 * Raccoglie tutti gli attributi di un input in una stringa unica (per l'analisi degli hint).
 */
function kmCollectInputHints(element) {
  return [
    element.id || "",
    element.name || "",
    element.getAttribute("autocomplete") || "",
    element.getAttribute("placeholder") || "",
    element.getAttribute("aria-label") || "",
    element.getAttribute("data-testid") || "",
    typeof element.className === "string" ? element.className : ""
  ].join(" ").toLowerCase();
}

/**
 * Verifica se un array di suggerimenti contiene almeno uno dei termini cercati.
 */
function kmHasAnyHint(hints, terms) {
  return terms.some((term) => hints.includes(term));
}

/**
 * Verifica se un input è probabilmente un campo username/email.
 * Usa gli attributi del campo e le liste di hint/esclusioni.
 */
function kmIsUsernameLikeInput(element) {
  if (!kmIsEditableInput(element)) return false;

  const type = (element.type || "text").toLowerCase();
  if (!["text", "email", "search", "tel", "url"].includes(type)) return false;

  const autocomplete = (element.getAttribute("autocomplete") || "").trim().toLowerCase();
  if (autocomplete === "one-time-code") return false;
  if (autocomplete === "username" || autocomplete === "email") return true;

  const hints = kmCollectInputHints(element);
  if (!hints) return false;
  if (kmHasAnyHint(hints, KM_EXCLUDED_INPUT_HINTS)) return false;

  return kmHasAnyHint(hints, KM_USERNAME_HINTS);
}

/**
 * Trova il campo password visibile nello stesso form di un input dato.
 */
function kmFindPasswordField(anchor) {
  const scope = anchor.form || document;
  const candidates = Array.from(scope.querySelectorAll("input[type='password']"))
    .filter(kmIsVisible);
  return anchor.type === "password" ? anchor : (candidates[0] || null);
}

/**
 * Trova il campo username/email più vicino al campo password in un form.
 */
function kmFindUsernameField(anchor, passwordField) {
  const scope = anchor.form || document;
  const candidates = Array.from(
    scope.querySelectorAll("input[type='text'], input[type='email'], input:not([type])")
  )
    .filter(kmIsVisible)
    .filter((f) => !f.disabled && !f.readOnly)
    .filter(kmIsUsernameLikeInput);

  if (candidates.length === 0) {
    return kmIsUsernameLikeInput(anchor) ? anchor : null;
  }

  if (kmIsUsernameLikeInput(anchor)) return anchor;

  // Prende il campo che precede il campo password nel DOM
  if (passwordField) {
    for (let i = candidates.length - 1; i >= 0; i--) {
      const candidate = candidates[i];
      if (candidate.compareDocumentPosition(passwordField) & Node.DOCUMENT_POSITION_FOLLOWING) {
        return candidate;
      }
    }
  }

  return candidates[0];
}

/**
 * Invia eventi "input" e "change" su un elemento.
 * Necessario per aggiornare framework JS come React, Vue, Angular.
 */
function kmDispatchInputEvents(element) {
  element.dispatchEvent(new Event("input", { bubbles: true }));
  element.dispatchEvent(new Event("change", { bubbles: true }));
}


// ===========================
// ANALISI FORM DI LOGIN
// ===========================

/**
 * Analizza un contenitore (form o body) per determinare il tipo:
 * - "login"    → ha 1 solo campo password visibile
 * - "register" → ha 2+ campi password (es. conferma password)
 * - "unknown"  → nessun campo password visibile
 */
function kmDetectFormType(scope) {
  const passwordFields = Array.from(scope.querySelectorAll("input[type='password']"))
    .filter(kmIsVisible);

  if (passwordFields.length === 0) return "unknown";
  if (passwordFields.length >= 2) return "register";
  return "login";
}

/**
 * Trova tutti i form di login nella pagina corrente.
 * Ritorna un array di oggetti { usernameField, passwordField }.
 *
 * Cerca prima nei tag <form>; se non esistono, cerca nell'intero body
 * (utile per pagine che non usano il tag form standard).
 */
function kmFindLoginForms() {
  const results = [];

  const forms = Array.from(document.querySelectorAll("form"));
  const scopes = forms.length > 0 ? forms : [document.body];

  for (const scope of scopes) {
    if (kmDetectFormType(scope) !== "login") continue;

    const passwordField = Array.from(scope.querySelectorAll("input[type='password']"))
      .filter(kmIsVisible)[0];
    if (!passwordField) continue;

    // Passa passwordField come anchor: kmFindUsernameField usa passwordField.form come scope
    // e cerca il campo username che precede il campo password nel DOM
    const usernameField = kmFindUsernameField(passwordField, passwordField);
    if (!usernameField) continue;

    results.push({ usernameField, passwordField });
  }

  return results;
}


// ===========================
// CORRISPONDENZA CREDENZIALI
// ===========================

/**
 * Carica le credenziali dalla cache locale dell'estensione.
 */
async function kmGetCachedCredentials() {
  const result = await new Promise((resolve) => {
    chrome.storage.local.get(["km_credentials_cache"], resolve);
  });
  return Array.isArray(result.km_credentials_cache) ? result.km_credentials_cache : [];
}

/**
 * Trova e ordina le credenziali che corrispondono alla pagina corrente.
 * Criteri di match: host esatto (punteggio 2) > dominio radice (punteggio 1).
 */
async function kmGetSuggestionsForPage() {
  const host = kmNormalizeHost(window.location.hostname);
  if (!host) return [];

  const credentials = await kmGetCachedCredentials();
  const matched = [];

  credentials.forEach((credential) => {
    const credHost = kmHostFromCredential(credential);
    const score = kmCredentialMatchScore(credHost, host);
    if (score <= 0) return;

    matched.push({
      ...credential,
      match_type: score === 2 ? "exact_host" : "root_domain"
    });
  });

  // Ordina: match esatto prima, poi dominio radice
  matched.sort((a, b) => {
    if (a.match_type === b.match_type) return 0;
    return a.match_type === "exact_host" ? -1 : 1;
  });

  return matched;
}


// ===========================
// OVERLAY / DROPDOWN UI
// ===========================

/**
 * Nasconde e rimuove il dropdown di suggerimenti dal DOM.
 */
function kmHideOverlay() {
  if (kmOverlayElement && kmOverlayElement.parentNode) {
    kmOverlayElement.parentNode.removeChild(kmOverlayElement);
  }
  kmOverlayElement = null;
}

/**
 * Crea e mostra il dropdown di suggerimenti sotto il campo `anchor`.
 * Ogni riga mostra "Servizio | username"; al click compila il form.
 */
function kmCreateOverlay(anchor, credentials) {
  kmHideOverlay();
  if (!credentials || credentials.length === 0) return;

  const rect = anchor.getBoundingClientRect();
  const overlay = document.createElement("div");
  overlay.id = KM_OVERLAY_ID;

  // Stile del contenitore dropdown
  Object.assign(overlay.style, {
    position: "fixed",
    zIndex: "2147483647",
    left: Math.round(rect.left) + "px",
    top: Math.round(rect.bottom + 6) + "px",
    minWidth: Math.max(Math.round(rect.width), 260) + "px",
    maxWidth: "360px",
    maxHeight: "220px",
    overflowY: "auto",
    background: "#ffffff",
    border: "1px solid #c8d4e3",
    borderRadius: "8px",
    boxShadow: "0 8px 24px rgba(0,0,0,0.15)",
    padding: "6px"
  });

  credentials.forEach((credential) => {
    const row = document.createElement("button");
    row.type = "button";

    Object.assign(row.style, {
      display: "block",
      width: "100%",
      textAlign: "left",
      border: "0",
      background: "transparent",
      padding: "8px",
      borderRadius: "6px",
      cursor: "pointer",
      fontFamily: "Arial, sans-serif"
    });

    row.textContent = (credential.service_name || "Servizio") + " | " + (credential.username || "");

    row.addEventListener("mouseenter", () => { row.style.background = "#eef5ff"; });
    row.addEventListener("mouseleave", () => { row.style.background = "transparent"; });

    // Fix W3Schools (e altre modali che si chiudono al mousedown esterno):
    // il fill avviene già al mousedown, e stopPropagation blocca il listener
    // "chiudi modale se clicchi fuori" prima che possa scattare.
    row.addEventListener("mousedown", (e) => {
      e.preventDefault();   // Evita che l'input perda il focus
      e.stopPropagation();  // Blocca la propagazione al document (evita chiusura modali)
      kmFillCredential(anchor, credential);
      kmHideOverlay();
    });

    overlay.appendChild(row);
  });

  document.body.appendChild(overlay);
  kmOverlayElement = overlay;
}


// ===========================
// NOTIFICA AUTOFILL
// ===========================

/**
 * Mostra un piccolo toast in basso a destra della pagina per comunicare
 * all'utente che le credenziali sono state inserite con successo.
 * Si auto-rimuove dopo 3 secondi.
 *
 * @param {string} message - Testo da mostrare nel toast
 */
function kmShowFillNotification(message) {
  if (!message) return;

  // Rimuovi un eventuale toast precedente
  const existing = document.getElementById("km-fill-notification");
  if (existing) existing.remove();

  const toast = document.createElement("div");
  toast.id = "km-fill-notification";
  toast.textContent = message;

  Object.assign(toast.style, {
    position:     "fixed",
    bottom:       "20px",
    right:        "20px",
    zIndex:       "2147483647",
    background:   "#2e7d32",
    color:        "#ffffff",
    padding:      "10px 16px",
    borderRadius: "8px",
    fontSize:     "14px",
    fontFamily:   "Arial, sans-serif",
    boxShadow:    "0 4px 12px rgba(0,0,0,0.2)",
    opacity:      "0.95",
    pointerEvents: "none"
  });

  document.body.appendChild(toast);

  // Auto-rimozione dopo 3 secondi
  setTimeout(() => {
    if (toast.parentNode) toast.remove();
  }, 3000);
}


// ===========================
// IMPOSTAZIONE AUTOFILL ABILITATO
// ===========================

/**
 * Legge da chrome.storage.local se l'autofill è abilitato.
 * Valore di default: true (abilitato se non impostato).
 *
 * @returns {Promise<boolean>}
 */
function kmIsAutofillEnabled() {
  return new Promise((resolve) => {
    chrome.storage.local.get(["km_autofill_enabled"], (result) => {
      // Se la chiave non è presente, il default è true
      const enabled = result.km_autofill_enabled;
      resolve(enabled === undefined ? true : Boolean(enabled));
    });
  });
}


// ===========================
// COMPILAZIONE FORM
// ===========================

/**
 * Compila username e password in un form a partire dal campo `anchor`.
 * Usato quando l'utente seleziona una credenziale dal dropdown manuale.
 */
function kmFillCredential(anchor, credential) {
  const passwordField = kmFindPasswordField(anchor);
  const usernameField = kmFindUsernameField(anchor, passwordField);
  let filledCount = 0;

  if (usernameField && typeof credential.username === "string") {
    usernameField.focus();
    usernameField.value = credential.username;
    kmDispatchInputEvents(usernameField);
    filledCount++;
  }

  if (passwordField && typeof credential.password === "string") {
    passwordField.focus();
    passwordField.value = credential.password;
    kmDispatchInputEvents(passwordField);
    filledCount++;
  }

  // Mostra notifica di successo se almeno un campo è stato compilato
  if (filledCount > 0) {
    kmShowFillNotification("KeyManager ha inserito le credenziali ✓");
  }

  return filledCount;
}

/**
 * Compila direttamente i campi già individuati (username + password).
 * Usato dall'auto-fill autonomo dove i campi sono già noti.
 * Mostra una notifica di successo dopo aver compilato i campi.
 */
function kmFillFields(usernameField, passwordField, credential) {
  let filled = false;

  if (usernameField && typeof credential.username === "string") {
    usernameField.value = credential.username;
    kmDispatchInputEvents(usernameField);
    filled = true;
  }

  if (passwordField && typeof credential.password === "string") {
    passwordField.value = credential.password;
    kmDispatchInputEvents(passwordField);
    filled = true;
  }

  // Mostra il toast solo se almeno un campo è stato compilato
  if (filled) {
    kmShowFillNotification("KeyManager ha inserito le credenziali ✓");
  }
}


// ===========================
// AUTO-FILL AUTONOMO
// ===========================

/**
 * Scansiona la pagina e compila automaticamente i form di login trovati.
 *
 * Comportamento intelligente:
 * - 1 credenziale con match esatto (stesso host) → auto-fill silenzioso
 * - 1+ credenziali con match parziale (dominio radice) → mostra dropdown
 * - 2+ credenziali per qualsiasi match → mostra dropdown per la scelta
 * - 0 credenziali → nessuna azione
 *
 * Sicurezza:
 * - L'auto-fill silenzioso è attivo SOLO con match esatto (es. "github.com" == "github.com")
 * - Con match parziale (es. "api.github.com" per "github.com") chiede sempre conferma,
 *   per evitare che credenziali vengano inviate a sottodomini o siti simili non intenzionali.
 * - Non agisce se i campi hanno già un valore inserito dall'utente.
 */
async function kmAutoFillLoginForms() {
  // Non interferire con nessuna pagina interna di KeyManager (login, dashboard, impostazioni, ecc.)
  if (kmIsKeyManagerPage()) return;

  // Controlla se l'autofill è abilitato nelle impostazioni
  const enabled = await kmIsAutofillEnabled();
  if (!enabled) return;

  const loginForms = kmFindLoginForms();
  if (loginForms.length === 0) return;

  const suggestions = await kmGetSuggestionsForPage();
  if (suggestions.length === 0) return;

  // Auto-fill silenzioso solo se c'è esattamente 1 credenziale con host identico
  const exactMatches = suggestions.filter((s) => s.match_type === "exact_host");
  const shouldSilentFill = exactMatches.length === 1 && suggestions.length === 1;

  for (const { usernameField, passwordField } of loginForms) {
    // Non sovrascrivere se l'utente sta già digitando
    const usernameAlreadyFilled = usernameField && usernameField.value.trim() !== "";
    const passwordAlreadyFilled = passwordField && passwordField.value.trim() !== "";
    if (usernameAlreadyFilled || passwordAlreadyFilled) continue;

    if (shouldSilentFill) {
      // Auto-fill silenzioso: host identico, nessuna ambiguità
      kmFillFields(usernameField, passwordField, exactMatches[0]);
    } else {
      // Più scelte o match parziale: mostra il dropdown per conferma esplicita
      kmCreateOverlay(usernameField, suggestions);
    }
  }
}

/**
 * Pianifica l'auto-fill con un debounce di KM_AUTOFILL_DELAY_MS.
 * Evita chiamate multiple ravvicinate durante il caricamento di una SPA.
 */
function kmScheduleAutoFill() {
  if (kmScanTimeout) clearTimeout(kmScanTimeout);
  kmScanTimeout = setTimeout(() => {
    kmAutoFillLoginForms().catch((e) => console.warn("[KeyManager] Errore auto-fill:", e));
  }, KM_AUTOFILL_DELAY_MS);
}


// ===========================
// OBSERVER DOM (SPA SUPPORT)
// ===========================

/**
 * Avvia il MutationObserver che monitora l'aggiunta di nuovi campi input.
 * Indispensabile per le SPA (React, Vue, Angular) che caricano i form
 * dinamicamente senza ricaricare la pagina.
 */
function kmStartDomObserver() {
  if (kmDomObserver || !document.body) return;

  kmDomObserver = new MutationObserver((mutations) => {
    // Controlla se nelle mutazioni sono stati aggiunti input o form
    const hasNewInputs = mutations.some((mutation) =>
      Array.from(mutation.addedNodes).some((node) => {
        if (!(node instanceof Element)) return false;
        return (
          node.matches("input, form") ||
          node.querySelector("input[type='password'], input[type='email'], input[type='text']")
        );
      })
    );

    if (hasNewInputs) {
      kmScheduleAutoFill();
    }
  });

  kmDomObserver.observe(document.body, {
    childList: true,
    subtree: true
  });
}


// ===========================
// GESTIONE FOCUS (dropdown manuale)
// ===========================

/**
 * Gestisce il focus su un campo input: mostra il dropdown con le credenziali.
 * Permette all'utente di scegliere manualmente anche quando l'auto-fill
 * non si è attivato (es. campo già compilato).
 */
async function kmHandleInputFocus(event) {
  const target = event.target;

  // Non interferire con le pagine interne di KeyManager (login, dashboard, ecc.)
  if (kmIsKeyManagerPage()) {
    kmHideOverlay();
    return;
  }

  if (!kmIsEditableInput(target)) {
    kmHideOverlay();
    return;
  }

  if (!kmIsUsernameLikeInput(target)) {
    kmHideOverlay();
    return;
  }

  // Rispetta l'impostazione: se autofill è disabilitato, non mostrare nemmeno l'overlay
  const enabled = await kmIsAutofillEnabled();
  if (!enabled) {
    kmHideOverlay();
    return;
  }

  const suggestions = await kmGetSuggestionsForPage();
  kmCreateOverlay(target, suggestions);
}


// ===========================
// RACCOLTA CREDENZIALI DALLA DASHBOARD
// ===========================

/**
 * Verifica se la pagina corrente è una qualsiasi pagina interna di KeyManager
 * (dashboard, login, registrazione, impostazioni, ecc.).
 * Su queste pagine l'autofill non deve mai attivarsi.
 */
function kmIsKeyManagerPage() {
  return /\/project-work\//i.test(window.location.pathname);
}

/**
 * Verifica se la pagina corrente è ESATTAMENTE la dashboard principale (main.php).
 * Usata come guardia in kmCollectCredentialsFromDashboard() per
 * assicurarsi che la raccolta avvenga solo sulla pagina giusta.
 */
function kmIsDashboardPage() {
  return /\/project-work\/dashboard\/main\.php/i.test(window.location.pathname);
}

/**
 * Recupera la password decriptata di una credenziale via API.
 * Usato durante la sincronizzazione automatica dal background script.
 */
/* istanbul ignore next */
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

/**
 * Raccoglie tutte le credenziali dalla dashboard e le restituisce come array.
 * Viene chiamato dal background script durante la sincronizzazione automatica.
 */
/* istanbul ignore next */
async function kmCollectCredentialsFromDashboard() {
  if (!kmIsDashboardPage()) {
    throw new Error("Questa pagina non è la dashboard.");
  }

  const csrfInput =
    document.querySelector("#addCredentialForm input[name='csrf_token']") ||
    document.querySelector("input[name='csrf_token']");

  if (!csrfInput || !csrfInput.value) {
    throw new Error("Token CSRF non trovato in dashboard.");
  }

  const rows = Array.from(document.querySelectorAll("#credentialsTableBody tr[data-id]"));
  const result = [];

  for (const row of rows) {
    const id = parseInt(row.dataset.id || "0", 10);
    if (!Number.isInteger(id) || id <= 0) continue;

    const serviceName = row.dataset.service || "";
    const username = row.dataset.username || "";
    const url = row.dataset.url || "";
    const notes = row.dataset.notes || "";

    let password = "";
    try {
      password = await kmFetchPasswordForCredential(id, csrfInput.value);
    } catch {
      continue; // Salta credenziali con errore di recupero password
    }

    result.push({ id, service_name: serviceName, username, url, notes, password });
  }

  return result;
}


// ===========================
// EVENT LISTENERS E AVVIO
// ===========================

// Questa sezione viene eseguita solo nel browser (non durante i test Jest).
// In Node.js (Jest), `module` è definito; nel browser content script non lo è.
/* istanbul ignore next */
if (typeof module === "undefined") {
  // Focus su un campo → mostra dropdown per la scelta manuale
  document.addEventListener("focusin", (event) => {
    kmHandleInputFocus(event).catch((e) => console.warn("[KeyManager] Errore focus:", e));
  });

  // Click fuori dall'overlay → chiudi il dropdown
  document.addEventListener("mousedown", (event) => {
    if (kmOverlayElement && event.target instanceof Node && !kmOverlayElement.contains(event.target)) {
      kmHideOverlay();
    }
  });

  // Scroll o resize → chiudi overlay (posizionamento non più valido)
  window.addEventListener("scroll", kmHideOverlay, true);
  window.addEventListener("resize", kmHideOverlay);

  // Gestione messaggi dal background script (es. sincronizzazione)
  chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    // SICUREZZA: accetta messaggi solo dalla nostra estensione, non da pagine web esterne
    if (sender.id !== chrome.runtime.id) {
      sendResponse({ success: false, message: "Mittente non autorizzato" });
      return;
    }

    if (!message || !message.type) {
      sendResponse({ success: false, message: "Messaggio non valido" });
      return;
    }

    if (message.type === "km_collect_credentials_from_dashboard") {
      kmCollectCredentialsFromDashboard()
        .then((credentials) => sendResponse({ success: true, credentials }))
        .catch((error) => sendResponse({
          success: false,
          message: error && error.message ? error.message : "Errore sincronizzazione"
        }));
      return true; // necessario per sendResponse asincrono
    }

    sendResponse({ success: false, message: "Tipo messaggio non gestito" });
  });

  // --- Ponte impostazioni: ascolta postMessage dalla dashboard ---
  // Permette alla dashboard di leggere/scrivere impostazioni in chrome.storage
  // tramite la pagina web in modo sicuro (stesso origin).
  window.addEventListener("message", (event) => {
    // Sicurezza: accetta solo messaggi dalla stessa origine della pagina corrente
    if (event.origin !== window.location.origin) return;
    if (!event.data || !event.data.type) return;

    if (event.data.type === "km_set_setting" && event.data.key) {
      // Salva l'impostazione in chrome.storage.local
      const obj = {};
      obj[event.data.key] = event.data.value;
      chrome.storage.local.set(obj);
    }

    if (event.data.type === "km_get_setting" && event.data.key) {
      // Restituisce il valore alla pagina tramite postMessage
      chrome.storage.local.get([event.data.key], (result) => {
        const value = result[event.data.key];
        // Default per km_autofill_enabled è true
        const resolvedValue = (value === undefined && event.data.key === "km_autofill_enabled")
          ? true
          : value;
        window.postMessage({
          type: "km_setting_value",
          key: event.data.key,
          value: resolvedValue
        }, window.location.origin);
      });
    }
  });

  // --- Avvio automatico al caricamento della pagina ---

  // Prima scansione: cerca e compila form di login presenti al caricamento
  kmScheduleAutoFill();

  // Avvia il MutationObserver per i form caricati dinamicamente (SPA, dialoghi, ecc.)
  if (document.body) {
    kmStartDomObserver();
  } else {
    // Fallback: aspetta che il DOM sia pronto
    document.addEventListener("DOMContentLoaded", kmStartDomObserver, { once: true });
  }
}

// ===========================
// ESPORTAZIONI PER I TEST
// ===========================

// Rende le funzioni disponibili per Jest (non disponibile nel browser)
if (typeof module !== "undefined") {
  module.exports = {
    kmNormalizeHost,
    kmRootDomain,
    kmHostFromCredential,
    kmCredentialMatchScore,
    kmHasAnyHint,
    kmIsEditableInput,
    kmIsVisible,
    kmCollectInputHints,
    kmIsUsernameLikeInput,
    kmFindPasswordField,
    kmFindUsernameField,
    kmDetectFormType,
    kmFindLoginForms,
    kmGetSuggestionsForPage,
    kmAutoFillLoginForms,
    kmIsDashboardPage,
    kmIsKeyManagerPage,
    kmFillFields,
    kmFillCredential,
    kmHideOverlay,
    kmCreateOverlay,
    kmHandleInputFocus,
    kmScheduleAutoFill,
    kmStartDomObserver,
    kmCollectCredentialsFromDashboard,
    kmFetchPasswordForCredential,
    kmGetCachedCredentials,
    kmShowFillNotification,
    kmIsAutofillEnabled,
    kmGetSuggestionsForPage,
    kmHandleInputFocus
  };
}
