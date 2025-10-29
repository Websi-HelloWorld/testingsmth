DELIMITER $$

CREATE PROCEDURE mydb.CreateUserAsUser(
    IN p_username VARCHAR(45),
    IN p_email VARCHAR(100),
    IN p_password VARCHAR(255)
)
BEGIN
    INSERT INTO Users (UserName, Email, Password, Admin, JoinDate)
    VALUES (p_username, p_email, p_password, 0, NOW());
END$$

DELIMITER ;

-- Leaderboards for other games used in Uebersicht.php
DROP PROCEDURE IF EXISTS ShowDodgeLeaderboard;
DELIMITER $$
CREATE PROCEDURE ShowDodgeLeaderboard()
BEGIN
    SELECT 
        d.PK_DodgeScoreID,
        u.UserName,
        u.ProfilePic,
        d.Score,
        d.Speed,
        d.Rocks,
        d.TimeStamp
    FROM Dodge d
    JOIN Users u ON d.FK_UserID = u.PK_UserID
    ORDER BY d.Score DESC, d.TimeStamp DESC;
END$$
DELIMITER ;

DROP PROCEDURE IF EXISTS ShowPingPongLeaderboard;
DELIMITER $$
CREATE PROCEDURE ShowPingPongLeaderboard()
BEGIN
    SELECT 
        p.PK_PingPongScoreID,
        u.UserName,
        u.ProfilePic,
        p.Score,
        p.Speed,
        p.PaddleSize,
        p.TimeStamp
    FROM PingPong p
    JOIN Users u ON p.FK_UserID = u.PK_UserID
    ORDER BY p.Score DESC, p.TimeStamp DESC;
END$$
DELIMITER ;

DROP PROCEDURE IF EXISTS ShowSimonSaysLeaderboard;
DELIMITER $$
CREATE PROCEDURE ShowSimonSaysLeaderboard()
BEGIN
    SELECT 
        s.PK_SimonSaysScoreID,
        u.UserName,
        u.ProfilePic,
        s.Score,
        s.Speed,
        s.GridSize,
        s.TimeStamp
    FROM SimonSays s
    JOIN Users u ON s.FK_UserID = u.PK_UserID
    ORDER BY s.Score DESC, s.TimeStamp DESC;
END$$
DELIMITER ;


DELIMITER $$
CREATE PROCEDURE UpdateOwnProfile(
    IN p_userid INT,
    IN p_username VARCHAR(100),
    IN p_email VARCHAR(255),
    IN p_pic LONGBLOB
)
BEGIN
    UPDATE Users
    SET UserName = p_username,
        Email = p_email,
        ProfilePic = IF(p_pic IS NOT NULL, p_pic, ProfilePic)
    WHERE PK_UserID = p_userid;
END$$
DELIMITER ;


DELIMITER $$

CREATE PROCEDURE mydb.DeleteUser(
    IN p_user_id INT
)
BEGIN
    DELETE FROM Users
    WHERE PK_UserID = p_user_id
      AND Admin = 0;
END$$

DELIMITER ;


DELIMITER $$

CREATE PROCEDURE mydb.GetMySnakeScores(IN p_user_id INT)
BEGIN
    SELECT * FROM Snake WHERE FK_UserID = p_user_id;
END$$

DELIMITER ;
DELIMITER $$
CREATE PROCEDURE mydb.DeleteSnakeScore(
    IN p_user_id INT,
    IN p_score_id INT
)
BEGIN
    DECLARE is_admin TINYINT(1);

    SELECT Admin INTO is_admin FROM Users WHERE PK_UserID = p_user_id;

    IF is_admin = 1 THEN
        DELETE FROM Snake WHERE PK_SnakeScoreID = p_score_id;
    ELSE
        DELETE FROM Snake WHERE PK_SnakeScoreID = p_score_id AND FK_UserID = p_user_id;
    END IF;
END$$
DELIMITER ;
DELIMITER $$

