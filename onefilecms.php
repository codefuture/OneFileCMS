<?php
// OneFileCMS - http://onefilecms.com/
// Version 1.1.7 
// For license & copyright info, see OneFileCMS.License.BSD.txt


if( phpversion() < '5.0.0' ) { exit("OneFileCMS requires PHP5 to operate. Please contact your host to upgrade your PHP installation."); };

// CONFIGURATION INFO
	$config['version']	= "1.1.7"; // ONEFILECMS_BEGIN
	$config['address']	= $_SERVER["SCRIPT_NAME"];
// Array of users. Format: array("username","md5_password")
	$config['users']	= array(
								array("username",md5("password")),
								array("admin",md5("password"))
								);

	$config['title']	= "OneFileCMS";
	$config['footer']	= date("Y")." <a href='http://onefilecms.com/'>OneFileCMS</a>.";
	$config['disabled']	= array("bmp","ico","gif","jpg","png","psd","zip","exe","swf"); // file types you can't edit
	$config['excluded']	= array(); // files to exclude from directory listings (ie. array("passwords.txt","imageOFme.jpg");)
	$config['cssfile']	= "onefilecms.css"; // the css file name


//Allows OneFileCMS.php to be started from any dir on the site.
	chdir($_SERVER["DOCUMENT_ROOT"]);


///////////////////////////////////////////////////////////////////////
////// DO NOT EDIT BEYOND THIS IF YOU DONT KNOW WHAT YOU'RE DOING /////
///////////////////////////////////////////////////////////////////////

// Here we go...
	session_start();

//////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////

// config fix
	$config['disabled'] = array_flip($config['disabled']);
	$config['excluded'] = array_flip($config['excluded']);

// page var's
	$page = "index";
	$pagetitle = "/";

// entitize get params
	foreach ($_GET as $name => $value) {
		$_GET[$name] = htmlentities($value);
	}

// get user inputs
	$params['mode'] = isset($_GET['p'])?$_GET['p']:false;
	$params['path'] = isset($_GET['i'])?$_GET['i']:'';

// check for login post
	if (isset($_POST["login"])){
	// check posted password and username
		if(isset($_POST["onefilecms_username"],$_POST["onefilecms_password"]) && check_credentials(md5($_POST["onefilecms_username"].md5($_POST["onefilecms_password"])))) {
			$_SESSION['onefilecms_hash'] = md5($_POST["onefilecms_username"].md5($_POST["onefilecms_password"]));
		} else {
			$message = 'Invalid username or password';
		}
	}

// check if we are loged in, if not set page to login
	if(!isset($_SESSION['onefilecms_hash']) || !check_credentials($_SESSION['onefilecms_hash'])){
		$_SESSION['onefilecms_hash'] = '';
		$page = "login";
		$pagetitle = "Log In";
	}

	if (!empty($params['path'])) $pagetitle = "/".$params['path']."/";

	if ($params['mode']) {
		// redirect on invalid page attempts
		$page = $params['mode'];
		if (!in_array(strtolower($params['mode']), array("copy","delete","error","deletefolder","edit","folder","index","login","logout","new","other","rename","renamefolder","upload"))){
			header("Location: ".$config['address']);
		}
		
		if ($params['mode'] == "other") $pagetitle = "Other";
		if ($params['mode'] == "logout") {
			$pagetitle = "Log Out";
			$_SESSION['onefilecms_hash'] = '';
			session_destroy();
		}
	}




// COPY FILE *******************************************************************
if (isset($_GET["c"])) {
	$filename = $_GET["c"]; $pagetitle = "Copy &ldquo;".$filename."&rdquo;";  $page = "copy";
}

if (isset($_POST["copy_filename"]) && check_credentials($_SESSION['onefilecms_hash'],$_POST["sessionid"])) {
	$old_filename = $_POST["old_filename"];
	$filename = $_POST["copy_filename"];
	copy($old_filename, $filename);
	$message = '<b>'.$old_filename."</b> copied successfully to <b>".$filename."</b>.";
}



