<?php

//
// Config file for DGrep web UI
//

// Array of devices with log data in HDFS
// Order of device names here is reflected in web UI
$devices = array('fw1', 'fw2', 'fw3');

// LDAP Auth settings for web pages
$ldapconfig['host'] = 'ldaps://LDAP.SERVER.NAME.HERE';
$ldapconfig['port'] = NULL;
$ldapconfig['basedn'] = 'BASE,DN,HERE';
$ldapconfig['authrealm'] = 'LDAP';
$ldapconfig['helpdesk'] = 'MAIL@ADDRESS.HERE';

// Name and path of sqlite database
$DATABASEFILE = '../database/hadoopjobs.db';

// Web UI settings
$PAGE_TITLE = 'Distributed Grep';
// Adjust devices per column to get a two- or three-column view
$DEVICES_PER_COLUMN = 8;

// Hadoop cluster paths
// NOTE: Hadoop v1-specific, newer versions may require code changes.
$JOBTRACKER_HOST = 'HOSTNAME.HERE';
$JOBTRACKER_PORT = '50030';
$NAMENODE_HOST = 'HOSTNAME.HERE';
$HDFS_WEB_PORT = '50075';

// User account startjob.py is running as (run by cron)
$HADOOP_USER = 'root';

?>
