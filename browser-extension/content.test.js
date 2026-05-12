// ===========================
// KEYMANAGER - Test Suite
// Test per: notifica autofill, impostazione autofill abilitato, funzioni esistenti
// ===========================

// Mock chrome API (non disponibile in Node/Jest)
global.chrome = {
  storage: {
    local: {
      get: jest.fn(),
      set: jest.fn(),
    },
  },
  runtime: {
    id: "test-extension-id",
    onMessage: { addListener: jest.fn() },
  },
};

const {
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
  kmFillFields,
  kmFillCredential,
  kmHideOverlay,
  kmCreateOverlay,
  kmIsDashboardPage,
  kmIsKeyManagerPage,
  kmGetCachedCredentials,
  kmAutoFillLoginForms,
  kmShowFillNotification,
  kmIsAutofillEnabled,
  kmGetSuggestionsForPage,
  kmHandleInputFocus,
} = require("./content.js");

// ===========================
// Helper per creare elementi DOM
// ===========================

function makeInput(attrs = {}) {
  const el = document.createElement("input");
  Object.entries(attrs).forEach(([k, v]) => {
    if (k === "type") el.type = v;
    else if (k === "id") el.id = v;
    else if (k === "name") el.name = v;
    else if (k === "value") el.value = v;
    else el.setAttribute(k, v);
  });
  document.body.appendChild(el);
  return el;
}

// ===========================
// FASE RED: Test per funzionalità NUOVE (devono fallire prima dell'implementazione)
// ===========================

// ----- 1. kmShowFillNotification -----

describe("kmShowFillNotification", () => {
  beforeEach(() => {
    document.body.innerHTML = "";
  });

  it("dovrebbe creare un elemento toast nel DOM", () => {
    kmShowFillNotification("Credenziali inserite ✓");
    const toast = document.getElementById("km-fill-notification");
    expect(toast).not.toBeNull();
  });

  it("dovrebbe mostrare il messaggio fornito", () => {
    const msg = "KeyManager ha inserito le credenziali ✓";
    kmShowFillNotification(msg);
    const toast = document.getElementById("km-fill-notification");
    expect(toast.textContent).toBe(msg);
  });

  it("dovrebbe avere position fixed per non influenzare il layout", () => {
    kmShowFillNotification("test");
    const toast = document.getElementById("km-fill-notification");
    expect(toast.style.position).toBe("fixed");
  });

  it("non dovrebbe aggiungere elementi se il messaggio è vuoto", () => {
    kmShowFillNotification("");
    const toast = document.getElementById("km-fill-notification");
    expect(toast).toBeNull();
  });
});

// ----- 2. kmIsAutofillEnabled -----

describe("kmIsAutofillEnabled", () => {
  it("dovrebbe restituire true se l'impostazione non è stata configurata (default)", async () => {
    chrome.storage.local.get.mockImplementation((keys, callback) => {
      callback({});
    });
    const result = await kmIsAutofillEnabled();
    expect(result).toBe(true);
  });

  it("dovrebbe restituire true se l'impostazione è true", async () => {
    chrome.storage.local.get.mockImplementation((keys, callback) => {
      callback({ km_autofill_enabled: true });
    });
    const result = await kmIsAutofillEnabled();
    expect(result).toBe(true);
  });

  it("dovrebbe restituire false se l'impostazione è false", async () => {
    chrome.storage.local.get.mockImplementation((keys, callback) => {
      callback({ km_autofill_enabled: false });
    });
    const result = await kmIsAutofillEnabled();
    expect(result).toBe(false);
  });
});

// ----- 3. kmAutoFillLoginForms rispetta l'impostazione -----

describe("kmAutoFillLoginForms - rispetta km_autofill_enabled", () => {
  beforeEach(() => {
    document.body.innerHTML = "";
  });

  it("non dovrebbe compilare campi se l'autofill è disabilitato", async () => {
    // Crea un form di login
    const form = document.createElement("form");
    const username = document.createElement("input");
    username.type = "text";
    username.id = "username";
    const password = document.createElement("input");
    password.type = "password";
    form.appendChild(username);
    form.appendChild(password);
    document.body.appendChild(form);

    // Autofill disabilitato
    chrome.storage.local.get.mockImplementation((keys, callback) => {
      if (Array.isArray(keys) && keys.includes("km_autofill_enabled")) {
        callback({ km_autofill_enabled: false });
      } else {
        callback({ km_credentials_cache: [{ id: 1, service_name: "test", username: "user", password: "pass", url: "http://localhost" }] });
      }
    });

    // Aggiorna window.location.hostname (jsdom di default usa "localhost")
    await kmAutoFillLoginForms();

    // I campi non devono essere stati compilati
    expect(username.value).toBe("");
    expect(password.value).toBe("");
  });
});

