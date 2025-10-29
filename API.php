<?php
// Simple JSON API for ESP32 to submit game scores and heartbeats.
// Usage: POST application/json to /API.php with fields:
// {
//   "api_key": "12d34f67-89ab-cdef-0123-456789abcdef",
//   // Either heartbeat OR score submission
//   // 1) Heartbeat:
//   //   "action": "heartbeat",
//   //   "username": "PlayerName",
//   //   "device_id": "ESP32_...",        // optional but recommended
//   //   "timestamp": 123456               // optional device-side millis
//   // 2) Score submission:
//   //   "game": "snake|pingpong|simonsays|dodge",
//   "username": "PlayerName" | "user_id": 123,
//   "score": 999,
//   // optional-extra fields per game (all ints):
//   // snake:      speed, fruits
//   // pingpong:   speed, paddleSize (aliases: paddle_size, paddlesize, pedalSize, peddalSize)
//   // simonsays:  speed, gridSize   (aliases: grid_size, gridsize)
//   // dodge:      speed, rocks      (aliases: rockCount, rock_count)
// }

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode([ 'ok' => false, 'error' => 'Method Not Allowed. Use POST.' ]);
	exit;
}

// Load config
$config = require __DIR__ . '/config.php';
$apiKeyConfig = isset($config['api_key']) ? $config['api_key'] : null;
if (!$apiKeyConfig) {
	// Fallback to env var if not set in config
	$apiKeyConfig = getenv('PGONE_API_KEY') ?: null;
}