// DELETE FILE *****************************************************************
if (isset($_GET["d"])) {
	$filename = $_GET["d"];
	$pagetitle = "Delete &ldquo;".$filename."&rdquo;";
	$page = "delete";
}
if (isset($_POST["delete_filename"]) && check_credentials($_SESSION['onefilecms_hash'],$_POST["sessionid"])) {
	$filename = $_POST["delete_filename"];
	unlink($filename);
	$message = '<b>'.$filename."</b> successfully deleted.";
}



// DELETE FOLDER ***************************************************************
if ($params['mode'] == "deletefolder") {
	$pagetitle = "Delete Folder &ldquo;".$params['path']."&rdquo;";
}
if (isset($_POST["delete_foldername"]) && check_credentials($_SESSION['onefilecms_hash'],$_POST["sessionid"])) {
	$foldername = $_POST["delete_foldername"];
	if (@rmdir($foldername)) {
		$message = '<b>'.$foldername."</b> successfully deleted.";
	} else {
		$message = "That folder is not empty.";
	}
}



/*************************
 * EDIT FILE PAGE 
 ************************/
if (isset($_POST["filename"]) && check_credentials($_SESSION['onefilecms_hash'],$_POST["sessionid"])) {
	$filename = $_POST["filename"];
	$content = stripslashes($_POST["content"]);
	$fp = @fopen($filename, "w");
	if ($fp) {
		fwrite($fp, $content);
		fclose($fp);
	}
	$message = '<b>'.$filename."</b> saved successfully.";
}
if (isset($_GET["f"])) {
	$filename = stripslashes($_GET["f"]);
	$page = "edit";
	$pagetitle = "Edit &ldquo;".$filename."&rdquo;";
	if (isset($config['disabled'][pathinfo($filename, PATHINFO_EXTENSION)])) {
		$loadcontent = 'Sorry you can not edit this file, but you can rename, move, copy and delete the file.';
		$loadcontentbutton = ' disabled="disabled"';
	}else{
		if (file_exists($filename)) {
			$loadcontentbutton = '';
			$fp = @fopen($filename, "r");
			if (filesize($filename) !== 0) {
				$loadcontent = fread($fp, filesize($filename));
				$loadcontent = htmlspecialchars($loadcontent);
			}
			fclose($fp);
		} else {
			$page = "error";
			unset ($filename);
			$message = "File does not exist.";
		}
	}
}



// NEW FILE ********************************************************************
if ($params['mode'] == "new") {$pagetitle = "New File"; }
if (isset($_POST["new_filename"]) && check_credentials($_SESSION['onefilecms_hash'],$_POST["sessionid"])) {
	$filename = $_POST["new_filename"];
	if (file_exists($filename)) {
		$message = '<b>'.$filename."</b> not created. A file with that name already exists.";
	} else {
		$handle = fopen($filename, 'w') or die("can't open file");
		fclose($handle);
		$message = '<b>'.$filename."</b> created successfully.";
	}
}



// NEW FOLDER ******************************************************************
if ($params['mode'] == "folder") {$pagetitle = "New Folder"; }
if (isset($_POST["new_folder"]) && check_credentials($_SESSION['onefilecms_hash'],$_POST["sessionid"])) {
	$foldername = $_POST["new_folder"];
	if (!is_dir($foldername)) {
		mkdir($foldername);
		$message = '<b>'.$foldername."</b> created successfully.";
	} else {
		$message = "A folder by that name already exists.";
	}
}



// RENAME FILE *****************************************************************
if (isset($_GET["r"])) {
	$filename = $_GET["r"];
	$pagetitle = "Rename &ldquo;".$filename."&rdquo;";
	$page = "rename";
}
if (isset($_POST["rename_filename"]) && check_credentials($_SESSION['onefilecms_hash'],$_POST["sessionid"])) {
	$old_filename = $_POST["old_filename"];
	$filename = $_POST["rename_filename"];
	rename($old_filename, $filename);
	$message = '<b>'.$old_filename."</b> successfully renamed to <b>".$filename."</b>.";
}



// RENAME FOLDER ***************************************************************
if ($params['mode'] == "renamefolder") {$pagetitle = "Rename Folder &ldquo;".$params['path']."&rdquo;"; }
if (isset($_POST["rename_foldername"]) && check_credentials($_SESSION['onefilecms_hash'],$_POST["sessionid"])) {
	$old_foldername = $_POST["old_foldername"];
	$foldername = $_POST["rename_foldername"];
	if (rename($old_foldername, $foldername)) {
		$message = '<b>'.$old_foldername."</b> unsuccessfully renamed to <b>".$foldername."</b>.";
	} else {
		$message = "There was an error. Try again and/or contact your admin.";
	}
}



