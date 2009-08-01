<?php
	/*****************************************************************
	 * admin/class/delete.php  (c) 2005 Jonathan Dieter
	 *
	 * Delete class from database
	 *****************************************************************/

	/* Get variables */
	$classname  = dbfuncInt2String($_GET['keyname']);
	$classindex = dbfuncInt2String($_GET['key']);
	$nextLink   = dbfuncInt2String($_GET['next']);
	
	include "core/settermandyear.php";
	
	if($_POST['action'] == "Yes, delete class") {
		$title         = "LESSON - Deleting Class";
		$noJS          = true;
		$noHeaderLinks = true;

		include "header.php";

		/* Check whether user is authorized to change scores */
		if(dbfuncGetPermission($permissions, $PERM_ADMIN)) {
			$res =&  $db->query("DELETE FROM class " .                  // Delete class
								"WHERE ClassIndex  = $classindex");
			if(DB::isError($res)) die($res->getDebugInfo());            // Check for errors in query
			
			$res =&  $db->query("DELETE FROM classlist " .              // Remove students from class
								"WHERE ClassIndex  = $classindex");
			if(DB::isError($res)) die($res->getDebugInfo());            // Check for errors in query
			
			echo "      <p align=\"center\">Class successfully deleted.</p>\n";
			echo "      <p align=\"center\"><a href=\"$nextLink\">Continue</a></p>\n";
			log_event($LOG_LEVEL_ADMIN, "admin/class/delete.php", $LOG_ADMIN,
					"Deleted class $classname.");
		} else {
			log_event($LOG_LEVEL_ERROR, "admin/class/delete.php", $LOG_DENIED_ACCESS,
					"Tried to delete class $classname.");
			echo "      <p>You do not have the authority to remove this class.  <a href=\"$nextLink\">" .
			               "Click here to continue</a>.</p>\n";
		}
	} else {
		$title         = "LESSON - Cancelling";
		$noJS          = true;
		$noHeaderLinks = true;
		$extraMeta     = "      <meta http-equiv=\"REFRESH\" content=\"0;url=$nextLink\">\n";
		
		include "header.php";
		
		echo "      <p align=\"center\">Cancelling and redirecting you to <a href=\"$nextLink\">$nextLink</a>." . 
					"</p>\n";
	}
	
	include "footer.php";
?>