CREATE PROCEDURE mydb.GetMyDodgeScores(IN p_user_id INT)
BEGIN
    SELECT * FROM Dodge WHERE FK_UserID = p_user_id;
END$$

CREATE PROCEDURE mydb.DeleteDodgeScore(IN p_user_id INT, IN p_score_id INT)
BEGIN
    DELETE FROM Dodge
    WHERE PK_DodgeScoreID = p_score_id
      AND FK_UserID = p_user_id;
END$$

DELIMITER ;
DELIMITER $$

CREATE PROCEDURE mydb.GetMyPingPongScores(IN p_user_id INT)
BEGIN
    SELECT * FROM PingPong WHERE FK_UserID = p_user_id;
END$$

CREATE PROCEDURE mydb.DeletePingPongScore(IN p_user_id INT, IN p_score_id INT)
BEGIN
    DELETE FROM PingPong
    WHERE PK_PingPongScoreID = p_score_id
      AND FK_UserID = p_user_id;
END$$

DELIMITER ;
DELIMITER $$

CREATE PROCEDURE mydb.GetMySimonSaysScores(IN p_user_id INT)
BEGIN
    SELECT * FROM SimonSays WHERE FK_UserID = p_user_id;
END$$

CREATE PROCEDURE mydb.DeleteSimonSaysScore(IN p_user_id INT, IN p_score_id INT)
BEGIN
    DELETE FROM SimonSays
    WHERE PK_SimonSaysScoreID = p_score_id
      AND FK_UserID = p_user_id;
END$$

DELIMITER ;

DELIMITER $$

CREATE PROCEDURE mydb.LoginUser(
    IN p_username VARCHAR(45)
)
BEGIN
    SELECT PK_UserID, Password, Admin
    FROM Users
    WHERE UserName = p_username;
END$$

DELIMITER ;

DELIMITER $$
CREATE PROCEDURE ShowSnakeLeaderboard(
    IN usernameFilter VARCHAR(255),
    IN sortField VARCHAR(32),
    IN sortDir VARCHAR(4)
)
BEGIN
    SET @sql = CONCAT(
        'SELECT s.PK_SnakeScoreID, u.UserName, u.ProfilePic, s.Score, s.Speed, s.Fruits, s.TimeStamp ',
        'FROM Snake s ',
        'JOIN Users u ON s.FK_UserID = u.PK_UserID '
    );

    IF usernameFilter IS NOT NULL AND usernameFilter != '' THEN
        SET @sql = CONCAT(@sql, 'WHERE u.UserName LIKE ? ');
    END IF;

    -- Nur erlaubte Felder und Richtungen zulassen!
    IF sortField NOT IN ('Score', 'TimeStamp') THEN
        SET sortField = 'Score';
    END IF;
    IF UPPER(sortDir) NOT IN ('ASC', 'DESC') THEN
        SET sortDir = 'DESC';
    END IF;

    SET @sql = CONCAT(@sql, 'ORDER BY s.', sortField, ' ', sortDir);

    PREPARE stmt FROM @sql;
    IF usernameFilter IS NOT NULL AND usernameFilter != '' THEN
        SET @usernameLike = CONCAT('%', usernameFilter, '%');
        EXECUTE stmt USING @usernameLike;
    ELSE
        EXECUTE stmt;
    END IF;
    DEALLOCATE PREPARE stmt;
END$$
DELIMITER ;

-- Einzelne Userdaten per ID (jetzt von den Seiten genutzt)
DROP PROCEDURE IF EXISTS GetUserDataById;
DELIMITER $$
CREATE PROCEDURE GetUserDataById(IN p_user_id INT)
BEGIN
    SELECT 
        PK_UserID,
        UserName,
        Email,
        ProfilePic,
        Admin,
        TwoFASecret
    FROM Users
    WHERE PK_UserID = p_user_id
    LIMIT 1;
END$$
DELIMITER ;

