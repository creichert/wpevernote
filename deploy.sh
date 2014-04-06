#!/bin/bash

cp README.md readme.txt
zip -9 -r --exclude=*.git* --exclude=*~ \
     --exclude=*.swp --exclude=README.md \
     --exclude=deploy.sh  wpevernote.zip .
rm readme.txt
