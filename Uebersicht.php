<?php
session_start();
// Logout unbedingt vor jeglicher Ausgabe behandeln, sonst gibt es Header-Warnungen
if(isset($_POST['logout'])) {
    logout();
    header("Location: index.php");
    exit();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
require_once __DIR__ . '/theme.php';
?>

<!DOCTYPE html>
<html lang="de">
<head>
<?php echo render_theme_head('Profil - Spiel-Portal'); ?>
<script>
// Auto-Refresh für Online-Geräte (alle 30 Sekunden)
setInterval(function() {
    fetch(window.location.href)
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const newDoc = parser.parseFromString(html, 'text/html');
            const newOnlineSection = newDoc.querySelector('#online-devices-section');
            const currentOnlineSection = document.querySelector('#online-devices-section');
            if (newOnlineSection && currentOnlineSection) {
                currentOnlineSection.innerHTML = newOnlineSection.innerHTML;
            }
        })
        .catch(err => console.log('Refresh failed:', err));
}, 30000);
</script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 font-sans text-gray-800 dark:text-gray-100">
<!-- Toggle jetzt im Header integriert -->

<!-- Header -->
<header class="glass shadow p-4 flex justify-between items-center gap-4 transition-colors">
    <h1 class="text-xl font-extrabold section-title">Mein Profil</h1>
    <div class="flex gap-2 items-center">
        <?php
    // Profilbild über Prozedur holen
    $pdo = connectPlayer();
    // Profilbild über Prozedur nach ID holen
    $stmt = $pdo->prepare("CALL GetUserDataById(:uid)");
    $stmt->bindParam(':uid', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $pic = $row ? $row['ProfilePic'] : null;
    $stmt->closeCursor();
    disconnectPlayer($pdo);
        ?>
        <?php if (!empty($pic)): ?>
            <img src="data:image/jpeg;base64,<?= base64_encode($pic) ?>" alt="Profilbild" style="width:48px;height:48px;border-radius:50%;object-fit:cover;">
        <?php else: ?>
            <span class="inline-block" style="width:48px;height:48px;background:#ccc;border-radius:50%;display:inline-block;"></span>
        <?php endif; ?>
                <a href="profil_bearbeiten.php" class="btn btn-primary text-sm hover:brightness-110">
                        <!-- pencil icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="w-5 h-5">
                            <path d="M17.414 2.586a2 2 0 00-2.828 0L6.5 10.672V13.5h2.828l8.086-8.086a2 2 0 000-2.828z"/>
                            <path fill-rule="evenodd" d="M5.5 12.5l-1 3 3-1 9-9-2-2-9 9z" clip-rule="evenodd"/>
                        </svg>
                        <span>Profil bearbeiten</span>
                </a>
                <form method="POST" style="display:inline;">
                        <button type="submit" name="logout" class="btn btn-danger text-sm hover:brightness-110">
                            <!-- logout icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="w-5 h-5">
                                <path fill-rule="evenodd" d="M3 4.5A1.5 1.5 0 014.5 3h6A1.5 1.5 0 0112 4.5v1a.5.5 0 01-1 0v-1a.5.5 0 00-.5-.5h-6a.5.5 0 00-.5.5v11a.5.5 0 00.5.5h6a.5.5 0 00.5-.5v-1a.5.5 0 011 0v1A1.5 1.5 0 0110.5 17h-6A1.5 1.5 0 013 15.5v-11z" clip-rule="evenodd" />
                                <path d="M12.293 6.293a1 1 0 011.414 0L16.414 9.0a1 1 0 010 1.414l-2.707 2.707a1 1 0 11-1.414-1.414L13.586 10l-1.293-1.293a1 1 0 010-1.414z" />
                            </svg>
                            <span>Logout</span>
                        </button>
                </form>
        <?php echo render_theme_toggle(true); ?>
    </div>
</header>

<main class="p-6 max-w-5xl mx-auto fade-up">
    <h2 class="text-2xl font-bold mb-6">Hallo, <?= $_SESSION['username'] ?></h2>

    <!-- Online ESP32 Geräte (nur Admin) -->
    <?php if (isset($_SESSION['admin']) && $_SESSION['admin'] == 1): ?>
    <section class="mb-8" id="online-devices-section">
        <div class="card p-4 rounded-xl hover-raise transition-colors">
            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                <span class="inline-block w-3 h-3 bg-green-500 rounded-full animate-pulse"></span>
                Online ESP32 Geräte
                <span class="text-sm font-normal text-gray-500">(Auto-Refresh 30s)</span>
            </h3>
            <?php
            // Online-Status aus DB lesen (letzte 2 Minuten)
            try {
                $pdoAdmin = connectAdmin();
                $stmt = $pdoAdmin->prepare(
                    "SELECT username, device_id, ip_address, last_seen\n                     FROM esp32_heartbeat\n                     WHERE last_seen >= (NOW() - INTERVAL 2 MINUTE)\n                     ORDER BY last_seen DESC"
                );
                $stmt->execute();
                $activeUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmt->closeCursor();
                disconnectAdmin($pdoAdmin);

                // Zeit formatieren für Anzeige
                foreach ($activeUsers as &$u) {
                    $u['last_seen_formatted'] = isset($u['last_seen']) ? date('Y-m-d H:i:s', strtotime($u['last_seen'])) : '';
                    if (!isset($u['device_id'])) { $u['device_id'] = ''; }
                }
                unset($u);
            } catch (Exception $e) {
                if (isset($pdoAdmin)) { disconnectAdmin($pdoAdmin); }
                $activeUsers = [];
            }
            ?>
            
            <?php if (count($activeUsers) > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($activeUsers as $user): ?>
                        <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="font-medium text-green-600 dark:text-green-400 mb-1">
                                User: <?= htmlspecialchars($user['username']) ?>
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                MAC: <?= htmlspecialchars((string)$user['device_id']) ?>
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                IP: <?= htmlspecialchars($user['ip_address']) ?>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                 <?= htmlspecialchars($user['last_seen_formatted']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                    Insgesamt <?= count($activeUsers) ?> Gerät(e) online
                </div>
            <?php else: ?>
                <div class="text-gray-500 dark:text-gray-400 text-center py-4">
                    <span class="text-4xl mb-2 block">😴</span>
                    Keine ESP32-Geräte online
                    <div class="text-sm mt-2">Geräte werden hier angezeigt, wenn sie in den letzten 2 Minuten aktiv waren.</div>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Top 3 Highscores je Spiel -->
    <section class="mb-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php
            $games = ['Snake', 'Dodge', 'PingPong', 'SimonSays'];
            foreach($games as $game):
                $rows = [];
                try {
                    $pdo = connectPlayer();
                    // Alle Spiele nutzen jetzt die gleiche, parametrisierte Prozedur-Signatur
                    $proc = "Show".$game."Leaderboard";
                    $stmt = $pdo->prepare("CALL $proc(:usernameFilter, :sortField, :sortDir)");
                    $empty = '';
                    $sortField = 'Score';
                    $sortDir = 'DESC';
                    $stmt->bindParam(':usernameFilter', $empty, PDO::PARAM_STR);
                    $stmt->bindParam(':sortField', $sortField, PDO::PARAM_STR);
                    $stmt->bindParam(':sortDir', $sortDir, PDO::PARAM_STR);
                    $stmt->execute();
                    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $stmt->closeCursor();
                    disconnectPlayer($pdo);
                    // Nur Top 3
                    $rows = array_slice($all, 0, 3);
                    // Eigener bester Score + Platzierung (erste Fundstelle in sortierter Liste)
                    $myBest = null; $myRank = null;
                    $me = isset($_SESSION['username']) ? $_SESSION['username'] : '';
                    if ($all && $me !== '') {
                        foreach ($all as $idx => $item) {
                            if (isset($item['UserName']) && $item['UserName'] === $me) {
                                $myBest = $item;
                                $myRank = $idx + 1; // 1-basiert
                                break;
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Prozedur evtl. nicht vorhanden (z. B. SimonSays) – leer anzeigen
                    if(isset($pdo)) { disconnectPlayer($pdo); }
                    $rows = [];
                    $myBest = null; $myRank = null;
                }
            ?>
            <div class="card p-4 rounded-xl hover-raise transition-colors">
                <h4 class="font-bold text-lg mb-3"><?= htmlspecialchars($game) ?> – Top 3</h4>
                <?php if($rows): ?>
                <ol class="list-decimal ml-5 space-y-1">
                    <?php foreach($rows as $r): ?>
                        <li class="p-2 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 rounded transition-colors">
                            <span class="font-medium"><?= htmlspecialchars(isset($r['UserName']) ? $r['UserName'] : 'Unbekannt') ?></span>
                            – <span><?= htmlspecialchars($r['Score']) ?></span>
                            <?php if(!empty($r['TimeStamp'])): ?>
                                <span class="text-xs text-gray-500">(<?= htmlspecialchars($r['TimeStamp']) ?>)</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <?php else: ?>
                <p>Keine Daten verfügbar.</p>
                <?php endif; ?>

                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 text-sm">
                    <?php if ($myBest): ?>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200 font-semibold"><?php echo (int)$myRank; ?></span>
                            <span>Dein bester Score: <strong><?php echo htmlspecialchars($myBest['Score']); ?></strong></span>
                            <?php if (!empty($myBest['TimeStamp'])): ?>
                                <span class="text-xs text-gray-500">(<?php echo htmlspecialchars($myBest['TimeStamp']); ?>)</span>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-gray-500">Du hast in diesem Spiel noch keinen Score.</div>
                    <?php endif; ?>
                </div>
                <a href="<?= strtolower($game) ?>.php"
                   title="<?= htmlspecialchars($game) ?> Leaderboard öffnen"
                   class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-medium shadow-md hover:shadow-lg hover:from-blue-600 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-400 dark:focus:ring-offset-gray-800 transition">
                    <span>Leaderboard öffnen</span>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="w-5 h-5">
                        <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l5 5a1 1 0 010 1.414l-5 5a1 1 0 11-1.414-1.414L13.586 10 10.293 6.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        <path d="M4 10a1 1 0 011-1h9a1 1 0 110 2H5a1 1 0 01-1-1z"/>
                    </svg>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
</main>

</body>
</html>
<?php

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
 * DB-Verbindung für Admin
 */
function connectAdmin() {
    $config = require 'config.php';
    try {
        $pdo = new PDO(
            "mysql:host={$config['host']};dbname={$config['db']};charset=utf8",
            $config['admin_user'],      // aus config: game_admin
            $config['admin_pass']       // Passwort für Admin
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Admin DB-Verbindung fehlgeschlagen: " . $e->getMessage());
    }
}

/**
 * Player abmelden (PDO schließen)
 */
function disconnectPlayer(&$pdo) {
    $pdo = null; // PDO-Objekt zerstören, Verbindung schließen
}

/**
 * Admin abmelden (PDO schließen)
 */
function disconnectAdmin(&$pdo) {
    $pdo = null; // PDO-Objekt zerstören, Verbindung schließen
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
?>