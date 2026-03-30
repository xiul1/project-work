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
