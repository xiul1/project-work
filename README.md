# KeyManager

> Sistema sicuro di gestione credenziali sviluppato come **Progetto di 5ª superiore**.

KeyManager è una soluzione completa per la gestione delle credenziali utente che combina un backend PHP, un'estensione browser per l'autofill e un client desktop Python. Le credenziali vengono cifrate end-to-end con doppio livello di sicurezza (master password + cifratura per-credenziale) e gestite tramite una dashboard web intuitiva, multilingua e con tema chiaro/scuro.

---

## Indice

- [Funzionalità](#funzionalità)
- [Architettura](#architettura)
- [Tech Stack](#tech-stack)
- [Requisiti](#requisiti)
- [Setup locale](#setup-locale)
- [Installazione browser extension](#installazione-browser-extension)
- [Test](#test)
- [Struttura del progetto](#struttura-del-progetto)
- [Sicurezza](#sicurezza)
- [Autore](#autore)

---

## Funzionalità

- **Autenticazione sicura** — Registrazione con verifica email, login con master password, reset password tramite token.
- **Storage credenziali cifrato** — Doppio livello di cifratura (master + per-credenziale).
- **Dashboard web** — Lista credenziali, ricerca, multi-selezione, eliminazione bulk.
- **Import / Export** — Importa ed esporta credenziali in formato JSON.
- **Audit log** — Tutte le azioni significative vengono registrate (login, modifiche, eliminazioni).
- **Browser Extension** — Autofill automatico dei campi username/password sui siti web (Chrome, Manifest V3).
- **Client Desktop** — GUI Python (Tkinter) per accesso alternativo.
- **Multilingua** — Sistema i18n integrato.
- **Tema chiaro/scuro** — Preferenza utente salvata.
- **Auto-lock configurabile** — Timeout di inattività personalizzabile (5 / 30 / 60 min).
- **Protezione CSRF** — Token CSRF su tutte le richieste state-changing.

---

## Architettura

```
┌──────────────────────────────────────────────────────────┐
│                   KeyManager Backend (PHP)               │
│  ┌────────────┐  ┌────────────┐  ┌────────────────────┐  │
│  │   Auth     │  │  Dashboard │  │   Credential CRUD  │  │
│  │ (login,    │  │  (main,    │  │  (add, update,     │  │
│  │  register, │  │  settings, │  │   delete, import,  │  │
│  │  reset)    │  │  activity) │  │   export)          │  │
│  └────────────┘  └────────────┘  └────────────────────┘  │
│         │              │                  │              │
│  ┌──────┴──────────────┴──────────────────┴──────────┐   │
│  │  Core: PDO · Crypto · Security · Logger · i18n    │   │
│  └────────────────────────┬──────────────────────────┘   │
└───────────────────────────┼──────────────────────────────┘
                            │
                ┌───────────┴──────────┐
                │   MySQL (KeyManager) │
                └──────────────────────┘
                            ▲
                            │ HTTP / JSON
        ┌───────────────────┼───────────────────┐
        │                                       │
┌───────┴────────┐                    ┌─────────┴─────────┐
│  Browser Ext.  │                    │   Desktop Client  │
│  (Chrome MV3)  │                    │  (Python Tkinter) │
│  Autofill UI   │                    │   Login GUI       │
└────────────────┘                    └───────────────────┘
```

---

## Tech Stack

| Componente | Tecnologie |
|------------|------------|
| **Backend** | PHP 8.x · PDO (MySQL) · PHPMailer |
| **Database** | MySQL / MariaDB |
| **Frontend** | HTML5 · CSS3 · JavaScript vanilla |
| **Browser Extension** | JavaScript · Manifest V3 · Jest (test) |
| **Desktop** | Python 3 · Tkinter |
| **Email** | SMTP via PHPMailer |
| **Server di sviluppo** | XAMPP (Apache + MySQL) |

---

## Requisiti

- **XAMPP** (o stack equivalente: Apache + PHP 8.x + MySQL)
- **Node.js** 18+ e **npm** (per test dell'estensione)
- **Python 3.8+** (per il client desktop, opzionale)
- **Google Chrome** o browser Chromium-based (per l'estensione)

---

## Setup locale

### 1. Clona la repository

```bash
git clone https://github.com/xiul1/project-work.git
cd project-work
```

Posiziona il progetto nella cartella `htdocs` di XAMPP (es. `/Applications/XAMPP/xamppfiles/htdocs/project-work` su macOS).

### 2. Configura il database

Avvia MySQL da XAMPP Control Panel, poi crea il database `KeyManager` tramite phpMyAdmin (`http://localhost/phpmyadmin`).

La configurazione DB è **environment-aware**: in locale viene rilevato automaticamente (`localhost` / `root` / password vuota). Per ambienti custom:

```bash
export DB_HOST=your_host
export DB_NAME=your_database
export DB_USER=your_user
export DB_PASS=your_password
export APP_ENV=production
```

### 3. Configura SMTP (opzionale)

Per abilitare verifica email e reset password, copia e personalizza `requirement/mail_config.php` con le tue credenziali SMTP. **Il file è escluso dal versionamento** (vedi `.gitignore`).

### 4. Avvia l'applicazione

Apri il browser su:

```
http://localhost/project-work/auth/login.php
```

---

## Installazione browser extension

1. Apri `chrome://extensions` in Chrome.
2. Attiva **Modalità sviluppatore** (in alto a destra).
3. Clicca **Carica estensione non pacchettizzata**.
4. Seleziona la cartella `browser-extension/`.
5. Effettua il login almeno una volta sulla dashboard web — l'estensione sincronizza automaticamente le credenziali.
6. Su qualunque sito di login, clicca su un campo username/password: apparirà il suggerimento di autofill.

Per forzare la sincronizzazione, clicca sull'icona dell'estensione e premi **Sincronizza ora**.

---

## Test

### Browser Extension (Jest)

```bash
cd browser-extension
npm install
npm test
```

Coverage richiesta: **80%+** (configurata in `package.json`).

I test coprono:
- `content.js` — Logica di iniezione autofill
- `import-export.js` — Import/export credenziali
- `multi-select.js` — Selezione multipla e bulk delete
- `preferences-client.js` — Preferenze utente
- `theme.js` — Toggle tema chiaro/scuro
- `autofill-toggle.js` — Abilitazione/disabilitazione autofill
- `clipboard.js` — Copia sicura negli appunti

### Backend PHP

Attualmente il backend è testato manualmente via browser. Per validazione sintassi:

```bash
php -l auth/login.php
```

---

## Struttura del progetto

```
project-work/
├── auth/                       # Endpoint di autenticazione
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   ├── reset_password.php
│   └── session_status.php
├── dashboard/                  # Dashboard utente
│   ├── main.php                # Lista credenziali
│   ├── settings.php            # Preferenze utente
│   ├── activity_log.php        # Audit log
│   ├── credential/             # CRUD credenziali
│   │   ├── add_credential.php
│   │   ├── update_credential.php
│   │   ├── delete_credential.php
│   │   ├── bulk_delete_credentials.php
│   │   ├── get_password.php
│   │   ├── export_credentials.php
│   │   ├── import_credentials.php
│   │   └── import_credentials_json.php
│   └── settings/
│       ├── change_password.php
│       └── update_preference.php
├── requirement/                # Moduli core
│   ├── pdo.php                 # Connessione DB
│   ├── security.php            # Sessione, CSRF, timeout
│   ├── crypto.php              # Cifratura/decifratura
│   ├── helpers.php             # Utility
│   ├── logger.php              # Audit log
│   ├── i18n.php                # Internazionalizzazione
│   ├── preferences.php         # Preferenze utente
│   └── mail_config.php         # Config SMTP (gitignored)
├── assets/                     # Frontend
│   ├── css/style.css
│   └── js/
│       ├── theme.js
│       ├── clipboard.js
│       ├── import-export.js
│       ├── multi-select.js
│       ├── preferences-client.js
│       └── autofill-toggle.js
├── browser-extension/          # Estensione Chrome
│   ├── manifest.json
│   ├── background.js           # Service worker
│   ├── content.js              # Content script (autofill UI)
│   ├── popup.html / popup.js   # Popup estensione
│   ├── shared.js               # Utility condivise
│   └── *.test.js               # Test Jest
├── desktop/
│   └── login_gui.py            # Client desktop (Tkinter)
├── lib/
│   └── PHPMailer/              # Libreria email
├── index/                      # Pagine di ingresso
└── CLAUDE.md                   # Guida per Claude Code
```

---

## Sicurezza

KeyManager implementa diverse misure di sicurezza:

- **Prepared Statements** — Tutte le query DB usano PDO con `ERRMODE_EXCEPTION` ed emulazione disabilitata, prevenendo SQL injection.
- **CSRF Token** — Generato con `random_bytes(32)`, verificato con `hash_equals()` su tutte le richieste state-changing.
- **Session Hardening** — Regenerazione ID di sessione dopo il login per prevenire session fixation.
- **Auto-Logout** — Timeout di inattività configurabile (default 30 minuti).
- **Cifratura a Due Livelli** — Master password derivata in chiave + cifratura per-credenziale.
- **Password Hashing** — Hash sicuro tramite `password_hash()` / `password_verify()`.
- **Reset Token** — Token monouso con scadenza per il recupero password.
- **Audit Logging** — Ogni azione critica (login, modifica, eliminazione) viene registrata.
- **Validazione Input** — Sanitizzazione di tutti gli input utente prima del processamento.

> Per dettagli sulle best practice di sviluppo, vedi `CLAUDE.md`.

---

## Autore

Progetto realizzato come **project-work** per l'esame di Stato (5ª superiore).

- GitHub: [@xiul1](https://github.com/xiul1)
- Repository: [project-work](https://github.com/xiul1/project-work)

---

## Licenza

Progetto scolastico — uso didattico.
