#!/bin/bash

ERRORSTRING="Error. Please make sure you've indicated correct parameters"
RSYNC_PARAM="-az --force --progress --delete --exclude-from=rsync_exclude.txt -e ssh ./"

if [ $# -eq 0 ]
    then
        echo $ERRORSTRING;
else
    TARGET="none";
    if [ $1 == "local" ]; then
        TARGET="localhost";
    elif [ $1 == "prod" ]; then
        TARGET="rp2";
    fi
    if [ $TARGET != "none" ]; then
        if [[ -z $2 ]]
            then
                echo "Running dry-run"
                rsync --dry-run $RSYNC_PARAM sven@$TARGET:/var/www/runalyze
        elif [ $2 == "go" ]
            then
                echo "Running actual deploy"
                rsync $RSYNC_PARAM sven@$TARGET:/var/www/runalyze
        else
            echo $ERRORSTRING;
        fi
    else
	echo "Unknown target. \"local\" and \"prod\" are known."
    fi
fi
