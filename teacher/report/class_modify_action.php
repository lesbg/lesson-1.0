<?php
/**
 * ***************************************************************
 * teacher/report/class_modify_action.php (c) 2008, 2018 Jonathan Dieter
 *
 * Run query to change report information
 * ***************************************************************
 */

/* Get variables */
if (! isset($_GET['next']))
    $_GET['next'] = dbfuncString2Int($backLink);
$class = dbfuncInt2String($_GET['keyname']);
$student_name = dbfuncInt2String($_GET['keyname2']);
$classterm_index = dbfuncInt2String($_GET['key']);
$student_username = dbfuncInt2String($_GET['key2']);
$nextLink = dbfuncInt2String($_GET['next']); // Link to next page
$error = false; // Boolean to store any errors

include "core/settermandyear.php";
if (isset($_GET['key3']))
    $termindex = dbfuncInt2String($_GET['key3']);

    /* Check whether subject is open for report editing */
$query = $pdb->prepare(
    "SELECT classterm.AverageType, classterm.EffortType, classterm.ConductType, " .
    "       classterm.AverageTypeIndex, classterm.EffortTypeIndex, " .
    "       classterm.ConductTypeIndex, classterm.CTCommentType, " .
    "       classterm.HODCommentType, classterm.PrincipalCommentType, " .
    "       classterm.CanDoReport, classterm.AbsenceType, class.DepartmentIndex, " .
    "       department.ProofreaderUsername " .
    "       FROM classterm, class, department " .
    "WHERE classterm.ClassTermIndex    = :classterm_index " .
    "AND   class.ClassIndex = classterm.ClassIndex " .
    "AND   department.DepartmentIndex = class.DepartmentIndex "
);
$query->execute(['classterm_index' => $classterm_index]);

if (!$row = $query->fetch() or $row['CanDoReport'] == 0) {
    /* Print error message */
    $title = "LESSON - Error";

    include "header.php";
    echo "      <p>Reports for this class aren't open.</p>\n";
    echo "      <p><a href='$backLink'>Click here to go back</a></p>\n";
    log_event($LOG_LEVEL_ERROR, "teacher/report/class_modify_action.php",
            $LOG_DENIED_ACCESS, "Tried to modify report for $subject.");

    include "footer.php";
    exit(0);
}

$include = "teacher/report/class_modify.php";
$do_include = true;
$average_type = $row['AverageType'];
$absence_type = $row['AbsenceType'];
$effort_type = $row['EffortType'];
$conduct_type = $row['ConductType'];
$ct_comment_type = $row['CTCommentType'];
$hod_comment_type = $row['HODCommentType'];
$pr_comment_type = $row['PrincipalCommentType'];
$can_do_report = $row['CanDoReport'];
$average_type_index = $row['AverageTypeIndex'];
$effort_type_index = $row['EffortTypeIndex'];
$conduct_type_index = $row['ConductTypeIndex'];
$proof_username = $row['ProofreaderUsername'];

$is_principal = check_principal($username);
$is_hod = check_hod_classterm($username, $classterm_index);
$is_ct = check_class_teacher_classterm($username, $classterm_index);

/* Check whether user is proofreader */
if ($proof_username == $username) {
    $is_proofreader = true;
} else {
    $is_proofreader = false;
}

if (!$is_ct and !$is_hod and !$is_principal and !$is_admin and !$is_proofreader) {
    $noJS = true;
    $noHeaderLinks = true;
    include "header.php"; // Show header

    echo "      <p>You do not have permission to access this page</p>\n";
    echo "      <p><a href='$backLink'>Click here to go back</a></p>\n";
    log_event($LOG_LEVEL_ERROR, "teacher/report/class_modify_action.php",
            $LOG_DENIED_ACCESS, "Tried to modify report for $subject.");

    include "footer.php";
    exit(0);
}

