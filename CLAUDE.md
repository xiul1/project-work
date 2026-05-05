# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

KeyManager is a secure credential management system for a student project-work (5th year high school). It consists of:

1. **PHP Backend** — User authentication, credential storage/retrieval, encryption, activity logging
2. **Browser Extension** — Auto-fills username/password fields on web forms (Node.js + JavaScript)
3. **MySQL Database** — Stores encrypted credentials and user sessions

The project emphasizes clarity, simplicity, and proper security practices (prepared statements, CSRF protection, session timeouts).

## Quick Start

### Database Setup

The project expects a MySQL database named `KeyManager`. Configuration is environment-based:

```bash
# Local development (XAMPP default)
# No setup required — uses localhost/root with empty password
# Set APP_ENV=local or leave unset to auto-detect localhost

# Production/custom setup
# Set environment variables:
export DB_HOST=your_host
export DB_NAME=your_database
export DB_USER=your_user
export DB_PASS=your_password
export APP_ENV=production
```

The database initialization script should create the schema if not present (check `requirement/pdo.php` for current config).

### Running Locally

1. **Start XAMPP** — MySQL and Apache must be running
2. **Access** — `http://localhost/project-work/auth/login.php` to start
3. **Database** — Check XAMPP phpMyAdmin at `http://localhost/phpmyadmin`

### Browser Extension Development

```bash
cd browser-extension

# Install dependencies
npm install

# Run tests with coverage
npm test

# Expected coverage: 80%+ (configured in package.json)
```

## Architecture

### Backend (PHP)

**Entry Points:**
- `auth/login.php` — User login and forgot-password flow
- `auth/register.php` — User registration with email verification
- `auth/logout.php` — Session cleanup and redirect
- `dashboard/main.php` — Credential list and management
- `dashboard/activity_log.php` — User activity audit log
- `dashboard/settings.php` — User profile and preferences

**Core Modules** (`requirement/`):
- `pdo.php` — Database connection (environment-aware config)
- `security.php` — Session management, CSRF tokens, timeout enforcement (30-min inactivity)
- `crypto.php` — Encryption/decryption for credential storage
- `helpers.php` — Utility functions (validation, response formatting)
- `logger.php` — Activity logging for auditing
- `mail_config.php` — Email configuration (password reset, verification)

**Key Patterns:**
- Prepared statements for all database queries (PDO with `ERRMODE_EXCEPTION`)
- DTO-like validation in security.php before request processing
- Master password + individual credential encryption (two-layer security)
- Session regeneration on login to prevent fixation

### Browser Extension (JavaScript + Node.js)

**Files:**
- `manifest.json` — Extension metadata and permissions
- `background.js` — Service worker for credential requests
- `content.js` — Injects autofill UI into page forms
- `popup.js` — Extension popup for user interactions
- `shared.js` — Shared utilities (API calls, data handling)
- `content.test.js` — Jest tests for content.js

**Key Feature:**
- Detects username-like input fields (email, username, text inputs)
- Requests credentials from service worker (secure channel)
- Auto-fills only username fields to keep workflow intuitive

## Development Workflow

### Adding a Credential Feature

1. **Backend** — Add endpoint in `dashboard/credential/` (e.g., `add_credential.php`)
   - Validate input (sanitize, check lengths)
   - Encrypt sensitive data using `crypto.php`
   - Log the action via `logger.php`
   - Return JSON response

2. **Frontend** — Update dashboard or settings page
   - Make AJAX call with CSRF token (`getCsrfToken()`)
   - Handle errors gracefully

3. **Extension** — Update background.js if credential request API changes
   - Keep shared.js in sync with backend API format

### Testing

**Browser Extension Tests:**
```bash
npm test                    # Run all tests
npm test -- --coverage      # Show coverage report
npm test -- --watch        # Watch mode during development
```

Tests must achieve 80%+ line coverage (enforced in Jest config).

**PHP Backend:**
- Currently manual testing via browser
- Recommend adding PHPUnit tests for security-critical paths (auth, encryption)

## Important Implementation Details

### Security Notes

- **Master Password** — User sets during registration; derives encryption key for all stored credentials
- **Session Timeout** — 30 minutes of inactivity auto-logs user out
- **CSRF Protection** — All state-changing requests require token from `getCsrfToken()`
- **Password Reset** — Token-based email link (check token not expired)
- **Prepared Statements** — Never concatenate SQL; always use `PDO::prepare()` + `bindValue()`

### Code Style

- **PHP** — PSR-12 style (see global rules in `~/.claude/rules/php/`)
- **JavaScript** — Clear variable names, comments on non-obvious logic
- **Database Fields** — snake_case in schema; use aliases in queries
- **File Structure** — Small focused files (200–400 lines typical)

### Known Limitations

- Email verification currently requires SMTP setup (see `mail_config.php`)
- Browser extension only works on HTTP/HTTPS pages
- Extension requests are synchronous (blocking UI briefly on slow networks)

## Common Commands

While there's no build system, these are useful development patterns:

```bash
# Check PHP syntax
php -l auth/login.php

# Database backup (via XAMPP)
mysqldump -h localhost -u root KeyManager > backup.sql

# Local server restart (XAMPP)
# Use XAMPP Control Panel or: sudo /Applications/XAMPP/xamppfiles/bin/apachectl restart

# Browser extension linting (if ESLint added)
npm run lint  # (not yet configured)
```

## Editor Setup

**VS Code recommended:**
- Install PHP IntelliPhp extension
- Install Jest extension for `content.test.js` debugging
- Add `.vscode/launch.json` configuration if debugging backend (see project settings)

## Guidance for Claude Code

- **Always use prepared statements** for database operations; never build SQL with string concatenation
- **Verify CSRF tokens** before processing state-changing requests
- **Test with browser extension enabled** when making changes to credential flow
- **Keep files under 800 lines** — extract utility functions to `requirement/` if modules grow
- **Log significant actions** (login, password change, credential operations) via `logger.php`
- **Validate at boundaries** — always sanitize user input before storing or processing
