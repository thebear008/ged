#!/bin/bash

[ -z "$1" ] && echo "Error : need one argument" && exit 1

case "$1" in
  "local-linux")
    echo "Deploying code locally on Linux"
    sudo cp -v *.php *.json *.js *.css /var/www/html/ged/
    sudo cp -rv /home/lonclegr/Images/ged/* /var/www/html/ged/datas/
    sudo chown -R apache. /var/www/html/ged
  ;;
  "local-mac")
    echo "Deploying code locally on Mac"
    cp -v *.php *.json *.js /Applications/MAMP/htdocs/ged
    cp -rv /Users/lonclegr/Pictures/ged/* /Applications/MAMP/htdocs/ged/datas/
  ;;
  *)
    echo "Unknow option! Try again with local for instance"
    exit 1
esac
