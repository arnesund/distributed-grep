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

// Test database for content, initialize table if not found
$q = $db->exec('SELECT id FROM jobs');
if ($q === false) {
    // Initialize job table
    $db->exec('CREATE TABLE jobs (
            id INTEGER PRIMARY KEY,
            username TEXT NOT NULL,
            searchterm TEXT NOT NULL,
            filterterm TEXT,
            devices TEXT NOT NULL,
            timeperiod INTEGER NOT NULL,
            postprocessing TEXT,
            created DATETIME,
            started DATETIME,
            jobid TEXT,
            status TEXT,
            mapstatus REAL,
            reducestatus REAL,
            finished DATETIME
        );');
    $hits = 1;
} else {
    $hits = $q+1;
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title><?php echo $PAGE_TITLE; ?></title>
<link rel="stylesheet" type="text/css" href="view.css" media="all">
<script type="text/javascript" src="view.js"></script>
<script type="text/javascript">
<!--
function delayer(jobid){
    window.location = "status.php?jobid=" + jobid
}
//-->
</script>
</head>

<?php
// Process POSTed form data
if (array_key_exists('submit', $_POST)) {
    // Form data submitted, don't display form
    $displayform = 0;
    // Get form input
    $searchterm = $_POST['searchterm'];
    $filterterm = $_POST['filterterm'];
    // Check which devices user has selected
    $devicelist = '';
    foreach ($devices as $index => $value) {
        if (array_key_exists($value, $_POST)) {
            $devicelist = $devicelist . $value . ' ';
        }
    }
    // Convert timeperiod to number of days
    $t = array('1', '7', '30', '90', '*');
    $timeperiod = $t[$_POST['timeperiod']];
    // Check which postprosessing types have been selected
    if ($_POST['element_5'] == '2') {
        $postprocessing = 'egrep-dropped';
    } else if ($_POST['element_5'] == '3') {
        $postprocessing = 'connlist.py';
    } else if ($_POST['element_5'] == '4') {
        $postprocessing = 'fg.py';
    } else {
        $postprocessing = '';
    }

    // Validate input
    if ($searchterm == '') { 
        echo '<body id="main_body" >';
        echo '<br/><FONT COLOR="red">You have to supply a search string.</FONT><br/>';
        $displayform = 1; 
    }
    if ($devicelist == '') { 
        echo '<body id="main_body" >';
        echo '<br/><FONT COLOR="red">You have to choose at least one device.</FONT><br/>';
        $displayform = 1; 
    }

    if ($displayform == 0) {
        // Save job to database
        $res = $db->exec('INSERT INTO jobs (id, username, searchterm, filterterm, ' . 
            'devices, timeperiod, postprocessing, created, mapstatus, reducestatus, status) VALUES (NULL, "' . $_SERVER['PHP_AUTH_USER'] . '", "' . 
            $searchterm . '", "' . $filterterm . '", "' . $devicelist . '", "' . 
            $timeperiod . '", "' . $postprocessing . '", DATETIME("now"), 0, 0, "New")');
        if ( $res === false ) {
            echo '<body id="main_body" >';
            echo '<h2>An error occured when trying to save job details to database. Please try again.</h2>';
            die("Error: " . print_r($db->errorInfo(), true));
        } else {
            $query = $db->query('SELECT id FROM jobs WHERE status="New" ORDER BY created DESC');
            if ( $query === false ) {
                echo '<body id="main_body" >';
                echo '<h2>The job details got stored in database, but an error occured when fetching the ID of the new job from the database. Please try again.</h2>';
            } else {
                $entry = $query->fetch();
                $jobid = $entry['id'];
                echo '<body id="main_body" onLoad="setTimeout(\'delayer(' . $jobid . ')\', 5000)">';
                echo '<h2>Search job ' . $jobid . ' started.</h2><p>In a few seconds you are redirected to status page for job.</p>';
            }
        }
    }

} else {
    // No data POSTed, display search form
    $displayform = 1;
    echo '<body id="main_body" >';
}

if ($displayform == 1) {
?>
    <img id="top" src="top.png" alt="">
    <div id="form_container">
    
        <h1><a>Massively Parallel Log Search</a></h1>
        <form id="form_535336" class="appnitro"  method="post" action="">
                    <div class="form_description">
            <h2>Massively Parallel Log Search</h2>
            <p>Search log files in parallel to process enormous amounts of data in next to no time.</p>
        </div>                      
            <ul >
            
                    <li id="li_1" >
        <label class="description" for="element_1">Search term:</label>
        <div>
            <input id="element_1" name="searchterm" class="element text large" type="text" maxlength="255" value=""/> 
        </div><p class="guidelines" id="guide_1"><small>Regex is allowed!</small></p> 
        </li>
        <input id="element_2" name="filterterm" class="element text large" type="hidden" maxlength="255" value=""/> 
        <li id="li_3" >
        <label class="description" for="element_3">Choose device logs to search:</label>
        <span>
        <?php 
        for ($i=0; $i < count($devices); $i++) {
            if ($i % ($DEVICES_PER_COLUMN-1) == 0) {
                echo "</span><span>";
            }
            ?>
        <input id="element_3_<?php echo $i; ?>" name="<?php echo $devices[$i]; ?>" class="element checkbox" type="checkbox" value="1" />
        <label class="choice" for="element_3_0"><?php echo $devices[$i]; ?></label>
            <?php
        }
        ?>
        </span> 
        </li>
        <li id="li_4" >
        <label class="description" for="element_4">Time period to search: </label>
        <div>
        <select class="element select medium" id="element_4" name="timeperiod"> 
            <option value="0" >1 day</option>
            <option value="1" selected="selected">1 week</option>
            <option value="2" >1 month</option>
            <option value="3" >3 months</option>
            <option value="4" >"Infinitely"</option>
        </select>
        </div> 
        </li>
        <li id="li_5" >
        <label class="description" for="element_5">Postprocessing: </label>
        <span>
            <input id="element_5_1" name="element_5" class="element radio" type="radio" value="1" checked="checked"/>
            <label class="choice" for="element_5_1">None</label>
            <input id="element_5_2" name="element_5" class="element radio" type="radio" value="2" />
            <label class="choice" for="element_5_2">Only dropped traffic</label>
            <input id="element_5_3" name="element_5" class="element radio" type="radio" value="3" />
            <label class="choice" for="element_5_3">Connlist</label>
            <input id="element_5_4" name="element_5" class="element radio" type="radio" value="4" />
            <label class="choice" for="element_5_4">More readable FortiGate</label>
        </span><p class="guidelines" id="guide_5">
<small>
Postprocessing of search results:
<br/></br>
"Only dropped traffic" only displays log messages about traffic dropped by a firewall (Cisco-specific).
<br/></br>
"Connlist" produces a short table of unique connections, with hit counts and timestamp for first and last hit.
<br/></br>
"More readable FortiGate" makes FortiGate log lines much more readable, by discarding many unnecessary fields.
</small></p> 
        </li>
            
                    <li class="buttons">
                <input type="hidden" name="form_id" value="535336" />
                
                <input id="saveForm" class="button_text" type="submit" name="submit" value="Submit" />
        </li>
            </ul>
        </form> 
        <div id="footer">
            Generated by <a href="http://www.phpform.org">pForm</a>
        </div>
    </div>
    <img id="bottom" src="bottom.png" alt="">
<?php } ?>
    </body>
</html>
