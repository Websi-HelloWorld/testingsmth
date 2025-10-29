<?php
session_start();
require_once __DIR__ . '/Authenticator.php';
require_once __DIR__ . '/theme.php';
// Google Authenticator Helper
$ga = new PHPGangsta_GoogleAuthenticator();

// Frühzeitige Weiterleitung: Wenn bereits eingeloggt, vor jeglicher Ausgabe umleiten
if (isset($_SESSION['user_id'])) {
    header('Location: Uebersicht.php');
    exit();
}
/*
============================================================
 Zwei-Faktor-Registrierung & Login (TOTP / Google Authenticator)
 Ablauf Registrierung:
     1. Nutzer füllt Basisdaten aus -> Validierung -> Secret wird erzeugt und in $_SESSION['pending_registration'] gespeichert.
     2. Nutzer scannt QR-Code, gibt 6-stelligen Code ein -> Verifikation -> Benutzer wird angelegt (CreateUserAsUser) -> Secret via SetUserTwoFASecret gespeichert.
 Login:
     - Passwort wird geprüft. Falls TwoFASecret vorhanden -> 6-stelliger Code Pflicht. Liefert differenzierte Rückgabewerte.
 Sicherheitshinweise:
     - Secret niemals im Klartext an Dritte geben.
     - Session-Pending-Eintrag verfällt nach 10 Minuten.
     - consider: Rate-Limiting für Login & 2FA Code (noch nicht implementiert).
 Migration bestehender User:
     - Für existierende User ohne Secret entweder Forced-Setup beim nächsten Login oder Option im Profil anbieten.
============================================================
*/

// 1. Prüfen, ob Logout gedrückt
if(isset($_POST['logout'])) {
    logout();
    // Nach dem Logout sofort neu laden, keine Ausgabe vorher -> verhindert Header-Warnungen
    header("Location: index.php");
    exit();
}

// 2. Login / 2FA Handling (nur einmal zentral vor jeglicher HTML-Ausgabe)
$loginErrorMessage = '';
// Password-Reset State/Messages
$resetErrorMessage = '';
$resetSuccessMessage = '';
$resetStep = 0; // 0=aus, 1=Prüfen Username/E-Mail/2FA, 2=neues Passwort setzen
if(isset($_POST['login'])) {
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    $loginResult = login($username, $password);
    if($loginResult === true) {
        header("Location: Uebersicht.php");
        exit();
    } elseif($loginResult === '2fa_required') {
        $loginErrorMessage = 'Bitte 2FA Code eingeben.';
    } elseif($loginResult === '2fa_invalid') {
        $loginErrorMessage = '2FA Code ungültig.';
    } else {
        $loginErrorMessage = 'Login fehlgeschlagen!';
    }
}

