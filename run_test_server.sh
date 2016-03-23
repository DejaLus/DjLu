#!/bin/bash

BASEDIR=$(dirname $0)
if [ $BASEDIR = '.' ]
then
BASEDIR=$(pwd)
fi

php -S localhost:8080 -t $BASEDIR