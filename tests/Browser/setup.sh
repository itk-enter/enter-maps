#!/bin/sh
# Makes an empty database for the browser tests and imports the fixtures.
# Writes the id of each map to var/browser/maps.json, for example
# {"toilets": "01M7…"}. "task test:browser" runs this script in the
# browser-site container.
set -eu

rm -f var/browser.db
bin/console doctrine:schema:create --quiet
mkdir -p var/browser

COLUMNS=200 bin/console app:map-config:import tests/Fixtures/map-config/*.json \
  | sed -n 's#.*Imported tests/Fixtures/map-config/\(.*\)\.json as \([0-9A-Z]\{26\}\)\..*#"\1": "\2"#p' \
  | paste -sd, - \
  | sed 's/^/{/; s/$/}/' > var/browser/maps.json

cat var/browser/maps.json
