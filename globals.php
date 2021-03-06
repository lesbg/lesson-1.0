<?php
/**
 * ***************************************************************
 * globals.php (c) 2004 Jonathan Dieter
 *
 * Store any user modifiable global variables to be used by LESSON.
 * ***************************************************************
 */
include "core/constants.php"; // Get login functions

/* User modifiable globals */

$MAX_TRIES = 3; // Maximum number of login attempts with
                // non-existent usernames before IP is blacklisted

$MAX_LOW_MARKS = 3000; // Maximum number of low marks to show without a
                       // warning

$DSN = "mysql://user@example.com/lesson"; // DSN to connect to database
$PDO_DSN = "mysql:host=example.com;dbname=lesson;charset=utf8"; // DSN to connect to database
$PDO_USER = "user";
$PDO_PWD = "password";

$LDAP_URI = "ldap://ldap.example.com";
$LDAP_SEARCH = "cn=users,dc=example,dc=com";
$LDAP_RDN = "uid=";

$IPA_PW_UID = "pwserver";
$IPA_PW_PWD = "password";

$LOG_LEVEL = $LOG_LEVEL_TEACHER; // Set log level. See core/constants.php for more details
$LOGS_PER_PAGE = 100; // Number of logs to show per page
$LOCAL_HOSTS = ".example.local"; // Domain of local hosts
$UPLOAD_BASE_DIR = "/var/www/share/uploads"; // Base directory for uploads

$URL = "https://lesson.example.com";

$SMS_PASSWORD = "password";

$REPLICA_COUNT = 1;
$REPLICA_ID = 1;

$DYNAMIC_FILES_LOCATION = "/var/www/lesson-dynamic";
$STATIC_FILES_LOCATION = "/var/www/html/lesson-static";
$STATIC_FILES_WEBPATH = "/lesson-static";

$SHOW_COMMENT_LENGTH = 30;

$DEFAULT_PUN_PERM = 0;
