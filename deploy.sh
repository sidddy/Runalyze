#!/bin/bash

ERRORSTRING="Error. Please make sure you've indicated correct parameters"

if [ $# -eq 0 ]
    then
        echo $ERRORSTRING;
elif [ $1 == "local" ]
    then
        if [[ -z $2 ]]
            then
                echo "Running dry-run"
                rsync --dry-run -az --force --progress --exclude-from=rsync_exclude.txt -e "ssh -p22" ./ sven@apollon:/var/www/runalyze
        elif [ $2 == "go" ]
            then
                echo "Running actual deploy"
                rsync -az --force --progress --exclude-from=rsync_exclude.txt -e "ssh -p22" ./ sven@apollon:/var/www/runalyze
        else
            echo $ERRORSTRING;
        fi
fi
