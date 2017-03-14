#!/bin/bash

# Change base dir
BASEDIR=$(dirname $0)
if [ $BASEDIR = '.' ]; then
	BASEDIR=$(pwd)
fi

# Run server
php -S 0.0.0.0:8080 -t $BASEDIR
