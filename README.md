distributed-grep
================

Distributed Grep using Hadoop, with a web UI for easy launching of search jobs.

What is it?
===========

Distributed Grep is an efficient way to utilize a Hadoop cluster to find log messages hidden within terabytes of log data. Distributed Grep utilizes all available CPU cores to run grep on log files in parallel, and has support for optional reducers to do postprocessing on the search results.

Distributed Grep consists of jobscripts which form the Hadoop Streaming job, a web app with a form to make it easy to perform searches, and a launcher typically run by cron every minute which submits jobs to the cluster. The web app shows the progress of the job and displays the results when the job has finished, with an option to download the results as a file.

Installation
============
Please see INSTALL.md for installation instructions.

Known caveats
=============

  * The postprocessing scripts are vendor-specific:
    * Only dropped connections: Only Cisco ASA/FWSM log format supported
    * Connlist: Only Cisco ASA/FWSM log format supported
    * More readable: Only Fortinet FortiGate logs in CSV format supported
  * The launcher is only tested running as root, should preferably run as some other user

Copyright
=========
Released as open source under the GPLv2 license, see LICENSE file.
