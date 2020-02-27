#!/bin/bash

# This script is run by Bamboo after prepare-deployment.sh

export PATH=/opt/plesk/php/7.1/bin:$PATH

# the environment to deploy to; one of "production", "stage"
ENVIRONMENT=$1

if [ "$ENVIRONMENT" == "" ]; then
  echo "Usage: $0 env"
  echo ""
  echo "'env' is one of 'production', 'stage' (and others if defined in deploy.php)"

  exit 1
fi

./dep --ansi -vv deploy $ENVIRONMENT