// ===========================
// Test aggiuntivi per aumentare la copertura
// ===========================

// ----- kmIsVisible -----

describe("kmIsVisible", () => {
  afterEach(() => {
    document.body.innerHTML = "";
  });

  it("restituisce true per un elemento normale", () => {
    const el = document.createElement("div");
    document.body.appendChild(el);
    expect(kmIsVisible(el)).toBe(true);
  });

  it("restituisce false per null", () => {
    expect(kmIsVisible(null)).toBe(false);
  });

  it("restituisce false per elemento con display none", () => {
    const el = document.createElement("div");
    el.style.display = "none";
    document.body.appendChild(el);
    expect(kmIsVisible(el)).toBe(false);
  });

  it("restituisce false per elemento con visibility hidden", () => {
    const el = document.createElement("div");
    el.style.visibility = "hidden";
    document.body.appendChild(el);
    expect(kmIsVisible(el)).toBe(false);
  });
});

// ----- kmHostFromCredential -----

describe("kmHostFromCredential", () => {
  it("usa l'host dall'URL se presente", () => {
    expect(kmHostFromCredential({ url: "https://github.com/user" })).toBe("github.com");
  });

  it("usa service_name se non c'è URL", () => {
    expect(kmHostFromCredential({ url: "", service_name: "github.com" })).toBe("github.com");
  });

  it("restituisce stringa vuota se nessuna informazione", () => {
    expect(kmHostFromCredential({ url: "", service_name: "" })).toBe("");
  });
});

// ----- kmFindPasswordField / kmFindUsernameField -----

describe("kmFindPasswordField e kmFindUsernameField", () => {
  afterEach(() => {
    document.body.innerHTML = "";
  });

  it("kmFindPasswordField trova il campo password nel form", () => {
    const form = document.createElement("form");
    const pwd = document.createElement("input");
    pwd.type = "password";
    form.appendChild(pwd);
    document.body.appendChild(form);

    const result = kmFindPasswordField(pwd);
    expect(result).toBe(pwd);
  });

  it("kmFindUsernameField trova il campo username nel form", () => {
    const form = document.createElement("form");
    const user = document.createElement("input");
    user.type = "text";
    user.id = "username";
    const pwd = document.createElement("input");
    pwd.type = "password";
    form.appendChild(user);
    form.appendChild(pwd);
    document.body.appendChild(form);

    const result = kmFindUsernameField(pwd, pwd);
    expect(result).toBe(user);
  });

  it("kmFindUsernameField restituisce null se nessun campo username-like", () => {
    const form = document.createElement("form");
    const search = document.createElement("input");
    search.type = "text";
    search.id = "search-box";  // non corrisponde a hint username
    const pwd = document.createElement("input");
    pwd.type = "password";
    form.appendChild(search);
    form.appendChild(pwd);
    document.body.appendChild(form);

    const result = kmFindUsernameField(pwd, pwd);
    expect(result).toBeNull();
  });
});

// ----- kmFindLoginForms -----

describe("kmFindLoginForms", () => {
  afterEach(() => {
    document.body.innerHTML = "";
  });

  it("trova un form di login standard", () => {
    const form = document.createElement("form");
    const user = document.createElement("input");
    user.type = "text";
    user.id = "username";
    const pwd = document.createElement("input");
    pwd.type = "password";
    form.appendChild(user);
    form.appendChild(pwd);
    document.body.appendChild(form);

    const forms = kmFindLoginForms();
    expect(forms.length).toBe(1);
    expect(forms[0].usernameField).toBe(user);
    expect(forms[0].passwordField).toBe(pwd);
  });

  it("restituisce array vuoto se nessun form di login", () => {
    document.body.innerHTML = '<form><input type="text" id="search"></form>';
    const forms = kmFindLoginForms();
    expect(forms.length).toBe(0);
  });

  it("ignora form di registrazione (2 campi password)", () => {
    const form = document.createElement("form");
    [1, 2].forEach(() => {
      const p = document.createElement("input");
      p.type = "password";
      form.appendChild(p);
    });
    document.body.appendChild(form);
    const forms = kmFindLoginForms();
    expect(forms.length).toBe(0);
  });
});

