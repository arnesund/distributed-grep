#!/usr/bin/env python
import os
import sqlite3
import subprocess
import time
import threading
from datetime import datetime
from datetime import timedelta
from dateutil import rrule
from asynchronousfilereader import AsynchronousFileReader

try:
    # Python 2
    from Queue import Queue
except ImportError:
    # Python 3
    from queue import Queue


DATADIR = '../database'
DEBUG = False

# Connect to database
db = sqlite3.connect(DATADIR + '/hadoopjobs.db')
cur = db.cursor()

startcmd = '''
HADOOP_BIN/hadoop jar HADOOP_CONTRIB/contrib/streaming/hadoop-streaming*.jar \
-Dmapred.job.name="Distributed Grep: HadoopOnDemand job JOBID" \
-Dmapred.reduce.tasks=1 \
-Dmapred.job.priority=VERY_HIGH \
-mapper "JOBSCRIPT_DIR/egrepwrapper.sh 'SEARCHSTRING'" \
'''

details = {}
details['HADOOP_BIN'] = '/usr/bin'
details['HADOOP_CONTRIB'] = '/usr/share/hadoop'
details['JOBSCRIPT_DIR'] = '../jobscripts'


def process_job_output(db, cur, jobid, line):
    line = line.strip()

    if line.find('INFO streaming.StreamJob:  map ') != -1:
        mapstatus = line.split()[5]
        reducestatus = line.split()[7]
        try:
            cur.execute('UPDATE jobs SET mapstatus=' + mapstatus[:-1] + ', reducestatus=' + 
                reducestatus[:-1] + ' WHERE id=' + jobid)
            db.commit()
        except Exception, e:
            print('ERROR: Unable to update database for job ' + jobid)
            print(e)
            return False

    elif line.find('INFO streaming.StreamJob: Running job:') != -1:
        hjobid = line.split()[6]
        try:
            cur.execute('UPDATE jobs SET status="Running" WHERE id=' + jobid)
            cur.execute('UPDATE jobs SET started=DATETIME("now") WHERE id=' + jobid)
            cur.execute('UPDATE jobs SET jobid="' + hjobid + '" WHERE id=' + jobid)
            db.commit()
        except Exception, e:
            print('ERROR: Unable to update database for job ' + jobid)
            print(e)
            return False

    elif line.find('INFO streaming.StreamJob: Job complete') != -1:
        try:
            cur.execute('UPDATE jobs SET status="Complete" WHERE id=' + jobid)
            cur.execute('UPDATE jobs SET finished=DATETIME("now") WHERE id=' + jobid)
            db.commit()
        except Exception, e:
            print('ERROR: Unable to update database for job ' + jobid)
            print(e)
            return False

    elif line.find('ERROR streaming.StreamJob: Job not successful') != -1:
        try:
            cur.execute('UPDATE jobs SET status="Failed" WHERE id=' + jobid)
            cur.execute('UPDATE jobs SET finished=DATETIME("now") WHERE id=' + jobid)
            db.commit()
        except Exception, e:
            print('ERROR: Unable to update database for job ' + jobid)
            print(e)
            return False

    elif line.find('ERROR streaming.StreamJob: Error launching job , Output path already exists') != -1:
        print('ERROR: Output path /user/root/ondemand-' + jobid + ' already exists! Unable to run job.')
        return False

    # Return success
    return True



# Get all new jobs
cur.execute('SELECT * FROM jobs WHERE status="New"')
res = cur.fetchall()
# Example result:
'''
id|username|searchterm    |filterterm|devices    |timeperiod|postprocessing|created            |started|jobid|status|mapstatus|reducestatus|finished
1 |arne    |198.51.100.184|          |firewall_1 |30        |connlist.py   |2012-12-12 12:04:54|       |     |New   |         |            |
'''

for job in res:
    # Get job ID and search string
    details['JOBID'] = str(job[0])
    details['SEARCHSTRING'] = job[2]

    # Determine reducer
    # NOTE: When adding new reducers, remember to add script file to list of included files below
    if job[6] == 'connlist.py':
        startcmd = startcmd + ' -reducer JOBSCRIPT_DIR/connlist.py '
    elif job[6] == 'egrep-dropped':
        startcmd = startcmd + ' -reducer JOBSCRIPT_DIR/egrep-dropped.sh '
    elif job[6] == 'fg.py':
        startcmd = startcmd + ' -reducer JOBSCRIPT_DIR/fg.py '
    else:
        startcmd = startcmd + ' -reducer JOBSCRIPT_DIR/noreducer.sh '

    # Set output path
    startcmd = startcmd + ' -output /user/root/ondemand-' + str(job[0]) + ' '

    if job[5] > 0:
        # Calculate all input dates
        enddate = datetime.today()
        startdate = enddate - timedelta(days=job[5])

        # Loop through all dates and add to input list, once for each chosen device
        for dt in rrule.rrule(rrule.DAILY, dtstart=startdate, until=enddate):
            for device in job[4].split():
                startcmd = startcmd + '-input "/data/' + device + '/*' + dt.strftime('%Y%m%d') + '*" '
    else:
        # Search through all files for each chosen device
        for device in job[4].split():
            startcmd = startcmd + '-input "/data/' + device + '/*" '

    # List of included files as last argument to job setup
    startcmd = startcmd + '''-file JOBSCRIPT_DIR/egrepwrapper.sh \
    -file JOBSCRIPT_DIR/fg.py \
    -file JOBSCRIPT_DIR/connlist.py \
    -file JOBSCRIPT_DIR/egrep-dropped.sh \
    -file JOBSCRIPT_DIR/noreducer.sh'''

    # Replace variables with necessary details
    for key, value in details.items():
        startcmd = startcmd.replace(key, value)

    if DEBUG:
        print startcmd

    # Start search job
    process = subprocess.Popen(startcmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, shell=True)

    # Launch the asynchronous readers of the process' stdout and stderr.
    stdout_queue = Queue()
    stdout_reader = AsynchronousFileReader(process.stdout, stdout_queue)
    stdout_reader.start()
    stderr_queue = Queue()
    stderr_reader = AsynchronousFileReader(process.stderr, stderr_queue)
    stderr_reader.start()
 
    # Check the queues if we received some output (until there is nothing more to get).
    while not stdout_reader.eof() or not stderr_reader.eof():
        # Show what we received from standard output.
        while not stdout_queue.empty():
            line = stdout_queue.get()
            process_job_output(db, cur, details['JOBID'], line)
            if DEBUG:
                print line,
 
        # Show what we received from standard error.
        while not stderr_queue.empty():
            line = stderr_queue.get()
            process_job_output(db, cur, details['JOBID'], line)
            if DEBUG:
                print line,
 
        # Sleep a bit before asking the readers again.
        time.sleep(.1)
 
    # Let's be tidy and join the threads we've started.
    stdout_reader.join()
    stderr_reader.join()
 
    # Close subprocess' file descriptors.
    process.stdout.close()
    process.stderr.close()

# Clean up
db.close()

