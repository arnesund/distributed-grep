#!/usr/bin/env python
#
# Parse firewall logs and present a short summary of TCP connections
#
# Version: 1.0
#
import re, sys

# Regular expressions to match info in 'Built conn'- and 'Teardown'-messages
regexBuiltConn = r'[a-zA-Z]+ [0-9 ]?[0-9] ([0-9:]+) ([a-zA-Z]+) ([0-9]+) ([0-9]+) .* Built (out|in)bound ([a-zA-Z]+) .* for [a-zA-Z0-9_-]+:([0-9.]+)/([0-9]+) .* to [a-zA-Z0-9_-]+:([0-9.]+)/([0-9]+)'
regexTeardownConn = r'[a-zA-Z]+ [0-9 ]?[0-9] ([0-9:]+) ([a-zA-Z]+) ([0-9]?[0-9]) ([0-9]+) .* Teardown ([a-zA-Z]+) .* for [a-zA-Z0-9_-]+:([0-9.]+)/([0-9]+) to [a-zA-Z0-9_-]+:([0-9.]+)/([0-9]+) duration ([0-9:]+) bytes ([0-9]+)\s?([a-zA-Z ]+)?'
BUILT = re.compile(regexBuiltConn)
TEARDOWN = re.compile(regexTeardownConn)

months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', \
						'Oct', 'Nov', 'Dec']

# List of connections and timestamps
conns = {}
connFirst = {}
connLast = {}

for line in sys.stdin:
	if line.find('-6-302013') != -1 or line.find('-6-302015') != -1:
		match = re.search(BUILT, line)
		if match:
			res = match.groups()

			# Create a connection hash: PROTO;FROMIP;TOIP;TOPORT
			conn = ';'.join([res[5], res[6], res[8], res[9]])
			# Create a timestamp
			month = str(months.index(res[1])+1).zfill(2)
			timestamp = res[3] + '-' + month + '-' + res[2].zfill(2) + ' ' + res[0]

			if conn in conns.keys():
				conns[conn] = conns[conn] + 1
				if timestamp < connFirst[conn]:
					connFirst[conn] = timestamp
				if timestamp > connLast[conn]:
					connLast[conn] = timestamp
			else:
				conns[conn] = 1
				connFirst[conn] = timestamp
				connLast[conn] = timestamp

# Sort list of conns
entries = conns.keys()
entries.sort(key=lambda conn: ' '.join(conn.split(';')[2:4]))

# Print header
print '%6s %4s  %-15s %-14s %-5s %-19s  %-19s' % ('COUNT', 'PROTO', \
	'FROM IP', 'TO IP', 'PORT', 'FIRST SEEN', 'LAST SEEN')

# Print connection table
for conn in entries:
	proto, fromIP, toIP, toport = conn.split(';')
	print '%6d %4s %15s  %15s %-5s %19s  %19s' % (conns[conn], proto, fromIP, \
			toIP, toport, connFirst[conn], connLast[conn])

