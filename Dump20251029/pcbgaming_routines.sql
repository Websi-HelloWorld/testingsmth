-- MySQL dump 10.13  Distrib 8.0.43, for Win64 (x86_64)
--
-- Host: jkjt.your-database.de    Database: pcbgaming
-- ------------------------------------------------------
-- Server version	5.5.5-10.11.14-MariaDB-0+deb12u2

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping events for database 'pcbgaming'
--
/*!50106 SET @save_time_zone= @@TIME_ZONE */ ;
/*!50106 DROP EVENT IF EXISTS `delete_esp32_heartbeat` */;
DELIMITER ;;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;;
/*!50003 SET character_set_client  = utf8mb4 */ ;;
/*!50003 SET character_set_results = utf8mb4 */ ;;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;;
/*!50003 SET @saved_time_zone      = @@time_zone */ ;;
/*!50003 SET time_zone             = 'SYSTEM' */ ;;
/*!50106 CREATE*/ /*!50117 DEFINER=`jofadmin`@`%`*/ /*!50106 EVENT `delete_esp32_heartbeat` ON SCHEDULE EVERY 1 MINUTE STARTS '2025-10-02 15:51:10' ON COMPLETION NOT PRESERVE ENABLE DO DELETE FROM `esp32_heartbeat`
  WHERE `last_seen` < (NOW() - INTERVAL 2 MINUTE) */ ;;
/*!50003 SET time_zone             = @saved_time_zone */ ;;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;;
/*!50003 SET character_set_client  = @saved_cs_client */ ;;
/*!50003 SET character_set_results = @saved_cs_results */ ;;
/*!50003 SET collation_connection  = @saved_col_connection */ ;;
/*!50106 DROP EVENT IF EXISTS `del_esp32_heartbeat` */;;
DELIMITER ;;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;;
/*!50003 SET character_set_client  = utf8mb4 */ ;;
/*!50003 SET character_set_results = utf8mb4 */ ;;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;;
/*!50003 SET @saved_time_zone      = @@time_zone */ ;;
/*!50003 SET time_zone             = 'SYSTEM' */ ;;
/*!50106 CREATE*/ /*!50117 DEFINER=`jofadmin`@`%`*/ /*!50106 EVENT `del_esp32_heartbeat` ON SCHEDULE EVERY 30 SECOND STARTS '2025-10-06 13:26:54' ON COMPLETION NOT PRESERVE ENABLE DO DELETE FROM `esp32_heartbeat`
  WHERE `last_seen` < (NOW() - INTERVAL 1 MINUTE) */ ;;
/*!50003 SET time_zone             = @saved_time_zone */ ;;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;;
/*!50003 SET character_set_client  = @saved_cs_client */ ;;
/*!50003 SET character_set_results = @saved_cs_results */ ;;
/*!50003 SET collation_connection  = @saved_col_connection */ ;;
DELIMITER ;
/*!50106 SET TIME_ZONE= @save_time_zone */ ;

