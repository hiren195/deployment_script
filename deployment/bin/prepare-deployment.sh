#!/bin/bash

# This script is run by Bamboo after updating the Git repository

curl -L -o ./dep https://deployer.org/releases/v6.4.6/deployer.phar
curl -L -o ./composer https://getcomposer.org/download/1.9.0/composer.phar
chmod a+x ./dep ./composer

sha256sum -c ./deployment/bin/SHA256SUMS.txt