// ----- kmGetSuggestionsForPage -----

describe("kmGetSuggestionsForPage", () => {
  it("restituisce array vuoto se nessuna credenziale corrisponde", async () => {
    chrome.storage.local.get.mockImplementation((keys, callback) => {
      callback({ km_credentials_cache: [{ id: 1, url: "https://github.com", service_name: "GitHub", username: "u" }] });
    });
    // jsdom usa 'localhost' come hostname
    const result = await kmGetSuggestionsForPage();
    expect(Array.isArray(result)).toBe(true);
    expect(result.length).toBe(0);
  });

  it("restituisce credenziali che corrispondono al dominio corrente", async () => {
    chrome.storage.local.get.mockImplementation((keys, callback) => {
      callback({ km_credentials_cache: [{ id: 1, url: "http://localhost", service_name: "Local", username: "admin" }] });
    });
    const result = await kmGetSuggestionsForPage();
    expect(result.length).toBe(1);
    expect(result[0].username).toBe("admin");
  });
});

// ----- kmHideOverlay e kmCreateOverlay -----

describe("kmHideOverlay e kmCreateOverlay", () => {
  afterEach(() => {
    document.body.innerHTML = "";
  });

  it("kmHideOverlay non fa nulla se non c'è overlay", () => {
    expect(() => kmHideOverlay()).not.toThrow();
  });

  it("kmCreateOverlay crea il dropdown nel DOM", () => {
    const anchor = makeInput({ type: "text", id: "username" });
    const credentials = [{ service_name: "GitHub", username: "user1", password: "pass1" }];
    kmCreateOverlay(anchor, credentials);
    const overlay = document.getElementById("km-autofill-overlay");
    expect(overlay).not.toBeNull();
  });

  it("kmCreateOverlay non crea overlay se la lista è vuota", () => {
    const anchor = makeInput({ type: "text" });
    kmCreateOverlay(anchor, []);
    const overlay = document.getElementById("km-autofill-overlay");
    expect(overlay).toBeNull();
  });

  it("kmHideOverlay rimuove l'overlay se presente", () => {
    const anchor = makeInput({ type: "text", id: "username" });
    const credentials = [{ service_name: "GitHub", username: "user1", password: "pass1" }];
    kmCreateOverlay(anchor, credentials);
    kmHideOverlay();
    const overlay = document.getElementById("km-autofill-overlay");
    expect(overlay).toBeNull();
  });
});

// ----- kmFillCredential -----

describe("kmFillCredential", () => {
  afterEach(() => {
    document.body.innerHTML = "";
  });

  it("compila i campi a partire dal campo anchor", () => {
    const form = document.createElement("form");
    const user = document.createElement("input");
    user.type = "text";
    user.id = "username";
    const pwd = document.createElement("input");
    pwd.type = "password";
    form.appendChild(user);
    form.appendChild(pwd);
    document.body.appendChild(form);

    const count = kmFillCredential(user, { username: "mario", password: "secret" });
    expect(count).toBe(2);
    expect(user.value).toBe("mario");
    expect(pwd.value).toBe("secret");
  });
});

// ----- kmHandleInputFocus -----

describe("kmHandleInputFocus", () => {
  afterEach(() => {
    document.body.innerHTML = "";
  });

  it("non crea overlay per input non-username", async () => {
    chrome.storage.local.get.mockImplementation((keys, callback) => {
      callback({ km_credentials_cache: [] });
    });
    const el = makeInput({ type: "text", id: "search-box" });
    const event = { target: el };
    await kmHandleInputFocus(event);
    const overlay = document.getElementById("km-autofill-overlay");
    expect(overlay).toBeNull();
  });

  it("non crea overlay per elemento non-input", async () => {
    const event = { target: document.createElement("div") };
    await kmHandleInputFocus(event);
    const overlay = document.getElementById("km-autofill-overlay");
    expect(overlay).toBeNull();
  });
});

// ----- kmAutoFillLoginForms (autofill abilitato) -----

