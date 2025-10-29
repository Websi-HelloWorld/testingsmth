<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Admin-Flag
$isAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] == 1;
require_once __DIR__ . '/theme.php';

$meldung = '';

if ($isAdmin) {
    // Optionaler Username-Filter (serverseitig)
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $pdo = connectAdmin();
    if ($q !== '') {
        $stmt = $pdo->prepare("SELECT PK_UserID, UserName, Email, ProfilePic, Admin FROM users WHERE UserName LIKE :q ORDER BY UserName ASC");
        $like = "%".$q."%";
        $stmt->bindParam(':q', $like, PDO::PARAM_STR);
        $stmt->execute();
    } else {
        $stmt = $pdo->query("SELECT PK_UserID, UserName, Email, ProfilePic, Admin FROM users ORDER BY UserName ASC");
    }
    $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    disconnectAdmin($pdo);
} else {
    $pdo = connectPlayer();
    $stmt = $pdo->prepare("CALL GetUserDataById(:uid)");
    $stmt->bindParam(':uid', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    disconnectPlayer($pdo);
}

// Admin: Delete
if ($isAdmin && isset($_POST['delete']) && isset($_POST['delete_user_id'])) {
    $deleteId = (int)$_POST['delete_user_id'];
    $pdo = connectAdmin();
    $stmt = $pdo->prepare("CALL DeleteUser(:uid)");
    $stmt->bindParam(':uid', $deleteId, PDO::PARAM_INT);
    $stmt->execute();
    $stmt->closeCursor();
    disconnectAdmin($pdo);
    header("Location: profil_bearbeiten.php?deleted=1");
    exit();
}

// Admin: Update
if ($isAdmin && isset($_POST['save_edit'])) {
    $editId = (int)$_POST['edit_user_id'];
    $username = trim($_POST['modal_username']);
    $email = trim($_POST['modal_email']);
    $adminFlag = isset($_POST['modal_admin']) ? 1 : 0;
    $password = $_POST['modal_password'];
    $passwordHash = '';
    if (!empty($password)) {
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    }
    // Bild-Upload verarbeiten
    $profilePicBlob = null;
    if (!empty($_POST['modal_profilepic_dataurl'])) {
        $dataurl = $_POST['modal_profilepic_dataurl'];
        if (preg_match('/^data:image\\/(png|jpeg);base64,(.*)$/', $dataurl, $matches)) {
            $profilePicBlob = base64_decode($matches[2]);
        }
    }   
    $pdo = connectAdmin();
    $stmt = $pdo->prepare("CALL UpdateUserAsAdmin(:uid, :username, :email, :pw_hash, :admin_flag, :profile_pic)");
    $stmt->bindParam(':uid', $editId, PDO::PARAM_INT);
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->bindParam(':pw_hash', $passwordHash, PDO::PARAM_STR);
    $stmt->bindParam(':admin_flag', $adminFlag, PDO::PARAM_INT);
    $stmt->bindParam(':profile_pic', $profilePicBlob, PDO::PARAM_LOB);
    $stmt->execute();
    $stmt->closeCursor();
    disconnectAdmin($pdo);
    header("Location: profil_bearbeiten.php?updated=1");
    exit();
}

// Normaler User: eigenes Profil speichern (nur via Prozeduren)
if (!$isAdmin && isset($_POST['save'])) {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    // Basis-Validierung
    if (strlen($username) < 3) {
        $meldung = 'Benutzername zu kurz (min. 3 Zeichen).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $meldung = 'Ungültige E-Mail-Adresse.';
    } else {
        // Profilbild (optional) aus DataURL oder Datei lesen
        $profilePicBlob = null;
        if (!empty($_POST['profilepic_dataurl'])) {
            $dataurl = $_POST['profilepic_dataurl'];
            if (preg_match('/^data:image\/(png|jpeg);base64,(.*)$/', $dataurl, $m)) {
                $profilePicBlob = base64_decode($m[2]);
            }
        } elseif (isset($_FILES['profilepic']) && isset($_FILES['profilepic']['tmp_name']) && is_uploaded_file($_FILES['profilepic']['tmp_name'])) {
            $mime = mime_content_type($_FILES['profilepic']['tmp_name']);
            if (in_array($mime, ['image/png', 'image/jpeg'])) {
                // max ~5MB
                if ((int)$_FILES['profilepic']['size'] <= 5 * 1024 * 1024) {
                    $profilePicBlob = file_get_contents($_FILES['profilepic']['tmp_name']);
                } else {
                    $meldung = 'Profilbild ist zu groß (max. 5MB).';
                }
            } else {
                $meldung = 'Nur PNG oder JPEG als Profilbild erlaubt.';
            }
        }

        if ($meldung === '') {
            try {
                $pdo = connectPlayer();
                $stmt = $pdo->prepare('CALL UpdateOwnProfile(:uid, :u, :e, :p)');
                $stmt->bindValue(':uid', $_SESSION['user_id'], PDO::PARAM_INT);
                $stmt->bindValue(':u', $username, PDO::PARAM_STR);
                $stmt->bindValue(':e', $email, PDO::PARAM_STR);
                if ($profilePicBlob !== null) {
                    $stmt->bindParam(':p', $profilePicBlob, PDO::PARAM_LOB);
                } else {
                    $stmt->bindValue(':p', null, PDO::PARAM_NULL);
                }
                $stmt->execute();
                $stmt->closeCursor();
                disconnectPlayer($pdo);

                // Session aktualisieren
                $_SESSION['username'] = $username;

                header('Location: profil_bearbeiten.php?saved=1');
                exit();
            } catch (Exception $ex) {
                // Duplikat-Fehler schön ausgeben
                $msg = $ex->getMessage();
                if (stripos($msg, 'Duplicate') !== false || stripos($msg, '1062') !== false) {
                    if (stripos($msg, 'UserName') !== false) {
                        $meldung = 'Benutzername ist bereits vergeben.';
                    } elseif (stripos($msg, 'Email') !== false) {
                        $meldung = 'E-Mail wird bereits verwendet.';
                    } else {
                        $meldung = 'Ein Eintrag mit diesen Daten existiert bereits.';
                    }
                } else {
                    $meldung = 'Fehler beim Speichern: ' . $msg;
                }
            }
        }
    }
}

// Normaler User: eigenes Konto löschen
if (!$isAdmin && isset($_POST['delete_own'])) {
    try {
        $pdo = connectPlayer();
        // Bevorzugt: eigene Prozedur für Selbstlöschung (Least Privilege)
        $stmt = $pdo->prepare('CALL DeleteOwnAccount(:uid)');
        $stmt->bindValue(':uid', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        $stmt->closeCursor();
        disconnectPlayer($pdo);

        // Session sicher beenden
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();

        header('Location: index.php?account_deleted=1');
        exit();
    } catch (Exception $ex) {
        $msg = $ex->getMessage();
        // Nutzerfreundliche Fehlermeldung ohne technische Details
        $meldung = 'Konto konnte nicht gelöscht werden.';
        // Optional: leicht detaillierter, wenn gewünscht
        if (stripos($msg, 'procedure') !== false || stripos($msg, 'not found') !== false) {
            $meldung .= ' (Lösch-Prozedur nicht verfügbar)';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<?php echo render_theme_head('Profil bearbeiten', ['extra' => '<style>.modal-bg{position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:50;display:none;align-items:center;justify-content:center}.modal{background:#fff;border-radius:1rem;box-shadow:0 2px 18px rgba(0,0,0,0.35);padding:2rem;min-width:320px;max-width:90vw;z-index:51}.dark .modal{background:#1f2937;color:#f3f4f6}</style>']); ?>
</head>
<body class="bg-gray-50 dark:bg-gray-900 font-sans text-gray-800 dark:text-gray-100">
<header class="glass shadow p-4 flex justify-between items-center gap-4 transition-colors">
    <h1 class="text-xl font-extrabold section-title">Profil bearbeiten</h1>
    <div class="flex items-center gap-2">
        <a href="Uebersicht.php" class="btn btn-primary">Zur Übersicht</a>
        <?php echo render_theme_toggle(true); ?>
    </div>
</header>
<main class="p-6 max-w-lg mx-auto">
<?php if (isset($_GET['saved'])): ?>
    <div class="mb-4 p-3 rounded bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100">Profil wurde gespeichert.</div>
<?php endif; ?>
<?php if (isset($_GET['updated'])): ?>
    <div class="mb-4 p-3 rounded bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100">Änderungen gespeichert.</div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
    <div class="mb-4 p-3 rounded bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-100">Benutzer gelöscht.</div>
<?php endif; ?>
<?php if (!empty($meldung)): ?>
    <div class="mb-4 p-3 rounded bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100"><?php echo htmlspecialchars($meldung); ?></div>
<?php endif; ?>
<?php if ($isAdmin): ?>
    <div class="flex items-center justify-center min-h-[80vh]">
    <div class="bg-white dark:bg-gray-800 p-8 rounded-2xl shadow-xl mb-10 transition-colors" style="width:1000px; max-width:96vw;">
            <h2 class="text-3xl font-bold mb-4 text-center">Alle Benutzer</h2>
            <form method="get" class="mb-6 flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-2 justify-center">
                <input type="text" name="q" value="<?= isset($q) ? htmlspecialchars($q) : '' ?>" placeholder="Nach Benutzername filtern" class="w-full sm:w-64 max-w-full border rounded px-3 py-2 bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-100" />
                <button type="submit" class="btn btn-primary w-full sm:w-auto">Suchen</button>
                <a href="profil_bearbeiten.php" class="btn btn-outline w-full sm:w-auto">Zurücksetzen</a>
            </form>
            <div style="max-height:600px;overflow-y:auto;">
                <table class="text-left w-full text-lg">
                    <thead class="bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-100">
                        <tr>
                            <th class="p-2">Profilbild</th>
                            <th class="p-2">Benutzername</th>
                            <th class="p-2">E-Mail</th>
                            <th class="p-2">Admin</th>
                            <th class="p-2">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($allUsers as $u): ?>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <td class="p-2">
                                <?php if (!empty($u['ProfilePic'])): ?>
                                    <img src="data:image/jpeg;base64,<?= base64_encode($u['ProfilePic']) ?>" style="width:64px;height:64px;border-radius:50%;object-fit:cover;">
                                <?php else: ?>
                                    <span class="inline-block bg-gray-300 rounded-full" style="width:64px;height:64px;"></span>
                                <?php endif; ?>
                            </td>
                            <td class="p-2"><?= htmlspecialchars($u['UserName']) ?></td>
                            <td class="p-2"><?= htmlspecialchars($u['Email']) ?></td>
                            <td class="p-2"><?= $u['Admin'] ? 'Ja' : 'Nein' ?></td>
                            <td class="p-2">
                                <div class="flex flex-wrap gap-2">
                    <button type="button" title="Bearbeiten"
                        onclick="openEditModal(<?= $u['PK_UserID'] ?>,'<?= htmlspecialchars($u['UserName'],ENT_QUOTES) ?>','<?= htmlspecialchars($u['Email'],ENT_QUOTES) ?>',<?= $u['Admin'] ?>, '<?= !empty($u['ProfilePic']) ? ('data:image/jpeg;base64,'.base64_encode($u['ProfilePic'])) : '' ?>')"
                                            class="btn btn-primary text-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5h2m2 0h.01M4 21v-2a4 4 0 014-4h4a4 4 0 014 4v2M12 7a4 4 0 110-8 4 4 0 010 8z"/></svg>
                                        Bearbeiten
                                    </button>
                                    <form method="post" onsubmit="return confirm('Benutzer wirklich löschen?');" class="inline">
                                        <input type="hidden" name="delete_user_id" value="<?= $u['PK_UserID'] ?>">
                                        <button type="submit" name="delete" title="Löschen" class="btn btn-danger text-sm min-w-[120px]">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6m-1-3H10a1 1 0 00-1 1v1h8V5a1 1 0 00-1-1z"/></svg>
                                            Löschen
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="editModal" class="modal-bg">
        <div class="modal">
            <h3 class="text-lg font-bold mb-4">Benutzer bearbeiten</h3>
            <form method="post" class="space-y-3" enctype="multipart/form-data">
                <input type="hidden" name="edit_user_id" id="modal_user_id">
                <div class="flex flex-col items-center gap-2">
                    <div class="relative group">
                        <div id="modalProfilePicPreview" style="position:relative;width:96px;height:96px;">
                            <!-- Profilbild anzeigen oder Platzhalter -->
                            <img id="modalProfilePicImg" src="" style="width:96px;height:96px;border-radius:50%;object-fit:cover;display:none;">
                            <span id="modalProfilePicPlaceholder" class="inline-block bg-gray-300 rounded-full" style="width:96px;height:96px;"></span>
                        </div>
                        <label for="modalProfilePicInput" class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 cursor-pointer bg-black bg-opacity-40 rounded-full transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 13l6.293-6.293a1 1 0 011.414 0l3.586 3.586a1 1 0 010 1.414L13 17H9v-4z" /></svg>
                        </label>
                    <input type="file" id="modalProfilePicInput" accept="image/png,image/jpeg" class="hidden" onchange="openAdminCropModal(event)">
                    <input type="hidden" name="modal_profilepic_dataurl" id="modalProfilePicDataurl">                    </div>
                </div>

                <!-- Admin Crop Modal -->
                <div id="adminCropModal" class="modal-bg" style="display:none;">
                    <div class="modal" style="max-width:400px;">
                        <h3 class="text-lg font-bold mb-4">Profilbild zuschneiden</h3>
                        <div id="adminCropContainer" style="width:256px;height:256px;"></div>
                        <div class="flex justify-end gap-2 pt-4">
                            <button type="button" onclick="closeAdminCropModal()" class="btn btn-outline">Abbrechen</button>
                            <button type="button" onclick="applyAdminCrop()" class="btn btn-primary">Übernehmen</button>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block mb-1">Benutzername</label>
                    <input type="text" name="modal_username" id="modal_username" class="w-full border rounded px-2 py-1 bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-100">
                </div>
                <div>
                    <label class="block mb-1">E-Mail</label>
                    <input type="email" name="modal_email" id="modal_email" class="w-full border rounded px-2 py-1 bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-100">
                </div>
                <div>
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="modal_admin" id="modal_admin" class="mr-2"> Admin
                    </label>
                </div>
                <div>
                    <label class="block mb-1">Neues Passwort (optional)</label>
                    <input type="password" name="modal_password" class="w-full border rounded px-2 py-1 bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-100">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeEditModal()" class="btn btn-outline">Abbrechen</button>
                    <button type="submit" name="save_edit" class="btn btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>
    <script>
// Admin Crop Modal logic
let adminCropper;
let adminRawImage;
function openAdminCropModal(event) {
    const file = event.target.files[0];
    if (!file) return;
    // Hide round profile pic while cropping
    document.getElementById('modalProfilePicPreview').style.display = 'none';
    document.getElementById('adminCropModal').style.display = 'flex';
    const reader = new FileReader();
    reader.onload = function(e) {
        adminRawImage = document.createElement('img');
        adminRawImage.src = e.target.result;
        adminRawImage.style.maxWidth = '256px';
        adminRawImage.style.maxHeight = '256px';
        const cropContainer = document.getElementById('adminCropContainer');
        cropContainer.innerHTML = '';
        cropContainer.appendChild(adminRawImage);
        adminCropper = new Cropper(adminRawImage, {
            aspectRatio: 1,
            viewMode: 1,
            autoCropArea: 1,
            minContainerWidth: 256,
            minContainerHeight: 256,
        });
    };
    reader.readAsDataURL(file);
}
function closeAdminCropModal() {
    document.getElementById('adminCropModal').style.display = 'none';
    document.getElementById('modalProfilePicPreview').style.display = '';
    if (adminCropper) adminCropper.destroy();
}
function applyAdminCrop() {
    if (!adminCropper) return;
    const canvas = adminCropper.getCroppedCanvas({ width: 256, height: 256, imageSmoothingQuality: 'high' });
    // Zeige das eckige Bild in der runden Vorschau
    const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
    const img = document.getElementById('modalProfilePicImg');
    img.src = dataUrl;
    document.getElementById('modalProfilePicDataurl').value = dataUrl;
    img.style.display = 'block';
    const ph = document.getElementById('modalProfilePicPlaceholder');
    if (ph) ph.style.display = 'none';
    document.getElementById('adminCropModal').style.display = 'none';
    document.getElementById('modalProfilePicPreview').style.display = '';
    if (adminCropper) adminCropper.destroy();
}
</script>
<?php else: ?>
    <!-- Normaler User -->
    <form method="post" enctype="multipart/form-data" class="space-y-6 bg-white dark:bg-gray-800 p-6 rounded-xl shadow transition-colors">
        <div class="flex flex-col items-center gap-2">
            <div class="relative group">
                <div id="userProfilePicPreview" style="position:relative;width:96px;height:96px;">
                    <?php if (!empty($user['ProfilePic'])): ?>
                        <img id="userProfilePicImg" src="data:image/jpeg;base64,<?= base64_encode($user['ProfilePic']) ?>" style="width:96px;height:96px;border-radius:50%;object-fit:cover;display:block;">
                        <span id="userProfilePicPlaceholder" class="inline-block bg-gray-300 rounded-full" style="width:96px;height:96px;display:none;"></span>
                    <?php else: ?>
                        <img id="userProfilePicImg" src="" style="width:96px;height:96px;border-radius:50%;object-fit:cover;display:none;">
                        <span id="userProfilePicPlaceholder" class="inline-block bg-gray-300 rounded-full" style="width:96px;height:96px;"></span>
                    <?php endif; ?>
                </div>
                <label for="userProfilePicInput" class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 cursor-pointer bg-black bg-opacity-40 rounded-full transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 13l6.293-6.293a1 1 0 011.414 0l3.586 3.586a1 1 0 010 1.414L13 17H9v-4z" /></svg>
                </label>
                <input type="file" name="profilepic" id="userProfilePicInput" accept="image/png,image/jpeg" class="hidden" onchange="openUserCropModal(event)">
                <input type="hidden" name="profilepic_dataurl" id="userProfilePicDataurl">
            </div>
        </div>

<!-- User Crop Modal -->
<div id="userCropModal" class="modal-bg" style="display:none;">
    <div class="modal" style="max-width:400px;">
        <h3 class="text-lg font-bold mb-4">Profilbild zuschneiden</h3>
        <div id="userCropContainer" style="width:256px;height:256px;"></div>
        <div class="flex justify-end gap-2 pt-4">
            <button type="button" onclick="closeUserCropModal()" class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400">Abbrechen</button>
            <button type="button" onclick="applyUserCrop()" class="px-4 py-2 rounded bg-blue-500 text-white hover:bg-blue-600">Übernehmen</button>
        </div>
    </div>
</div>
<script>
// User Crop Modal logic
let userCropper;
let userRawImage;
function openUserCropModal(event) {
    const file = event.target.files[0];
    if (!file) return;
    // Hide round profile pic while cropping
    document.getElementById('userProfilePicPreview').style.display = 'none';
    document.getElementById('userCropModal').style.display = 'flex';
    const reader = new FileReader();
    reader.onload = function(e) {
        userRawImage = document.createElement('img');
        userRawImage.src = e.target.result;
        userRawImage.style.maxWidth = '256px';
        userRawImage.style.maxHeight = '256px';
        const cropContainer = document.getElementById('userCropContainer');
        cropContainer.innerHTML = '';
        cropContainer.appendChild(userRawImage);
        userCropper = new Cropper(userRawImage, {
            aspectRatio: 1,
            viewMode: 1,
            autoCropArea: 1,
            minContainerWidth: 256,
            minContainerHeight: 256,
        });
    };
    reader.readAsDataURL(file);
}
function closeUserCropModal() {
    document.getElementById('userCropModal').style.display = 'none';
    document.getElementById('userProfilePicPreview').style.display = '';
    if (userCropper) userCropper.destroy();
}
function applyUserCrop() {
    if (!userCropper) return;
    const canvas = userCropper.getCroppedCanvas({ width: 256, height: 256, imageSmoothingQuality: 'high' });
    // Zeige das eckige Bild in der runden Vorschau
    const img = document.getElementById('userProfilePicImg');
    const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
    img.src = dataUrl;
    img.style.display = 'block';
    const ph = document.getElementById('userProfilePicPlaceholder');
    if (ph) ph.style.display = 'none';
    const hidden = document.getElementById('userProfilePicDataurl');
    if (hidden) hidden.value = dataUrl;
    document.getElementById('userCropModal').style.display = 'none';
    document.getElementById('userProfilePicPreview').style.display = '';
    if (userCropper) userCropper.destroy();
}
</script>
        </div>
        <div>
            <label class="block mb-1 font-medium">Benutzername</label>
            <input type="text" name="username" value="<?= htmlspecialchars($user['UserName']) ?>" required class="w-full border rounded px-3 py-2 bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-100">
        </div>
        <div>
            <label class="block mb-1 font-medium">E-Mail</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['Email']) ?>" required class="w-full border rounded px-3 py-2 bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-100">
        </div>
    <div class="flex flex-col sm:flex-row gap-3 pt-2">
        <button type="submit" name="save" class="btn btn-primary flex-1 justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path d="M3.5 4A1.5 1.5 0 015 2.5h10A1.5 1.5 0 0116.5 4v11a1.5 1.5 0 01-1.5 1.5H5A1.5 1.5 0 013.5 15V4zm3 1a.5.5 0 00-.5.5V15h8V5.5a.5.5 0 00-.5-.5H6.5z"/></svg>
            <span>Speichern</span>
        </button>
        <button type="submit" name="delete_own" formnovalidate onclick="return confirm('Dein Konto wirklich dauerhaft löschen? Diese Aktion kann nicht rückgängig gemacht werden.');" class="btn btn-danger justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M6 6h8l-.833 9.333A2 2 0 0111.176 17H8.824a2 2 0 01-1.99-1.667L6 6zm2.5 2a.5.5 0 00-1 0v6a.5.5 0 001 0V8zm4 0a.5.5 0 10-1 0v6a.5.5 0 001 0V8z" clip-rule="evenodd"/><path d="M7 4.5A1.5 1.5 0 018.5 3h3A1.5 1.5 0 0113 4.5V5h3a.5.5 0 010 1H4a.5.5 0 010-1h3v-.5z"/></svg>
            <span>Konto löschen</span>
        </button>
    </div>
    </form>
<?php endif; ?>
</main>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet"/>
<script>
let cropper;
let rawImage;

function openEditModal(id, username, email, isAdmin, picUrl) {
    document.getElementById('modal_user_id').value = id;
    document.getElementById('modal_username').value = username;
    document.getElementById('modal_email').value = email;
    document.getElementById('modal_admin').checked = !!isAdmin;
    // Bild/Platzhalter steuern
    const img = document.getElementById('modalProfilePicImg');
    const ph = document.getElementById('modalProfilePicPlaceholder');
    if (picUrl) {
        img.src = picUrl;
        img.style.display = 'block';
        if (ph) ph.style.display = 'none';
    } else {
        img.src = '';
        img.style.display = 'none';
        if (ph) ph.style.display = 'inline-block';
    }
    document.getElementById('editModal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function showCropModal(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        rawImage = document.createElement('img');
        rawImage.src = e.target.result;
        rawImage.style.maxWidth = '256px';
        rawImage.style.maxHeight = '256px';
        const cropContainer = document.getElementById('cropContainer');
        cropContainer.innerHTML = '';
        cropContainer.appendChild(rawImage);
        cropper = new Cropper(rawImage, {
            aspectRatio: 1,
            viewMode: 1,
            autoCropArea: 1,
            minContainerWidth: 256,
            minContainerHeight: 256,
        });
        document.getElementById('cropModal').style.display = 'flex';
    };
    reader.readAsDataURL(file);
}
function closeCropModal() {
    document.getElementById('cropModal').style.display = 'none';
    // Show round profile pic again
    document.getElementById('modalProfilePicPreview').style.display = '';
    if (cropper) cropper.destroy();
}
function cropAndPreview() {
    if (!cropper) return;
    const canvas = cropper.getCroppedCanvas({ width: 256, height: 256, imageSmoothingQuality: 'high' });
    const previewDiv = document.getElementById('profilepicPreview');
    previewDiv.innerHTML = '';
    previewDiv.appendChild(canvas);
    // Optional: set hidden input for server upload
    canvas.toBlob(function(blob) {
        const fileInput = document.getElementById('profilepicInput');
        // Create a new File object for upload if needed
        // Or use AJAX to upload directly
    }, 'image/jpeg', 0.85);
    closeCropModal();
}
</script>
</body>
</html>

<?php
function connectPlayer() {
    $config = require 'config.php';
    return new PDO("mysql:host={$config['host']};dbname={$config['db']};charset=utf8",
                   $config['player_user'], $config['player_pass'],
                   [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}
function disconnectPlayer(&$pdo) { $pdo = null; }

function connectAdmin() {
    $config = require 'config.php';
    return new PDO("mysql:host={$config['host']};dbname={$config['db']};charset=utf8",
                   $config['admin_user'], $config['admin_pass'],
                   [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}
function disconnectAdmin(&$pdo) { $pdo = null; }
?>
