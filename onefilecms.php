<?php
// OneFileCMS - http://onefilecms.com/
// Version 1.1.7 
// For license & copyright info, see OneFileCMS.License.BSD.txt


if( phpversion() < '5.0.0' ) { exit("OneFileCMS requires PHP5 to operate. Please contact your host to upgrade your PHP installation."); };

///////////////////
// CONFIGURATION
///////////////////

// Array of users. Format: array("username","md5_password")
	$config['users'] = array(
								array("username",md5("password")),
								array("admin",md5("password"))
								);

	$config['title'] = "OneFileCMS";
	$config['disabled'] = array("bmp","ico","gif","jpg","png","psd","zip","exe","swf","ttf"); // file types you can't edit
	$config['excluded'] = array("onefilecms.php"); // files to exclude from directory listings (ie. array("passwords.txt","imageOFme.jpg");)
	$config['cssExternal'] = ''; // holds the address for a external css file, to use instead of the default one.



///////////////////////////////////////////////////////////////////////
////// DO NOT EDIT BEYOND THIS IF YOU DONT KNOW WHAT YOU'RE DOING
///////////////////////////////////////////////////////////////////////

	$config['version'] = "1.1.7";
	$config['address'] = $_SERVER["SCRIPT_NAME"];

//Allows OneFileCMS to be started from any dir on the site.
	chdir($_SERVER["DOCUMENT_ROOT"]);

// Here we go...
	session_start();

///////////////////////////////////////////////////////////////////////
////// LOGIN/LOGOUT
///////////////////////////////////////////////////////////////////////

// check for login post
	if (isset($_POST["login"])){
	// check posted password and username
		if(isset($_POST["u"],$_POST["p"]) && check_credentials(md5($_POST["u"].md5($_POST["p"])))) {
			$_SESSION['onefilecms_hash'] = md5($_POST["u"].md5($_POST["p"]));
		} else {
			$message = inote("Invalid username or password",1);
		}
	}
// check for logout
	elseif (isset($_GET["logout"])) {
		$pagetitle = "Log Out";
		$_SESSION['onefilecms_hash'] = '';
		session_destroy();
		$message = inote("You have successfully been logged out and may close this window",2);
	}

// check if we are loged in, if not show login page
	if (!isset($_SESSION['onefilecms_hash']) || !check_credentials($_SESSION['onefilecms_hash'])){
		$_SESSION['onefilecms_hash'] = '';
		inc_header('login','login');
		echo (isset($message)?$message:'');
		?>
		<h2>Log In</h2>
		<form method="POST">
			<p><label for="u">Username:</label><input type="text" name="u" id="u" class="textinput" /></p>
			<p><label for="p">Password:</label><input type="password" name="p" id="p" class="textinput" /></p>
			<input class="button" type="submit" name="login" value="Login" />
		</form>
		<?
		inc_footer();
		exit;
	}

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
	
// show file size/time on index page
	$showFileTime = 0;
	if(isset($_COOKIE["ft"]) && !isset($_GET['ft'])){
		$showFileTime = $_COOKIE["ft"];
	}elseif(isset($_GET['ft'])){
		$showFileTime = ($_GET['ft']?1:0);
		setcookie("ft", $showFileTime, time()+2592000);
	}

	$showFileSize = 0;
	if(isset($_COOKIE["fs"]) && !isset($_GET['fs'])){
		$showFileSize = $_COOKIE["fs"];
	}elseif(isset($_GET['fs'])){
		$showFileSize = ($_GET['fs']?1:0);
		setcookie("fs", $showFileSize, time()+2592000);
	}


	if (!empty($params['path'])) $pagetitle = "/".$params['path']."/";

	if ($params['mode']) {
		// redirect on invalid page attempts
		$page = $params['mode'];
		if (!in_array(strtolower($params['mode']), array("copy","delete","error","deletefolder","edit","folder","index","new","about","rename","renamefolder","upload"))){
			header("Location: ".$config['address']);
		}
		
		if ($params['mode'] == "About") $pagetitle = "About";
	}


