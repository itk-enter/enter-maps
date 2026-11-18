#!/bin/sh
# Deletes all data in the dev database, runs the migrations and imports the
# fixtures. Writes the imported maps to var/browser/maps.json, as the JSON of
# "app:map-config:import --format=json". support.js reads the id of each map
# from it. "task test:browser" asks the user first, and then runs this script
# in the phpfpm container.
set -eu

bin/console doctrine:schema:drop --full-database --force --quiet
bin/console doctrine:migrations:migrate --no-interaction --quiet
mkdir -p var/browser

bin/console app:map-config:import --format=json tests/Fixtures/map-config/*.json > var/browser/maps.json

cat var/browser/maps.json