// Parse JSON or form fields
$raw = file_get_contents('php://input');
$data = [];
if (!empty($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
	$data = json_decode($raw, true) ?: [];
} else {
	// Accept x-www-form-urlencoded as fallback
	$data = $_POST;
}

// Debug: Log incoming data
error_log("[API DEBUG] Raw input: " . $raw);
error_log("[API DEBUG] Parsed data: " . json_encode($data));
error_log("[API DEBUG] Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
error_log("[API DEBUG] Request method: " . $_SERVER['REQUEST_METHOD']);

// Case-insensitive access helper for numeric values (int). Accepts alias list.
$dataLower = is_array($data) ? array_change_key_case($data, CASE_LOWER) : [];
$getInt = function(array $keys) use ($data, $dataLower) {
	foreach ($keys as $k) {
		if (isset($data[$k]) && is_numeric($data[$k])) return (int)$data[$k];
		$kl = strtolower($k);
		if (isset($dataLower[$kl]) && is_numeric($dataLower[$kl])) return (int)$dataLower[$kl];
	}
	return null;
};

// API Key from body or header
$providedKey = isset($data['api_key']) ? trim((string)$data['api_key']) : '';
if (!$providedKey && isset($_SERVER['HTTP_X_API_KEY'])) {
	$providedKey = trim($_SERVER['HTTP_X_API_KEY']);
}

if (!$apiKeyConfig) {
	http_response_code(500);
	echo json_encode([
		'ok' => false,
		'error' => 'API key not configured on server. Add api_key to config.php or set PGONE_API_KEY env var.'
	]);
	exit;
}

if (!$providedKey || !hash_equals($apiKeyConfig, $providedKey)) {
	http_response_code(401);
	echo json_encode([ 'ok' => false, 'error' => 'Unauthorized: invalid API key.' ]);
	exit;
}

// Validate user existence without inserting scores
$action = isset($data['action']) ? strtolower(trim((string)$data['action'])) : '';
if ($action === 'validate_user') {
	$username = isset($data['username']) ? trim((string)$data['username']) : '';
	if ($username === '') {
		http_response_code(400);
		echo json_encode([ 'ok' => false, 'error' => 'Username required for validation' ]);
		exit;
	}

	try {
		$pdo = connectAdmin();
		$stmt = $pdo->prepare('CALL GetUserIdByUsername(:uname)');
		$stmt->bindParam(':uname', $username, PDO::PARAM_STR);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		while ($stmt->nextRowset()) {}
		$stmt->closeCursor();
		disconnectAdmin($pdo);

		if (!$row || !isset($row['PK_UserID'])) {
			http_response_code(404);
			echo json_encode([ 'ok' => false, 'error' => 'User not found.' ]);
			exit;
		}

		echo json_encode([ 'ok' => true, 'user_id' => (int)$row['PK_UserID'], 'username' => $username ]);
		exit;
	} catch (Throwable $e) {
		if (isset($pdo)) { disconnectAdmin($pdo); }
		http_response_code(500);
		echo json_encode([ 'ok' => false, 'error' => $e->getMessage() ]);
		exit;
	}
}

// Heartbeat handling (no game/score required) -> persist to DB table
if ($action === 'heartbeat' || (isset($data['heartbeat']) && $data['heartbeat'])) {
	$username = isset($data['username']) ? trim((string)$data['username']) : '';
	if ($username === '') {
		http_response_code(400);
		echo json_encode([ 'ok' => false, 'error' => 'Username required for heartbeat' ]);
		exit;
	}

	// Prepare online status payload
	$deviceId = isset($data['device_id']) ? trim((string)$data['device_id']) : '';
	$ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
	$timestamp = isset($data['timestamp']) && is_numeric($data['timestamp']) ? (int)$data['timestamp'] : null;

	if ($deviceId === '') {
		http_response_code(400);
		echo json_encode([ 'ok' => false, 'error' => 'device_id (MAC) required for heartbeat to avoid duplicates' ]);
		exit;
	}

	try {
		$pdo = connectAdmin();

		// First try update by MAC (device_id)
		$update = $pdo->prepare("UPDATE esp32_heartbeat 
			SET username = :username, ip_address = :ip, last_seen = NOW(), esp32_timestamp_ms = :esp_ts
			WHERE device_id = :device_id LIMIT 1");
		$update->bindValue(':username', $username, PDO::PARAM_STR);
		$update->bindValue(':ip', $ipAddress, PDO::PARAM_STR);
		$update->bindValue(':device_id', $deviceId, PDO::PARAM_STR);
		if ($timestamp === null) {
			$update->bindValue(':esp_ts', null, PDO::PARAM_NULL);
		} else {
			$update->bindValue(':esp_ts', $timestamp, PDO::PARAM_INT);
		}
		$update->execute();

		if ($update->rowCount() === 0) {
			// No existing entry -> insert new
			$insert = $pdo->prepare("INSERT INTO esp32_heartbeat (username, device_id, ip_address, esp32_timestamp_ms, last_seen) 
				VALUES (:username, :device_id, :ip, :esp_ts, NOW())");
			$insert->bindValue(':username', $username, PDO::PARAM_STR);
			$insert->bindValue(':device_id', $deviceId, PDO::PARAM_STR);
			$insert->bindValue(':ip', $ipAddress, PDO::PARAM_STR);
			if ($timestamp === null) {
				$insert->bindValue(':esp_ts', null, PDO::PARAM_NULL);
			} else {
				$insert->bindValue(':esp_ts', $timestamp, PDO::PARAM_INT);
			}
			$insert->execute();
			$resultType = 'inserted';
		} else {
			$resultType = 'updated';
		}

		disconnectAdmin($pdo);
		echo json_encode([ 'ok' => true, 'message' => 'Heartbeat '.$resultType, 'username' => $username, 'device_id' => $deviceId ]);
		exit;
	} catch (Throwable $e) {
		if (isset($pdo)) { disconnectAdmin($pdo); }
		http_response_code(500);
		echo json_encode([ 'ok' => false, 'error' => 'DB error: ' . $e->getMessage() ]);
		exit;
	}
}

// Validate inputs
$game = isset($data['game']) ? strtolower(trim((string)$data['game'])) : '';
$allowedGames = [ 'snake', 'pong', 'simonsays', 'dodge' ];
// Accept common aliases
if ($game === 'simon' || $game === 'simon_says') $game = 'simonsays';
if (!in_array($game, $allowedGames, true)) {
	http_response_code(400);
	echo json_encode([ 'ok' => false, 'error' => 'Invalid or missing game. Allowed: snake, pong, simonsays, dodge.' ]);
	exit;
}

$score = isset($data['score']) ? (int)$data['score'] : null;
if ($score === null || !is_numeric($data['score']) || $score < 0) {
	http_response_code(400);
	echo json_encode([ 'ok' => false, 'error' => 'Invalid or missing score.' ]);
	exit;
}

// Identify user
$userId = null;
if (isset($data['user_id']) && is_numeric($data['user_id'])) {
	$userId = (int)$data['user_id'];
}
$username = isset($data['username']) ? trim((string)$data['username']) : '';

try {
	// Connect using admin user to perform inserts safely
	error_log("[API DEBUG] Attempting to connect to database...");
	$pdo = connectAdmin();
	error_log("[API DEBUG] Database connected successfully");

	if (!$userId) {
		if ($username === '') {
			error_log("[API DEBUG] Error: No username or user_id provided");
			http_response_code(400);
			echo json_encode([ 'ok' => false, 'error' => 'Provide username or user_id.' ]);
			disconnectAdmin($pdo);
			exit;
		}
		// Use stored procedure to resolve user id
		error_log("[API DEBUG] Looking up user: " . $username);
		$stmt = $pdo->prepare('CALL GetUserIdByUsername(:uname)');
		$stmt->bindParam(':uname', $username, PDO::PARAM_STR);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		error_log("[API DEBUG] User lookup result: " . json_encode($row));
		// Advance to be safe if there are remaining result sets
		while ($stmt->nextRowset()) {}
		$stmt->closeCursor();
		if (!$row || !isset($row['PK_UserID'])) {
			error_log("[API DEBUG] User not found in database");
			http_response_code(404);
			echo json_encode([ 'ok' => false, 'error' => 'User not found.' ]);
			disconnectAdmin($pdo);
			exit;
		}
		$userId = (int)$row['PK_UserID'];
		error_log("[API DEBUG] Found user ID: " . $userId);
	}

	// Call stored procedure by game and read InsertedId
	error_log("[API DEBUG] Preparing to call stored procedure for game: " . $game);
		switch ($game) {
		case 'snake':
			$speed = $getInt(['speed','Speed']);
			$fruits = $getInt(['fruits','Fruits']);
			error_log("[API DEBUG] Snake params - userId: $userId, score: $score, speed: $speed, fruits: $fruits");
			$call = $pdo->prepare('CALL AddSnakeScore(:uid, :score, :speed, :fruits)');
			$call->bindValue(':uid', $userId, PDO::PARAM_INT);
			$call->bindValue(':score', $score, PDO::PARAM_INT);
			is_null($speed) ? $call->bindValue(':speed', null, PDO::PARAM_NULL) : $call->bindValue(':speed', $speed, PDO::PARAM_INT);
			is_null($fruits) ? $call->bindValue(':fruits', null, PDO::PARAM_NULL) : $call->bindValue(':fruits', $fruits, PDO::PARAM_INT);
			error_log("[API DEBUG] Executing AddSnakeScore procedure...");
			$call->execute();
			error_log("[API DEBUG] AddSnakeScore executed successfully");
			break;
		case 'pong':
			// Accept common aliases for PaddleSize
			$speed = $getInt(['speed','Speed']);
			$paddleSize = $getInt(['PaddleSize','paddleSize','paddle_size','paddlesize','PedalSize','pedalSize','PeddalSize','peddalSize','paddle']);
			$call = $pdo->prepare('CALL AddPingPongScore(:uid, :score, :speed, :paddleSize)');
			$call->bindValue(':uid', $userId, PDO::PARAM_INT);
			$call->bindValue(':score', $score, PDO::PARAM_INT);
			is_null($speed) ? $call->bindValue(':speed', null, PDO::PARAM_NULL) : $call->bindValue(':speed', $speed, PDO::PARAM_INT);
			is_null($paddleSize) ? $call->bindValue(':paddleSize', null, PDO::PARAM_NULL) : $call->bindValue(':paddleSize', $paddleSize, PDO::PARAM_INT);
			$call->execute();
			break;
		case 'simonsays':
			$speed = $getInt(['speed','Speed']);
			$gridSize = $getInt(['GridSize','gridSize','grid_size','gridsize','grid']);
			$call = $pdo->prepare('CALL AddSimonSaysScore(:uid, :score, :speed, :gridSize)');
			$call->bindValue(':uid', $userId, PDO::PARAM_INT);
			$call->bindValue(':score', $score, PDO::PARAM_INT);
			is_null($speed) ? $call->bindValue(':speed', null, PDO::PARAM_NULL) : $call->bindValue(':speed', $speed, PDO::PARAM_INT);
			is_null($gridSize) ? $call->bindValue(':gridSize', null, PDO::PARAM_NULL) : $call->bindValue(':gridSize', $gridSize, PDO::PARAM_INT);
			$call->execute();
			break;
		case 'dodge':
			$speed = $getInt(['speed','Speed']);
			$rocks = $getInt(['Rocks','rocks','rockCount','rock_count']);
			$call = $pdo->prepare('CALL AddDodgeScore(:uid, :score, :speed, :rocks)');
			$call->bindValue(':uid', $userId, PDO::PARAM_INT);
			$call->bindValue(':score', $score, PDO::PARAM_INT);
			is_null($speed) ? $call->bindValue(':speed', null, PDO::PARAM_NULL) : $call->bindValue(':speed', $speed, PDO::PARAM_INT);
			is_null($rocks) ? $call->bindValue(':rocks', null, PDO::PARAM_NULL) : $call->bindValue(':rocks', $rocks, PDO::PARAM_INT);
			$call->execute();
			break;
	}

	$row = $call->fetch(PDO::FETCH_ASSOC);
	error_log("[API DEBUG] Procedure result: " . json_encode($row));
	// Ensure all result sets are consumed (important for CALL)
	while ($call->nextRowset()) {}
	$call->closeCursor();

	$id = $row && isset($row['InsertedId']) ? (int)$row['InsertedId'] : 0;
	error_log("[API DEBUG] Final InsertedId: " . $id);
	echo json_encode([
		'ok' => true,
		'game' => $game,
		'inserted_id' => $id,
		'user_id' => $userId,
		'score' => $score
	]);
	disconnectAdmin($pdo);
} catch (Throwable $e) {
	error_log("[API DEBUG] Exception caught: " . $e->getMessage());
	error_log("[API DEBUG] Exception trace: " . $e->getTraceAsString());
	http_response_code(500);
	echo json_encode([ 'ok' => false, 'error' => $e->getMessage() ]);
	if (isset($pdo)) { disconnectAdmin($pdo); }
}

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