// COPY FILE *******************************************************************
if ($params['mode'] == "copy") {
	$pagetitle = "Copy &ldquo;".$params['path']."&rdquo;";
	$page = "copy";
	$pathinfo = pathinfo($params['path']);
}
if (isset($_POST["copy_filename"]) && check_credentials($_SESSION['onefilecms_hash'],$_POST["sessionid"])) {
	$old_filename = $_POST["old_filename"];
	$filename = $_POST["copy_filename"];
	copy($old_filename, $filename);
	$message = inote("<b>{$old_filename}</b> copied successfully to <b>{$filename}</b>",2);
}


// DELETE FILE *****************************************************************
if ($params['mode'] == "delete") {
	$pagetitle = "Delete &ldquo;".$params['path']."&rdquo;";
	$page = "delete";
	$pathinfo = pathinfo($params['path']);
}
if (isset($_POST["delete_filename"]) && check_credentials($_SESSION['onefilecms_hash'],$_POST["sessionid"])) {
	$filename = $_POST["delete_filename"];
	unlink($filename);
	$message = inote("<b>{$filename}</b> successfully deleted.",2);
}


// DELETE FOLDER ***************************************************************
if ($params['mode'] == "deletefolder") {
	$pagetitle = "Delete Folder &ldquo;".$params['path']."&rdquo;";
}
if (isset($_POST["delete_foldername"]) && check_credentials($_SESSION['onefilecms_hash'],$_POST["sessionid"])) {
	$foldername = $_POST["delete_foldername"];
	if (@rmdir($foldername))	$message = inote("<b>{$foldername}</b> successfully deleted.",2);
	else $message = inote("That folder is not empty.",1);
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
	$message = inote("<b>{$filename}</b> saved successfully.",2);
}
if ($params['mode'] == "edit") {
	$pagetitle = "Edit &ldquo;".$params['path']."&rdquo;";
	$page = "edit";
	$pathinfo = pathinfo($params['path']);
	$disabledSaveButton = false;
	$pathinfo['extension'] = strtolower($pathinfo['extension']);
	// open image to view
	if (in_array($pathinfo['extension'],array("jpg","gif","png","ico"))){
		$pagetitle = "Image &ldquo;".$params['path']."&rdquo;";
		$is_image = true; 
		$disabledSaveButton = true;
	}
	elseif (isset($config['disabled'][$pathinfo['extension']])) {
		$loadcontent = 'Sorry you can not edit this file, but you can rename, move, copy and delete the file.';
		$disabledSaveButton = true;
	}else{
		if (file_exists($params['path'])) {
			$loadcontent = @utf8_encode(implode("", file($params['path']))); 
			$loadcontent = htmlspecialchars($loadcontent);
		} else {
			$page = "error";
			unset ($filename);
			$message = inote("File does not exist.",1);
		}
	}
}


// NEW FILE ********************************************************************
if ($params['mode'] == "new") {$pagetitle = "New File"; }
if (isset($_POST["new_filename"]) && check_credentials($_SESSION['onefilecms_hash'],$_POST["sessionid"])) {
	$filename = $_POST["new_filename"];
	if (file_exists($filename)) {
		$message = inote("<b>{$filename}</b> not created. A file with that name already exists.",1);
	} else {
		$handle = fopen($filename, 'w') or die("can't open file");
		fclose($handle);
		$message = inote("<b>{$filename}</b> created successfully.",2);
	}
}


