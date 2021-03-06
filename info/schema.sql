-- MySQL Script generated by MySQL Workbench
-- mié 04 nov 2015 08:00:11 BOT
-- Model: New Model    Version: 1.0
-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL,ALLOW_INVALID_DATES';

-- -----------------------------------------------------
-- Schema mydb
-- -----------------------------------------------------

-- -----------------------------------------------------
-- Table `user`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `user` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `password` VARCHAR(100) NOT NULL,
  `email` CHAR(150) NOT NULL,
  `created_on` DATETIME NOT NULL,
  `status` ENUM('enabled', 'disabled') NOT NULL,
  `first_name` VARCHAR(150) NULL,
  `last_name` VARCHAR(150) NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `index2` (`email` ASC))
ENGINE = MyISAM
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `client`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `client` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `email` CHAR(150) NOT NULL,
  `password` CHAR(60) NOT NULL,
  `domain` CHAR(200) NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `created_on` DATETIME NOT NULL,
  `deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_on` DATETIME NULL,
  `approved` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'indicates if the account was approved by admin\n',
  `approved_on` DATETIME NULL,
  `approved_by` INT NULL,
  `email_verified` TINYINT(1) NOT NULL,
  `email_verified_on` DATETIME NULL,
  `logo` TEXT NULL COMMENT 'ref to user.id',
  `lang` CHAR(2) NOT NULL DEFAULT 'en',
  PRIMARY KEY (`id`),
  UNIQUE INDEX `email` (`email` ASC),
  INDEX `index3` (`domain` ASC),
  INDEX `fk_client_user1_idx` (`approved_by` ASC))
ENGINE = MyISAM
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `database`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `database` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `db_host` CHAR(100) NOT NULL,
  `db_name` CHAR(100) NULL,
  `db_user` CHAR(50) NOT NULL,
  `db_password` CHAR(50) NOT NULL,
  PRIMARY KEY (`id`))
ENGINE = MyISAM
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `session`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `session` (
  `id` CHAR(32) NOT NULL,
  `name` CHAR(32) NOT NULL,
  `modified` INT NOT NULL,
  `lifetime` INT NOT NULL,
  `data` LONGTEXT NOT NULL,
  PRIMARY KEY (`id`, `name`))
ENGINE = MyISAM
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `error_log`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `error_log` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `created_on` DATETIME NOT NULL,
  `type` CHAR(50) NOT NULL,
  `request_method` CHAR(50) NOT NULL,
  `error_info` LONGTEXT NOT NULL,
  `cookies` TEXT NOT NULL COMMENT 'info saved using json format',
  `session` TEXT NOT NULL COMMENT 'info saved using json format',
  `post` TEXT NOT NULL COMMENT 'info saved using json format',
  `user_agent` TEXT NOT NULL,
  `url` TEXT NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `fixed` TINYINT(1) NULL DEFAULT 0,
  `fixed_on` DATETIME NULL,
  PRIMARY KEY (`id`))
ENGINE = MyISAM
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `user_property`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_property` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` CHAR(70) NOT NULL,
  `description` VARCHAR(250) NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `index2` (`name` ASC))
ENGINE = MyISAM
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `user_has_property`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_has_property` (
  `user_id` INT NOT NULL,
  `property_id` INT NOT NULL,
  PRIMARY KEY (`user_id`, `property_id`),
  INDEX `fk_user_has_user_property_user_property1_idx` (`property_id` ASC),
  INDEX `fk_user_has_user_property_user1_idx` (`user_id` ASC))
ENGINE = MyISAM
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `group`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `group` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` CHAR(20) NOT NULL,
  `description` VARCHAR(250) NULL,
  PRIMARY KEY (`id`))
ENGINE = MyISAM;


-- -----------------------------------------------------
-- Table `user_has_group`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_has_group` (
  `user_id` INT NOT NULL,
  `group_id` INT NOT NULL,
  PRIMARY KEY (`user_id`, `group_id`),
  INDEX `fk_user_has_group_group1_idx` (`group_id` ASC),
  INDEX `fk_user_has_group_user1_idx` (`user_id` ASC))
ENGINE = MyISAM;


-- -----------------------------------------------------
-- Table `blacklist_domain`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `blacklist_domain` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `domain` CHAR(150) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `index2` (`domain` ASC))
ENGINE = MyISAM
DEFAULT CHARACTER SET = utf8
COMMENT = 'lista de dominios que no deberian poder crearse cuenta';


-- -----------------------------------------------------
-- Table `email_template`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_template` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` CHAR(50) NOT NULL,
  `subject` VARCHAR(250) NOT NULL,
  `body` TEXT NOT NULL,
  `description` VARCHAR(250) NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `index2` (`name` ASC))
ENGINE = MyISAM
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `client_has_database`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `client_has_database` (
  `client_id` BIGINT NOT NULL,
  `database_id` BIGINT NOT NULL,
  PRIMARY KEY (`client_id`, `database_id`),
  INDEX `fk_client_has_database_database1_idx` (`database_id` ASC),
  INDEX `fk_client_has_database_client1_idx` (`client_id` ASC))
ENGINE = MyISAM
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `shopify_auth`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `shopify_auth` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `store` CHAR(250) NOT NULL COMMENT 'shopify store',
  `code` VARCHAR(100) NULL COMMENT 'shopify code',
  `access_token` VARCHAR(100) NULL COMMENT 'shopify token code',
  `lms_instance` CHAR(255) NULL,
  `lms_token` CHAR(50) NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `store_UNIQUE` (`store` ASC))
ENGINE = MyISAM;


-- -----------------------------------------------------
-- Table `lms_instance`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `lms_instance` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `lms_url` TEXT NOT NULL,
  PRIMARY KEY (`id`))
ENGINE = MyISAM;


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
