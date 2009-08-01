<?php
	/*****************************************************************
	 * teacher/casenote/watchlist/delete_confirm.php  (c) 2006 Jonathan Dieter
	 *
	 * Confirm deletion of student from teacher's watchlist
	 *****************************************************************/

	/* Get variables */
	$student          = dbfuncInt2String($_GET['keyname']);
	$studentusername  = safe(dbfuncInt2String($_GET['key']));

	$title         = "LESSON - Confirm to remove from watchlist";
	$noJS          = true;
	$noHeaderLinks = true;
	
	include "core/settermandyear.php";
	include "header.php";
	
	/* Check whether student is on current user's watchlist */
	$res =&  $db->query("SELECT WorkerUsername FROM casenotewatch " .
						"WHERE WorkerUsername=\"$username\" " .
						"AND   StudentUsername=\"$studentusername\"");
	if(DB::isError($res)) die($res->getDebugInfo());         // Check for errors in query

	if($res->numRows() > 0) {
		$link     = "index.php?location=" . dbfuncString2Int("teacher/casenote/watchlist/delete.php") .
					"&amp;key=" .           $_GET['key'] .
					"&amp;keyname=" .       $_GET['keyname'];
		
		echo "      <p align=\"center\">Are you <strong>sure</strong> you want to remove " .
		                               "$student from your casenote watchlist.  " .
		                               "You will no longer be informed when a new casenote " .
		                               "is created for this student.</p>\n";
		echo "      <form action=\"$link\" method=\"post\">\n";
		echo "         <p align=\"center\">";
		echo "            <input type=\"submit\" name=\"action\" value=\"Yes, remove from my watchlist\" \>&nbsp; \n";
		echo "            <input type=\"submit\" name=\"action\" value=\"No, I changed my mind\" \>&nbsp; \n";
		echo "         </p>";
		echo "      </form>\n";
	} else {
		$nextLink = "index.php?location=" . dbfuncString2Int("teacher/casenote/watchlist/list.php");
		echo "      <p>$student is not in your watchlist.  <a href=\"$nextLink\">Click here to continue</a>.</p>\n";
	}
	
	include "footer.php";
?>