<?php
/**
 * Gestione semplice delle traduzioni (EN/IT).
 *
 * La lingua corrente è memorizzata in $_SESSION['language'].
 * Il dizionario è inline qui sotto; per chiavi mancanti si torna alla
 * chiave originale (così non si crashano pagine se manca una stringa).
 */

const SUPPORTED_LANGUAGES = ["en", "it"];
const DEFAULT_LANGUAGE = "en";

const TRANSLATIONS = [
    "en" => [
        // Settings - navigation
        "settings.title"        => "Settings",
        "settings.general"      => "General",
        "settings.account"      => "Account",
        "settings.security"     => "Security",
        "settings.activity"     => "Activity Log",
        "settings.billing"      => "Billing",
        "settings.notifications"=> "Notifications",
        "settings.integrations" => "Integrations",
        "settings.about"        => "About",
        "settings.dashboard"    => "Dashboard",
        "settings.sign_out"     => "Sign Out",
        "settings.search"       => "Search settings...",

        // General tab
        "general.heading"        => "General",
        "general.subheading"     => "Configure how KeyManager looks and behaves",
        "general.appearance"     => "Appearance",
        "general.theme_light"    => "Light",
        "general.theme_dark"     => "Dark",
        "general.theme_system"   => "System",
        "general.preferences"    => "Preferences",
        "general.language"       => "Language",
        "general.language_desc"  => "Choose your interface language",
        "general.default_vault"  => "Default Vault",
        "general.default_vault_desc"  => "Used when saving new credentials",
        "general.auto_lock"      => "Auto-lock timer",
        "general.auto_lock_desc" => "Lock vault after inactivity",
        "general.minutes_5"      => "5 min",
        "general.minutes_30"     => "30 min",
        "general.hour_1"         => "1 hour",
        "general.autofill"       => "Browser Autofill",
        "general.autofill_desc"  => "Fill logins automatically on supported sites",
        "general.clipboard_clear"      => "Clipboard auto-clear",
        "general.clipboard_clear_desc" => "Clear copied passwords after 30 seconds",
        "general.analytics"      => "Anonymous analytics",
        "general.analytics_desc" => "Help improve KeyManager with usage data",

        // Dashboard
        "dash.add_credential"      => "Add Credential",
        "dash.add_credential_desc" => "Store new login",
        "dash.generate_password"   => "Generate Password",
        "dash.generate_password_desc" => "Create strong keys",
        "dash.security_audit"      => "Security Audit",
        "dash.security_audit_desc" => "Check vault health",
        "dash.recent_credentials"  => "Recent Credentials",
        "dash.select"              => "Select",
        "dash.cancel"              => "Cancel",
        "dash.delete_selected"     => "Delete Selected",
        "dash.vault_summary"       => "Vault Summary",
        "dash.total_credentials"   => "Total Credentials",
        "dash.weak_passwords"      => "Weak Passwords",
        "dash.last_sync"           => "Last Sync",
        "dash.just_now"            => "Just now",
        "dash.categories"          => "Categories",
        "dash.favorites"           => "Favorites",
        "dash.new_credential"      => "New Credential",
        "dash.service"             => "Service",
        "dash.username"            => "Username",
        "dash.password"            => "Password",
        "dash.url"                 => "URL",
        "dash.notes"               => "Notes",
        "dash.save"                => "Save",
        "dash.save_changes"        => "Save Changes",
        "dash.close"               => "Close",
        "dash.edit"                => "Edit",
        "dash.delete"              => "Delete",
        "dash.generate"            => "Generate",
        "dash.keep_blank"          => "(leave blank to keep)",
        "dash.credential"          => "Credential",
        "dash.weak_warning"        => "Warning: weak password",
        "dash.weak_warning_body"   => "The password you entered is weak. Save it anyway?",
        "dash.save_anyway"         => "Save anyway",
        "dash.security_alert"      => "Security alert: %d failed login attempts in the last 24 hours.",

        // Auth pages
        "auth.welcome_back"        => "Welcome back",
        "auth.signin_subtitle"     => "Sign in to access your vault",
        "auth.email"               => "Email address",
        "auth.master_password"     => "Master password",
        "auth.forgot_password"     => "Forgot password?",
        "auth.sign_in"             => "Sign In",
        "auth.no_account"          => "Don't have an account?",
        "auth.sign_up"             => "Sign up",
        "auth.create_account"      => "Create Account",
        "auth.signup_subtitle"     => "Start protecting your passwords today",
        "auth.full_name"           => "Full name",
        "auth.confirm_password"    => "Confirm password",
        "auth.terms_agree"         => "I agree to the Terms of Service and Privacy Policy",
        "auth.have_account"        => "Already have an account?",
        "auth.reset_password"      => "Reset Password",
        "auth.reset_subtitle"      => "Enter your email and we'll send you a reset link",
        "auth.send_reset"          => "Send Reset Link",
        "auth.back_to_signin"      => "← Back to Sign In",
        "auth.panel_signin"        => "Your vault.|Always secure.",
        "auth.panel_signup"        => "Start your|secure journey.",
        "auth.panel_forgot"        => "Forgot?|No worries.",
        // Error messages
        "auth.err_invalid_email"   => "Invalid email.",
        "auth.err_password_req"    => "Password required.",
        "auth.err_user_not_found"  => "User not found.",
        "auth.err_email_unverified"=> "Email not verified.",
        "auth.err_wrong_password"  => "Wrong password.",
        "auth.err_username_req"    => "Username required.",
        "auth.err_password_short"  => "Password must be at least 8 characters.",
        "auth.err_email_taken"     => "Email already registered.",
        "auth.msg_registered_ok"   => "Registration complete! Check your email.",
        "auth.msg_registered_no_mail" => "Registration complete, but email could not be sent.",
        "auth.msg_check_email"     => "Check your email for the reset link.",
        "auth.msg_mail_error"      => "Error sending email.",
        "auth.msg_session_expired" => "Session expired due to inactivity. Please sign in again.",
        "auth.msg_email_verified"  => "Email verified successfully! You can sign in.",
        "auth.msg_link_expired"    => "The verification link has expired.",
        "auth.msg_link_invalid"    => "Invalid verification link.",

        // Common messages
        "msg.preference_saved"   => "Preference saved",
        "msg.preference_error"   => "Could not save preference",
    ],
    "it" => [
        // Settings - navigation
        "settings.title"        => "Impostazioni",
        "settings.general"      => "Generale",
        "settings.account"      => "Account",
        "settings.security"     => "Sicurezza",
        "settings.activity"     => "Registro attività",
        "settings.billing"      => "Fatturazione",
        "settings.notifications"=> "Notifiche",
        "settings.integrations" => "Integrazioni",
        "settings.about"        => "Info",
        "settings.dashboard"    => "Dashboard",
        "settings.sign_out"     => "Esci",
        "settings.search"       => "Cerca impostazioni...",

        // General tab
        "general.heading"        => "Generale",
        "general.subheading"     => "Configura come KeyManager appare e funziona",
        "general.appearance"     => "Aspetto",
        "general.theme_light"    => "Chiaro",
        "general.theme_dark"     => "Scuro",
        "general.theme_system"   => "Sistema",
        "general.preferences"    => "Preferenze",
        "general.language"       => "Lingua",
        "general.language_desc"  => "Scegli la lingua dell'interfaccia",
        "general.default_vault"  => "Vault predefinito",
        "general.default_vault_desc"  => "Usato per salvare nuove credenziali",
        "general.auto_lock"      => "Timer blocco automatico",
        "general.auto_lock_desc" => "Blocca il vault dopo inattività",
        "general.minutes_5"      => "5 min",
        "general.minutes_30"     => "30 min",
        "general.hour_1"         => "1 ora",
        "general.autofill"       => "Compilazione automatica",
        "general.autofill_desc"  => "Compila automaticamente i login sui siti supportati",
        "general.clipboard_clear"      => "Pulizia automatica appunti",
        "general.clipboard_clear_desc" => "Cancella le password copiate dopo 30 secondi",
        "general.analytics"      => "Analisi anonime",
        "general.analytics_desc" => "Aiuta a migliorare KeyManager con dati di utilizzo",

        // Dashboard
        "dash.add_credential"      => "Aggiungi credenziale",
        "dash.add_credential_desc" => "Salva un nuovo login",
        "dash.generate_password"   => "Genera password",
        "dash.generate_password_desc" => "Crea chiavi sicure",
        "dash.security_audit"      => "Audit sicurezza",
        "dash.security_audit_desc" => "Controlla salute vault",
        "dash.recent_credentials"  => "Credenziali recenti",
        "dash.select"              => "Seleziona",
        "dash.cancel"              => "Annulla",
        "dash.delete_selected"     => "Elimina selezionate",
        "dash.vault_summary"       => "Riepilogo vault",
        "dash.total_credentials"   => "Credenziali totali",
        "dash.weak_passwords"      => "Password deboli",
        "dash.last_sync"           => "Ultima sincronizzazione",
        "dash.just_now"            => "Adesso",
        "dash.categories"          => "Categorie",
        "dash.favorites"           => "Preferiti",
        "dash.new_credential"      => "Nuova credenziale",
        "dash.service"             => "Servizio",
        "dash.username"            => "Username",
        "dash.password"            => "Password",
        "dash.url"                 => "URL",
        "dash.notes"               => "Note",
        "dash.save"                => "Salva",
        "dash.save_changes"        => "Salva modifiche",
        "dash.close"               => "Chiudi",
        "dash.edit"                => "Modifica",
        "dash.delete"              => "Elimina",
        "dash.generate"            => "Genera",
        "dash.keep_blank"          => "(lasciare vuoto per mantenere)",
        "dash.credential"          => "Credenziale",
        "dash.weak_warning"        => "Attenzione: password debole",
        "dash.weak_warning_body"   => "La password inserita è debole. Salvare comunque?",
        "dash.save_anyway"         => "Salva comunque",
        "dash.security_alert"      => "Avviso di sicurezza: %d tentativi di login falliti nelle ultime 24 ore.",

        // Auth pages
        "auth.welcome_back"        => "Ben tornato",
        "auth.signin_subtitle"     => "Accedi al tuo vault",
        "auth.email"               => "Indirizzo email",
        "auth.master_password"     => "Master password",
        "auth.forgot_password"     => "Password dimenticata?",
        "auth.sign_in"             => "Accedi",
        "auth.no_account"          => "Non hai un account?",
        "auth.sign_up"             => "Registrati",
        "auth.create_account"      => "Crea account",
        "auth.signup_subtitle"     => "Inizia a proteggere le tue password oggi",
        "auth.full_name"           => "Nome completo",
        "auth.confirm_password"    => "Conferma password",
        "auth.terms_agree"         => "Accetto i Termini di servizio e la Privacy Policy",
        "auth.have_account"        => "Hai già un account?",
        "auth.reset_password"      => "Reimposta password",
        "auth.reset_subtitle"      => "Inserisci la tua email e ti invieremo un link di reset",
        "auth.send_reset"          => "Invia link di reset",
        "auth.back_to_signin"      => "← Torna al login",
        "auth.panel_signin"        => "Il tuo vault.|Sempre sicuro.",
        "auth.panel_signup"        => "Inizia il tuo|viaggio sicuro.",
        "auth.panel_forgot"        => "Dimenticata?|Nessun problema.",
        // Error messages
        "auth.err_invalid_email"   => "Email non valida.",
        "auth.err_password_req"    => "Password obbligatoria.",
        "auth.err_user_not_found"  => "Utente non trovato.",
        "auth.err_email_unverified"=> "Email non verificata.",
        "auth.err_wrong_password"  => "Password errata.",
        "auth.err_username_req"    => "Username obbligatorio.",
        "auth.err_password_short"  => "La password deve avere almeno 8 caratteri.",
        "auth.err_email_taken"     => "Email già registrata.",
        "auth.msg_registered_ok"   => "Registrazione completata! Controlla la tua email.",
        "auth.msg_registered_no_mail" => "Registrazione completata, ma email non inviata.",
        "auth.msg_check_email"     => "Controlla la tua email per il link di reset.",
        "auth.msg_mail_error"      => "Errore nell'invio dell'email.",
        "auth.msg_session_expired" => "Sessione scaduta per inattività. Effettua nuovamente il login.",
        "auth.msg_email_verified"  => "Email verificata con successo! Puoi accedere.",
        "auth.msg_link_expired"    => "Il link di verifica è scaduto.",
        "auth.msg_link_invalid"    => "Link di verifica non valido.",

        // Common messages
        "msg.preference_saved"   => "Preferenza salvata",
        "msg.preference_error"   => "Impossibile salvare la preferenza",
    ],
];

/**
 * Restituisce la lingua corrente, validandola contro SUPPORTED_LANGUAGES.
 */
function currentLanguage() {
    $lang = $_SESSION["language"] ?? DEFAULT_LANGUAGE;
    if (!in_array($lang, SUPPORTED_LANGUAGES, true)) {
        $lang = DEFAULT_LANGUAGE;
    }
    return $lang;
}

/**
 * Imposta la lingua corrente nella sessione.
 * Restituisce true se la lingua è supportata.
 */
function setCurrentLanguage($lang) {
    if (!in_array($lang, SUPPORTED_LANGUAGES, true)) {
        return false;
    }
    $_SESSION["language"] = $lang;
    return true;
}

/**
 * Traduce una chiave nella lingua corrente.
 * Se la chiave manca, ritorna la chiave stessa (utile per debug).
 */
function __($key) {
    $lang = currentLanguage();
    $dict = TRANSLATIONS[$lang] ?? TRANSLATIONS[DEFAULT_LANGUAGE];
    return $dict[$key] ?? $key;
}
