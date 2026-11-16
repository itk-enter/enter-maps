#!/bin/sh
# Deletes all data in the dev database, runs the migrations and imports the
# fixtures. Writes the id of each map to var/browser/maps.json, for example
# {"toilets": "01M7…"}. "task test:browser" asks the user first, and then
# runs this script in the phpfpm container.
set -eu

bin/console doctrine:schema:drop --full-database --force --quiet
bin/console doctrine:migrations:migrate --no-interaction --quiet
mkdir -p var/browser

COLUMNS=200 bin/console app:map-config:import tests/Fixtures/map-config/*.json \
  | sed -n 's#.*Imported tests/Fixtures/map-config/\(.*\)\.json as \([0-9A-Z]\{26\}\)\..*#"\1": "\2"#p' \
  | paste -sd, - \
  | sed 's/^/{/; s/$/}/' > var/browser/maps.json

cat var/browser/maps.json