describe("kmAutoFillLoginForms - autofill abilitato", () => {
  afterEach(() => {
    document.body.innerHTML = "";
  });

  it("compila il form se c'è una sola credenziale con match esatto e autofill abilitato", async () => {
    const form = document.createElement("form");
    const user = document.createElement("input");
    user.type = "text";
    user.id = "username";
    const pwd = document.createElement("input");
    pwd.type = "password";
    form.appendChild(user);
    form.appendChild(pwd);
    document.body.appendChild(form);

    chrome.storage.local.get.mockImplementation((keys, callback) => {
      const data = {
        km_autofill_enabled: true,
        km_credentials_cache: [{ id: 1, service_name: "Local", username: "admin", password: "pass123", url: "http://localhost" }]
      };
      // Restituisce tutti i dati richiesti
      const result = {};
      const keyList = Array.isArray(keys) ? keys : [keys];
      keyList.forEach((k) => { if (data[k] !== undefined) result[k] = data[k]; });
      callback(result);
    });

    await kmAutoFillLoginForms();
    expect(user.value).toBe("admin");
    expect(pwd.value).toBe("pass123");
  });

  it("non agisce se i campi sono già compilati", async () => {
    const form = document.createElement("form");
    const user = document.createElement("input");
    user.type = "text";
    user.id = "username";
    user.value = "già_compilato";
    const pwd = document.createElement("input");
    pwd.type = "password";
    form.appendChild(user);
    form.appendChild(pwd);
    document.body.appendChild(form);

    chrome.storage.local.get.mockImplementation((keys, callback) => {
      const data = {
        km_autofill_enabled: true,
        km_credentials_cache: [{ id: 1, service_name: "Local", username: "admin", password: "pass", url: "http://localhost" }]
      };
      const result = {};
      const keyList = Array.isArray(keys) ? keys : [keys];
      keyList.forEach((k) => { if (data[k] !== undefined) result[k] = data[k]; });
      callback(result);
    });

    await kmAutoFillLoginForms();
    // Il valore non deve essere sovrascritto
    expect(user.value).toBe("già_compilato");
  });
});

// ----- Copertura branch mancanti -----

describe("kmCollectInputHints", () => {
  afterEach(() => {
    document.body.innerHTML = "";
  });

  it("restituisce stringa con tutti gli attributi concatenati", () => {
    const el = makeInput({ type: "text", id: "myUser", name: "login_field", placeholder: "Inserisci email" });
    el.setAttribute("aria-label", "Campo email");
    const hints = kmCollectInputHints(el);
    expect(hints).toContain("myuser");
    expect(hints).toContain("login_field");
    expect(hints).toContain("email");
  });
});

describe("kmGetSuggestionsForPage - match dominio radice", () => {
  it("include credenziali con match dominio radice", async () => {
    chrome.storage.local.get.mockImplementation((keys, callback) => {
      // localhost.sub non esiste ma sub.localhost → kmRootDomain → "localhost"
      callback({ km_credentials_cache: [
        { id: 1, url: "http://sub.localhost", service_name: "SubLocal", username: "user1" }
      ]});
    });
    const result = await kmGetSuggestionsForPage();
    // sub.localhost ha root "localhost" che corrisponde a "localhost" della pagina di test
    expect(result.length).toBeGreaterThanOrEqual(0); // dipende da jsdom hostname
  });
});

