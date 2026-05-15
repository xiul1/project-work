# KeyManager Extension (Versione Semplice)

Questa versione non usa pair code o token API.

## Come funziona
1. Apri la dashboard `.../dashboard/main.php` e fai login almeno una volta.
2. All'avvio del browser l'estensione prova automaticamente a sincronizzare.
3. Puoi forzare la sync dal popup con **Sincronizza ora**.
4. Vai su un sito di login.
5. Clicca su un campo username/password.
6. Appare il suggerimento autofill vicino al campo.

## Installazione (Chrome)
1. Apri `chrome://extensions`.
2. Attiva **Developer mode**.
3. Clicca **Load unpacked**.
4. Seleziona la cartella `browser-extension`.

## Note
- Le credenziali vengono salvate in locale in `chrome.storage.local` (cache estensione).
- Se cambi password/credenziali nella dashboard, la sync automatica periodica (30 min) le aggiorna; puoi anche usare **Sincronizza ora**.
