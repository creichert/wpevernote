#!/bin/bash

zip -9 -r --exclude=*.git* --exclude=*~ \
     --exclude=*.swp --exclude=README.md \
     --exclude=deploy.sh  wpevernote.zip .
