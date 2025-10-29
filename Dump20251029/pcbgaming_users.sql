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
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `PK_UserID` int(11) NOT NULL AUTO_INCREMENT,
  `UserName` varchar(45) NOT NULL,
  `Email` varchar(45) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `TwoFASecret` varchar(32) DEFAULT NULL,
  `Admin` tinyint(1) unsigned zerofill NOT NULL,
  `JoinDate` datetime NOT NULL,
  `ProfilePic` mediumblob DEFAULT NULL,
  PRIMARY KEY (`PK_UserID`),
  UNIQUE KEY `PK_UserID_UNIQUE` (`PK_UserID`),
  UNIQUE KEY `UserName_UNIQUE` (`UserName`),
  UNIQUE KEY `Email_UNIQUE` (`Email`)
) ENGINE=InnoDB AUTO_INCREMENT=225 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`jofadmin`@`%`*/ /*!50003 TRIGGER trg_users_ai
AFTER INSERT ON users
FOR EACH ROW
BEGIN
  INSERT INTO audit_log (
    table_name, operation, row_pk, changed_by, old_data, new_data,
    profilepic_old_bytes, profilepic_new_bytes
  )
  VALUES (
    'users',
    'INSERT',
    CAST(NEW.PK_UserID AS CHAR),
    COALESCE(@app_user, CURRENT_USER()),
    NULL,
    JSON_OBJECT(
      'PK_UserID', NEW.PK_UserID,
      'UserName', NEW.UserName,
      'Email', NEW.Email,
      -- sensible Felder nicht ins Log schreiben
      'Password', NULL,
      'TwoFASecret', NULL,
      'Admin', NEW.Admin,
      -- Datum als String
      'JoinDate', DATE_FORMAT(NEW.JoinDate, '%Y-%m-%d %H:%i:%s'),
      -- BLOB nicht loggen (zu groß)
      'ProfilePic', NULL
    ),
    NULL,
    OCTET_LENGTH(NEW.ProfilePic)
  );
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`jofadmin`@`%`*/ /*!50003 TRIGGER trg_users_au
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
  INSERT INTO audit_log (
    table_name, operation, row_pk, changed_by, old_data, new_data,
    profilepic_old_bytes, profilepic_new_bytes
  )
  VALUES (
    'users',
    'UPDATE',
    CAST(NEW.PK_UserID AS CHAR),
    COALESCE(@app_user, CURRENT_USER()),
    JSON_OBJECT(
      'PK_UserID', OLD.PK_UserID,
      'UserName', OLD.UserName,
      'Email', OLD.Email,
      'Password', NULL,
      'TwoFASecret', NULL,
      'Admin', OLD.Admin,
      'JoinDate', DATE_FORMAT(OLD.JoinDate, '%Y-%m-%d %H:%i:%s'),
      'ProfilePic', NULL
    ),
    JSON_OBJECT(
      'PK_UserID', NEW.PK_UserID,
      'UserName', NEW.UserName,
      'Email', NEW.Email,
      'Password', NULL,
      'TwoFASecret', NULL,
      'Admin', NEW.Admin,
      'JoinDate', DATE_FORMAT(NEW.JoinDate, '%Y-%m-%d %H:%i:%s'),
      'ProfilePic', NULL
    ),
    OCTET_LENGTH(OLD.ProfilePic),
    OCTET_LENGTH(NEW.ProfilePic)
  );
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`jofadmin`@`%`*/ /*!50003 TRIGGER trg_users_ad
AFTER DELETE ON users
FOR EACH ROW
BEGIN
  INSERT INTO audit_log (
    table_name, operation, row_pk, changed_by, old_data, new_data,
    profilepic_old_bytes, profilepic_new_bytes
  )
  VALUES (
    'users',
    'DELETE',
    CAST(OLD.PK_UserID AS CHAR),
    COALESCE(@app_user, CURRENT_USER()),
    JSON_OBJECT(
      'PK_UserID', OLD.PK_UserID,
      'UserName', OLD.UserName,
      'Email', OLD.Email,
      'Password', NULL,
      'TwoFASecret', NULL,
      'Admin', OLD.Admin,
      'JoinDate', DATE_FORMAT(OLD.JoinDate, '%Y-%m-%d %H:%i:%s'),
      'ProfilePic', NULL
    ),
    NULL,
    OCTET_LENGTH(OLD.ProfilePic),
    NULL
  );
END */;;
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

-- Dump completed on 2025-10-29 22:00:19
