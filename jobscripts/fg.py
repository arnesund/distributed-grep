#!/usr/bin/env python
import re
import sys

# Parse command line arguments
UNIQUE = False
if len(sys.argv) > 1:
    if sys.argv[1] == '-u':
        UNIQUE = True

# Call flush on stdout every MAX_LINES_BUFFERED
MAX_LINES_BUFFERED = 3
flushcounter = 0

# Fields to emit in output
fields =    [
            'type', 
            'status', 
            'srcintf', 
            'dstintf', 
            'srcip', 
            'dstip', 
            'service', 
            'action', 
            'msg',
            'hostname',
            'duration',
            'sentbyte',
            'rcvdbyte'
            ]

# Color codes
RED = '\033[91m'
YELLOW = '\033[93m'
ENDCOLOR = '\033[0m'

# Data structure for connection hashes
seen = []

try:
    for line in sys.stdin:
        # Get syslog metadata (date, time, device)
        match = re.search(r'([a-zA-Z]+ [0-9 ][0-9] [0-9:]+ [a-zA-Z0-9.-]+) (.*)', line)
        if match:
            groups = match.groups()
            logentry = groups[1]

            # Parse CSV log entry into parts
            data = {}
            parts = logentry.split(',')
            for part in parts:
                try:
                    key, value = part.split('=')
                    data[key] = value
                except:
                    continue

            # Build connection hash
            if 'dstport' in data:
                connhash = ';'.join([data['srcip'], data['dstip'], data['proto'], data['dstport']])
            else:
                connhash = False

            if UNIQUE and connhash and connhash in seen:
                # Skip line, already seen
                continue
            else:
                if connhash:
                    seen.append(connhash)

                # Print metadata
                print groups[0].replace('.met.no', ''),

                # Print interesting fields only
                for key in fields:
                    if key in data:
                        if key == 'status':
                            data[key] = data[key].replace('"', '')
                            # Make status field stand out with colorized text
                            if data[key] in ['deny', 'blocked']:
                                print((RED + '%s=%s' + ENDCOLOR) % (key, data[key])),
                            elif data[key] == 'timeout':
                                print((YELLOW + '%s=%s' + ENDCOLOR) % (key, data[key])),
                            else:
                                # Default case
                                print('%s=%s' % (key, data[key])),
                        elif key == 'srcip' and 'srcport' in data:
                            # Concatenate source IP and port
                            print('%s:%s =>' % (data[key], data['srcport'])),
                        elif key == 'dstip' and 'dstport' in data:
                            # Concatenate destination IP and port
                            print('%s:%s' % (data[key], data['dstport'])),
                        elif key in ['duration', 'sentbyte', 'rcvdbyte']:
                            # Skip three irrelevant fields for start-messages
                            if 'status' in data and data['status'] != 'start':
                                print('%s=%s' % (key, data[key])),
                        elif key == 'srcintf':
                            # Shorten interface names
                            if data[key].find('inside') != -1:
                                print('inside ->'),
                            elif data[key].find('outside') != -1:
                                print('outside ->'),
                            else:
                                print('%s ->' % (data[key].replace('"', ''))),
                        elif key == 'dstintf':
                            # Shorten interface names
                            if data[key].find('inside') != -1:
                                print('inside'),
                            elif data[key].find('outside') != -1:
                                print('outside'),
                            else:
                                print('%s' % (data[key].replace('"', ''))),
                        elif key == 'msg':
                            # Shorten messages
                            if data[key] == '"URL belongs to an allowed category in policy"':
                                print('%s=%s' % (key, '"URL allowed"')),
                            elif data[key] == '"URL belongs to a denied category in policy"':
                                print('%s=%s' % (key, '"URL denied"')),
                            elif data[key] == '"URL has been visited"':
                                print('%s=%s' % (key, '"URL visited"')),
                            else:
                                # Default case
                                print('%s=%s' % (key, data[key])),
                        elif key == 'hostname':
                            if 'status' in data and data['status'] == 'blocked':
                                # Make blocked domain name RED
                                print((RED + '%s=%s' + ENDCOLOR) % (key, data[key])),
                            else:
                                # Default case
                                print('%s=%s' % (key, data[key])),
                        else:
                            # Default case
                            print('%s=%s' % (key, data[key])),
                print

                flushcounter += 1
                if flushcounter > MAX_LINES_BUFFERED:
                    try:
                        sys.stdout.flush()
                    except IOError:
                        pass
                    else:
                        flushcounter = 0

except KeyboardInterrupt:
    sys.exit()
