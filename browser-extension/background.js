const KM_SYNC_ALARM = "km_auto_sync";
const KM_SYNC_PERIOD_MINUTES = 30;
const KM_DASHBOARD_URL = "http://localhost/project-work/dashboard/main.php";
const KM_DASHBOARD_PATTERNS = [
  "http://localhost/project-work/dashboard/*",
  "http://127.0.0.1/project-work/dashboard/*"
];

function storageGet(keys) {
  return new Promise((resolve) => chrome.storage.local.get(keys, resolve));
}

function storageSet(payload) {
  return new Promise((resolve) => chrome.storage.local.set(payload, resolve));
}

function tabsQuery(queryInfo) {
  return new Promise((resolve) => chrome.tabs.query(queryInfo, resolve));
}

function tabsCreate(createProperties) {
  return new Promise((resolve) => chrome.tabs.create(createProperties, resolve));
}

function tabsRemove(tabId) {
  return new Promise((resolve) => chrome.tabs.remove(tabId, () => resolve()));
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

function isDashboardTabUrl(url) {
  return /\/project-work\/dashboard\/main\.php/i.test(String(url || ""));
}

function waitTabLoaded(tabId, timeoutMs = 12000) {
  return new Promise((resolve) => {
    let done = false;

    const finish = (ok) => {
      if (done) {
        return;
      }

      done = true;
      chrome.tabs.onUpdated.removeListener(onUpdated);
      clearTimeout(timer);
      resolve(ok);
    };

    const onUpdated = (updatedTabId, changeInfo) => {
      if (updatedTabId !== tabId) {
        return;
      }

      if (changeInfo.status === "complete") {
        finish(true);
      }
    };

    const timer = setTimeout(() => finish(false), timeoutMs);
    chrome.tabs.onUpdated.addListener(onUpdated);

    chrome.tabs.get(tabId, (tab) => {
      if (chrome.runtime.lastError) {
        finish(false);
        return;
      }

      if (tab && tab.status === "complete") {
        finish(true);
      }
    });
  });
}

async function collectCredentialsFromTab(tabId) {
  let response = await tabsSendMessage(tabId, {
    type: "km_collect_credentials_from_dashboard"
  });

  if (
    !response.success &&
    typeof response.message === "string" &&
    response.message.includes("Receiving end does not exist")
  ) {
    const injection = await injectContentScript(tabId);

    if (!injection.success) {
      return {
        success: false,
        message: injection.message || "Impossibile inizializzare content script"
      };
    }

    response = await tabsSendMessage(tabId, {
      type: "km_collect_credentials_from_dashboard"
    });
  }

  return response;
}

async function saveSyncResult(credentials) {
  await storageSet({
    km_credentials_cache: credentials,
    km_last_sync: new Date().toISOString(),
    km_last_sync_error: ""
  });
}

async function saveSyncError(message) {
  await storageSet({
    km_last_sync_error: String(message || "Errore sincronizzazione")
  });
}

async function runSyncInternal(allowTempTab) {
  let tab = null;
  let tempTabCreated = false;

  const dashboardTabs = await tabsQuery({ url: KM_DASHBOARD_PATTERNS });

  if (dashboardTabs.length > 0) {
    tab = dashboardTabs.find((t) => isDashboardTabUrl(t.url)) || dashboardTabs[0];
  }

  if (!tab && allowTempTab) {
    tab = await tabsCreate({
      url: KM_DASHBOARD_URL,
      active: false
    });
    tempTabCreated = true;
  }

  if (!tab || typeof tab.id !== "number") {
    return {
      success: false,
      message: "Dashboard non trovata. Apri la dashboard almeno una volta."
    };
  }

  try {
    await waitTabLoaded(tab.id);
    const response = await collectCredentialsFromTab(tab.id);

    if (!response || !response.success) {
      return {
        success: false,
        message: (response && response.message) || "Sincronizzazione fallita"
      };
    }

    const credentials = Array.isArray(response.credentials) ? response.credentials : [];
    await saveSyncResult(credentials);

    return {
      success: true,
      count: credentials.length
    };
  } finally {
    if (tempTabCreated) {
      await tabsRemove(tab.id);
    }
  }
}

async function runAutoSync(reason, allowTempTab = true) {
  try {
    const result = await runSyncInternal(allowTempTab);

    if (!result.success) {
      await saveSyncError(result.message);
    }
  } catch (error) {
    const message = error && error.message ? error.message : "Errore auto-sync";
    await saveSyncError(message);
  }
}

function ensureAlarm() {
  chrome.alarms.create(KM_SYNC_ALARM, { periodInMinutes: KM_SYNC_PERIOD_MINUTES });
}

chrome.runtime.onInstalled.addListener(() => {
  ensureAlarm();
  runAutoSync("install", true);
});

chrome.runtime.onStartup.addListener(() => {
  ensureAlarm();
  runAutoSync("startup", true);
});

chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === KM_SYNC_ALARM) {
    runAutoSync("alarm", true);
  }
});

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (!message || !message.type) {
    sendResponse({ success: false, message: "Messaggio non valido" });
    return;
  }

  if (message.type === "km_sync_now") {
    runSyncInternal(true)
      .then(async (result) => {
        if (!result.success) {
          await saveSyncError(result.message);
          sendResponse(result);
          return;
        }

        sendResponse(result);
      })
      .catch(async (error) => {
        const msg = error && error.message ? error.message : "Errore sincronizzazione";
        await saveSyncError(msg);
        sendResponse({ success: false, message: msg });
      });
    return true;
  }

  if (message.type === "km_get_sync_meta") {
    storageGet(["km_last_sync", "km_last_sync_error"])
      .then((data) => {
        sendResponse({
          success: true,
          lastSync: data.km_last_sync || "",
          lastError: data.km_last_sync_error || ""
        });
      })
      .catch(() => {
        sendResponse({
          success: false,
          message: "Impossibile leggere metadati sync"
        });
      });
    return true;
  }

  sendResponse({ success: false, message: "Tipo messaggio non gestito" });
});
