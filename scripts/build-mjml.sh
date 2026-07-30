#!/bin/bash

# This script assumes it's run from the project root.
# It executes the PHP script responsible for compiling MJML files
# using the spatie/mjml-php library, which itself requires the
# mjml Node.js package.

# Ensure Composer dependencies are installed before running this,
# as the PHP script requires vendor/autoload.php and spatie/mjml-php.

# The mjml version is pinned because the committed templates in email/templates/
# are build artifacts: MJML 5 emits materially different HTML from the 4.x output
# they were generated with (it drops the custom @font-face block and falls back to
# a default Google font), so an unpinned install silently rewrites every template.
# --prefix . keeps node_modules inside the plugin; without it npm walks up the
# directory tree and installs into a parent project, since a package.json at this
# repo's root is intentionally gitignored.
MJML_VERSION="4.15.3"

echo "Ensuring local mjml Node.js package (${MJML_VERSION}) is installed..."
npm i "mjml@${MJML_VERSION}" --no-save --prefix .

echo "Compiling MJML templates using PHP script (email/mjml/build-mjml.php)..."
php email/mjml/build-mjml.php

if [ $? -eq 0 ]; then
    echo "MJML compilation successful. HTML templates should be updated in email/templates/"
else
    echo "MJML compilation failed. Check output from the PHP script and npm."
    exit 1
fi