// UPLOAD FILE *****************************************************************
if ($params['mode'] == "upload") {$pagetitle = "Upload File"; }
if (isset($_FILES['upload_filename']['name']) && check_credentials($_SESSION['onefilecms_hash'],$_POST["sessionid"])) {
	$filename = $_FILES['upload_filename']['name'];
	$destination = $_POST["upload_destination"];
	if(move_uploaded_file($_FILES['upload_filename']['tmp_name'],
	$destination.basename($filename))) {
		$message = '<b>'.basename($filename)."</b> uploaded successfully to <b>".$destination."</b>.";
	} else{
		$message = "There was an error. Try again and/or contact your admin.";
	}
}



//******************************************************************************
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta name="robots" content="noindex">
	<title><?php echo $config['title'].' - '.$pagetitle; ?></title>
	<link href="<?php echo $config['cssfile'];?>" type="text/css" rel="stylesheet" />
</head>
<body class="page_<?php echo $page; ?>">
	<div class="container">
		<div class="header">
			<?php echo '<a href="'.$config['address'].'" id="logo" >'.$config['title']; ?></a>
			<?php if (check_credentials($_SESSION['onefilecms_hash'])): ?>
				<div class="nav">
					<a href="/">Visit Site</a> | 
					<a href="<?php echo $config['address']; ?>">Index</a> | 
					<a href="<?php echo $config['address']; ?>?p=logout">Log Out</a>
				</div>
			<?php endif; ?>
		</div>
		<?php echo (isset($message)?'<div id="message"><p>'.$message.'</p></div>':'');?>
<?php


// COPY FILE *******************************************************************
if ($page == "copy") { 
	$extension = strrchr($filename, ".");
	$slug = substr($filename, 0, strlen($filename) - strlen($extension));
	$varvar = "?i=".substr($_GET["c"],0,strrpos($_GET["c"],"/")); ?>
	<h2>Copy &ldquo;<a href="/<?php echo $filename; ?> "> <?php echo $filename; ?> </a> &rdquo;</h2>
	<p>Existing files with the same filename are automatically overwritten... Be careful!</p>
	<form method="post" id="new" action="<?php echo $config['address'].$varvar; ?>">
		<input type="hidden" name="old_filename" value="<?php echo $filename; ?>" />
		<p><label>Old filename:</label><input type="text" name="dummy" value="<?php echo $filename; ?>" class="textinput" disabled="disabled" /></p>
		<p><label for="copy_filename">New filename:</label><input type="text" name="copy_filename" id="copy_filename" class="textinput" value="<?php echo $slug."_".date("mdyHi").$extension; ?>" />	</p>
		<?php Cancel_Submit_Buttons("Copy"); ?>
	</form>
<?php }



// DELETE FILE *****************************************************************
if ($page == "delete") {
	$varvar = "?i=".substr($_GET["d"],0,strrpos($_GET["d"],"/")); ?>
	<h2>Delete &ldquo;<a href="/<?php echo $filename; ?> " >
	<?php echo $filename; ?></a>&rdquo;</h2>
	<p>Are you sure?</p>
	<form method="post" action="<?php echo $config['address'].$varvar; ?>">
		<input type="hidden" name="delete_filename" value="<?php echo $filename; ?>" />
		<?php Cancel_Submit_Buttons("DELETE"); ?>
	</form>
<?php }



// DELETE FOLDER ***************************************************************
if ($page == "deletefolder") { ?>
	<h2>Delete Folder &ldquo;<?php echo $params['path']; ?>&rdquo;</h2>
	<p>Folders have to be empty before they can be deleted.</p>
	<form method="post" action="<?php echo $config['address']."?i=".substr($params['path'],0,strrpos(substr_replace($params['path'],"",-1),"/")); ?>">
		<input type="hidden" name="delete_foldername" value="<?php echo $params['path']; ?>" />
		<?php Cancel_Submit_Buttons("DELETE"); ?>
	</form>
<?php }


