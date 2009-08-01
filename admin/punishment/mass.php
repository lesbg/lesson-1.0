<?php
	/*****************************************************************
	 * admin/punishment/mass.php  (c) 2006 Jonathan Dieter
	 *
	 * Create a punishment that applies to many students at once
	 *****************************************************************/

	$title            = "Issue punishment for many students at once";

	$link             = "index.php?location=" . dbfuncString2Int("admin/punishment/mass_action.php") .
						"&amp;next=" .          $_GET['next'];

	$query =    "SELECT Permissions FROM disciplineperms WHERE Username=\"$username\"";
	$res =&  $db->query($query);
	if(DB::isError($res)) die($res->getDebugInfo());           // Check for errors in query
	if($row =& $res->fetchRow(DB_FETCHMODE_ASSOC)) {
		$perm = $row['Permissions'];
	} else {
		$perm = 0;
	}
	
	$showalldeps = true;
	include "core/settermandyear.php";
	include "header.php";                                    // Show header
	$showyear = false;
	$showterm = false;
	include "core/titletermyear.php";
	if(dbfuncGetPermission($permissions, $PERM_ADMIN) or $perm >= $PUN_PERM_MASS) {
		log_event($LOG_LEVEL_EVERYTHING, "admin/punishment/mass.php", $LOG_TEACHER,
					"Starting new mass punishment.");
		if(!isset($punish_list)) {
			$punish_list = array();
			$punish_str = "";
		} else {
			ksort($punish_list);
			$punish_str = dbfuncArray2String($punish_list);
		}
		echo "      <form action=\"$link\" method=\"post\" name=\"punishment\">\n"; // Form method
		echo "         <table align=\"center\" border=\"1\">\n";
		echo "            <tr>\n";
		echo "               <th>Punished students</th>\n";
		echo "               <th>Unpunished students</th>\n";
		echo "            </tr>\n";
		echo "            <tr class=\"std\">\n";
		
		/* Get list of students in subject and store in option list */
		echo "               <td>\n";
		echo "                  <select name=\"removefrompunishment[]\" style=\"width: 398px;\" multiple size=16>\n";
		foreach($punish_list as $studentusername => $student) {
			if($student != "") {
				echo "                     <option value=\"{$studentusername}\">{$student}\n";
			}
		}
		echo "                  </select>\n";
		echo "               </td>\n";
		
		/* Create listboxes with classes */
		echo "               <td>\n";
		echo "                  List Class: <select name=\"class\" onchange=\"punishment.submit()\">\n";
		$res =&  $db->query("SELECT ClassIndex, ClassName FROM class " .
							"WHERE YearIndex = $yearindex " .
							"AND   DepartmentIndex = $depindex " .
							"ORDER BY Grade, ClassName");
		if(DB::isError($res)) die($res->getDebugInfo());           // Check for errors in query
		while ($row =& $res->fetchRow(DB_FETCHMODE_ASSOC)) {
			if(!isset($_POST['class'])) $_POST['class'] = $row['ClassIndex'];
			echo "                     <option value=\"{$row['ClassIndex']}\"";
			if($_POST['class'] == $row['ClassIndex']) echo " selected";
			echo ">{$row['ClassName']}\n";
		}
		echo "                  </select>\n";
		echo "                  <noscript>\n"; // No javascript compatibility
		echo "                     <input type=\"submit\" name=\"action\" value=\"Update\" \>\n";
		echo "                  </noscript><br>\n";
		
		/* Get list of students who are in the active class */
		echo "                  <select name=\"addtopunishment[]\" style=\"width: 398px;\" multiple size=14>\n";
		if ($_POST['class'] != "") {
			$_POST['class'] = intval($_POST['class']);
			$selected_classes_sql = "";
			foreach($punish_list as $studentusername=>$student) {
				if(substr($studentusername, 0, 1) == "!") {
					$classindex = intval(substr(strrchr($studentusername, "!"), 1));
					$selected_classes_sql .= "OR classlist.ClassIndex=$classindex ";
				}
			}
			$query =        "SELECT user.FirstName, user.Surname, user.Username FROM " .
							"       user, classlist " .
							"WHERE  user.Username = classlist.Username " .
							"AND    classlist.ClassIndex = {$_POST['class']} " .
							"AND    NOT (1 = 0 " .
							"            $selected_classes_sql " .
							"           ) " .
							"ORDER BY user.Username";
			$res =&  $db->query($query);
			if(DB::isError($res)) die($res->getDebugInfo());           // Check for errors in query
			
			while ($row =& $res->fetchRow(DB_FETCHMODE_ASSOC)) {
				if(!isset($punish_list[$row['Username']])) {
					echo "                     <option value=\"{$row['Username']}\">{$row['Username']} - {$row['FirstName']} " .
															"{$row['Surname']}\n";
				}
			}
		}
		echo "                  </select>\n";
		echo "               </td>\n";
		echo "            </tr>\n";
		echo "            <tr class=\"alt\">\n";
		echo "               <td align=\"center\">\n";
		echo "                  <input type=\"submit\" name=\"action\" value=\"&gt;&gt;\">\n";
		echo "                  <input type=\"submit\" name=\"action\" value=\"&gt;\">\n";
		echo "               </td>\n";
		echo "               <td align=\"center\">\n";
		echo "                  <input type=\"submit\" name=\"action\" value=\"&lt;\">\n";
		echo "                  <input type=\"submit\" name=\"action\" value=\"&lt;&lt;\">\n";
		echo "               </td>\n";
		echo "            </tr>\n";
		echo "         </table>\n";
		echo "         <table border=\"0\" class=\"transparent\" align=\"center\" width=\"800px\">\n";
		echo "            <tr>\n";
		echo "               <td>\n";
		echo "                  What punishment would you like to give?<br>\n";
		$query =	"SELECT disciplinetype.DisciplineType, disciplineweight.DisciplineWeightIndex " .
					"       FROM disciplinetype, disciplineweight " .
					"WHERE  disciplinetype.DisciplineTypeIndex = disciplineweight.DisciplineTypeIndex " .
					"AND    disciplineweight.YearIndex = $yearindex " .
					"AND    disciplineweight.TermIndex = $termindex " .
					"AND    disciplineweight.DisciplineWeight > 0 " .
					"ORDER BY disciplineweight.DisciplineWeight";
		$res =&  $db->query($query);
		if(DB::isError($res)) die($res->getDebugInfo());           // Check for errors in query
		$count = 0;
		while($row =& $res->fetchRow(DB_FETCHMODE_ASSOC)) {
			if(isset($_POST['type'])) {
				if($_POST['type'] == $row['DisciplineWeightIndex']) {
					$default = "checked";
				} else {
					$default = "";
				}
			} else {
				$count += 1;
				if($count == 1) {
					$default = "checked";
				} else {
					$default = "";
				}
			}
			echo "                  <label for=\"type{$row['DisciplineWeightIndex']}\">\n";
			echo "                  <input type=\"radio\" name=\"type\" value=\"{$row['DisciplineWeightIndex']}\" id=\"type{$row['DisciplineWeightIndex']}\" $default>\n";
			echo "                     {$row['DisciplineType']}\n";
			echo "                  </label><br>\n";
		}
		echo "                  <br>\n";
		if(isset($_POST['date'])) {
			$dateinfo = $_POST['date'];
		} else {
			$dateinfo = date($dateformat);
		}
		echo "                  Date of rule violation?<br>\n";
		echo "                  <input type=\"text\" name=\"date\" value=\"$dateinfo\" id=\"datetext\">\n";
		echo "               </td>\n";
		echo "               <td>\n";
		echo "                  Reason for punishment?<br>\n";
		echo "                  <select name=\"reason\">\n";
		if(isset($_POST['reason'])) {
			if($_POST['reason'] == "other") {
				$default = "selected";
			} else {
				$default = "";
			}
		} else {
			$default = "selected";
		}
		echo "                  <option value=\"other\" $default>\n";
		echo "                     Other...\n";
		$query =	"SELECT DisciplineReasonIndex, DisciplineReason FROM disciplinereason " .
					"ORDER BY DisciplineReasonIndex";
		$res =&  $db->query($query);
		if(DB::isError($res)) die($res->getDebugInfo());           // Check for errors in query
		while($row =& $res->fetchRow(DB_FETCHMODE_ASSOC)) {
			if(isset($_POST['reason'])) {
				if($_POST['reason'] == $row['DisciplineReasonIndex']) {
					$default = "selected";
				} else {
					$default = "";
				}
			} else {
				$default = "";
			}
			echo "                  <option value=\"{$row['DisciplineReasonIndex']}\" $default>";
			echo                                   "{$row['DisciplineReasonIndex']} - {$row['DisciplineReason']}\n";
		}
		echo "                  </select><br>\n";
		if(isset($_POST['reasonother'])) {
			$reason = $_POST['reasonother'];
		} else {
			$reason = "";
		}
		echo "                  Other: <input type=\"text\" name=\"reasonother\" id=\"reasonothertext\" value=\"$reason\">\n";
		echo "                  <br>\n";
		echo "                  <br>\n";
		echo "                  <br>\n";
		echo "                  <br>\n";
		echo "               </td>\n";
		echo "            </tr>\n";
		echo "         </table>\n";
		echo "         <p align=\"center\">\n";
		echo "            <input type=\"hidden\" name=\"punished\" value=\"$punish_str\">\n";
		echo "            <input type=\"submit\" name=\"action\" value=\"Issue Punishment\">&nbsp; \n";
		echo "            <input type=\"submit\" name=\"action\" value=\"Cancel\">&nbsp; \n";
		echo "         </p>\n";
		echo "      </form>\n";
		//foreach($punish_list as $studentusername=>$student) {
		//	echo "      <p>$studentusername = $student</p>\n";
		//}
	} else {  // User isn't authorized to create a punishment
		/* Log unauthorized access attempt */
		log_event($LOG_LEVEL_ERROR, "admin/punishment/mass.php", $LOG_DENIED_ACCESS,
					"Tried to create mass punishments.");

		echo "      <p>You do not have permission to access this page</p>\n";
		echo "      <p><a href=\"$backLink\">Click here to go back</a></p>\n";
	}
	
	include "footer.php";
?>