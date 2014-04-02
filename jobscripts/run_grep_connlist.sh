#!/bin/bash
#
# Start Hadoop job for firewall log analysis
#

# Abort if any error occurs
set -e

# Directories
HADOOP_BIN='/usr/bin'
HADOOP_CONTRIB='/usr/share/hadoop'

# Check number of arguments
if [ ! $# -eq 3 ] 
then
	echo "USAGE: $0 egrep-pattern inputdir description"
	echo " "
	echo "       egrep-pattern: Any pattern egrep will accept"
	echo "       inputdir     : A valid Hadoop HDFS path of input file(s)"
	echo "       description  : Descriptive name of grep job *without spaces*"
	echo " "
	exit 1
fi

$HADOOP_BIN/hadoop jar $HADOOP_CONTRIB/contrib/streaming/hadoop-streaming*.jar \
-Dmapred.job.name="Distributed Grep: $3" \
-Dmapred.reduce.tasks=1 \
-mapper "egrepwrapper.sh '$1'" \
-reducer connlist.py \
-input $2 \
-output output-`date +%Y%m%d-%H%M`-DGrep-connlist-$3 \
-file egrepwrapper.sh \
-file connlist.py