/*************************
 * EDIT FILE PAGE 
 ************************/
 if($page == "edit"){
?>
	<h2 id="edit_header">Edit &ldquo;
	<a href="/<?php echo $filename; ?>" ><?php echo $filename; ?></a>&rdquo;</h2>
	<form method="post" action="<?php echo $config['address'].'?f='.$filename; ?>">
		<input type="button" class="button close" name="close" value="Close" onclick="parent.location='<?php echo $config['address'].'?i='.substr($_GET["f"],0,strrpos($_GET["f"],"/")); ?>'" />
		<input type="hidden" name="sessionid" value="<?php echo session_id(); ?>" />
		<input type="hidden" name="filename" id="filename" class="textinput" value="<?php echo (empty($loadcontentbutton)?$filename:''); ?>" />
		<p><textarea name="content" class="textinput" cols="70" rows="25"><?php echo $loadcontent; ?></textarea></p>
		<p class="buttons_right">
			<input type="submit" class="button" name="save_file" id="save_file" value="Save" <?php echo $loadcontentbutton;?> />
			<input type="button" class="button" name="rename_file" value="Rename/Move" onclick="parent.location='<?php echo $config['address'].'?r='.$filename; ?>'" />
			<input type="button" class="button" name="delete_file" value="Delete" onclick="parent.location='<?php echo $config['address'].'?d='.$filename; ?>'" />
			<input type="button" class="button" name="copy_file" value="Copy" onclick="parent.location='<?php echo $config['address'].'?c='.$filename; ?>'" />
			<input type="button" class="button" name="close" value="Close" onclick="parent.location='<?php echo $config['address'].'?i='.substr($_GET["f"],0,strrpos($_GET["f"],"/")); ?>'" />
		</p>
		<div class="meta">
			<p><i>File Size:</i> <?php echo round(filesize($filename)/1000,2); ?> kb - <i>Last Updated:</i> <?php echo date("n/j/y g:ia", filemtime($filename)); ?></p>
		</div>
	</form>
	<div style="clear:both;"></div>
<?php
}




// INDEX ***********************************************************************
if ($page == "index") {
	$varvar = "";
	if (!empty($params['path'])) { $varvar = $params['path']."/"; }

 	// Current path. ie: docroot/current/path/ 
	// Each level is a link to that level.
	echo '<h2>';
		$full_path = basename(getcwd());
		if (!empty($params['path'])) { $full_path = basename(getcwd()).'/'.$params['path']; }

		$path_levels = explode("/",$full_path);
		$levels = count($path_levels); //If levels=3, indexes = 0, 1, 2  etc...

		//docroot folder of site
		if (empty($params['path'])) { 
			echo $path_levels[0].' /'; // if at root, no need for link.
		} else {
			echo '<a href="'.$config['address'].'" class="path"> '.$path_levels[0].' </a>/';
		}

		//Remainder of current/path
		$current_path = '';
		for ($x=1; $x < $levels; $x++) {
			if ($x !== 1){ $current_path .= '/'; }
			$current_path = $current_path.$path_levels[$x];
			echo '<a href="'.$config['address'].'?i='.$current_path.'" class="path"> ';
			echo ' '.$path_levels[$x]." </a>/";
		}
	?></h2>


	<!--===== List folders/sub-directores =====-->
	<p class="index_folders">
		<?php
		$files = glob($varvar."*",GLOB_ONLYDIR);
		sort($files);
		foreach ($files as $file) {
			echo '<a href="'.$config['address'].'?i='.$file.'" class="folder">'.basename($file).'</a>';
		} ?>
	</p>
	

	<!--============= List files ==============-->
	<div style="clear:both;"></div>
	<ul class="index">
		<?php
		$files = glob($varvar."{,.}*", GLOB_BRACE); sort($files);
		foreach ($files as $file) {
			if (!is_dir($file) && !isset($config['excluded'][pathinfo($file, PATHINFO_BASENAME)])) {
				$file_class = pathinfo($file, PATHINFO_EXTENSION);
				if (in_array($file_class,array("jpg","gif","png","ico"))) $file_class = "img";
		?>
		<li>
			<a href="<?php echo $config['address'].'?f='.$file; ?>" class="<?php echo $file_class; ?>"><?php echo basename($file); ?></a>
			<div class="meta">
				<span><i>File Size:</i><?php echo round(filesize($file)/1000,2);?> kb<br /></span>
				<span><i>Last Updated:</i><?php echo date("n/j/y g:ia", filemtime($file)); ?></span>
			</div>
		</li>
		<?php
			} // endif
		}// foreach
	?>
	</ul>

	<!--=== Upload/New/Rename/Copy/etc... links ===-->
	<p class="front_links">
		<a href="<?php echo $config['address'].'?p=upload&amp;i='.$varvar; ?>" class="upload">Upload File</a>
		<a href="<?php echo $config['address'].'?p=new&amp;i='.$varvar; ?>" class="new">New File</a>
		<a href="<?php echo $config['address'].'?p=folder&amp;i='.$varvar; ?>" class="newfolder">New Folder</a>
		<?php if ($varvar !== "") { ?>
			<a href="<?php echo $config['address'].'?p=deletefolder&amp;i='.$varvar; ?>" class="deletefolder">Delete Folder</a>
			<a href="<?php echo $config['address'].'?p=renamefolder&amp;i='.$varvar; ?>" class="renamefolder">Rename Folder</a>
		<?php } ?>
		<a href="<?php echo $config['address']; ?>?p=other" class="other">Other</a>
	</p>
<?php }