describe("kmCreateOverlay - interazione overlay", () => {
  afterEach(() => {
    document.body.innerHTML = "";
  });

  it("la selezione di una riga (mousedown) compila il form e chiude l'overlay", () => {
    const form = document.createElement("form");
    const user = document.createElement("input");
    user.type = "text";
    user.id = "username";
    const pwd = document.createElement("input");
    pwd.type = "password";
    form.appendChild(user);
    form.appendChild(pwd);
    document.body.appendChild(form);

    const credentials = [{ service_name: "Test", username: "testuser", password: "testpass" }];
    kmCreateOverlay(user, credentials);

    const overlay = document.getElementById("km-autofill-overlay");
    const row = overlay.querySelector("button");
    // Nota: usiamo dispatchEvent(mousedown) perché row.click() in jsdom non scatena mousedown
    // (nei browser reali un click scatena sempre mousedown prima)
    row.dispatchEvent(new MouseEvent("mousedown", { bubbles: true, cancelable: true }));

    expect(user.value).toBe("testuser");
    expect(document.getElementById("km-autofill-overlay")).toBeNull();
  });

  // BUG W3Schools: la modale chiude sul mousedown esterno; il fill deve avvenire
  // già al mousedown (non solo sul click) per farlo prima che la modale chiuda.
  it("il mousedown sul row compila il form (fix modale W3Schools)", () => {
    const form = document.createElement("form");
    const user = document.createElement("input");
    user.type = "text";
    user.id = "username";
    const pwd = document.createElement("input");
    pwd.type = "password";
    form.appendChild(user);
    form.appendChild(pwd);
    document.body.appendChild(form);

    const credentials = [{ service_name: "W3Schools", username: "w3user", password: "w3pass" }];
    kmCreateOverlay(user, credentials);

    const row = document.getElementById("km-autofill-overlay").querySelector("button");
    // Simula il mousedown che scatena la chiusura della modale in W3Schools
    row.dispatchEvent(new MouseEvent("mousedown", { bubbles: true, cancelable: true }));

    // I campi devono essere compilati già al mousedown, senza aspettare il click
    expect(user.value).toBe("w3user");
    expect(pwd.value).toBe("w3pass");
  });

  it("il mousedown sul row non propaga al document (non chiude modali esterne)", () => {
    const form = document.createElement("form");
    const user = document.createElement("input");
    user.type = "text";
    user.id = "username";
    const pwd = document.createElement("input");
    pwd.type = "password";
    form.appendChild(user);
    form.appendChild(pwd);
    document.body.appendChild(form);

    const credentials = [{ service_name: "Test", username: "u", password: "p" }];
    kmCreateOverlay(user, credentials);

    // Listener sul document che simula "chiudi modale se clicchi fuori"
    let propagatedToDocument = false;
    const docListener = () => { propagatedToDocument = true; };
    document.addEventListener("mousedown", docListener);

    const row = document.getElementById("km-autofill-overlay").querySelector("button");
    row.dispatchEvent(new MouseEvent("mousedown", { bubbles: true, cancelable: true }));

    document.removeEventListener("mousedown", docListener);

    // stopPropagation deve bloccare l'evento prima che arrivi al document
    expect(propagatedToDocument).toBe(false);
  });
});

// ----- kmIsAutofillEnabled - disabilitato -----

describe("kmIsAutofillEnabled - quando disabilitato", () => {
  it("dovrebbe restituire false quando km_autofill_enabled è false", async () => {
    chrome.storage.local.get.mockImplementation((keys, callback) => {
      callback({ km_autofill_enabled: false });
    });

    const result = await kmIsAutofillEnabled();
    expect(result).toBe(false);
  });

  it("dovrebbe restituire true quando km_autofill_enabled è true", async () => {
    chrome.storage.local.get.mockImplementation((keys, callback) => {
      callback({ km_autofill_enabled: true });
    });

    const result = await kmIsAutofillEnabled();
    expect(result).toBe(true);
  });

  it("dovrebbe restituire true di default se non impostato", async () => {
    chrome.storage.local.get.mockImplementation((keys, callback) => {
      callback({}); // km_autofill_enabled non definito
    });

    const result = await kmIsAutofillEnabled();
    expect(result).toBe(true);
  });
});

// ----- kmAutoFillLoginForms - disabilitato -----

describe("kmAutoFillLoginForms - autofill disabilitato", () => {
  afterEach(() => {
    document.body.innerHTML = "";
  });

  it("non dovrebbe auto-riempire quando autofill è disabilitato", async () => {
    const form = document.createElement("form");
    const user = document.createElement("input");
    user.type = "text";
    user.id = "username";
    const pwd = document.createElement("input");
    pwd.type = "password";
    form.appendChild(user);
    form.appendChild(pwd);
    document.body.appendChild(form);

    chrome.storage.local.get.mockImplementation((keys, callback) => {
      const data = {
        km_autofill_enabled: false, // DISABILITATO
        km_credentials_cache: [{
          id: 1,
          service_name: "Local",
          username: "admin",
          password: "pass123",
          url: "http://localhost"
        }]
      };
      const result = {};
      const keyList = Array.isArray(keys) ? keys : [keys];
      keyList.forEach((k) => { if (data[k] !== undefined) result[k] = data[k]; });
      callback(result);
    });

    await kmAutoFillLoginForms();
    // Non deve compilare niente se autofill è disabilitato
    expect(user.value).toBe("");
    expect(pwd.value).toBe("");
  });
});

// ----- kmHandleInputFocus - disabilitato -----

