#!/bin/bash

### BEGIN INIT INFO
# Provides:          udpsum
# Required-Start:    $local_fs $networking
# Required-Stop:     $local_fs $networking
# Default-Start:     2 3 4 5
# Default-Stop:      0 1 6
# Short-Description: UDP SUM
# Description:       UDP SUM
### END INIT INFO

mode=$1
port=16207

if [ $mode = 'stop' -o $mode = 'restart' ]
then
	pkill udpsum
fi

if [ $mode = 'start' -o $mode = 'restart' ]
then
	nohup /home/vnlappserver/udpsum $port &>/dev/null &
fi