// LOG IN **********************************************************************
if ($page == "login") { ?>
	<h2>Log In</h2>
	<form method="post" action="<?php echo $config['address']; ?>">
		<p><label for="onefilecms_username">Username:</label><input type="text" name="onefilecms_username" id="onefilecms_username" class="login_input" /></p>
		<p><label for="onefilecms_password">Password:</label><input type="password" name="onefilecms_password" id="onefilecms_password" class="login_input" /></p>
		<input class="button" type="submit" name="login" value="Login" />
	</form>
<?php }


// LOG OUT *********************************************************************
if ($page == "logout") { ?>
	<h2>Log Out</h2>
	<p>You have successfully been logged out and may close this window.</p>
<?php }


// NEW FILE ********************************************************************
if ($page == "new") {?>
		<h2>New File</h2>
		<p>Existing files with the same name will not be overwritten.</p>
		<form method="post" id="new" action="<?php echo $config['address'].(!empty($params['path'])?"?i=".substr_replace($params['path'],"",-1):''); ?>">
			<p><label for="new_filename">New filename: </label><input type="text" name="new_filename" id="new_filename" class="textinput" value="<?php echo $params['path']; ?>" /></p>
			<?php Cancel_Submit_Buttons("Create"); ?>
		</form>
<?php }


// NEW FOLDER ******************************************************************
if ($page == "folder") {?>
	<h2>New Folder</h2>
	<p>Existing folders with the same name will not be overwritten.</p>
	<form method="post" action="<?php echo $config['address'].(!empty($params['path'])?"?i=".substr_replace($params['path'],"",-1):''); ?>">
		<p><label for="new_folder">Folder name: </label><input type="text" name="new_folder" id="new_folder" class="textinput" value="<?php echo $params['path']; ?>" /></p>
		<?php Cancel_Submit_Buttons("Create"); ?>
	</form>
<?php }


