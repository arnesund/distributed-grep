Installation instructions
=========================

Summary
=======

  1. Install dependencies
  2. Check out repository, f.ex under /srv
  3. Run 'git submodule update' to check out dependencies in repository
  4. Add webapp folder of repository as a site to the Apache config
  5. Enable the new Apache site
  6. Add a crontab entry to run the launcher every minute, f.ex as root

Dependencies
============

Distributed Grep has been tested under Ubuntu Precise, so the list of dependencies is in the form of Ubuntu package names:
  * apache2
  * sqlite3
  * php5 php5-curl php5-ldap php5-sqlite
  * python

Setup
=====

The settings for the web app is in the file config.php. Adjust them to your environment. LDAP authentication is both used for controlling access to the web app and for determining which user is launching a search job.

The launcher has some settings which may need to be adjusted, most notably the path to the hadoop binary and Hadoop Streaming jar file. Edit launcher/startjob.py to change the paths.