// Passwort zurücksetzen – Schritt 1: Zugang prüfen (Username, E-Mail, 2FA-Code)
if (isset($_POST['verify_reset'])) {
    $u = trim(isset($_POST['reset_username']) ? $_POST['reset_username'] : '');
    $e = trim(isset($_POST['reset_email']) ? $_POST['reset_email'] : '');
    $c = trim(isset($_POST['reset_totp']) ? $_POST['reset_totp'] : '');
    if ($u === '' || $e === '' || !filter_var($e, FILTER_VALIDATE_EMAIL) || !preg_match('/^[0-9]{6}$/', $c)) {
        $resetErrorMessage = 'Bitte gültigen Benutzernamen, E-Mail und 6-stelligen Code eingeben.';
        $resetStep = 1;
    } else {
        $pdo = connectPlayer();
        try {
            $stmt = $pdo->prepare('CALL LoginUser(:username)');
            $stmt->bindParam(':username', $u, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            if (!$user) {
                $resetErrorMessage = 'Ungültiger Benutzername oder E-Mail.';
                $resetStep = 1;
            } else {
                // E-Mail gegen Daten in DB prüfen
                $stmtE = $pdo->prepare('CALL GetUserDataById(:uid)');
                $stmtE->bindParam(':uid', $user['PK_UserID'], PDO::PARAM_INT);
                $stmtE->execute();
                $ud = $stmtE->fetch(PDO::FETCH_ASSOC);
                $stmtE->closeCursor();
                if (!$ud || strcasecmp($ud['Email'], $e) !== 0) {
                    $resetErrorMessage = 'Ungültiger Benutzername oder E-Mail.';
                    $resetStep = 1;
                } else {
                    // 2FA prüfen
                    $stmt2 = $pdo->prepare('CALL GetUserTwoFASecret(:uid)');
                    $stmt2->bindParam(':uid', $user['PK_UserID'], PDO::PARAM_INT);
                    $stmt2->execute();
                    $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
                    $stmt2->closeCursor();
                    $twoFASecret = isset($row2['TwoFASecret']) ? $row2['TwoFASecret'] : '';
                    if ($twoFASecret === '') {
                        $resetErrorMessage = '2FA ist für diesen Account nicht eingerichtet.';
                        $resetStep = 1;
                    } else {
                        $gaLocal = new PHPGangsta_GoogleAuthenticator();
                        if (!$gaLocal->verifyCode($twoFASecret, $c, 1)) {
                            $resetErrorMessage = '2FA Code ungültig.';
                            $resetStep = 1;
                        } else {
                            $_SESSION['pending_pw_reset'] = [
                                'user_id' => (int)$user['PK_UserID'],
                                'username' => $u,
                                'verified_at' => time(),
                            ];
                            $resetStep = 2;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $resetErrorMessage = 'Fehler: ' . $e->getMessage();
            $resetStep = 1;
        }
        disconnectPlayer($pdo);
    }
}

// Passwort zurücksetzen – Schritt 2: Neues Passwort setzen (nach erfolgreicher Verifikation)
if (isset($_POST['do_reset'])) {
    $pending = isset($_SESSION['pending_pw_reset']) ? $_SESSION['pending_pw_reset'] : null;
    if (!$pending || $pending['verified_at'] < time() - 600) { // 10 Minuten gültig
        unset($_SESSION['pending_pw_reset']);
        $resetErrorMessage = 'Reset-Session abgelaufen. Bitte erneut verifizieren.';
        $resetStep = 1;
    } else {
    $pw1 = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $pw2 = isset($_POST['new_password2']) ? $_POST['new_password2'] : '';
        if ($pw1 !== $pw2) {
            $resetErrorMessage = 'Passwörter stimmen nicht überein.';
            $resetStep = 2;
        } elseif (strlen($pw1) < 8 || !preg_match('/[A-Z]/', $pw1) || !preg_match('/[a-z]/', $pw1) || !preg_match('/[0-9]/', $pw1)) {
            $resetErrorMessage = 'Passwort zu schwach: Mind. 8 Zeichen mit Groß-/Kleinbuchstaben und Zahl.';
            $resetStep = 2;
        } else {
            $hash = password_hash($pw1, PASSWORD_BCRYPT);
            $pdo = connectPlayer();
            try {
                $stmt = $pdo->prepare('CALL ResetUserPasswordById(:uid, :hpw)');
                $stmt->bindValue(':uid', $pending['user_id'], PDO::PARAM_INT);
                $stmt->bindValue(':hpw', $hash, PDO::PARAM_STR);
                $stmt->execute();
                $stmt->closeCursor();
                unset($_SESSION['pending_pw_reset']);
                $resetSuccessMessage = 'Passwort erfolgreich aktualisiert. Du kannst dich jetzt einloggen.';
                $resetStep = 0;
            } catch (Exception $e) {
                $resetErrorMessage = 'Fehler beim Zurücksetzen: ' . $e->getMessage();
                $resetStep = 2;
            }
            disconnectPlayer($pdo);
        }
    }
}

// Registrierung Schritt 1 (Basisdaten sammeln & prüfen)
if(isset($_POST['register'])) {
    $regUser = trim($_POST['reg_username']);
    $regEmail = trim($_POST['reg_email']);
    $regPass = $_POST['reg_password'];
    $regPass2 = $_POST['reg_password2'];
    $registerError = '';
    // DSGVO-Checkbox prüfen
    if(empty($_POST['dsgvo'])) {
        $registerError = 'Du musst der Datenschutzerklärung zustimmen!';
    } elseif($regPass !== $regPass2) {
        $registerError = 'Passwörter stimmen nicht überein!';
    } elseif(strlen($regUser) < 3) {
        $registerError = 'Benutzername zu kurz!';
    } elseif(strlen($regPass) < 8) {
        $registerError = 'Das Passwort muss mindestens 8 Zeichen lang sein!';
    } elseif(!preg_match('/[A-Z]/', $regPass) || !preg_match('/[a-z]/', $regPass) || !preg_match('/[0-9]/', $regPass)) {
        $registerError = 'Das Passwort muss Groß- und Kleinbuchstaben sowie eine Zahl enthalten!';
    } elseif(!filter_var($regEmail, FILTER_VALIDATE_EMAIL)) {
        $registerError = 'Ungültige E-Mail-Adresse!';
    } else {
        $pdo = connectPlayer();
        try {
            // Prüfen ob Name oder E-Mail schon existiert (Prozedur)
            $stmt = $pdo->prepare("CALL CheckUserExists(:uname, :email)");
            $stmt->bindParam(':uname', $regUser, PDO::PARAM_STR);
            $stmt->bindParam(':email', $regEmail, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            if($row['UsernameExists'] > 0) {
                $registerError = 'Benutzername bereits vergeben!';
            } elseif($row['EmailExists'] > 0) {
                $registerError = 'Account mit dieser E-Mail existiert bereits!';
            } else {
                // Schritt 1 erfolgreich -> Secret für TOTP erzeugen & in Session zwischenspeichern
                $secret = $ga->createSecret();
                $_SESSION['pending_registration'] = [
                    'username' => $regUser,
                    'email' => $regEmail,
                    'password_hash' => password_hash($regPass, PASSWORD_BCRYPT),
                    'secret' => $secret,
                    'created_at' => time()
                ];
                $registrationStep = 2; // Zum zweiten Schritt (TOTP Bestätigung) wechseln
            }
        } catch (PDOException $e) {
            $registerError = 'Fehler: ' . $e->getMessage();
        }
        disconnectPlayer($pdo);
    }
}

// Registrierung Schritt 2 (TOTP Code prüfen & User wirklich anlegen)
if(isset($_POST['verify_2fa'])) {
    $registerError = '';
    if(empty($_SESSION['pending_registration'])) {
        $registerError = 'Session für Registrierung abgelaufen. Bitte erneut registrieren.';
    } else {
        $pending = $_SESSION['pending_registration'];
        // Timeout 10 Minuten
        if($pending['created_at'] < time() - 600) {
            $registerError = 'Registrierung abgelaufen. Bitte erneut versuchen.';
            unset($_SESSION['pending_registration']);
        } else {
            $code = trim(isset($_POST['totp_code']) ? $_POST['totp_code'] : '');
            if(!preg_match('/^[0-9]{6}$/', $code)) {
                $registerError = 'Ungültiger Codeformat (6 Ziffern).';
            } else {
                $secret = $pending['secret'];
                if(!$ga->verifyCode($secret, $code, 1)) { // 1 Zeitscheibe Toleranz (~30s vor/zurück)
                    $registerError = 'TOTP Code ungültig.';
                } else {
                    // Jetzt User wirklich anlegen
                    $pdo = connectPlayer();
                    try {
                        $stmt = $pdo->prepare("CALL CreateUserAsUser(:uname, :email, :pw)");
                        $stmt->bindParam(':uname', $pending['username'], PDO::PARAM_STR);
                        $stmt->bindParam(':email', $pending['email'], PDO::PARAM_STR);
                        $stmt->bindParam(':pw', $pending['password_hash'], PDO::PARAM_STR);
                        $stmt->execute();
                        $stmt->closeCursor();

                        // Benutzer-ID ermitteln (Username ist eindeutig)
                        // Benutzer-ID via Prozedur holen
                        $stmt2 = $pdo->prepare('CALL GetUserIdByUsername(:uname)');
                        $stmt2->bindParam(':uname', $pending['username'], PDO::PARAM_STR);
                        $stmt2->execute();
                        $userRow = $stmt2->fetch(PDO::FETCH_ASSOC);
                        $stmt2->closeCursor();
                        if(!$userRow) {
                            throw new Exception('User konnte nach Insert nicht gefunden werden.');
                        }
                        $userId = (int)$userRow['PK_UserID'];

                        // Secret speichern per Prozedur
                        $stmt3 = $pdo->prepare('CALL SetUserTwoFASecret(:uid, :secret)');
                        $stmt3->bindParam(':uid', $userId, PDO::PARAM_INT);
                        $stmt3->bindParam(':secret', $secret, PDO::PARAM_STR);
                        $stmt3->execute();
                        $stmt3->closeCursor();

                        $registerSuccess = true;
                        $regUser = $pending['username'];
                        $regEmail = $pending['email'];
                        unset($_SESSION['pending_registration']);
                        // Hinweis: Countdown-Redirect wird clientseitig per JS gesteuert
                    } catch (Exception $e) {
                        $registerError = 'Fehler beim Speichern: ' . $e->getMessage();
                    }
                    disconnectPlayer($pdo);
                }
            }
        }
    }
    if(isset($registerError) && !$registerError && isset($registerSuccess) && $registerSuccess) {
        // Erfolgreich -> zurück zu normalem Register-Formular mit Erfolgsmeldung
        $registrationStep = 0;
    } else {
        $registrationStep = 2; // bei Fehler im zweiten Schritt bleiben
    }
}

// Registrierung abbrechen
if(isset($_POST['cancel_2fa'])) {
    unset($_SESSION['pending_registration']);
    $registrationStep = 0;
}
?>


<!DOCTYPE html>
<html lang="de">
<head>
<?php echo render_theme_head('Spiel-Portal'); ?>
</head>
<body class="bg-gray-50 dark:bg-gray-900 font-sans text-gray-800 dark:text-gray-100 transition-colors duration-300">
<?php // Auf dieser Seite wieder die feste (fixed) Variante oben rechts anzeigen ?>
<?php echo render_theme_toggle(false); ?>

<div class="min-h-[calc(100vh-200px)] flex flex-col items-center justify-center p-4">
    <?php if(!isset($_SESSION['user_id'])) { 
        // Entscheidet ob Register-Box sichtbar ist
    $showRegister = (isset($registerError) && $registerError)
            || isset($_POST['register'])
            || isset($_POST['verify_2fa'])
            || !empty($_SESSION['pending_registration'])
            || (isset($registerSuccess) && $registerSuccess)
            ? 'true' : 'false';
    $registrationStep = isset($registrationStep) ? $registrationStep : ( !empty($_SESSION['pending_registration']) ? 2 : 0 );
    ?>
    <div class="flex flex-col md:flex-row gap-8 w-full max-w-3xl justify-center items-center mx-auto">
        <!-- Login-Formular -->
    <div id="loginBox" class="bg-white dark:bg-gray-800 p-8 rounded-2xl shadow-lg w-full max-w-md flex-1 transition-colors<?php if($showRegister==='true') echo ' hidden'; ?>">
            <h2 class="text-2xl font-bold mb-6 text-center">Login</h2>
            <?php if(!empty($loginErrorMessage)): ?>
                <p class="text-red-500 text-center mt-4"><?php echo htmlspecialchars($loginErrorMessage); ?></p>
            <?php endif; ?>
            <?php if(!empty($resetSuccessMessage)): ?>
                <p class="text-green-600 text-center mt-4"><?php echo htmlspecialchars($resetSuccessMessage); ?></p>
            <?php endif; ?>
            <form method="POST" class="space-y-4" autocomplete="off">
                <input type="text" name="username" placeholder="Benutzername" required class="w-full p-3 border rounded-lg bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 text-gray-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-400" value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">
                <input type="password" name="password" placeholder="Passwort" required class="w-full p-3 border rounded-lg bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 text-gray-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-400" autocomplete="current-password">
                <input type="text" name="totp_code" placeholder="2FA Code (6 Ziffern)" pattern="[0-9]{6}" maxlength="6" class="w-full p-3 border rounded-lg bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 text-gray-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-400" inputmode="numeric" autocomplete="one-time-code">
                <button type="submit" name="login" class="btn btn-primary w-full justify-center">Login</button>
            </form>
            <button id="openReset" type="button" class="btn btn-outline w-full justify-center mt-3">Passwort zurücksetzen</button>
            <button id="showRegister" type="button" class="btn btn-primary w-full justify-center mt-6">Registrieren</button>
        </div>
        <!-- Registrierungs-Formular -->
    <div id="registerBox" class="bg-white dark:bg-gray-800 p-8 rounded-2xl shadow-lg w-full max-w-md flex-1 transition-colors<?php if($showRegister==='true') echo ''; else echo ' hidden'; ?>">
            <h2 class="text-2xl font-bold mb-4 text-center">Registrieren</h2>
            <?php if(isset($registerError) && $registerError): ?>
                <p class="text-red-500 text-center mb-4"><?php echo $registerError; ?></p>
            <?php elseif(isset($registerSuccess) && $registerSuccess): ?>
                <p class="text-green-600 text-center mb-4">Registrierung erfolgreich! Du kannst dich jetzt einloggen. Weiterleitung in <span id="regCountdown">2</span>&nbsp;Sekunden …</p>
            <?php endif; ?>

            <?php if(!$registrationStep): // Schritt 1 ?>
            <form method="POST" class="space-y-4" autocomplete="off">
                <input type="text" name="reg_username" placeholder="Benutzername" required class="w-full p-3 border rounded-lg bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-green-500 dark:focus:ring-green-400 text-gray-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-400" value="<?php echo isset($regUser) ? htmlspecialchars($regUser) : ''; ?>">
                <input type="email" name="reg_email" placeholder="E-Mail" required class="w-full p-3 border rounded-lg bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-green-500 dark:focus:ring-green-400 text-gray-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-400" value="<?php echo isset($regEmail) ? htmlspecialchars($regEmail) : ''; ?>">
                <input type="password" name="reg_password" placeholder="Passwort (min. 8 Zeichen, Groß-/Kleinbuchstaben, Zahl)" required class="w-full p-3 border rounded-lg bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-green-500 dark:focus:ring-green-400 text-gray-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-400" autocomplete="new-password">
                <input type="password" name="reg_password2" placeholder="Passwort wiederholen" required class="w-full p-3 border rounded-lg bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-green-500 dark:focus:ring-green-400 text-gray-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-400" autocomplete="new-password">
                <div class="flex items-center">
                    <input type="checkbox" name="dsgvo" id="dsgvo" required class="mr-2">
                    <label for="dsgvo" class="text-sm">Ich akzeptiere die <a href="datenschutz.html" target="_blank" class="underline text-blue-600">Datenschutzerklärung (DSGVO)</a> und die <a href="nutzungsbedingungen.html" target="_blank" class="underline text-blue-600">Nutzungsbedingungen</a>.</label>
                </div>
                <button type="submit" name="register" class="btn btn-primary w-full justify-center">Weiter &raquo;</button>
                <button id="hideRegister" type="button" class="btn btn-outline w-full justify-center">Abbrechen</button>
            </form>
            <div class="text-xs text-gray-500 mt-2">
                Mit der Registrierung akzeptierst du die Speicherung deiner Daten gemäß DSGVO. Deine Daten werden ausschließlich zur Nutzung dieses Portals verwendet und nicht an Dritte weitergegeben.
            </div>
            <?php elseif($registrationStep === 2 && !empty($_SESSION['pending_registration'])): // Schritt 2 ?>
                <?php $pending = $_SESSION['pending_registration'];
                    $issuer = 'SpielPortal';
                    $account = $pending['username'];
                    $secret = $pending['secret'];
                    // Build a properly encoded otpauth URI to support special characters like # in the username
                    $labelEncoded = rawurlencode($issuer) . ':' . rawurlencode($account);
                    $otpauth = 'otpauth://totp/' . $labelEncoded . '?secret=' . urlencode($secret) . '&issuer=' . rawurlencode($issuer);
                    // Primary provider: QuickChart, Fallback: qrserver
                    $qrUrl = 'https://quickchart.io/qr?size=220&text=' . rawurlencode($otpauth);
                    $qrUrlFallback = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode($otpauth);
                ?>
                <div class="space-y-4">
                    <p class="text-sm text-gray-600">Schritt 2 von 2: Richte jetzt deine Zwei-Faktor-Authentifizierung ein. Scanne den QR-Code mit der Google Authenticator (oder kompatiblen) App und gib anschließend den 6-stelligen Code ein.</p>
                    <div class="flex flex-col items-center gap-2">
                        <img src="<?php echo htmlspecialchars($qrUrl); ?>" alt="QR Code" class="border rounded shadow bg-white dark:bg-gray-700 p-2" onerror="this.onerror=null; this.src='<?php echo htmlspecialchars($qrUrlFallback); ?>';">
                        <code class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded break-all text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($pending['secret']); ?></code>
                                                <a href="<?php echo htmlspecialchars($otpauth); ?>" class="text-blue-600 underline text-xs break-all" title="Als otpauth-Link öffnen (für kompatible Apps)">
                                                    <?php echo htmlspecialchars($otpauth); ?>
                                                </a>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Falls der Scan nicht klappt, Secret manuell eingeben (Zeitbasierte Tokens / TOTP).</p>
                    </div>
                    <form method="POST" class="space-y-4" autocomplete="off">
                        <input type="text" name="totp_code" placeholder="6-stelliger Code" pattern="[0-9]{6}" maxlength="6" required class="w-full p-3 border rounded-lg bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-green-500 dark:focus:ring-green-400 text-gray-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-400" autocomplete="one-time-code" inputmode="numeric">
                        <div class="flex gap-2">
                            <button type="submit" name="verify_2fa" class="btn btn-primary flex-1 justify-center">Bestätigen</button>
                            <button type="submit" name="cancel_2fa" formnovalidate class="btn btn-outline flex-1 justify-center">Abbrechen</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <!-- Reset Password Modal -->
        <div id="resetModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center p-4 z-50" style="display:none;">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6 relative">
                <button type="button" data-close-reset class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">✕</button>
                <h3 class="text-xl font-bold mb-4 text-center">Passwort zurücksetzen</h3>
                <?php if ($resetStep === 2): ?>
                    <?php if(!empty($resetErrorMessage)): ?>
                        <p class="text-red-500 text-center mb-3"><?php echo htmlspecialchars($resetErrorMessage); ?></p>
                    <?php endif; ?>
                    <form method="POST" class="space-y-4">
                        <input type="password" name="new_password" placeholder="Neues Passwort" required class="w-full p-3 border rounded-lg bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-100" autocomplete="new-password">
                        <input type="password" name="new_password2" placeholder="Neues Passwort bestätigen" required class="w-full p-3 border rounded-lg bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-100" autocomplete="new-password">
                        <div class="flex gap-2">
                            <button type="submit" name="do_reset" class="btn btn-primary flex-1 justify-center">Speichern</button>
                            <button type="button" data-close-reset class="btn btn-outline flex-1 justify-center">Abbrechen</button>
                        </div>
                    </form>
                <?php else: ?>
                    <?php if(!empty($resetErrorMessage)): ?>
                        <p class="text-red-500 text-center mb-3"><?php echo htmlspecialchars($resetErrorMessage); ?></p>
                    <?php endif; ?>
                    <form method="POST" class="space-y-4" autocomplete="off">
                        <input type="text" name="reset_username" placeholder="Benutzername" required class="w-full p-3 border rounded-lg bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-100">
                        <input type="email" name="reset_email" placeholder="E-Mail" required class="w-full p-3 border rounded-lg bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-100">
                        <input type="text" name="reset_totp" placeholder="2FA Code (6 Ziffern)" pattern="[0-9]{6}" maxlength="6" class="w-full p-3 border rounded-lg bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-100" inputmode="numeric" autocomplete="one-time-code">
                        <div class="flex gap-2">
                            <button type="submit" name="verify_reset" class="btn btn-primary flex-1 justify-center">Prüfen</button>
                            <button type="button" data-close-reset class="btn btn-outline flex-1 justify-center">Abbrechen</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <script>
        const showBtn = document.getElementById('showRegister');
        const regBox = document.getElementById('registerBox');
        const loginBox = document.getElementById('loginBox');
        const hideBtn = document.getElementById('hideRegister');
        const showRegister = <?php echo $showRegister; ?>;
        const openResetBtn = document.getElementById('openReset');
        const resetModal = document.getElementById('resetModal');
        const closeResetBtns = document.querySelectorAll('[data-close-reset]');
        
        if(showRegister && regBox && loginBox) {
            regBox.classList.remove('hidden');
            loginBox.classList.add('hidden');
        }
        if(showBtn && regBox && loginBox) {
            showBtn.onclick = () => { regBox.classList.remove('hidden'); loginBox.classList.add('hidden'); };
        }
        if(hideBtn && regBox && loginBox) {
            hideBtn.onclick = () => { regBox.classList.add('hidden'); loginBox.classList.remove('hidden'); };
        }
        if (openResetBtn && resetModal) {
            openResetBtn.onclick = () => { resetModal.style.display = 'flex'; };
        }
        if (closeResetBtns) {
            closeResetBtns.forEach(btn => btn.addEventListener('click', () => { resetModal.style.display = 'none'; }));
        }
        // Nach erfolgreicher Registrierung: Countdown anzeigen und zurück zum Login wechseln
        const regSuccess = <?php echo (isset($registerSuccess) && $registerSuccess) ? 'true' : 'false'; ?>;
        if (regSuccess && regBox && loginBox) {
            let remaining = 2;
            const c = document.getElementById('regCountdown');
            if (c) c.textContent = String(remaining);
            const t = setInterval(() => {
                remaining -= 1;
                if (c) c.textContent = String(Math.max(remaining, 0));
                if (remaining <= 0) {
                    clearInterval(t);
                    regBox.classList.add('hidden');
                    loginBox.classList.remove('hidden');
                }
            }, 1000);
        }
        // Falls Server Schritt 1 oder 2 verlangt, Modal automatisch öffnen
        <?php if (!empty($resetErrorMessage) || $resetStep === 1 || $resetStep === 2): ?>
            if (resetModal) resetModal.style.display = 'flex';
        <?php endif; ?>
                </script>
    </div>
    <?php } ?>
</div>

</body>
</html>

<?php

function login($username, $password) {
    $pdo = connectPlayer(); // Verbindung nur hier öffnen

    try {
        $stmt = $pdo->prepare("CALL LoginUser(:username)");
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if(!$user || !password_verify($password, $user['Password'])) {
            disconnectPlayer($pdo);
            return false; // Passwort falsch oder User existiert nicht
        }

        // TwoFA Secret laden (falls nicht schon im LoginUser enthalten)
    $twoFASecret = isset($user['TwoFASecret']) ? $user['TwoFASecret'] : null;
        if($twoFASecret === null) {
            $stmt2 = $pdo->prepare('CALL GetUserTwoFASecret(:uid)');
            $stmt2->bindParam(':uid', $user['PK_UserID'], PDO::PARAM_INT);
            $stmt2->execute();
            $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
            $stmt2->closeCursor();
            $twoFASecret = isset($row2['TwoFASecret']) ? $row2['TwoFASecret'] : '';
        }

        // Wenn Secret gesetzt -> Code aus POST prüfen
        if(!empty($twoFASecret)) {
            $code = trim(isset($_POST['totp_code']) ? $_POST['totp_code'] : '');
            if(!preg_match('/^[0-9]{6}$/', $code)) {
                disconnectPlayer($pdo);
                return '2fa_required'; // Signal an Aufrufer: Code fehlt/ungültig
            }
            $gaLocal = new PHPGangsta_GoogleAuthenticator();
            if(!$gaLocal->verifyCode($twoFASecret, $code, 1)) {
                disconnectPlayer($pdo);
                return '2fa_invalid';
            }
        }

        // Login finalisieren
        $_SESSION['user_id'] = $user['PK_UserID'];
        $_SESSION['username'] = $username;
        $_SESSION['admin'] = $user['Admin'];
        disconnectPlayer($pdo); // Verbindung schließen
        return true;
    } catch (PDOException $e) {
        disconnectPlayer($pdo);
        error_log("Login-Fehler: " . $e->getMessage());
        return false;
    }
}

function logout() {
    // Session starten, falls noch nicht gestartet
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Session-Array leeren
    $_SESSION = [];

    // Session-Cookie löschen
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), 
            '', 
            time() - 42000, 
            $params["path"], 
            $params["domain"], 
            $params["secure"], 
            $params["httponly"]
        );
    }

    // Session zerstören
    session_destroy();
}

function connectPlayer() {
    $config = require 'config.php';
    try {
        $pdo = new PDO(
            "mysql:host={$config['host']};dbname={$config['db']};charset=utf8",
            $config['player_user'],     // aus config: game_app
            $config['player_pass']      // aus config: Passwort für Player
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Player DB-Verbindung fehlgeschlagen: " . $e->getMessage());
    }
}

/**
 * Player abmelden (PDO schließen)
 */
function disconnectPlayer(&$pdo) {
    $pdo = null; // PDO-Objekt zerstören, Verbindung schließen
}

?>


