#!/bin/bash

# Change base dir
BASEDIR=$(dirname $0)
if [ $BASEDIR = '.' ]; then
	BASEDIR=$(pwd)
fi

# Copy distributed config as config if no config done
if [ ! -f config.ini ]; then
	cp config.dist.ini config.ini
fi

# Run server
php -S localhost:8080 -t $BASEDIR