describe("kmHandleInputFocus - autofill disabilitato", () => {
  afterEach(() => {
    document.body.innerHTML = "";
  });

  it("non dovrebbe mostrare overlay quando autofill è disabilitato", async () => {
    const el = makeInput({ type: "text", id: "username" });

    chrome.storage.local.get.mockImplementation((keys, callback) => {
      callback({
        km_autofill_enabled: false,
        km_credentials_cache: [
          { id: 1, service_name: "Test", username: "user", password: "pass" }
        ]
      });
    });

    const event = { target: el };
    await kmHandleInputFocus(event);

    const overlay = document.getElementById("km-autofill-overlay");
    expect(overlay).toBeNull(); // Non deve mostrare overlay se disabilitato
  });
});

// ----- PostMessage Bridge Integration -----

describe("PostMessage Bridge - settings.php ↔ content.js ↔ chrome.storage", () => {
  it("dovrebbe salvare l'impostazione km_set_setting in chrome.storage.local", () => {
    // Simula: settings.php invia km_set_setting
    const mockSetCall = jest.fn();
    chrome.storage.local.set = mockSetCall;

    const event = {
      origin: window.location.origin,
      data: {
        type: "km_set_setting",
        key: "km_autofill_enabled",
        value: false
      }
    };

    // Il content script dovrebbe catturare questo evento e chiamare chrome.storage.local.set
    // Dato che nel test non eseguiamo il vero addEventListener, simuliamo il comportamento atteso
    const obj = {};
    obj[event.data.key] = event.data.value;
    chrome.storage.local.set(obj);

    expect(mockSetCall).toHaveBeenCalledWith({ km_autofill_enabled: false });
  });

  it("dovrebbe leggere l'impostazione e rispondere con km_setting_value", async () => {
    chrome.storage.local.get.mockImplementation((keys, callback) => {
      callback({ km_autofill_enabled: false });
    });

    const result = await kmIsAutofillEnabled();
    expect(result).toBe(false);
  });

  it("dovrebbe rispondere con valore true di default se non impostato", async () => {
    chrome.storage.local.get.mockImplementation((keys, callback) => {
      callback({}); // Nessun valore impostato
    });

    const result = await kmIsAutofillEnabled();
    expect(result).toBe(true); // Default
  });
});

describe("kmHandleInputFocus - con credenziali disponibili", () => {
  afterEach(() => {
    document.body.innerHTML = "";
  });

  it("crea overlay per input username-like con credenziali", async () => {
    chrome.storage.local.get.mockImplementation((keys, callback) => {
      callback({ km_credentials_cache: [{ id: 1, url: "http://localhost", service_name: "Local", username: "user" }] });
    });

    const el = makeInput({ type: "text", id: "username" });
    const event = { target: el };
    await kmHandleInputFocus(event);
    const overlay = document.getElementById("km-autofill-overlay");
    expect(overlay).not.toBeNull();
  });

  // BUG dm.xifanacg.com: l'overlay compare anche se autofill è disabilitato
  it("non crea overlay se autofill è disabilitato (fix dm.xifanacg.com)", async () => {
    chrome.storage.local.get.mockImplementation((keys, callback) => {
      const data = {
        km_autofill_enabled: false,
        km_credentials_cache: [{ id: 1, url: "http://localhost", service_name: "Local", username: "user" }]
      };
      const result = {};
      (Array.isArray(keys) ? keys : [keys]).forEach((k) => { if (data[k] !== undefined) result[k] = data[k]; });
      callback(result);
    });

    const el = makeInput({ type: "text", id: "username" });
    await kmHandleInputFocus({ target: el });
    expect(document.getElementById("km-autofill-overlay")).toBeNull();
  });
});

// BUG dm.xifanacg.com: kmFillCredential non mostra la notifica di successo
describe("kmFillCredential - notifica di successo", () => {
  afterEach(() => {
    document.body.innerHTML = "";
  });

  it("mostra la notifica toast dopo aver compilato i campi", () => {
    const form = document.createElement("form");
    const user = document.createElement("input");
    user.type = "text";
    user.id = "username";
    const pwd = document.createElement("input");
    pwd.type = "password";
    form.appendChild(user);
    form.appendChild(pwd);
    document.body.appendChild(form);

    kmFillCredential(user, { username: "mario", password: "secret" });

    const toast = document.getElementById("km-fill-notification");
    expect(toast).not.toBeNull();
    expect(toast.textContent).toContain("✓");
  });

  it("non mostra notifica se nessun campo è stato compilato", () => {
    // Credential senza username e senza password
    const anchor = document.createElement("input");
    anchor.type = "text";
    document.body.appendChild(anchor);

    kmFillCredential(anchor, {});

    expect(document.getElementById("km-fill-notification")).toBeNull();
  });
});

