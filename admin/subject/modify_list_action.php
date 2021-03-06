<?php
/**
 * ***************************************************************
 * admin/subject/modify_list_action.php (c) 2005 Jonathan Dieter
 *
 * Add or remove students from a subject, as well as changing
 * subject information
 * ***************************************************************
 */

/* Get variables */
$nextLink = dbfuncInt2String($_GET['next']); // Link to next page
$subjectindex = dbfuncInt2String($_GET['key']); // Index of subject to add and remove students from
$subject = dbfuncInt2String($_GET['keyname']);

if (! isset($_POST["action"]))
    $_POST["action"] = "";
if (! isset($_POST["actiont"]))
    $_POST["actiont"] = "";
    /* Check whether user is authorized to change subject */
if (dbfuncGetPermission($permissions, $PERM_ADMIN)) {
    /* Check which button was pressed */

    // If > was pressed, remove students from subject
    if ($_POST["action"] == ">" and isset($_POST['removefromsubject'])) {
        foreach ( $_POST['removefromsubject'] as $remUserName ) {
            if (substr($remUserName, 0, 1) == "!") {
                $remUserName = substr($remUserName, 1);
                $forceRemove = true;
            }
            $res = &  $db->query(
                            "SELECT user.FirstName, user.Surname, mark.Username FROM mark, assignment, user " .
                             "WHERE mark.Username = '$remUserName' " .
                             "AND   mark.AssignmentIndex = assignment.AssignmentIndex " .
                             "AND   user.Username = mark.Username " .
                             "AND   assignment.SubjectIndex = $subjectindex " .
                             "AND   mark.Score > 0");
            if (DB::isError($res))
                die($res->getDebugInfo()); // Check for errors in query

            if ($res->numRows() > 0 && ! $forceRemove) { // If there's at least one mark with a score or comment,
                $row = & $res->fetchRow(DB_FETCHMODE_ASSOC); // and we're not force the removal, pop up an error
                $errorlist[$remUserName] = "{$row['FirstName']} {$row['Surname']} ($remUserName)"; // message
            } else { // Remove all null score and comment marks, then remove user from subject
                subject_remove_student($remUserName, $subjectindex);
                log_event($LOG_LEVEL_ADMIN, "admin/subject/modify_list_action.php",
                          $LOG_ADMIN, "Removed $remUserName from subject $subject.");
            }
        }
        include "admin/subject/modify_list.php";
    } elseif ($_POST["action"] == ">>") { // If < was pressed, add students to
        $ares = & $db->query(
                    "SELECT user.FirstName, user.Surname, user.Username FROM " .
                             "       user, subjectstudent " .
                             "WHERE subjectstudent.Username = user.Username " .
                             "AND   subjectstudent.SubjectIndex = $subjectindex " .
                             "ORDER BY user.Username");
        if (DB::isError($ares))
            die($ares->getDebugInfo()); // Check for errors in query
        while ( $arow = & $ares->fetchRow(DB_FETCHMODE_ASSOC) ) {
            $remUserName = $arow['Username'];
            $res = &  $db->query(
                            "SELECT user.FirstName, user.Surname, mark.Username FROM mark, assignment, user " .
                             "WHERE mark.Username = '$remUserName' " .
                             "AND   mark.AssignmentIndex = assignment.AssignmentIndex " .
                             "AND   user.Username = mark.Username " .
                             "AND   assignment.SubjectIndex = $subjectindex " .
                             "AND   mark.Score > 0");
            if (DB::isError($res))
                die($res->getDebugInfo()); // Check for errors in query

            if ($res->numRows() > 0 && ! $forceRemove) { // If there's at least one mark with a score or comment,
                $row = & $res->fetchRow(DB_FETCHMODE_ASSOC); // and we're not force the removal, pop up an error
                $errorlist[$remUserName] = "{$row['FirstName']} {$row['Surname']} ($remUserName)"; // message
            } else { // Remove all null score and comment marks, then remove user from subject
                subject_remove_student($remUserName, $subjectindex);
                log_event($LOG_LEVEL_ADMIN, "admin/subject/modify_list_action.php",
                          $LOG_ADMIN, "Removed $remUserName from subject $subject.");
            }
        }
        include "admin/subject/modify_list.php";
    } elseif ($_POST["action"] == "<") { // If < was pressed, add students to
        foreach ( $_POST['addtosubject'] as $addUserName ) { // subject
            $res = &  $db->query(
                    "SELECT Username FROM subjectstudent " .
                             "WHERE Username     = \"$addUserName\" " .
                             "AND   SubjectIndex = $subjectindex");
            if (DB::isError($res))
                die($res->getDebugInfo()); // Check for errors in query
            if ($res->numRows() == 0) {
                $res = & $db->query(
                                "INSERT INTO subjectstudent (Username, SubjectIndex) VALUES " .
                         "                           (\"$addUserName\", $subjectindex)");
                if (DB::isError($res))
                    die($res->getDebugInfo()); // Check for errors in query
                log_event($LOG_LEVEL_ADMIN, "admin/subject/modify_list_action.php",
                          $LOG_ADMIN, "Added $addUserName to subject $subject.");
            }
        }
        include "admin/subject/modify_list.php";
    } elseif ($_POST["action"] == "<<") { // If << was pressed, add all students in
        if (isset($_POST['show'])) { // class to subject
            if ($_POST['show'] == "new")
                $showNew = "checked";
            elseif ($_POST['show'] == "old")
                $showOld = "checked";
            elseif ($_POST['show'] == "spec")
                $showSpec = "checked";
            elseif ($_POST['show'] == "reg")
                $showReg = "checked";
            else
                $showAll = "checked";
        } else {
            $showAll = "checked";
        }
        /* Get list of students who are in the active class */
        if ($_POST['class'] != "") {
            $query = "SELECT user.FirstName, user.Surname, user.Username, newmem.Username AS New, specialmem.Username AS Special FROM " .
                 "       user " .
                 "       LEFT OUTER JOIN (groupgenmem AS newmem INNER JOIN " .
                 "                        groups AS newgroups ON (newgroups.GroupID=newmem.GroupID " .
                 "                                                AND newgroups.GroupTypeID='new' " .
                 "                                                AND newgroups.YearIndex=$yearindex)) ON (user.Username=newmem.Username) " .
                 "       LEFT OUTER JOIN (groupgenmem AS specialmem INNER JOIN " .
                 "                        groups AS specgroups ON (specgroups.GroupID=specialmem.GroupID " .
                 "                                                 AND specgroups.GroupTypeID='special' " .
                 "                                                 AND specgroups.YearIndex=$yearindex)) ON (user.Username=specialmem.Username), " .
                 "       classterm, classlist LEFT JOIN subjectstudent ON classlist.Username=subjectstudent.Username AND " .
                 "       subjectstudent.SubjectIndex = $subjectindex " .
                 "WHERE  user.Username = classlist.Username " .
                 "AND    subjectstudent.Username IS NULL " .
                 "AND    classlist.ClassTermIndex = classterm.ClassTermIndex " .
                 "AND    classterm.TermIndex = $termindex " .
                 "AND    classterm.ClassIndex = {$_POST['class']} ";
            if ($showNew == "checked") // Add appropriate filter according to radio button that has been selected
                $query .= "AND newmem.Username IS NOT NULL ";
            elseif ($showOld == "checked")
                $query .= "AND newmem.Username IS NULL ";
            elseif ($showSpec == "checked")
                $query .= "AND specialmem.Username IS NOT NULL ";
            elseif ($showReg == "checked")
                $query .= "AND specialmem.Username IS NULL ";
            $query .= "ORDER BY user.Username";
            $nres = &  $db->query($query);
            if (DB::isError($nres))
                die($nres->getDebugInfo()); // Check for errors in query

            while ( $nrow = & $nres->fetchRow(DB_FETCHMODE_ASSOC) ) {
                $addUserName = $nrow['Username'];
                $res = &  $db->query(
                                "SELECT Username FROM subjectstudent " .
                                 "WHERE Username     = \"$addUserName\" " .
                                 "AND   SubjectIndex = $subjectindex");
                if (DB::isError($res))
                    die($res->getDebugInfo()); // Check for errors in query
                if ($res->numRows() == 0) {
                    $res = & $db->query(
                                    "INSERT INTO subjectstudent (Username, SubjectIndex) VALUES " .
                             "                           (\"$addUserName\", $subjectindex)");
                    if (DB::isError($res))
                        die($res->getDebugInfo()); // Check for errors in query
                    log_event($LOG_LEVEL_ADMIN, "admin/subject/modify_list_action.php",
                              $LOG_ADMIN, "Added $addUserName to subject $subject.");
                }
            }
        }
        include "admin/subject/modify_list.php";
    } elseif ($_POST["actiont"] == ">") { // If > was pressed, remove students from
        foreach ( $_POST['removefromteacherlist'] as $remUserName ) { // subject
            $res = &  $db->query(
                    "DELETE FROM subjectteacher " .
                             "WHERE Username     = \"$remUserName\" " .
                             "AND   SubjectIndex = $subjectindex");
            if (DB::isError($res))
                die($res->getDebugInfo()); // Check for errors in query
            log_event($LOG_LEVEL_ADMIN, "admin/subject/modify_list_action.php",
                      $LOG_ADMIN, "Removed $remUserName from teaching subject $subject.");
        }
        include "admin/subject/modify_list.php";
    } elseif ($_POST["actiont"] == "<") {
        foreach ( $_POST['addtoteacherlist'] as $addUserName ) { // class
            $res = &  $db->query(
                    "SELECT Username FROM subjectteacher " .
                             "WHERE Username     = \"$addUserName\" " .
                             "AND   SubjectIndex = $subjectindex");
            if (DB::isError($res))
                die($res->getDebugInfo()); // Check for errors in query
            if ($res->numRows() == 0) {
                $res = & $db->query(
                                "INSERT INTO subjectteacher (Username, SubjectIndex) VALUES " .
                         "                           (\"$addUserName\", $subjectindex)");
                if (DB::isError($res))
                    die($res->getDebugInfo()); // Check for errors in query
            }
            log_event($LOG_LEVEL_ADMIN, "admin/subject/modify_list_action.php",
                      $LOG_ADMIN, "Set $addUserName as a teacher for subject $subject.");
        }
        include "admin/subject/modify_list.php";
    } elseif ($_POST["action"] == "Done") {
        redirect($nextLink);
    } else {
        include "admin/subject/modify_list.php";
    }
} else {
    /* Log unauthorized access attempt */
    log_event($LOG_LEVEL_ERROR, "admin/subject/modify_list_action.php",
              $LOG_DENIED_ACCESS, "Attempted to modify subject $subject.");

    $noJS = true;
    $noHeaderLinks = true;
    $title = "LESSON - Unauthorized access!";

    include "header.php";

    echo "      <p align=\"center\">You do not have permission to access this page. <a href=" .
         "\"$nextLink\">Click here to continue.</a></p>\n";

    include "footer.php";
}

?>