// OTHER ***********************************************************************
if ($page == "other") { ?>
	<h2>Other</h2>

	<h3>Check for Updates</h3>
	<p>You are using version <?php echo $config['version']; ?>.<br>
	Future versions of OneFileCMS may have a one-click upgrade process.
	For now, though,<a href="https://github.com/Self-Evident/OneFileCMS">&gt;check here&lt;</a> for current versions.</p>

	<h3>Want some good Karma?</h3>
	<p>Let people know you use OneFileCMS by putting this in your footer:</p>
	<pre><code>This site managed with &#60;a href="http://onefilecms.com/"&#62;OneFileCMS&#60;/a&#62;.</code></pre>

	<h3>Admin Link</h3>
	<p>Add this to your footer (or something) for lazy/forgetful admins. They'll still have to know the username and password, of course.</p>
	<pre><code>[&#60;a href="<?php echo $config['address']; ?>"&#62;Admin&#60;/a&#62;]</code></pre>
<?php }


// RENAME FILE *****************************************************************
if ($page == "rename") {
	$varvar = "?i=".substr($_GET["r"],0,strrpos($_GET["r"],"/")); ?>
	<h2>Rename &ldquo;<a href="/<?php echo $filename; ?>"><?php echo $filename; ?></a>&rdquo;</h2>
	<p>Existing files with the same filename are automatically overwritten... Be careful!</p>
	<p>To move a file, preface its name with the folder's name, as in "<i>foldername/filename.txt</i>." The folder must already exist.</p>
	<form method="post" action="<?php echo $config['address'].$varvar;?>">
		<input type="hidden" name="old_filename" value="<?php echo $filename; ?>" />
		<p><label>Old filename:</label><input type="text" name="dummy" value="<?php echo $filename; ?>" class="textinput" disabled="disabled" /></p>
		<p><label for="rename_filename">New filename:</label><input type="text" name="rename_filename" id="rename_filename" class="textinput" value="<?php echo $filename; ?>" /></p>
		<?php Cancel_Submit_Buttons("Rename"); ?>
	</form>
<?php }


// RENAME FOLDER ***************************************************************
if ($page == "renamefolder") {
	$varvar = "?i=".substr($params['path'],0,strrpos(substr_replace($params['path'],"",-1),"/")); ?>
	<h2>Rename Folder &ldquo;<?php echo $params['path']; ?>&rdquo;</h2>
	<form method="post" action="<?php echo $config['address'].$varvar; ?>">
		<input type="hidden" name="old_foldername" value="<?php echo $params['path']; ?>" />
		<p><label>Old name:</label><input type="text" name="dummy" value="<?php echo $params['path']; ?>" class="textinput" disabled="disabled" /></p>
		<p><label for="rename_foldername">New name:</label><input type="text" name="rename_foldername" id="rename_foldername" class="textinput" value="<?php echo $params['path']; ?>" /></p>
		<?php Cancel_Submit_Buttons("Rename"); ?>
	</form>
<?php }


// UPLOAD FILE *****************************************************************
if ($page == "upload") { ?>
	<h2>Upload</h2>
	<form enctype="multipart/form-data" action="<?php echo $config['address'].(!empty($params['path'])?"?i=".substr_replace($params['path'],"",-1):''); ?>" method="post">
		<input type="hidden" name="MAX_FILE_SIZE" value="100000" />
		<p><label for="upload_destination">Destination:</label><input type="text" name="upload_destination" value="<?php echo $params['path']; ?>" class="textinput" /></p>
		<p><label for="upload_filename">File:</label><input name="upload_filename" type="file" size="93"/></p>
		<?php Cancel_Submit_Buttons("Upload"); ?>
	</form>
<?php } ?>

	<div class="footer"> <hr/>(Icons courtesy of <a href="http://www.famfamfam.com/lab/icons/silk/" target="_BLANK">FAMFAMFAM</a>)</div>

</div>
</body>
</html>
<?php

/*************************
 * FUNCTIONS
 ************************/
 // [Cancel] returns to either the current/path, or current/path/file
function Cancel_Submit_Buttons($button_label) { 
	global $varvar,$config;

	if (isset($params['path'])) $ipath = '?i='.rtrim($params['path'],"/");
	elseif (isset($_GET["c"])) $ipath = '?f='.$_GET["c"];
	elseif (isset($_GET["d"])) $ipath = '?f='.$_GET["d"];
	elseif (isset($_GET["r"])) $ipath = '?f='.$_GET["r"];
	else $ipath = rtrim($varvar,"/");
	echo '<input type="hidden" name="sessionid" value="'.session_id().'" />
	<p><input type="button" class="button" name="cancel" value="Cancel" onclick="parent.location=\''.$config['address'].$ipath.'\'" />
	<input type="submit" class="button" value="'.$button_label.'" id="action" style="margin-left: 2.5em;"></p>';

}

// check credentials, returns false if not okay
function check_credentials($hash, $sessionId=null){
	global $config;
	if(!is_null($sessionId) && $sessionId != session_id()) return false;
	foreach ($config['users'] as $user){
		if (md5($user[0].$user[1]) == $hash) return true;
	}
	return false;
}