-- (Optional) Per Benutzername (nur falls wirklich benötigt)
DROP PROCEDURE IF EXISTS GetUserDataByName;
DELIMITER $$
CREATE PROCEDURE GetUserDataByName(IN p_username VARCHAR(100))
BEGIN
    SELECT 
        PK_UserID,
        UserName,
        Email,
        ProfilePic,
        Admin,
        TwoFASecret
    FROM Users
    WHERE UserName = p_username
    LIMIT 1;
END$$
DELIMITER ;

DELIMITER $$

CREATE PROCEDURE UpdateUserData(
    IN p_user_id INT,
    IN p_username VARCHAR(45),
    IN p_email VARCHAR(100),
    IN p_admin_id INT,
    IN p_admin_flag TINYINT(1)
)
BEGIN
    IF p_admin_flag = 1 THEN
        UPDATE Users
        SET UserName = p_username,
            Email = p_email
        WHERE PK_UserID = p_user_id;
    ELSE
        IF p_user_id = p_admin_id THEN
            UPDATE Users
            SET UserName = p_username,
                Email = p_email
            WHERE PK_UserID = p_user_id AND Admin = 0;
        END IF;
    END IF;
END$$

DELIMITER ;

DELIMITER $$
CREATE PROCEDURE mydb.UpdateUserAsAdmin(
    IN p_user_id INT,
    IN p_username VARCHAR(45),
    IN p_email VARCHAR(100),
    IN p_password_hash VARCHAR(255),
    IN p_admin_flag TINYINT(1),
    IN p_profile_pic LONGBLOB
)
BEGIN
    UPDATE Users
    SET
        UserName = p_username,
        Email = p_email,
        Admin = p_admin_flag,
        Password = CASE
            WHEN p_password_hash IS NOT NULL AND LENGTH(p_password_hash) > 0
            THEN p_password_hash
            ELSE Password
        END,
        ProfilePic = CASE
            WHEN p_profile_pic IS NOT NULL AND LENGTH(p_profile_pic) > 0
            THEN p_profile_pic
            ELSE ProfilePic
        END
    WHERE PK_UserID = p_user_id;
END$$
DELIMITER ;

DELIMITER $$

CREATE PROCEDURE SetUserTwoFASecret(
    IN p_userid INT,
    IN p_secret VARCHAR(32)
)
BEGIN
    UPDATE Users
    SET TwoFASecret = p_secret
    WHERE PK_UserID = p_userid;
END$$

DELIMITER ;

DELIMITER $$

CREATE PROCEDURE CheckUserExists(
    IN uname VARCHAR(45),
    IN uemail VARCHAR(45)
)
BEGIN
    SELECT 
        COUNT(*) AS UsernameExists,
        (SELECT COUNT(*) FROM Users WHERE Email = uemail) AS EmailExists
    FROM Users
    WHERE UserName = uname;
END$$

DELIMITER ;

DELIMITER $$

CREATE PROCEDURE GetUserIdByUsername(
    IN p_username VARCHAR(100)
)
BEGIN
    SELECT PK_UserID
    FROM Users
    WHERE UserName = p_username
    LIMIT 1;
END$$

DELIMITER ;

DELIMITER $$

CREATE PROCEDURE GetUserTwoFASecret(
    IN p_userid INT
)
BEGIN
    SELECT TwoFASecret
    FROM Users
    WHERE PK_UserID = p_userid
    LIMIT 1;
END$$


DELIMITER ;

DELIMITER $$
CREATE PROCEDURE ResetUserPassword(
    IN p_username VARCHAR(100),
    IN p_new_pw_hash VARCHAR(255)
)
BEGIN
    UPDATE Users
    SET Password = p_new_pw_hash
    WHERE UserName = p_username;
END$$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE ResetUserPasswordById(
    IN p_user_id INT,
    IN p_new_pw_hash VARCHAR(255)
)
BEGIN
    UPDATE Users
    SET Password = p_new_pw_hash
    WHERE PK_UserID = p_user_id;
END$$
DELIMITER ;