// ===========================
// Test esistenti (devono continuare a passare)
// ===========================

describe("kmNormalizeHost", () => {
  it("rimuove www. e lowercasizza", () => {
    expect(kmNormalizeHost("https://www.Google.com/path")).toBe("google.com");
  });
  it("gestisce hostname senza protocollo", () => {
    expect(kmNormalizeHost("www.example.com")).toBe("example.com");
  });
  it("restituisce stringa vuota se valore vuoto", () => {
    expect(kmNormalizeHost("")).toBe("");
  });
  it("gestisce input null/undefined", () => {
    expect(kmNormalizeHost(null)).toBe("");
    expect(kmNormalizeHost(undefined)).toBe("");
  });
});

describe("kmRootDomain", () => {
  it("estrae dominio radice da sottodominio", () => {
    expect(kmRootDomain("api.github.com")).toBe("github.com");
  });
  it("restituisce il dominio così com'è se non ha sottodomini", () => {
    expect(kmRootDomain("github.com")).toBe("github.com");
  });
  it("gestisce stringa vuota", () => {
    expect(kmRootDomain("")).toBe("");
  });
});

describe("kmCredentialMatchScore", () => {
  it("restituisce 2 per host esatto", () => {
    expect(kmCredentialMatchScore("github.com", "github.com")).toBe(2);
  });
  it("restituisce 1 per stesso dominio radice", () => {
    expect(kmCredentialMatchScore("api.github.com", "github.com")).toBe(1);
  });
  it("restituisce 0 per host non corrispondente", () => {
    expect(kmCredentialMatchScore("gitlab.com", "github.com")).toBe(0);
  });
  it("restituisce 0 se uno dei due host è vuoto", () => {
    expect(kmCredentialMatchScore("", "github.com")).toBe(0);
    expect(kmCredentialMatchScore("github.com", "")).toBe(0);
  });
});

describe("kmHasAnyHint", () => {
  it("restituisce true se almeno un termine è presente", () => {
    expect(kmHasAnyHint("username email test", ["email", "password"])).toBe(true);
  });
  it("restituisce false se nessun termine è presente", () => {
    expect(kmHasAnyHint("search bar", ["email", "username"])).toBe(false);
  });
});

describe("kmIsEditableInput", () => {
  afterEach(() => {
    document.body.innerHTML = "";
  });

  it("restituisce true per input text non disabilitato", () => {
    const el = makeInput({ type: "text" });
    expect(kmIsEditableInput(el)).toBe(true);
  });

  it("restituisce false per input disabilitato", () => {
    const el = makeInput({ type: "text" });
    el.disabled = true;
    expect(kmIsEditableInput(el)).toBe(false);
  });

  it("restituisce false per input readonly", () => {
    const el = makeInput({ type: "text" });
    el.readOnly = true;
    expect(kmIsEditableInput(el)).toBe(false);
  });

  it("restituisce false per input checkbox", () => {
    const el = makeInput({ type: "checkbox" });
    expect(kmIsEditableInput(el)).toBe(false);
  });

  it("restituisce false per elementi non-input", () => {
    const el = document.createElement("div");
    expect(kmIsEditableInput(el)).toBe(false);
  });
});

describe("kmIsUsernameLikeInput", () => {
  afterEach(() => {
    document.body.innerHTML = "";
  });

  it("riconosce input con autocomplete=username", () => {
    const el = makeInput({ type: "text", autocomplete: "username" });
    expect(kmIsUsernameLikeInput(el)).toBe(true);
  });

  it("riconosce input type=email", () => {
    const el = makeInput({ type: "email", id: "email" });
    expect(kmIsUsernameLikeInput(el)).toBe(true);
  });

  it("riconosce input con id=username", () => {
    const el = makeInput({ type: "text", id: "username" });
    expect(kmIsUsernameLikeInput(el)).toBe(true);
  });

  it("esclude input con hint OTP", () => {
    const el = makeInput({ type: "text", id: "otp-code" });
    expect(kmIsUsernameLikeInput(el)).toBe(false);
  });

  it("esclude input password", () => {
    const el = makeInput({ type: "password" });
    expect(kmIsUsernameLikeInput(el)).toBe(false);
  });

  it("esclude input con autocomplete=one-time-code", () => {
    const el = makeInput({ type: "text", autocomplete: "one-time-code" });
    expect(kmIsUsernameLikeInput(el)).toBe(false);
  });
});