$query = $pdb->prepare(
    "SELECT MIN(subjectstudent.ReportDone) AS ReportDone " .
    "       FROM subject, subjectstudent, classterm, class " .
    "WHERE subjectstudent.Username      = :student_username " .
    "AND   subjectstudent.SubjectIndex  = subject.SubjectIndex " .
    "AND   classterm.ClassTermIndex     = :classterm_index " .
    "AND   subject.TermIndex            = classterm.TermIndex " .
    "AND   class.ClassIndex             = classterm.ClassIndex " .
    "AND   subject.YearIndex            = class.YearIndex " .
    "GROUP BY subjectstudent.Username"
);
$query->execute(['student_username' => $student_username,
                 'classterm_index' => $classterm_index]);


$subject_report_done = 1;
if ($row = $query->fetch())
    $subject_report_done = $row['ReportDone'];

$query = $pdb->prepare(
    "SELECT classlist.classlistIndex, user.Gender, user.FirstName, user.Surname, " .
    "       classlist.Average, classlist.Conduct, classlist.Effort, " .
    "       classlist.Rank, classlist.CTComment, classlist.HODComment, " .
    "       classlist.CTCommentDone, classlist.HODCommentDone, " .
    "       classlist.PrincipalComment, classlist.PrincipalCommentDone, " .
    "       classlist.PrincipalUsername, classlist.HODUsername, " .
    "       classlist.ReportDone, classlist.Absences, " .
    "       average_index.Display AS AverageDisplay, " .
    "       effort_index.Display AS EffortDisplay, " .
    "       conduct_index.Display AS ConductDisplay " .
    "       FROM user, classlist " .
    "       LEFT OUTER JOIN nonmark_index AS average_index ON " .
    "            classlist.Average = average_index.NonmarkIndex " .
    "       LEFT OUTER JOIN nonmark_index AS effort_index ON " .
    "            classlist.Effort = effort_index.NonmarkIndex " .
    "       LEFT OUTER JOIN nonmark_index AS conduct_index ON " .
    "            classlist.Conduct = conduct_index.NonmarkIndex " .
    "WHERE classlist.Username       = :student_username " .
    "AND   user.Username            = :student_username " .
    "AND   classlist.ClassTermIndex = :classterm_index "
);
$query->execute(['student_username' => $student_username,
                 'classterm_index' => $classterm_index]);

if (!$row = $query->fetch()) {
    /* Print error message */
    $noJS = true;
    $noHeaderLinks = true;
    include "header.php"; // Show header

    echo "      <p>No report.</p>\n";
    echo "      <p><a href='$backLink'>Click here to go back</a></p>\n";

    include "footer.php";
    exit(0);
}

$student_info = $row;

$new_student = "";
$change_subject = "";

foreach ($_POST as $postkey => $postval) {
    if (substr($postkey, 0, 8) == "student_" and ($postval == "<<" or $postval == ">>")) {
        $new_student = safe(substr($postkey, 8));
    } elseif (substr($postkey, 0, 5) == "edit_" and $postval == "Change") {
        $change_subject = substr($postkey, 5);
    } elseif ($postkey == "ct_comment" or $postkey == "hod_comment" or
             $postkey == "pr_comment") {
        $startloc = strpos($postval, '{');
        while ( $startloc !== false ) {
            $endloc = strpos($postval, '}');
            if ($endloc === false) {
                $postval = str_replace('{', '(', $postval);
                $startloc = strpos($postval, '{');
                continue;
            }
            if ($endloc < $startloc) {
                $postval = substr_replace($postval, ')', $endloc, 1);
                $startloc = strpos($postval, '{');
                continue;
            }
            $nextloc = strpos($postval, '{', $startloc + 1);
            if ($nextloc !== false and $nextloc < $endloc) {
                $postval = substr_replace($postval, '(', $startloc, 1);
                $startloc = strpos($postval, '{');
                continue;
            }
            $replaceval = substr($postval, $startloc + 1,
                                $endloc - ($startloc + 1));
            if ($replaceval == "") {
                $postval = str_replace('{}', '()', $postval);
                $startloc = strpos($postval, '{');
                continue;
            }
            if (strval(intval($replaceval)) != $replaceval) {
                $postval = str_replace("{" . $replaceval . "}", "($replaceval)",
                                    $postval);
                $startloc = strpos($postval, '{');
                continue;
            }

            $comment_array = get_comment($student_username, $replaceval);

            if ($comment_array === false) {
                $postval = str_replace("{" . $replaceval . "}", "($replaceval)",
                                    $postval);
                $startloc = strpos($postval, '{');
                continue;
            }

            $comment = $comment_array[0];

            $postval = str_replace("{" . $replaceval . "}", $comment, $postval);

            $startloc = strpos($postval, '{');
        }
        $postval = str_replace('}', ')', $postval);
        $postval = trim($postval);
        $_POST[$postkey] = $postval;
    }
}

