<?php
// Read config file
require 'config.php';

// Authorize user against LDAP
// Based on: http://code.activestate.com/recipes/101525/ (r1) by Shane Caraveo
function ldap_authenticate() {
    global $ldapconfig;

    if (isset($_SERVER['PHP_AUTH_USER'])) {
        $ds = @ldap_connect($ldapconfig['host']);
        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);

        // Try to bind to LDAP using supplied password
        if ($bind = @ldap_bind($ds, 'uid=' . $_SERVER['PHP_AUTH_USER'] .
            ',ou=user,' . $ldapconfig['basedn'], $_SERVER['PHP_AUTH_PW'])) {
            // Return success
            return 'Login OK';
        } else {
            echo('<BR><H2>Incorrect username or bad password.</H2>');
        }
    } else {
        // No authentication data supplied, present login window
        header('WWW-Authenticate: Basic realm="'.$ldapconfig['authrealm'].'"');
        header('HTTP/1.0 401 Unauthorized');
    }
    // Return failure
    return NULL;
}

if (($result = ldap_authenticate()) == NULL) {
    echo('<H2>' . $ldapconfig['authrealm'] . ' authentication failed, ' .
    'please try again or contact ' . $ldapconfig['helpdesk'] . '.</H2>');
    echo("<B>To try again, close all open browser windows before you go to " .
    "this site again.</B> That's necessary to get the browser to display ");
    echo("the login box again.<BR><B>Or, you can open a different browser</B>" .
    " and go to this site there.");
    exit(0);
}

// Connect to SQLite database
try {
    $db = new PDO('sqlite:' . $DATABASEFILE);
} catch (PDOException $e) {
    die("Unable to connect to database, error: " . $e->getMessage());
}

# Get job ID and validate
$jobid = $_GET['jobid'];
if (!is_numeric($jobid))
    die('Job ID is not numeric, please try again!');

$query = $db->query('SELECT id, username, searchterm, filterterm, ' . 
    'devices, timeperiod, postprocessing, created, started, status, ' .
    'jobid, mapstatus, reducestatus, finished FROM jobs WHERE id=' . $jobid);

if ( $query === false ) {
    die('Found no job in database with supplied ID. Please try again.');
} else {
    $entry = $query->fetch();
    if ($entry['status'] == 'Complete') {
        $HDFS_PATH = "/user/" . $HADOOP_USER . "/ondemand-" . $entry['id'] . "/part-00000";
        $FILENAME = "DGrep-job-" . $entry['id'] . "-results.txt";

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . $FILENAME);
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');

        $ch = curl_init("http://" . $NAMENODE . ":" . $HDFS_WEB_PORT . "/webhdfs/v1" . $HDFS_PATH . "?op=OPEN");
        // Get file contents and dump to browser as a download
        $res = curl_exec($ch);
        curl_close($ch);
    } else {
        ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title><?php echo $PAGE_TITLE; ?>: Search results</title>
<link rel="stylesheet" type="text/css" href="view.css" media="all">
<script type="text/javascript" src="view.js"></script>
</head>
<body id="main_body" >
<b>The job has not finished yet, please wait. <a href='status.php?jobid=<?php echo $jobid; ?>'>Check status here</a>.</b>
        <?php
    }
}
?>
