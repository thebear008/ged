#!/bin/bash

[ -z "$1" ] && echo "Error : need one argument" && exit 1

case "$1" in
  "local")
    echo "Deploying code locally"
    sudo cp -v *.php /var/www/html/ged/
    sudo cp -v /home/lonclegr/Images/ged/.ged.db /var/www/html/ged/
    sudo cp -rv /home/lonclegr/Images/ged /var/www/html/ged/datas
  ;;
  *)
    echo "Unknow option! Try again with local for instance"
    exit 1
esac