if ($_POST['action'] == "Update" or $_POST['action'] == "Close report" or
     $_POST['action'] == "Finished with comments" or
     $_POST['action'] == "Edit comments" or
     $_POST['action'] == "Done with report" or $new_student != "" or
     $change_subject != "") {
    if ($student_info['ReportDone']) {
        if ($_POST['action'] == "Update" or $change_subject != "") {
            $title = "LESSON - Saving changes...";
            $noHeaderLinks = true;
            $noJS = true;

            include "header.php";

            echo "      <p align='center'>Saving changes...</p>\n";
            echo "      <p align='center'>This report has been closed.  Please open it if you want to change anything.</p>\n";
            echo "      <p align='center'>failed</p>";
            echo "      <p align='center'><a href='$nextLink'>Continue</a></p>\n"; // Link to next page
            include "footer.php";
        } elseif ($new_student != "" or $_POST['action'] == "Done with report") {
            if ($_POST['action'] == "Done with report") {
                $query = "UPDATE classlist SET ReportProofDone=1 " .
                         "WHERE  classlistIndex={$student_info['classlistIndex']}";
                $res = &  $db->query($query);
                if (DB::isError($res))
                    die($res->getDebugInfo()); // Check for errors in query

                if (isset($_POST['studentnext'])) {
                    $new_student = safe($_POST['studentnext']);
                } elseif (isset($_POST['studentprev'])) {
                    $new_student = safe($_POST['studentprev']);
                } else {
                    $nextLink = "index.php?location=" .
                                 dbfuncString2Int("user/main.php");
                    $noJS = true;
                    $noHeaderLinks = true;
                    $title = "LESSON - Finished";

                    include "header.php";

                    echo "      <p align='center'>There are no reports left to proofread.</p>\n";
                    echo "      <p align='center'><a href='$nextLink'>Click here to continue</a></p>\n";

                    include "footer.php";
                    exit(0);
                }
            }
            $query = $pdb->prepare(
                "SELECT user.FirstName, user.Surname " .
                "FROM user " .
                "WHERE user.Username = :new_student"
            );
            $query->execute(['new_student' => $new_student]);
            if ($row = $query->fetch()) {
                $_GET['key2'] = dbfuncString2Int($new_student);
                $_GET['keyname2'] = dbfuncString2Int("{$row['FirstName']} {$row['Surname']} ($new_student)");
                unset($_POST);
            }
            include "teacher/report/class_modify.php";
        } else {
            unset($_POST);
            include "teacher/report/class_modify.php";
        }
        exit(0);
    }
    if ($_POST['action'] == "Update") {
        $title = "LESSON - Saving changes...";
        $noHeaderLinks = true;
        $noJS = true;

        include "header.php";

        echo "      <p align='center'>Saving changes...";
    }

    if ($is_ct or $is_hod or $is_principal or $is_admin) {
        $average = "-1";
        if (isset($_POST["average"])) {
            if ($average_type == $CLASS_AVG_TYPE_PERCENT) {
                $scorestr = $_POST["average"];
                if (strval(intval($scorestr)) != $scorestr) {
                    $average = "-1";
                } elseif (intval($scorestr) > 100) {
                    $average = "100";
                } elseif (intval($scorestr) < 0) {
                    $average = "0";
                } else {
                    $average = $scorestr;
                }
            } elseif ($average_type == $CLASS_AVG_TYPE_INDEX) {
                $average = get_nonmark_index($_POST['average'], $average_type_index);
            }
        } else {
            $average = NULL;
        }

        $effort = "-1";
        if (isset($_POST["effort"])) {
            if ($effort_type == $CLASS_EFFORT_TYPE_PERCENT) {
                $scorestr = $_POST["effort"];
                if (strval(intval($scorestr)) != $scorestr) {
                    $effort = "-1";
                } elseif (intval($scorestr) > 100) {
                    $effort = "100";
                } elseif (intval($scorestr) < 0) {
                    $effort = "0";
                } else {
                    $effort = $scorestr;
                }
            } elseif ($effort_type == $CLASS_EFFORT_TYPE_INDEX) {
                $effort = get_nonmark_index($_POST['effort'], $effort_type_index);
            }
        } else {
            $effort = NULL;
        }

        $conduct = "-1";
        if (isset($_POST["conduct"])) {
            if ($conduct_type == $CLASS_CONDUCT_TYPE_PERCENT) {
                $scorestr = $_POST["conduct"];
                if (strval(intval($scorestr)) != $scorestr) {
                    $conduct = "-1";
                } elseif (intval($scorestr) > 100) {
                    $conduct = "100";
                } elseif (intval($scorestr) < 0) {
                    $conduct = "0";
                } else {
                    $conduct = $scorestr;
                }
            } elseif ($conduct_type == $CLASS_CONDUCT_TYPE_INDEX) {
                $conduct = get_nonmark_index($_POST['conduct'], $conduct_type_index);
            }
        } else {
            $conduct = NULL;
        }

        $absences = "-1";
        if (isset($_POST["absences"])) {
            if ($absence_type == $ABSENCE_TYPE_NUM) {
                $scorestr = $_POST["absences"];
                if (strval(intval($scorestr)) != $scorestr) {
                    $absences = "-1";
                } elseif (intval($scorestr) < 0) {
                    $absences = "0";
                } else {
                    $absences = $scorestr;
                }
            }
        } else {
            $absences = NULL;
        }
    } else {
        $average = NULL;
        $effort = NULL;
        $conduct = NULL;
        $absences = NULL;
    }

    $ct_comment = NULL;
    $hod_comment = NULL;
    $pr_comment = NULL;

    if ($ct_comment_type == $COMMENT_TYPE_MANDATORY or
         $ct_comment_type == $COMMENT_TYPE_OPTIONAL) {
        if (isset($_POST["ct_comment"])) {
            $ct_comment = trim($_POST["ct_comment"]);
        }
    }
    if ($ct_comment == "")
        $ct_comment = NULL;

    if ($hod_comment_type == $COMMENT_TYPE_MANDATORY or
         $hod_comment_type == $COMMENT_TYPE_OPTIONAL) {
        if (isset($_POST["hod_comment"])) {
            $hod_comment = trim($_POST["hod_comment"]);
        }
    }
    if ($hod_comment == "")
        $hod_comment = NULL;

    if ($pr_comment_type == $COMMENT_TYPE_MANDATORY or
         $pr_comment_type == $COMMENT_TYPE_OPTIONAL) {
        if (isset($_POST["pr_comment"])) {
            $pr_comment = trim($_POST["pr_comment"]);
        }
    }
    if ($pr_comment == "")
        $pr_comment = NULL;

    $query = "UPDATE classlist SET ";
    $qvars = array();
    if (($average_type == $CLASS_AVG_TYPE_INDEX or
         $average_type == $CLASS_AVG_TYPE_PERCENT) and ! is_null($average)) {
        $query .= "       Average              = :average, ";
        $qvars['average'] = $average;
    }
    if ($effort_type == $CLASS_EFFORT_TYPE_INDEX or
         $effort_type == $CLASS_EFFORT_TYPE_PERCENT and ! is_null($effort)) {
        $query .= "       Effort               = :effort, ";
        $qvars['effort'] = $effort;
    }
    if ($conduct_type == $CLASS_CONDUCT_TYPE_INDEX or
         $conduct_type == $CLASS_CONDUCT_TYPE_PERCENT and ! is_null($conduct)) {
        $query .= "       Conduct              = :conduct, ";
        $qvars['conduct'] = $conduct;
    }
    if ($absence_type == $ABSENCE_TYPE_NUM and ! is_null($absences)) {
        $query .= "       Absences             = :absences, ";
        $qvars['absences'] = $absences;
    }
    if (($ct_comment_type == $COMMENT_TYPE_MANDATORY or
         $ct_comment_type == $COMMENT_TYPE_OPTIONAL) and
         ! $student_info['CTCommentDone'] and ! is_null($ct_comment)) {
        $query .= "       CTComment            = :ct_comment, " .
                  "       CTCommentDone        = 0, ";
        $qvars['ct_comment'] = $ct_comment;
    }
    if (($hod_comment_type == $COMMENT_TYPE_MANDATORY or
         $hod_comment_type == $COMMENT_TYPE_OPTIONAL) and
         ($is_hod or $is_principal or $is_admin or $is_proofreader) and
         ! $student_info['HODCommentDone'] and ! is_null($hod_comment)) {
        $query .= "       HODComment           = :hod_comment, ";
        $qvars['hod_comment'] = $hod_comment;
        if ($is_hod) {
            $query .= "       HODUsername          = :hod_username, ";
            $qvars['hod_username'] = $username;
        } else {
            $query .= "       HODUsername          = NULL, ";
        }
        $query .= "       HODCommentDone       = 0, ";
    }
    if (($pr_comment_type == $COMMENT_TYPE_MANDATORY or
         $pr_comment_type == $COMMENT_TYPE_OPTIONAL) and
         ($is_admin or $is_principal or $is_proofreader) and
         ! $student_info['PrincipalCommentDone'] and ! is_null($pr_comment)) {
        $query .= "       PrincipalComment     = :pr_comment, ";
        $qvars['pr_comment'] = $pr_comment;
        if ($is_principal) {
            $query .= "       PrincipalUsername    = :pr_username, ";
            $qvars['pr_username'] = $username;
        } else {
            $query .= "       PrincipalUsername    = NULL, ";
        }
        $query .= "       PrincipalCommentDone = 0, ";
    }
    $query .= "       ReportDone = 0 " .
              " WHERE classlist.classlistIndex = :classlist_index ";
    $qvars['classlist_index'] = $student_info['classlistIndex'];
    $query = $pdb->prepare($query);
    $query->execute($qvars);

    if ($_POST['action'] == "Update") {
        echo "done.</p>\n";
        echo "      <p align='center'><a href='$nextLink'>Continue</a></p>\n"; // Link to next page
        $do_include = false;
    } elseif ($_POST['action'] == "Finished with comments") {
        if ($is_ct and $ct_comment_type != $COMMENT_TYPE_NONE and
                 (!is_null($ct_comment) or
                 $ct_comment_type != $COMMENT_TYPE_MANDATORY)) {
            $pdb->prepare(
                "UPDATE classlist SET CTCommentDone=1 " .
                "WHERE classlistIndex = :classlist_index"
            )->execute(['classlist_index' => $student_info['classlistIndex']]);
        }
        if ($is_hod and $hod_comment_type != $COMMENT_TYPE_NONE and
             (!is_null($hod_comment) or $hod_comment_type != $COMMENT_TYPE_MANDATORY)) {
            $pdb->prepare(
                "UPDATE classlist SET HODCommentDone=1 " .
                "WHERE classlistIndex = :classlist_index"
            )->execute(['classlist_index' => $student_info['classlistIndex']]);
        }
        if ($is_principal and $pr_comment_type != $COMMENT_TYPE_NONE and
             (!is_null($pr_comment) or $pr_comment_type != $COMMENT_TYPE_MANDATORY)) {
            $pdb->prepare(
                "UPDATE classlist SET PrincipalCommentDone=1 " .
                "WHERE classlistIndex = :classlist_index"
            )->execute(['classlist_index' => $student_info['classlistIndex']]);
        }
        unset($_POST);
        $include = "teacher/report/class_modify.php";
    } elseif ($_POST['action'] == "Edit comments") {
        $query = "UPDATE classlist SET ";
        if ($is_admin or $is_hod or $is_principal or $is_proofreader) {
            $query .= "       HODCommentDone       = 0, ";
        }
        if ($is_admin or $is_principal or $is_proofreader) {
            $query .= "       PrincipalCommentDone = 0, ";
        }
        $query .= "       CTCommentDone        = 0 " .
                  "WHERE classlistIndex = :classlist_index";
        $query = $pdb->prepare($query);
        $query->execute(['classlist_index' => $student_info['classlistIndex']]);

        unset($_POST);
        $include = "teacher/report/class_modify.php";
    } elseif ($_POST['action'] == "Close report") {
        if ($is_hod or $is_principal or $is_admin) {
            $query = $pdb->prepare(
                "SELECT CTComment, HODComment, PrincipalComment FROM classlist " .
                "WHERE  classlistIndex = :classlist_index"
            );
            $query->execute(['classlist_index' => $student_info['classlistIndex']]);


            $nrow = $query->fetch();
            if ((is_null($nrow['CTComment']) and $ct_comment_type == $COMMENT_TYPE_MANDATORY) or
                 (! $student_info['CTCommentDone'] and $ct_comment_type != $COMMENT_TYPE_NONE) or
                 (is_null($nrow['HODComment']) and
                 $hod_comment_type == $COMMENT_TYPE_MANDATORY) or
                 (! $student_info['HODCommentDone'] and
                 $hod_comment_type != $COMMENT_TYPE_NONE) or
                 (is_null($nrow['PrincipalComment']) and
                 $pr_comment_type == $COMMENT_TYPE_MANDATORY) or
                 (! $student_info['PrincipalCommentDone'] and
                 $pr_comment_type != $COMMENT_TYPE_NONE) or ! $subject_report_done) {
                $title = "LESSON - Closing report...";
                $noHeaderLinks = true;
                $noJS = true;

                if (strpos($backLink, "next=") === FALSE)
                    $backLink .= "&amp;next={$_GET['next']}";

                include "header.php";

                if (is_null($nrow['CTComment']) and $ct_comment_type == $COMMENT_TYPE_MANDATORY) {
                    echo "      <p align='center'>Error: Class teacher must write a comment.</p>\n";
                }
                if (is_null($nrow['HODComment']) and $hod_comment_type == $COMMENT_TYPE_MANDATORY) {
                    echo "      <p align='center'>Error: Head of department must write a comment.</p>\n";
                }
                if (is_null($nrow['PrincipalComment']) and
                     $pr_comment_type == $COMMENT_TYPE_MANDATORY) {
                    echo "      <p align='center'>Error: Principal must write a comment.</p>\n";
                }
                if (! $student_info['CTCommentDone'] and $ct_comment_type != $COMMENT_TYPE_NONE) {
                    echo "      <p align='center'>Error: Class teacher must click &quot;Finished with comments&quot; button.</p>\n";
                }
                if (! $student_info['HODCommentDone'] and $hod_comment_type != $COMMENT_TYPE_NONE) {
                    echo "      <p align='center'>Error: Head of Department must click &quot;Finished with comments&quot; button.</p>\n";
                }
                if (! $student_info['PrincipalCommentDone'] and
                     $pr_comment_type != $COMMENT_TYPE_NONE) {
                    echo "      <p align='center'>Error: Principal must click &quot;Finished with comments&quot; button.</p>\n";
                }
                if (! $subject_report_done) {
                    echo "      <p align='center'>Error: All subjects must be finished.</p>\n";
                }
                echo "      <p align='center'><a href='$backLink'>Continue</a></p>\n"; // Link to next page
                include "footer.php";
                exit(0);
            }
            $query = "UPDATE classlist SET ";
            if (! is_null($proof_username)) {
                $query .= "       ReportProofread = 1, ";
            }
            $query .= "       ReportDone = 1 " .
                      " WHERE classlist.classlistIndex = :classlist_index ";
            $pdb->prepare($query)->execute(['classlist_index' => $student_info['classlistIndex']]);
        }
        unset($_POST);
        $include = "teacher/report/class_modify.php";
    } elseif ($new_student != "" or $_POST['action'] == "Done with report") {
        if ($_POST['action'] == "Done with report") {
            $pdb->prepare(
                "UPDATE classlist SET ReportProofread=0 " .
                "WHERE  classlistIndex=:classlist_index"
            )->execute(['classlist_index' => $student_info['classlistIndex']]);
            if (isset($_POST['studentnext'])) {
                $new_student = $_POST['studentnext'];
            } elseif (isset($_POST['studentprev'])) {
                $new_student = $_POST['studentprev'];
            } else {
                $nextLink = "index.php?location=" . dbfuncString2Int("main.php");
                $noJS = true;
                $noHeaderLinks = true;
                $title = "LESSON - Finished";

                include "header.php";

                echo "      <p align='center'>There are no reports left to proofread.</p>\n";
                echo "      <p align='center'><a href='$nextLink'>Click here to continue</a></p>\n";

                include "footer.php";
                exit(0);
            }
        }
        $query = $pdb->prepare(
            "SELECT user.FirstName, user.Surname " .
            "FROM user " .
            "WHERE user.Username = :new_student"
        );
        $query->execute(['new_student' => $new_student]);
        if ($row = $query->fetch()) {
            $_GET['key2'] = dbfuncString2Int($new_student);
            $_GET['keyname2'] = dbfuncString2Int("{$row['FirstName']} {$row['Surname']} ($new_student)");
            unset($_POST);
        }
        $include = "teacher/report/class_modify.php";
    } elseif ($change_subject != "") {
        $query = $pdb->prepare(
            "SELECT subject.Name " .
            "FROM subject " .
            "WHERE subject.SubjectIndex = :change_subject"
        );
        $query->execute(['change_subject' => $change_subject]);

        if ($row = $query->fetch()) {
            $_GET['next'] = dbfuncString2Int($curLink);
            $_GET['key'] = dbfuncString2Int($change_subject);
            $_GET['keyname'] = dbfuncString2Int($row['Name']);

            $_GET['key2'] = dbfuncString2Int($student_username);
            $_GET['keyname2'] = dbfuncString2Int($student_name);
            unset($_POST);
            $include = "teacher/report/modify.php";
        } else {
            $include = "teacher/report/class_modify.php";
        }
    }
} elseif ($_POST['action'] == "Open report") {
    $query = "UPDATE classlist SET " .
             "       ReportDone      = 0, ";
    if (! $is_proofreader) {
        $query .= "       ReportProofread = 0, ";
    }
    $query .= "       ReportProofDone = 0, " .
              "       ReportPrinted   = 0 " .
              " WHERE classlist.classlistIndex = :classlist_index ";
    $pdb->prepare($query)->execute(['classlist_index' => $student_info['classlistIndex']]);

    unset($_POST);
    $include = "teacher/report/class_modify.php";
} elseif ($_POST['action'] == "Cancel") {
    redirect($nextLink);
}

if ($do_include)
    include $include;

