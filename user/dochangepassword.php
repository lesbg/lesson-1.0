<?php
/**
 * ***************************************************************
 * user/dochangepassword.php (c) 2005, 2018 Jonathan Dieter
 *
 * Change password for user, or cancel if that's what was chosen
 * ***************************************************************
 */

/* Get variables */
$nextLink = dbfuncInt2String($_GET['next']); // Link to next page

if ($password_number == 2) {
    $pass_str = "Password2";
} else {
    $pass_str = "Password";
}

/* Check which button was pressed */
if ($_POST["action"] == "Ok") { // If ok was pressed, try to change password
    /* Check whether password has been set to username and give error if it was */
    if (isset($_POST["new"]) and (
         strtoupper($_POST["new"]) == strtoupper($username) or
         strtoupper($_POST["new"]) == strtoupper("p$username"))) {
        $error = true;
        include "user/changepassword.php";
        exit();
    }

    $title = "LESSON - Saving changes...";
    $noHeaderLinks = true;
    $noJS = true;

    include "header.php"; // Print header

    echo "      <p align='center'>Changing password...";

    $good_pw = False;

    $ldap = ldap_connect($LDAP_URI) or die("Unable to connect to $LDAP_URI");
    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
    ldap_start_tls($ldap) or die("Unable to use TLS when connecting to $LDAP_URI");
    $bind = @ldap_bind($ldap, "${LDAP_RDN}$username,${LDAP_SEARCH}", $_POST['old']);

    $good_pw = False;

    if ($bind) {
        $good_pw = True;
    }

    /* Check whether old MD5 password is correct */
    if (!$good_pw) {
        $query = $pdb->prepare(
            "SELECT Username FROM user " .
            "WHERE Username = :username " .
            "AND   $pass_str = MD5(:old_pw)"
        );
        $query->execute(['old_pw' => $_POST['old'], 'username' => $username]);
        $row = $query->fetch();
        if ($row) {
            $good_pw = True;
        }
    }

    /* Check whether old password_hash password is correct */
    if(!$good_pw) {
        $query = $pdb->prepare(
            "SELECT $pass_str FROM user " .
            "WHERE Username = :username "
        );
        $query->execute(['username' => $username]);
        $row = $query->fetch();
        if ($row && password_verify($_POST['old'], $row[$pass_str])) {
            $good_pw = True;
        }
    }

    if($good_pw) {
        if (strlen($_POST["new"]) >= 6) {
            if ($_POST["new"] == $_POST["confirmnew"]) {
                if(!change_own_pwd($username, $_POST['old'], $_POST['new']))
                    die();
                if($pass_str == "Password") {
                    $pdb->prepare(
                        "UPDATE user SET OriginalPassword=NULL " .
                        "WHERE Username = :username"
                    )->execute(['username' => $username]);
                }
                echo "done.</p>\n";
                unset($_SESSION['samepass']);
                unset($_SESSION['samepass2']);
                unset($_SESSION['samepass3']);
                unset($_POST['password']);
                log_event($LOG_LEVEL_ADMIN, "user/dochangepassword.php",
                        $LOG_USER, "Changed LDAP password.");
            } else {
                echo "failed!</p>\n";
                echo "      <p align='center'>The new password didn't match the confirm new password!</p>\n";
                log_event($LOG_LEVEL_EVERYTHING, "user/dochangepassword.php",
                        $LOG_ERROR,
                        "The new password didn't match the confirm new password.");
            }
        } else {
            echo "failed!</p>\n";
            echo "      <p align='center'>The new password must contain at least six characters!</p>\n";
            log_event($LOG_LEVEL_EVERYTHING, "user/dochangepassword.php",
                    $LOG_ERROR, "The new password wasn't long enough.");
        }
    } else {
        echo "failed!</p>\n";
        echo "      <p align='center'>The old password wasn't correct!</p>\n";

        log_event($LOG_LEVEL_ERROR, "user/dochangepassword.php", $LOG_ERROR,
                "Typed wrong passward when trying to change password.");
    }

    echo "      <p align='center'><a href='$nextLink'>Continue</a></p>\n"; // Link to next page

    include "footer.php";
} else {
    if($samepass) {
        include "user/logout.php";
    } else {
        redirect($nextLink);
    }
}
