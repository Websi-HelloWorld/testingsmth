<?php
session_start();
require_once __DIR__ . '/theme.php';

// Score löschen, wenn Button gedrückt und User eingeloggt
if (isset($_POST['delete_score'], $_POST['delete_score_id'])) {
    // Bestätigung erfolgt jetzt per JavaScript confirm-Popup

    $pdo = connectPlayer();
    $stmt = $pdo->prepare("CALL DeleteSnakeScore(:user_id, :score_id)");
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindParam(':score_id', $_POST['delete_score_id'], PDO::PARAM_INT);
    $success = $stmt->execute();
    $errorInfo = $stmt->errorInfo();
    $stmt->closeCursor();
    disconnectPlayer($pdo);
    if (!$success) {
        echo '<div style="color:red;">Fehler beim Löschen: '.htmlspecialchars($errorInfo[2]).'</div>';
    } else {
        // Nach dem Löschen neu laden, um doppeltes Absenden zu verhindern
        header("Location: snake.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
<?php echo render_theme_head('Snake Leaderboard'); ?>
</head>
<body class="bg-gray-50 dark:bg-gray-900 font-sans text-gray-800 dark:text-gray-100">
<header class="glass shadow p-3 sm:p-4 flex flex-row flex-nowrap items-center justify-between gap-2 transition-colors">
    <h1 class="flex-1 min-w-0 truncate text-lg sm:text-xl font-extrabold section-title">Snake Leaderboard</h1>
    <div class="flex items-center gap-2 shrink-0">
        <a href="Uebersicht.php" class="btn btn-primary text-sm sm:text-base whitespace-nowrap">Zur Übersicht</a>
        <?php echo render_theme_toggle(true); ?>
    </div>
</header>


<main class="p-6 max-w-5xl mx-auto fade-up">
    <div class="card rounded-xl overflow-hidden p-4 mb-5 transition-colors">
    <form method="get" class="flex flex-col sm:flex-row gap-3 sm:gap-4 items-stretch sm:items-end w-full">
        <div class="flex-1">
            <label class="block mb-1 text-sm font-medium">Benutzername</label>
            <input type="text" name="username" placeholder="Suchen..." value="<?= isset($_GET['username']) ? htmlspecialchars($_GET['username']) : '' ?>" class="w-full border rounded px-3 py-2 bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 placeholder-gray-400 dark:placeholder-gray-400 text-gray-800 dark:text-gray-100">
        </div>
        <div class="flex items-stretch sm:items-center gap-2 w-full sm:w-auto">
            <button type="submit" class="btn btn-primary text-sm w-full sm:w-auto">Suchen</button>
            <?php if(isset($_GET['username']) && $_GET['username'] !== ''): ?>
            <a href="snake.php" class="btn text-sm w-full sm:w-auto bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600">Reset</a>
            <?php endif; ?>
        </div>
    </form>
    </div>
    <div class="card rounded-xl overflow-hidden transition-colors">
        <div style="max-height:70vh; overflow:auto;">
        <table class="min-w-full text-left text-sm">
            <thead class="sticky top-0 z-10 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-100">
                <tr>
                    <th class="p-4 font-medium">Benutzername</th>
                    <th class="p-4 font-medium">
                        <a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'Score','dir'=>(isset($_GET['dir']) && $_GET['dir']==='ASC' && (isset($_GET['sort']) && $_GET['sort']==='Score') ? 'DESC' : 'ASC')])) ?>" class="hover:underline">Score
                            <?php if(isset($_GET['sort']) && $_GET['sort']==='Score'): ?>
                                <?= $_GET['dir']==='ASC' ? '▲' : '▼' ?>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th class="p-4 font-medium">Speed</th>
                    <th class="p-4 font-medium">Fruits</th>
                    <th class="p-4 font-medium">
                        <a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'TimeStamp','dir'=>(isset($_GET['dir']) && $_GET['dir']==='ASC' && (isset($_GET['sort']) && $_GET['sort']==='TimeStamp') ? 'DESC' : 'ASC')])) ?>" class="hover:underline">Datum
                            <?php if(isset($_GET['sort']) && $_GET['sort']==='TimeStamp'): ?>
                                <?= $_GET['dir']==='ASC' ? '▲' : '▼' ?>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th class="p-4 font-medium" style="text-align:center;">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $rows = [];
                $username = isset($_GET['username']) ? trim($_GET['username']) : '';
                $sort = isset($_GET['sort']) && in_array($_GET['sort'], ['Score','TimeStamp']) ? $_GET['sort'] : 'Score';
                $dir = isset($_GET['dir']) && in_array(strtoupper($_GET['dir']), ['ASC','DESC']) ? strtoupper($_GET['dir']) : 'DESC';

                $pdo = connectPlayer();
                $stmt = $pdo->prepare("CALL ShowSnakeLeaderboard(:usernameFilter, :sortField, :sortDir)");
                $stmt->bindParam(':usernameFilter', $username, PDO::PARAM_STR);
                $stmt->bindParam(':sortField', $sort, PDO::PARAM_STR);
                $stmt->bindParam(':sortDir', $dir, PDO::PARAM_STR);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmt->closeCursor();
                disconnectPlayer($pdo);

                $currentUserId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                $isAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] == 1;
                foreach($rows as $r):
                ?>
                <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <td class="p-4 flex items-center gap-2">
                        <?php if (!empty($r['ProfilePic'])): ?>
                            <img src="data:image/jpeg;base64,<?= base64_encode($r['ProfilePic']) ?>" alt="Profilbild" style="width:48px;height:48px;border-radius:50%;object-fit:cover;">
                        <?php else: ?>
                            <span class="inline-block" style="width:48px;height:48px;background:#ccc;border-radius:50%;display:inline-block;"></span>
                        <?php endif; ?>
                        <?= htmlspecialchars($r['UserName']) ?>
                    </td>
                    <td class="p-4"><?= htmlspecialchars($r['Score']) ?></td>
                    <td class="p-4"><?= htmlspecialchars($r['Speed']) ?></td>
                    <td class="p-4"><?= htmlspecialchars($r['Fruits']) ?></td>
                    <td class="p-4"><?= htmlspecialchars(isset($r['TimeStamp']) ? $r['TimeStamp'] : '') ?></td>
                    <td class="p-4" style="text-align:center;">
                    <?php if ($isAdmin || ($r['UserName'] == $_SESSION['username'])): ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Diesen Score wirklich löschen?');">
                            <input type="hidden" name="delete_score_id" value="<?= htmlspecialchars($r['PK_SnakeScoreID']) ?>">
                            <button type="submit" name="delete_score" title="Score löschen" class="px-2 py-1 rounded bg-red-500 text-white text-xs hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-400">Löschen</button>
                        </form>
                    <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
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
 * Player abmelden (PDO schließen)
 */
function disconnectPlayer(&$pdo) {
    $pdo = null; // PDO-Objekt zerstören, Verbindung schließen
}
?>