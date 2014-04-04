<?php
// Read config file
require 'config.php';

// No need to LDAP authenticate for this page, as long as search form and result page have authentication.

// Connect to SQLite database
try {
    $db = new PDO('sqlite:' . $DATABASEFILE);
} catch (PDOException $e) {
    die("Unable to connect to database, error: " . $e->getMessage());
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="refresh" content="2" />
<title><?php echo $PAGE_TITLE; ?>: Job status</title>
<link rel="stylesheet" type="text/css" href="view.css" media="all">
<link rel="stylesheet" type="text/css" href="progressmeter.css" media="all">
<script type="text/javascript" src="view.js"></script>
</head>
<body id="main_body" >
<?php

# Get job ID and validate
$jobid = $_GET['jobid'];
if (!is_numeric($jobid))
    die('Job ID is not numeric, please try again!');

$query = $db->query('SELECT username, searchterm, filterterm, ' . 
    'devices, timeperiod, postprocessing, created, started, status, ' .
    'jobid, mapstatus, reducestatus, finished FROM jobs WHERE id=' . $jobid);

if ( $query === false ) {
    die('Found no job in database with supplied ID. Please try again.');
} else {
    echo "<h2>Status for search job $jobid</h2>";
    
    while ($entry = $query->fetch()) {
        echo "<center><table>";
        echo "<tr><td><b>Status:</b></td><td><center><b>" . $entry['status'] . "</b></center></td></tr>";
        if ($entry['status'] == 'Complete') {
            ?>
            <script type="text/javascript">
            <!--
                window.location = "results.php?jobid=<?php echo $jobid; ?>"
            //-->
            </script>
            <?php
        }
        echo "<tr><td>&nbsp;</td><td>";
        # Display progress meter for mappers and reducer
        # Inspired by: http://benogle.com/2009/06/16/simple-css-shiny-progress-bar-technique.html by Ben Ogle
        echo "<tr><td><b>Mappers:</b></td><td>";
        ?>
        <div class="meter-wrap">
            <div class="meter-value" style="background-color: #0a0; width: <?php echo $entry['mapstatus']; ?>%;">
                <div class="meter-text">
                    <?php echo $entry['mapstatus']; ?>%
                </div>
            </div>
        </div>
        <?php
        echo "</td></tr>";
        echo "<tr><td><b>Reducer:</b></td><td>";
        ?>
        <div class="meter-wrap">
            <div class="meter-value" style="background-color: #0a0; width: <?php echo $entry['reducestatus']; ?>%;">
                <div class="meter-text">
                    <?php echo $entry['reducestatus']; ?>%
                </div>
            </div>
        </div>
        <?php
        echo "</td></tr>";
        echo "<tr><td>&nbsp;</td><td>";
        echo "</table></center>";
        if ($entry['status'] == 'New') {
            echo "<center>Please wait for the job to start on the cluster.<br />There is some delay due to the use of a job launcher run by cron once every 60 seconds.</center>";
        } else if ($entry['status'] == 'Running') {
            echo "<center>You will be redirected to the results page when the job finishes.</center>";
        } else if ($entry['status'] == 'Failed') {
            echo "<center><h2><font color='red'>Search job failed!</font></h2><br />To debug job failure, go to the <a href='" . $JOBTRACKER_URL . "/jobdetails.jsp?jobid=" . $entry['jobid'] . "'>status page for the job</a> and drill down to job logs for Failed/Killed Task Attempts.</center>";
        }
    }
    
}

?>