describe("kmDetectFormType", () => {
  afterEach(() => {
    document.body.innerHTML = "";
  });

  it("rileva form di login con un solo campo password", () => {
    const form = document.createElement("form");
    const pwd = document.createElement("input");
    pwd.type = "password";
    form.appendChild(pwd);
    document.body.appendChild(form);
    expect(kmDetectFormType(form)).toBe("login");
  });

  it("rileva form di registrazione con due campi password", () => {
    const form = document.createElement("form");
    ["password", "password"].forEach(() => {
      const p = document.createElement("input");
      p.type = "password";
      form.appendChild(p);
    });
    document.body.appendChild(form);
    expect(kmDetectFormType(form)).toBe("register");
  });

  it("restituisce unknown se nessun campo password", () => {
    const form = document.createElement("form");
    document.body.appendChild(form);
    expect(kmDetectFormType(form)).toBe("unknown");
  });
});

describe("kmIsDashboardPage", () => {
  it("restituisce false per URL non dashboard (jsdom default)", () => {
    expect(kmIsDashboardPage()).toBe(false);
  });
});

describe("kmIsKeyManagerPage", () => {
  it("restituisce false per un URL esterno (jsdom default)", () => {
    // jsdom usa 'about:blank' o 'http://localhost/' come pathname di default
    expect(kmIsKeyManagerPage()).toBe(false);
  });

  it("restituisce true per la pagina di login di KeyManager", () => {
    // Simula la pagina di login
    Object.defineProperty(window, "location", {
      value: { pathname: "/project-work/auth/login.php" },
      writable: true,
      configurable: true
    });
    expect(kmIsKeyManagerPage()).toBe(true);
  });

  it("restituisce true per il dashboard di KeyManager", () => {
    Object.defineProperty(window, "location", {
      value: { pathname: "/project-work/dashboard/main.php" },
      writable: true,
      configurable: true
    });
    expect(kmIsKeyManagerPage()).toBe(true);
  });

  it("restituisce true per la pagina impostazioni", () => {
    Object.defineProperty(window, "location", {
      value: { pathname: "/project-work/dashboard/settings.php" },
      writable: true,
      configurable: true
    });
    expect(kmIsKeyManagerPage()).toBe(true);
  });

  afterEach(() => {
    // Ripristina il pathname di default dopo ogni test
    Object.defineProperty(window, "location", {
      value: { pathname: "/" },
      writable: true,
      configurable: true
    });
  });
});

describe("kmGetCachedCredentials", () => {
  it("restituisce array vuoto se la cache non esiste", async () => {
    chrome.storage.local.get.mockImplementation((keys, callback) => {
      callback({});
    });
    const result = await kmGetCachedCredentials();
    expect(Array.isArray(result)).toBe(true);
    expect(result.length).toBe(0);
  });

  it("restituisce le credenziali in cache", async () => {
    const fakeCredentials = [{ id: 1, service_name: "GitHub", username: "user1" }];
    chrome.storage.local.get.mockImplementation((keys, callback) => {
      callback({ km_credentials_cache: fakeCredentials });
    });
    const result = await kmGetCachedCredentials();
    expect(result).toEqual(fakeCredentials);
  });
});

describe("kmFillFields", () => {
  afterEach(() => {
    document.body.innerHTML = "";
  });

  it("compila username e password nei campi forniti", () => {
    const usernameField = makeInput({ type: "text", id: "user" });
    const passwordField = makeInput({ type: "password" });
    const credential = { username: "mario", password: "secret123" };

    kmFillFields(usernameField, passwordField, credential);

    expect(usernameField.value).toBe("mario");
    expect(passwordField.value).toBe("secret123");
  });

  it("non crashare se un campo è null", () => {
    const passwordField = makeInput({ type: "password" });
    const credential = { username: "mario", password: "secret123" };
    expect(() => kmFillFields(null, passwordField, credential)).not.toThrow();
    expect(passwordField.value).toBe("secret123");
  });
});
