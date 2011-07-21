#!/bin/sh
[ -f costtracker.tar.gz ]  && rm costtracker.tar.gz
tar --exclude-vcs -czf  costtracker.tar.gz installer usagereports phonebook rates userpinmanagement module.xml readme.txt
