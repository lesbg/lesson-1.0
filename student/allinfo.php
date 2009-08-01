<?php
	/*****************************************************************
	 * student/allinfo.php  (c) 2004-2007 Jonathan Dieter
	 *
	 * Print information about how student is doing in all classes
	 *****************************************************************/
	 
	$studentusername = safe(dbfuncInt2String($_GET["key"]));
	$studentname     = dbfuncInt2String($_GET["keyname"]);
	$showtype        = dbfuncInt2String($_GET["show"]);
	
	if($showtype == "u") {
		$title = "Homework for $studentname";
	} elseif($showtype == "l") {
		$title = "Late assignments for $studentname";
	} elseif($showtype == "m") {
		$title = "Marked assignments for $studentname";
	} elseif($showtype == "t") {
		$title = "Today's homework for $studentname";
	} else {
		$title = "All assignments for $studentname";
	}

	/* Key wasn't included.  The only time I've seen this happen is when a student doesn't logout and lets
	 *  another student use their computer, so we'll force a logout */
	if(!isset($_GET['key'])) {
		log_event($LOG_LEVEL_ACCESS, "student/allinfo.php", $LOG_ERROR,
					"Page was accessed without key (Make sure user logged out).");
		include "user/logout.php";
		exit(0);
	}

	include "header.php";
	
	if($is_admin or $studentusername == $username) {
		include "core/settermandyear.php";
		include "core/titletermyear.php";

		$query =	"SELECT assignment.CategoryListIndex FROM assignment, subjectstudent, subject " .
					"WHERE  assignment.SubjectIndex  = subjectstudent.SubjectIndex " .
					"AND    subjectstudent.Username = '$studentusername' " .
					"AND    subject.SubjectIndex    = subjectstudent.SubjectIndex " .
					"AND    subject.YearIndex       = $yearindex " .
					"AND    subject.TermIndex       = $termindex " .
					"AND    assignment.CategoryListIndex IS NOT NULL";
		$res =&  $db->query($query);
		if(DB::isError($res)) die($res->getDebugInfo());           // Check for errors in query
		
		if($res->numRows() > 0) {
			$has_categories = True;
		} else {
			$has_categories = False;
		}

		$query =		"SELECT Title, Date, DueDate, AssignmentIndex, Description, DescriptionData, " .
						"       DescriptionFileType, AverageType, ShowAverage, SubjectIndex, " .
						"       Uploadable, Weight, Score, Percentage, Comment, StudentSubjectAverage, " .
						"       CanModify, CategoryName FROM view_marks " .
						"WHERE Username = '$studentusername' " .
						"AND   Hidden = 0 ";
		if($showtype == "u") {
			$query .=	"AND   Score IS NULL " .
						"AND   AverageType != $AVG_TYPE_NONE ";
		} elseif($showtype == "m") {
			$query .=	"AND   Score IS NOT NULL ";
		} elseif($showtype == "l") {
			$query .=	"AND   Score = $MARK_LATE ";
					"AND   AverageType == $AVG_TYPE_PERCENT ";
		} elseif($showtype == "t") {
			$query .=	"AND   Score IS NULL " .
					"AND   AverageType != $AVG_TYPE_NONE " .
					"AND   Date = DATE(NOW()) ";
		}
		$query .=		"AND   YearIndex = $yearindex " .
						"AND   TermIndex = $termindex " .
						"ORDER BY Date DESC, AssignmentIndex DESC";

		$res =&  $db->query($query);
		if(DB::isError($res)) die($res->getDebugInfo());           // Check for errors in query
		
		/* Print assignments and scores */
		if($res->numRows() > 0) {
			echo "      <table align='center' border='1'>\n"; // Table headers
			echo "         <tr>\n";
			if($is_local and ($is_admin or $studentusername == $username)) {
				echo "            <th>&nbsp;</th>\n";
			}
			echo "            <th>Title</th>\n";
			echo "            <th>Subject</th>\n";
			echo "            <th>Teacher</th>\n";
			if($has_categories) echo "            <th>Category</th>\n";
			echo "            <th>Date</th>\n";
			echo "            <th>Due Date</th>\n";
			echo "            <th>Score</th>\n";
			echo "            <th>Comment</th>\n";
			echo "         </tr>\n";
			
			/* For each assignment, print subject, teacher, assignment title, date, score, and any comments */
			$alt_count = 0;
			while ($row =& $res->fetchRow(DB_FETCHMODE_ASSOC)) {
				$can_modify = $row['CanModify'];
				
				$alt_count += 1;
				if($alt_count % 2 == 0) {
					$alt_step = "alt";
				} else {
					$alt_step = "std";
				}
				$alt = " class='$alt_step'";
				if($row['AverageType'] == $AVG_TYPE_PERCENT) {
					if($row['Score'] == $MARK_LATE and $can_modify == 1) {
						$alt = " class='late-$alt_step'";
					} elseif(is_null($row['Score']) and $can_modify == 1) {
						$alt = " class='unmarked-$alt_step'";
					}
				}  elseif($row['AverageType'] == $AVG_TYPE_INDEX) {
					if(is_null($row['Score']) and $can_modify == 1) {
						$alt = " class='unmarked-$alt_step'";
					}
				}
				echo "         <tr$alt>\n";
				if($is_local and ($is_admin or $studentusername == $username)) {
					if($row['Uploadable'] == 1 and (is_null($row['Score']) or $row['Score'] == $MARK_LATE) and $currentterm == $termindex and $currentyear == $yearindex and $can_modify) {
						$uploadlink   = "index.php?location=" .  dbfuncString2Int("student/upload.php") .
										"&amp;key=" .            dbfuncString2Int($row['AssignmentIndex']) .
										"&amp;keyname=" .        dbfuncString2Int($name) .
										"&amp;key2=" .           dbfuncString2Int($studentusername) .
										"&amp;key2name=" .       dbfuncString2Int($subject);
						$uploadbutton = dbfuncGetButton($uploadlink,  "U", "small", "", "Upload homework onto server");
						echo "            <td>$uploadbutton</td>\n";
					} else {
						echo "            <td>&nbsp;</td>\n";
					}
				}
				$aclass = "";
				if($row['AverageType'] == $AVG_TYPE_PERCENT) {
					if($row['Score'] == $MARK_LATE and $can_modify == 1) {
						$aclass = " class='late'";
					} elseif(is_null($row['Score']) and $can_modify == 1) {
						$aclass = " class='unmarked'";
					}
				} elseif($row['AverageType'] == $AVG_TYPE_INDEX) {
					if(is_null($row['Score']) and $can_modify == 1) {
						$aclass = " class='unmarked'";
					}
				}
				
				if(is_null($row['DescriptionFileType'])) {
					if(is_null($row['Description'])) {
						echo "            <td>{$row['Title']}</td>\n";
					} else {
						$newwin = "index.php?location=" . dbfuncString2Int("student/descr.php") .
								"&amp;key=" .               dbfuncString2Int($row['AssignmentIndex']);
						echo "          <td><a$aclass href='javascript:popup(&quot;$newwin&quot;)'>{$row['Title']}</a></td>\n";
					}
				} else {
					$newwin = "index.php?location=" . dbfuncString2Int("student/open_descr.php") .
							"&amp;key=" .               dbfuncString2Int($row['AssignmentIndex']);
					echo "          <td><a$aclass href='$newwin'>{$row['Title']}</a></td>\n";
				}

				echo "            <td>{$row['SubjectName']}</td>\n";   // Name of class
				
				/* Print name(s) of teacher(s) */
				echo "            <td>";
				$query =	"SELECT user.Title, user.FirstName, user.Surname FROM user, subjectteacher " .
							"WHERE subjectteacher.SubjectIndex = {$row['SubjectIndex']} " .
/*							"AND   subjectteacher.ShowTeacher  = '1' " .*/
							"AND   user.Username               = subjectteacher.Username";
				$teacherRes =& $db->query($query);
				if(DB::isError($teacherRes)) die($teacherRes->getDebugInfo());          // Check for errors in query
				if($teacherRow =& $teacherRes->fetchRow(DB_FETCHMODE_ASSOC)) {
					$teacherRow['Title']     = htmlspecialchars($teacherRow['Title']);
					$teacherRow['FirstName'] = htmlspecialchars($teacherRow['FirstName']);
					$teacherRow['Surname']   = htmlspecialchars($teacherRow['Surname']);
					$teacherp = "{$teacherRow['Title']} {$teacherRow['FirstName']} {$teacherRow['Surname']}";
					
					/* If there's more than one teacher, separate with commas */
					while ($teacherRow =& $teacherRes->fetchRow(DB_FETCHMODE_ASSOC)) {
						$teacherRow['Title']     = htmlspecialchars($teacherRow['Title']);
						$teacherRow['FirstName'] = htmlspecialchars($teacherRow['FirstName']);
						$teacherRow['Surname']   = htmlspecialchars($teacherRow['Surname']);
						$teacherp .= ", {$teacherRow['Title']} {$teacherRow['FirstName']} {$teacherRow['Surname']}";
					}
				}
				if(strlen($teacherp) > 30) {
					echo substr($teacherp, 0, 27) . "...</td>\n";
				} else {
					echo "$teacherp</td>\n";
				}
				
				if($has_categories) {
					if(is_null($row['CategoryName'])) {
						echo "<td><i>None</i></td>\n";
					} else {
						echo "<td>{$row['CategoryName']}</td>\n";
					}
				}
				$dateinfo = date($dateformat, strtotime($row['Date']));
				if(isset($row['DueDate'])) {
					$duedateinfo = date($dateformat, strtotime($row['DueDate']));
				} else {
					$duedateinfo = "";
				}
				echo "            <td>$dateinfo</td>\n";
				echo "            <td>$duedateinfo</td>\n";
				
				if($row['AverageType'] == $AVG_TYPE_PERCENT) {
					if($row['Score'] == $MARK_LATE) {
						if($can_modify == 1) {
							echo "            <td>&nbsp;</td>\n";
						} else {
							echo "            <td>0%</td>\n";
						}
					} elseif($row['Score'] == $MARK_ABSENT) {
						echo "            <td align='center'><i>Absent</i></td>\n";
					} elseif($row['Score'] == $MARK_EXEMPT) {
						echo "            <td align='center'><i>Exempt</i></td>\n";
					} elseif(is_null($row['Score'])) {
						if($can_modify == 1) {
							echo "            <td>&nbsp;</td>\n";
						} else {
							echo "            <td align='center'><i>Exempt</i></td>\n";
						}
					} else {
						$score = round($row['Percentage']);
						echo "            <td>$score%</td>\n";
					}
					if($row['Score'] == $MARK_LATE) {
						if($row['Comment'] == "" or is_null($row['Comment'])) {
							echo "            <td>Late</td>\n";
						} else {
							echo "            <td>{$row['Comment']}</td>\n";
						}
					} else {
						echo "            <td>{$row['Comment']}</td>\n";
					}
				} elseif($row['AverageType'] == $AVG_TYPE_INDEX) {
					if(!isset($row['AverageTypeIndex']) or $row['AverageTypeIndex'] == "" or !isset($row['Score']) or $row['Score'] == "") {
						$score = "N/A";
					} else {
						$query =	"SELECT Input, Display FROM nonmark_index " .
									"WHERE NonmarkTypeIndex = {$row['AverageTypeIndex']} " .
									"AND   NonmarkIndex     = {$row['Score']}";
						$sres =& $db->query($query);
						if(DB::isError($sres)) die($sres->getDebugInfo());           // Check for errors in query
						if($srow =& $sres->fetchRow(DB_FETCHMODE_ASSOC)) {
							$score = $srow['Display'];
						} else {
							$score = "N/A";
						}
					}
					echo "            <td>$score</td>\n";
					echo "            <td>{$row['Comment']}</td>\n";
				} else {
					echo "            <td>N/A</td>\n";
					echo "            <td>{$row['Comment']}</td>\n";
				}
				echo "         </tr>\n";
			}
			echo "      </table>\n";               // End of table
		} else {
			if($showtype == "u") {
				echo "      <p>No homework.</p>\n";
			} elseif($showtype == "l") {
				echo "      <p>No late assignments.</p>\n";
			} else {
				echo "      <p>No assignments.</p>\n";
			}
		}
		log_event($LOG_LEVEL_EVERYTHING, "student/allinfo.php", $LOG_STUDENT,
					"Viewed all of $studentname's assignments.");
	} else {
		/* Log unauthorized access attempt */
		log_event($LOG_LEVEL_ERROR, "student/allinfo.php", $LOG_DENIED_ACCESS,
					"Tried to access $studentname ($studentusername)'s marks.");
		
		echo "      <p>You do not have permission to access this page</p>\n";
		echo "      <p><a href='$backLink'>Click here to go back</a></p>\n";
	}
	
	include "footer.php";
?>