// NEW FOLDER ******************************************************************
if ($params['mode'] == "folder") {$pagetitle = "New Folder"; }
if (isset($_POST["new_folder"]) && check_credentials($_SESSION['onefilecms_hash'],$_POST["sessionid"])) {
	$foldername = $_POST["new_folder"];
	if (!is_dir($foldername)) {
		mkdir($foldername);
		$message = inote("<b>{$foldername}</b> created successfully.",2);
	} else {
		$message = inote("A folder by that name already exists.",1);
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
	$message = inote("<b>{$old_filename}</b> successfully renamed to <b>{$filename}</b>.",2);
}


// RENAME FOLDER ***************************************************************
if ($params['mode'] == "renamefolder") {$pagetitle = "Rename Folder &ldquo;".$params['path']."&rdquo;"; }
if (isset($_POST["rename_foldername"]) && check_credentials($_SESSION['onefilecms_hash'],$_POST["sessionid"])) {
	$old_foldername = $_POST["old_foldername"];
	$foldername = $_POST["rename_foldername"];
	if (rename($old_foldername, $foldername)) {
		$message = inote("<b>{$old_foldername}</b> successfully renamed to <b>{$foldername}</b>.",1);
	} else {
		$message = inote("There was an error. Try again and/or contact your admin.",1);
	}
}


// UPLOAD FILE *****************************************************************
if ($params['mode'] == "upload") $pagetitle = "Upload File";
if (isset($_FILES['upload_filename']['name']) && check_credentials($_SESSION['onefilecms_hash'],$_POST["sessionid"])) {
	$filename = $_FILES['upload_filename']['name'];
	$destination = $_POST["upload_destination"];
	if(move_uploaded_file($_FILES['upload_filename']['tmp_name'],$destination.basename($filename))) $message = inote("<b>".basename($filename)."</b> uploaded successfully to <b>{$destination}</b>.",2);
	else $message = inote("There was an error. Try again and/or contact your admin.",1);
}

///////////////////
// MAKE PAGE
///////////////////

	inc_header($pagetitle,$page);
	echo (isset($message)?$message:'');

// COPY FILE *******************************************************************
if ($page == "copy") { ?>
	<h2>Copy &ldquo;<a href="/<?php echo $params['path']; ?> "> <?php echo $params['path']; ?> </a> &rdquo;</h2>
	<p>Existing files with the same filename are automatically overwritten... Be careful!</p>
	<form method="post" id="new" action="<?php echo $config['address'].'?i='.$pathinfo['dirname']; ?>">
		<input type="hidden" name="old_filename" value="<?php echo $params['path']; ?>" />
		<p><label>Old filename:</label><input type="text" name="dummy" value="<?php echo $params['path']; ?>" class="textinput" disabled="disabled" /></p>
		<p><label for="copy_filename">New filename:</label><input type="text" name="copy_filename" id="copy_filename" class="textinput" value="<?php echo $pathinfo['dirname'].'/'.$pathinfo['filename']."_".date("mdyHi").'.'.$pathinfo['extension']; ?>" />	</p>
		<?php Cancel_Submit_Buttons("Copy"); ?>
	</form>
<?php }

// DELETE FILE *****************************************************************
if ($page == "delete") { ?>
	<h2>Delete &ldquo;<a href="/<?php echo $params['path']; ?> " ><?php echo $params['path']; ?></a>&rdquo;</h2>
	<p>Are you sure?</p>
	<form method="post" action="<?php echo $config['address'].'?i='.$pathinfo['dirname']; ?>">
		<input type="hidden" name="delete_filename" value="<?php echo $params['path']; ?>" />
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

// EDIT FILE PAGE 
 if($page == "edit"){
?>
<?php if(!isset($is_image)){ ?>
	<h2 id="edit_header">Edit &ldquo;<a href="/<?php echo $params['path']; ?>" ><?php echo $params['path']; ?></a>&rdquo;</h2>
<?php }else{ ?>
	<h2 id="edit_header">Image &ldquo;<a href="/<?php echo $params['path']; ?>"  rel="lightbox"><?php echo $params['path']; ?></a>&rdquo;</h2>
<?php } ?>
	<form method="post" action="<?php echo $config['address'].'?p=edit&amp;i='.$params['path']; ?>">
		<input type="hidden" name="sessionid" value="<?php echo session_id(); ?>" />
		<input type="hidden" name="filename" id="filename" class="textinput" value="<?php echo ($disabledSaveButton?'':$params['path']); ?>" />
		<?php if(!isset($is_image)){ ?>
		<p><textarea name="content" class="textinput" cols="70" rows="25"><?php echo $loadcontent; ?></textarea></p>
		<?php }else{ ?>
		<p><a href="/<?php echo $params['path'];?>" rel="lightbox"><img src="/<?php echo $params['path'];?>" class="edit_image" /></a><p>
		<?php } ?>
		<div class="meta">
			<i>File Size:</i> <?php echo format_size($params['path']); ?> <br/>
			<i>Last Updated:</i> <?php echo date("n/j/y g:ia", filemtime($params['path'])); ?>
		</div>
		<p class="buttons_right">
			<input type="submit" class="button" name="save_file" id="save_file" value="Save" <?php echo ($disabledSaveButton?' disabled="disabled"':'');?> />
			<input type="button" class="button" name="rename_file" value="Rename/Move" onclick="parent.location='<?php echo $config['address'].'?r='.$params['path']; ?>'" />
			<input type="button" class="button" name="delete_file" value="Delete" onclick="parent.location='<?php echo $config['address'].'?p=delete&amp;i='.$params['path']; ?>'" />
			<input type="button" class="button" name="copy_file" value="Copy" onclick="parent.location='<?php echo $config['address'].'?p=copy&amp;i='.$params['path']; ?>'" />
			<input type="button" class="button" name="close" value="Back" onclick="parent.location='<?php echo $config['address'].'?i='.substr($params['path'],0,strrpos($params['path'],"/")); ?>'" />
		</p>
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

	<h3>Folders<span class="line"> </span></h3>
	<ul class="index">
		<?php
		$files = glob($varvar."*",GLOB_ONLYDIR);
		sort($files);
		foreach ($files as $file) {?>
		<li><a href="<?php echo $config['address'].'?i='.$file; ?>" class="folder" title="<?php echo basename($file); ?>"><?php echo basename($file); ?></a></li>
		<?php
		}
		if(!$files){?>
		<li>No Folders Found</li>
		<?}?>
	</ul>
	
	<div style="clear:both;"></div>
	<h3>Files<span class="toolbar">
	<?php if($showFileTime){?><a href="<?php echo $config['address'].'?ft=0'.(!empty($params['path'])?'&amp;i='.$params['path']:''); ?>" class="folder" title="<?php echo basename($file); ?>">hide File time</a>
	<?php }else{ ?><a href="<?php echo $config['address'].'?ft=1'.(!empty($params['path'])?'&amp;i='.$params['path']:''); ?>" class="folder" title="<?php echo basename($file); ?>">show File time</a> 
	<?php }?>
	 | 
	<?php if($showFileSize){?><a href="<?php echo $config['address'].'?fs=0'.(!empty($params['path'])?'&amp;i='.$params['path']:''); ?>" class="folder" title="<?php echo basename($file); ?>">hide File size</a>
	<?php }else{ ?>	<a href="<?php echo $config['address'].'?fs=1'.(!empty($params['path'])?'&amp;i='.$params['path']:''); ?>" class="folder" title="<?php echo basename($file); ?>">show File size</a> 
	<?php }?>
	</span></h3>
	<ul class="index">
		<?php
		$files = glob($varvar."{,.}*", GLOB_BRACE); sort($files);
		$filesAdded = 0;
		foreach ($files as $file) {
			if (!is_dir($file) && !isset($config['excluded'][pathinfo($file, PATHINFO_BASENAME)])) {
				$filesAdded ++;
				$file_class = pathinfo($file, PATHINFO_EXTENSION);
			//	if (in_array($file_class,array("jpg","gif","png","ico"))) $file_class = "img";
			//	else
				$file_class = 'file';
		?>
		<li>
			<a href="<?php echo $config['address'].'?p=edit&amp;i='.$file; ?>" class="<?php echo $file_class; ?>" title="<?php echo basename($file); ?>"><?php echo basename($file); ?></a>
			<?php if($showFileTime || $showFileSize){?>
			<div class="meta">
				<?php if($showFileSize){?><span><i>File Size:</i> <?php echo format_size($file);?><br /></span><?php } ?>
				<?php if($showFileTime){?><span><i>Last Updated:</i><?php echo date("n/j/y g:ia", filemtime($file)); ?></span><?php } ?>
			</div>
			<?php } ?>
		</li>
		<?php
			} // endif
		}// foreach
		if(!$filesAdded){?>
		<li>No Files Found</li>
		<?}?>
	</ul>

	<div style="clear:both;"></div>
	<h3>Options<span class="line"> </span></h3>
	<ul class="front_links">
		<li><a href="<?php echo $config['address'].'?p=upload&amp;i='.$varvar; ?>" class="upload">Upload File</a></li>
		<li><a href="<?php echo $config['address'].'?p=new&amp;i='.$varvar; ?>" class="new">New File</a></li>
		<li><a href="<?php echo $config['address'].'?p=folder&amp;i='.$varvar; ?>" class="new">New Folder</a></li>
	<?php if ($varvar !== "") { ?>
		<li><a href="<?php echo $config['address'].'?p=deletefolder&amp;i='.$varvar; ?>" class="delete">Delete Folder</a></li>
		<li><a href="<?php echo $config['address'].'?p=renamefolder&amp;i='.$varvar; ?>" class="edit">Rename Folder</a></li>
	<?php } ?>
	</ul>
	<div style="clear:both;"></div>
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
if ($page == "about") { ?>
	<h2>About</h2>

	<h3>Check for Updates</h3>
	<p>You are using version <?php echo $config['version']; ?>.<br>
	Future versions of OneFileCMS may have a one-click upgrade process.
	For now, though,<a href="https://github.com/codefuture/OneFileCMS">check here</a> for current versions.</p>

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
<?php }

inc_footer(isset($is_image)?true:false);

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

// inote( note, type, return)
function inote($mynotes,$type='info',$return = true) {
	if(empty($mynotes)) return;
	if(!is_array($mynotes)) $notes[] = $mynotes;
	else $notes = $mynotes;
	switch($type){
		case 1:
			$type = 'err';
			break;
		case 2:
			$type = 'suc';
			break;
		default:
			$type = 'info';
	}
	foreach ($notes as $k=>$note){
		$notes_html = '<div id="'.$k.'_'.$type.'" class="notification '.($type=='err'?'error':($type=='suc'?'success':'information')).'"></a>'.$note.'</div>';
	}
	if($return) return $notes_html ;
	echo $notes_html ;
}

// page template functions
function inc_header($pagetitle,$pageClass){
	global $config;

	echo '<!doctype html>  
<html lang="en">  
<head>  
 	<meta charset="utf-8">  
	<meta name="robots" content="noindex">
	<title>'.$config['title'].' - '.$pagetitle.'</title>
	'.css().'
	</head>
	<body class="page_'.$pageClass.'">
		<div id="container">
			<div id="header">
				<a href="'.$config['address'].'" id="logo" >'.$config['title'].'</a>';
	if (check_credentials($_SESSION['onefilecms_hash'])){
		echo '<div class="nav">
						<a href="/">Visit Site</a> | 
						<a href="'. $config['address'].'">Index</a> | 
						<a href="'.$config['address'].'?p=about" class="other">about</a> | 
						<a href="'.$config['address'].'?logout">Log Out</a>
					</div>';
	}
	echo '</div>
		<div id="content">';
}

function inc_footer($lightBox=false){
	global $config;
	echo '</div></div><div class="footer">Powered by <a href="https://github.com/codefuture/OneFileCMS" alt="OneFileCMS" target="_blank">OneFileCMS</a><span class="right">version '.$config['version'].'</span></div>';
	if($lightBox) echo lightbox();
	echo '</body>
</html>';
}

function css(){
	global $config;
	
	if(!empty($config['cssExternal'])){
		return '<link href="'.$config['cssExternal'].'" type="text/css" rel="stylesheet" />';
	}

return '<style>
/*************************
	Reset
*************************/
html,body,div,span,applet,object,iframe,h1,h2,h3,h4,h5,h6,p,blockquote,pre,a,abbr,acronym,address,big,
cite,code,del,dfn,em,font,img,ins,kbd,q,s,samp,small,strike,strong,sub,sup,tt,var,dl,dt,dd,ol,ul,li,
fieldset,form,label,legend,table,caption,tbody,tfoot,thead,tr,th,td{border:0;outline:0;font-weight:inherit;font-style:inherit;font-size:100%;font-family:inherit;vertical-align:baseline;margin:0;padding:0;}
:focus{outline:0;}
html { overflow-y: scroll; }
ol,ul{list-style:none;}
table{border-collapse:separate;border-spacing:0;}
caption,th,td{text-align:left;font-weight:400;}
blockquote:before,blockquote:after,q:before,q:after{content:"";}
blockquote,q{quotes:"" "";}
div{position: relative;}
h1,h2,h3,h4,h5,h6{font-weight: bold;}

/*************************
	Layout
*************************/
body {font-size: 12px;line-height: 20px;background: #d5d0cc;font-family: sans-serif;color: #333;}
#container {-moz-border-radius: 5px;-webkit-border-radius: 5px;border-radius: 5px;-moz-box-shadow: 0 0 4px #333;-webkit-box-shadow: 0 0 4px #333;box-shadow: 0 0 4px #333;width: 900px; margin: 10px auto 0; padding: 20px; background-color: #fff;}
#header {height: 25px;-moz-border-radius: 3px;-webkit-border-radius: 3px;border-radius: 3px;background:#5C6E83;padding: 5px 10px 0;margin-bottom: 10px;}
#logo { font-size:30px; color: #fff;}
#content{ background: #EEE;-moz-border-radius: 3px;-webkit-border-radius: 3px;border-radius: 3px;padding: 10px;}
.hide{display:none;}

/*************************
	General formatting
*************************/
p,ul,table { margin: 0 15px 10px; }
p, li {line-height: 1.4em; }
form p { margin: 5px 0; }
a { color: #5C6E83; text-decoration: none; border: 1px solid  transparent; }
a:hover { color: #666; }
h2 { font-size: 20px;-moz-border-radius:4px;-webkit-border-radius:4px;border-radius:4px; background:#FFF;padding: 5px 10px;}
h3 {background: #EEE;color: #444;font-size: 16px;margin: 10px;padding-right: 10px;text-shadow: 1px 1px 0 #FFF;}
em, i { font-style: italic; }
strong { font-weight: bold; }
label {font-size: 14px;font-style: italic;width: 110px;display: inline-block;}
pre {background:#fff;border: 1px solid #807568;line-height: 1.25em;overflow: auto;overflow-Y: hidden;padding: 10px;margin: 5px 15px 10px;overflow: hidden;}

/*************************
	Index
*************************/
.index {margin: 0 auto;width: 868px;}
.index .meta {color: #333;font-size: 11px;height: 25px;line-height: 11px;margin-top: 3px;overflow: hidden;}
.index li {margin: 1px;width: 215px;float: left;position: relative;}
.index a {background: white;border: 1px solid #ddd;display: block;height: 15px;overflow: hidden;padding: 4px 5px 8px 30px;line-height: 20px;text-decoration: none;-moz-border-radius: 3px;-webkit-border-radius: 3px;border-radius: 3px;}
.index a:hover {background-color: #ACD1F5;border: 1px solid #aaa;}
.index a:before, .index a:after {content:"";position:absolute;left:0;}
.index a:before, .index a:after {margin:0;background:transparent;box-shadow: 0px 0px 8px rgba(255, 255, 255, 0.6) inset;}
/* File icon */
.index a.file:before {left:12px;margin-top: 2px;width:8px;height:11px;border:2px solid #666;}
.index a.file:after {border-bottom: 3px double #666;border-top: 1px solid #666;height: 1px;left: 15px;margin-top: 7px;width: 6px;}
.index a.file:hover:before, .index a.file:focus:before, .index a.file:active:before,
.index a.file:hover:after,.index a.file:focus:after,.index a.file:active:after {border-color:#333;}
/* folder icon */
.index a.folder:before {left:10px;width:6px;height:3px;margin-top:2px;background:#F6EC9B;border: 1px solid #E8CE29;-webkit-border-radius:2px 2px 0 0;-moz-border-radius:2px 2px 0 0;border-radius:2px 2px 0 0;}
.index a.folder:after {left:9px;width:16px;height:11px;margin-top:5px;background:#F8D201;border: 1px solid #E8CE29;-webkit-border-radius:0 0 2px 2px;-moz-border-radius:0 0 2px 2px;order-radius:0 0 2px 2px;}
/* images */
/* to come */

/*************************
	List view
*************************/
ul.list {width: 100%;margin-bottom: 31px;}
ul.index.list * {width: auto;height: auto;padding: 0;margin: 0;display: visible;line-height: 21px;}
ul.index.list li {float: none;clear: both;}
ul.index.list li a {display: block;float: left;background-color: transparent;background-position: top left;border: none;width: 200px;text-indent: 26px;}
ul.list .meta {display: block;float: left;height: auto;}
ul.list .meta br { display: none; }
ul.list .meta span {display: block;float: left;width: 200px;}
/* Options */
.front_links {clear: both;margin: 0 auto;width: 868px; }
.front_links li {margin: 1px;float: left;position: relative;}
.front_links a {padding: 3px;font-size: 13px;height: 16px;display: inline-block;background: white;	-moz-border-radius: 3px;-webkit-border-radius: 3px;border-radius: 3px;padding: 3px 10px 3px 21px;border: 1px solid #ddd;}
.front_links a:before, .front_links a:after {content:"";position:absolute;top:50%;left:0;}
.front_links a:before, .front_links a:after {margin:-8px 0 0;background:transparent;}
/* new file/folder */
.front_links a.new:before {left:10px;width:5px;height:15px;margin-top:-7px;background: #9adf8f;}
.front_links a.new:after {left:5px;width:15px;height:5px;margin-top:-2px;background:#9adf8f;}
.front_links a.new:hover:before, .front_links a.new:focus:before, .front_links a.new:active:before,
.front_links a.new:hover:after, .front_links a.new:focus:after, .front_links a.new:active:after {background:#fff;}
/* upload */
.front_links a.upload:before {left:5px;margin-top:-8px;border-width:0 7px 8px;border-color:#666 transparent;border-style:solid;background:transparent;}
.front_links a.upload:after {left:9px;width:6px;height:8px;margin-top:0;background:#666;}
.front_links a.upload:hover:before{border-color:#fff transparent;}
.front_links a.upload:hover:after {background:#fff;}
/* delete icon */
.front_links a.delete:before {left:10px;width:5px;height:15px;margin-top:-7px;background:red;-webkit-transform:rotate(45deg);-moz-transform:rotate(45deg);-o-transform:rotate(45deg);transform:rotate(45deg);}
.front_links a.delete:after {left:5px;width:15px;height:5px;margin-top:-2px;background:red;-webkit-transform:rotate(45deg);-moz-transform:rotate(45deg);-o-transform:rotate(45deg);transform:rotate(45deg);}
.front_links a.delete:hover:before, .front_links a.delete:focus:before, .front_links a.delete:active:before,
.front_links a.delete:hover:after, .front_links a.delete:focus:after, .front_links a.delete:active:after{background:#fff;}
/* edit/rename */
.front_links a.edit:before {left:6px;width:5px;height:5px;margin-top:2px;background:#333;-webkit-transform:skew(-10deg, -10deg);-moz-transform:skew(-10deg, -10deg);-o-transform:skew(-10deg, -10deg);transform:skew(-10deg, -10deg);}
.front_links a.edit:after {left:6px;width:13px;height:6px;border-left:1px solid #fff;background:#333;margin-top:-3px;-webkit-transform:rotate(-45deg);-moz-transform:rotate(-45deg);-o-transform:rotate(-45deg);transform:rotate(-45deg);}
.front_links a.edit:hover:before, .front_links a.edit:focus:before, .front_links a.edit:active:before{background:#fff;}
.front_links a.edit:hover:after, .front_links a.edit:focus:after, .front_links a.edit:active:after {background:#fff;border-left:1px solid #5C6E83;}
/*******/
.front_links a:hover { border: 1px solid #666; background-color: #5C6E83; color:#fff; }
form .meta {color: #666666;float: left;width: 250px;}
.textinput {-moz-border-radius: 3px;-webkit-border-radius: 3px;border-radius: 3px;border: 1px solid #ddd;padding: 2px;width: 600px;}
textarea.textinput {height:550px;padding:5px;width:869px;white-space: nowrap;overflow-y: scroll;overflow-x: scroll;}
textarea.disabled {height: 50px;}
.buttons_right {float: right;}
.buttons_right .button { margin-left: 7px;}
.button {border: 1px solid #aaa;padding: 4px 10px;background-color: #d4d4d4;cursor: pointer;font-size: 14px;-moz-border-radius: 3px;-webkit-border-radius: 3px;border-radius: 3px;}
.button:hover { background-color: #5C6E83; color:#fff;border: 1px solid #fff;}
.button[disabled]:hover { background-color: #d4d4d4; }
.toolbar{
    color: #999999;
    float: right;
    font-size: 12px;
}
.toolbar a{

}

.edit_image{
    margin: 0 auto;
    display: block;
    background: #fff;
    padding: 10px;
    border: 1px solid #ddd;
	max-width: 855px;
    -moz-border-radius: 3px;-webkit-border-radius: 3px;border-radius: 3px;
}

/*************************
	Header
*************************/
#header h1 a#logo {font-size: 28px;text-decoration: none;color: #0F0901;}
#header h1 a#logo:visited {color: #0F0901;}
#header .nav {float: right;position: relative;}
#header .nav a {color:#fff;border: 1px solid transparent;font-weight: bold;padding: .2em, .6em,.1em;}
#header .nav a:hover {color:#333;background:transparent;}

/*************************
	Footer
*************************/
.footer {font-size: 11px; margin: 0 auto 10px;text-shadow: 1px 1px 0 #F0F0F0;width: 930px;}
.footer .right{float: right;position: relative;color: #5C6E83;}

/*************************
	Login
*************************/
.page_login #container {margin-top: 5em;border: 1px solid #807568;padding: 1em;width: 360px;}
.page_login .textinput {width: 335px;}
.page_login label {display: block;margin-bottom: 2px;}
.page_login .footer {width: 360px;}

/* --- path/to/current/index --- */
.path { border: 1px solid  transparent; }
.path:hover { border: 1px solid #807568; background-color: #fffbce; }


/*************************
	notification
*************************/
.notification {
	-moz-border-radius:5px;
	-webkit-border-radius:5px;
	border-radius:5px;
	border: 1px solid;
	display: block;
	font-size: 13px;
	font-style: normal;
	line-height: 1.5em;
	margin: 0 0 10px;
	padding: 5px 10px;
	position: relative;
	text-align: left;
}
.information{background: #dbe3ff ;border-color: #a2b4ee;color: #585b66;}
.success {background: #d5ffce;border-color: #9adf8f;color: #556652;}
.error {background: #FFCECE;border-color: #DF8F8F;color: #665252;}
.information a{color: #09567A}
.success a{color: #47B032;}
.error a{color: #801818;}
.information a:hover,.success a:hover,.error a:hover{color: #222;}

/*lightbox*/
#lightbox {display:none;position:absolute;margin:auto;left:0px;top:0px;width:100%;height:100%;background:rgba(0,0,0,0.5);overflow:auto;padding-top:20px;z-index:100;}
#lightbox img {border:solid #333 1px;background: #fff;box-shadow: 0 0 5px#000;padding: 10px;cursor:pointer;}
#lbwrapper,#lbcontent{z-index:inherit}
#lbwrapper{position:fixed;left:50%;top:50%}
</style>
';
}

function format_size($file) {
	if (empty($file)) return;
	$bytes = @filesize($file);
	if ($bytes < 1024) return $bytes.' <span title="Byte">Byte</span>';
	elseif ($bytes < 1048576) return round($bytes / 1024, 2).' <span title="Kilobyte">KB</span>';
	elseif ($bytes < 1073741824) return round($bytes / 1048576, 2).' <span title="Megabyte">MB</span>';
	elseif ($bytes < 1099511627776) return round($bytes / 1073741824, 2).' <span title="Gigabyte">GB</span>';
	else return round($bytes / 1099511627776, 2).' <span title="Terabyte">TB</span>';
}

function lightbox(){
	return '<script type="text/javascript">
	var lb=document.createElement("div");lb.id = "lightbox";document.body.appendChild(lb);
	var lbw=document.createElement("div");lbw.id="lbwrapper";lb.appendChild(lbw);
	var lbc=document.createElement("div");lbc.id="lbcontent";lbw.appendChild(lbc);
	var lbi=document.createElement("img");lbi.id="lbi";lbc.appendChild(lbi);
	var lbl=document.getElementsByTagName("a");
	for(var z=0;z<lbl.length;z++){if(lbl[z].getAttribute("rel")=="lightbox"){lbl[z].onclick=function(){return setpic(this)}}else{}}
	function close(){lb.style.display="none"}lb.onclick = close;
	function setpic(thispic){lbi.onclick=close;lbi.style.opacity="0";lbi.style.filter="alpha(opacity=0)";lbi.src=thispic.href;lbi.onload=function(){
	lbw.style.width=lbi.offsetWidth+"px";lbw.style.marginLeft="-"+lbc.offsetWidth/2+"px";lbw.style.marginTop="-"+lbc.offsetHeight/2+"px";
	for(var fd=0;fd<11;fd++){setTimeout(\'lbi.style.opacity="\'+fd/10+\'";lbi.style.filter="alpha(opacity=\'+(fd*10)+\')";\',fd*50);}}
	lb.style.display="block";return false;}
	window.onscroll=function(){if(lb.style.display=="block")lb.style.left=(document.documentElement.scrollLeft||document.body.scrollLeft)+"px"}
</script>';
}