--
-- Dumping routines for database 'pcbgaming'
--
/*!50003 DROP PROCEDURE IF EXISTS `AddDodgeScore` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`jofadmin`@`%` PROCEDURE `AddDodgeScore`(
    IN p_user_id INT,
    IN p_score   INT,
    IN p_speed   INT,
    IN p_rocks   INT
)
BEGIN
    IF p_user_id IS NULL OR p_user_id <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid user_id';
    END IF;
    IF p_score IS NULL OR p_score < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid score';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pcbgaming.users WHERE PK_UserID = p_user_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User not found';
    END IF;

    INSERT INTO pcbgaming.dodge (FK_UserID, Score, Speed, Rocks, TimeStamp)
    VALUES (p_user_id, p_score, p_speed, p_rocks, NOW());

    SELECT LAST_INSERT_ID() AS InsertedId;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `AddPingPongScore` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`jofadmin`@`%` PROCEDURE `AddPingPongScore`(
    IN p_user_id   INT,
    IN p_score     INT,
    IN p_speed     INT,
    IN p_paddlesize INT
)
BEGIN
    IF p_user_id IS NULL OR p_user_id <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid user_id';
    END IF;
    IF p_score IS NULL OR p_score < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid score';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pcbgaming.users WHERE PK_UserID = p_user_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User not found';
    END IF;

    INSERT INTO pcbgaming.pingpong (FK_UserID, Score, Speed, PaddleSize, TimeStamp)
    VALUES (p_user_id, p_score, p_speed, p_paddlesize, NOW());

    SELECT LAST_INSERT_ID() AS InsertedId;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `AddSimonSaysScore` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`jofadmin`@`%` PROCEDURE `AddSimonSaysScore`(
    IN p_user_id  INT,
    IN p_score    INT,
    IN p_speed    INT,
    IN p_gridsize INT
)
BEGIN
    IF p_user_id IS NULL OR p_user_id <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid user_id';
    END IF;
    IF p_score IS NULL OR p_score < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid score';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pcbgaming.users WHERE PK_UserID = p_user_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User not found';
    END IF;

    INSERT INTO pcbgaming.simonsays (FK_UserID, Score, Speed, GridSize, TimeStamp)
    VALUES (p_user_id, p_score, p_speed, p_gridsize, NOW());

    SELECT LAST_INSERT_ID() AS InsertedId;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `AddSnakeScore` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`jofadmin`@`%` PROCEDURE `AddSnakeScore`(
    IN p_user_id INT,
    IN p_score   INT,
    IN p_speed   INT,
    IN p_fruits  INT
)
BEGIN
    IF p_user_id IS NULL OR p_user_id <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid user_id';
    END IF;
    IF p_score IS NULL OR p_score < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid score';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pcbgaming.users WHERE PK_UserID = p_user_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User not found';
    END IF;

    INSERT INTO pcbgaming.snake (FK_UserID, Score, Speed, Fruits, TimeStamp)
    VALUES (p_user_id, p_score, p_speed, p_fruits, NOW());

    SELECT LAST_INSERT_ID() AS InsertedId;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `CheckUserExists` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`jofadmin`@`%` PROCEDURE `CheckUserExists`(
    IN uname VARCHAR(45),
    IN uemail VARCHAR(45)
)
BEGIN
    SELECT 
        COUNT(*) AS UsernameExists,
        (SELECT COUNT(*) FROM users WHERE Email = uemail) AS EmailExists
    FROM users
    WHERE UserName = uname;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `CreateUserAsUser` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`jofadmin`@`%` PROCEDURE `CreateUserAsUser`(
    IN p_username VARCHAR(45),
    IN p_email VARCHAR(100),
    IN p_password VARCHAR(255)
)
BEGIN
    INSERT INTO users (UserName, Email, Password, Admin, JoinDate)
    VALUES (p_username, p_email, p_password, 0, NOW());
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `DeleteDodgeScore` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`jofadmin`@`%` PROCEDURE `DeleteDodgeScore`(
    IN p_user_id INT,
    IN p_score_id INT
)
BEGIN
    DECLARE is_admin TINYINT(1);

    SELECT Admin INTO is_admin FROM users WHERE PK_UserID = p_user_id;

    IF is_admin = 1 THEN
        DELETE FROM dodge WHERE PK_DodgeScoreID = p_score_id;
    ELSE
        DELETE FROM dodge WHERE PK_DodgeScoreID = p_score_id AND FK_UserID = p_user_id;
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `DeleteOwnAccount` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`jofadmin`@`%` PROCEDURE `DeleteOwnAccount`(IN p_uid INT)
BEGIN
  DELETE FROM users WHERE PK_UserID = p_uid;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `DeletePingPongScore` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`jofadmin`@`%` PROCEDURE `DeletePingPongScore`(
    IN p_user_id INT,
    IN p_score_id INT
)
BEGIN
    DECLARE is_admin TINYINT(1);

    SELECT Admin INTO is_admin FROM users WHERE PK_UserID = p_user_id;

    IF is_admin = 1 THEN
        DELETE FROM pingpong WHERE PK_PingPongScoreID = p_score_id;
    ELSE
        DELETE FROM pingpong WHERE PK_PingPongScoreID = p_score_id AND FK_UserID = p_user_id;
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `DeleteSimonSaysScore` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`jofadmin`@`%` PROCEDURE `DeleteSimonSaysScore`(
    IN p_user_id INT,
    IN p_score_id INT
)
BEGIN
    DECLARE is_admin TINYINT(1);

    SELECT Admin INTO is_admin FROM users WHERE PK_UserID = p_user_id;

    IF is_admin = 1 THEN
        DELETE FROM simonsays WHERE PK_SimonSaysScoreID = p_score_id;
    ELSE
        DELETE FROM simonsays WHERE PK_SimonSaysScoreID = p_score_id AND FK_UserID = p_user_id;
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `DeleteSnakeScore` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`jofadmin`@`%` PROCEDURE `DeleteSnakeScore`(
    IN p_user_id INT,
    IN p_score_id INT
)
BEGIN
    DECLARE is_admin TINYINT(1);

    SELECT Admin INTO is_admin FROM users WHERE PK_UserID = p_user_id;

    IF is_admin = 1 THEN
        DELETE FROM snake WHERE PK_SnakeScoreID = p_score_id;
    ELSE
        DELETE FROM snake WHERE PK_SnakeScoreID = p_score_id AND FK_UserID = p_user_id;
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `DeleteUser` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`jofadmin`@`%` PROCEDURE `DeleteUser`(
    IN p_user_id INT
)
BEGIN
    DELETE FROM users
    WHERE PK_UserID = p_user_id
      AND Admin = 0;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `GetUserDataById` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`jofadmin`@`%` PROCEDURE `GetUserDataById`(IN p_user_id INT)
BEGIN
    SELECT 
        PK_UserID,
        UserName,
        Email,
        ProfilePic,
        Admin,
        TwoFASecret
    FROM users
    WHERE PK_UserID = p_user_id
    LIMIT 1;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `GetUserIdByUsername` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`jofadmin`@`%` PROCEDURE `GetUserIdByUsername`(
    IN p_username VARCHAR(100)
)
BEGIN
    SELECT PK_UserID
    FROM users
    WHERE UserName = p_username
    LIMIT 1;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `GetUserTwoFASecret` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`jofadmin`@`%` PROCEDURE `GetUserTwoFASecret`(
    IN p_userid INT
)
BEGIN
    SELECT TwoFASecret
    FROM users
    WHERE PK_UserID = p_userid
    LIMIT 1;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `LoginUser` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`jofadmin`@`%` PROCEDURE `LoginUser`(
    IN p_username VARCHAR(45)
)
BEGIN
    SELECT PK_UserID, Password, Admin
    FROM users
    WHERE UserName = p_username;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `ResetUserPasswordById` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`jofadmin`@`%` PROCEDURE `ResetUserPasswordById`(
    IN p_user_id INT,
    IN p_new_pw_hash VARCHAR(255)
)
BEGIN
    UPDATE users
    SET Password = p_new_pw_hash
    WHERE PK_UserID = p_user_id;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `SetUserTwoFASecret` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`jofadmin`@`%` PROCEDURE `SetUserTwoFASecret`(
    IN p_userid INT,
    IN p_secret VARCHAR(32)
)
BEGIN
    UPDATE users
    SET TwoFASecret = p_secret
    WHERE PK_UserID = p_userid;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `ShowDodgeLeaderboard` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`jofadmin`@`%` PROCEDURE `ShowDodgeLeaderboard`(
    IN usernameFilter VARCHAR(255),
    IN sortField VARCHAR(32),
    IN sortDir VARCHAR(4)
)
BEGIN
    SET @sql = CONCAT(
        'SELECT d.PK_DodgeScoreID, u.UserName, u.ProfilePic, d.Score, d.Speed, d.Rocks, d.TimeStamp ',
        'FROM dodge d ',
        'JOIN users u ON d.FK_UserID = u.PK_UserID '
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

    SET @sql = CONCAT(@sql, 'ORDER BY d.', sortField, ' ', sortDir);

    PREPARE stmt FROM @sql;
    IF usernameFilter IS NOT NULL AND usernameFilter != '' THEN
        SET @usernameLike = CONCAT('%', usernameFilter, '%');
        EXECUTE stmt USING @usernameLike;
    ELSE
        EXECUTE stmt;
    END IF;
    DEALLOCATE PREPARE stmt;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `ShowPingPongLeaderboard` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`jofadmin`@`%` PROCEDURE `ShowPingPongLeaderboard`(
    IN usernameFilter VARCHAR(255),
    IN sortField VARCHAR(32),
    IN sortDir VARCHAR(4)
)
BEGIN
    SET @sql = CONCAT(
        'SELECT p.PK_PingPongScoreID, u.UserName, u.ProfilePic, p.Score, p.Speed, p.PaddleSize, p.TimeStamp ',
        'FROM pingpong p ',
        'JOIN users u ON p.FK_UserID = u.PK_UserID '
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

    SET @sql = CONCAT(@sql, 'ORDER BY p.', sortField, ' ', sortDir);

    PREPARE stmt FROM @sql;
    IF usernameFilter IS NOT NULL AND usernameFilter != '' THEN
        SET @usernameLike = CONCAT('%', usernameFilter, '%');
        EXECUTE stmt USING @usernameLike;
    ELSE
        EXECUTE stmt;
    END IF;
    DEALLOCATE PREPARE stmt;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `ShowSimonSaysLeaderboard` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`jofadmin`@`%` PROCEDURE `ShowSimonSaysLeaderboard`(
    IN usernameFilter VARCHAR(255),
    IN sortField VARCHAR(32),
    IN sortDir VARCHAR(4)
)
BEGIN
    SET @sql = CONCAT(
        'SELECT ss.PK_SimonSaysScoreID, u.UserName, u.ProfilePic, ss.Score, ss.Speed, ss.GridSize, ss.TimeStamp ',
        'FROM simonsays ss ',
        'JOIN users u ON ss.FK_UserID = u.PK_UserID '
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

    SET @sql = CONCAT(@sql, 'ORDER BY ss.', sortField, ' ', sortDir);

    PREPARE stmt FROM @sql;
    IF usernameFilter IS NOT NULL AND usernameFilter != '' THEN
        SET @usernameLike = CONCAT('%', usernameFilter, '%');
        EXECUTE stmt USING @usernameLike;
    ELSE
        EXECUTE stmt;
    END IF;
    DEALLOCATE PREPARE stmt;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `ShowSnakeLeaderboard` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`jofadmin`@`%` PROCEDURE `ShowSnakeLeaderboard`(
    IN usernameFilter VARCHAR(255),
    IN sortField VARCHAR(32),
    IN sortDir VARCHAR(4)
)
BEGIN
    SET @sql = CONCAT(
        'SELECT s.PK_SnakeScoreID, u.UserName, u.ProfilePic, s.Score, s.Speed, s.Fruits, s.TimeStamp ',
        'FROM snake s ',
        'JOIN users u ON s.FK_UserID = u.PK_UserID '
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
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `UpdateOwnProfile` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`jofadmin`@`%` PROCEDURE `UpdateOwnProfile`(
    IN p_userid INT,
    IN p_username VARCHAR(100),
    IN p_email VARCHAR(255),
    IN p_pic LONGBLOB
)
BEGIN
    UPDATE users
    SET UserName = p_username,
        Email = p_email,
        ProfilePic = IF(p_pic IS NOT NULL, p_pic, ProfilePic)
    WHERE PK_UserID = p_userid;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `UpdateUserAsAdmin` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`jofadmin`@`%` PROCEDURE `UpdateUserAsAdmin`(
    IN p_user_id INT,
    IN p_username VARCHAR(45),
    IN p_email VARCHAR(100),
    IN p_password_hash VARCHAR(255),
    IN p_admin_flag TINYINT(1),
    IN p_profile_pic LONGBLOB
)
BEGIN
    UPDATE users
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
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-10-29 22:00:22
