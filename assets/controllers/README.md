# JavaScript on the embed page

The embed page uses [Stimulus](https://stimulus.hotwired.dev/) controllers. AssetMapper loads them from
`importmap.php`. There is no Node build step.

## The approach

- The server renders the HTML. A controller adds behaviour to it.
- Markup that a controller adds comes from a `<template>` that a Twig component renders. The controller fills it
  with `textContent`. Do not use `innerHTML` with data.
- The `map` controller holds the state. The other controllers send events to it. The `map` controller calls the
  list and the detail view through outlets.
- Texts come from the translations. Twig gives them to a controller as a `messages` value.
- The settings of the map come from `map_json.leaflet`. Twig gives them to the `map` controller as the `settings`
  value. The embed options from the query string are the `options` value.

The events and the actions are in `templates/embed/leaflet.html.twig`:

```text
map-list:select        -> map#select
map-detail:close       -> map#deselect
map-filters:change     -> map#filter
address-search:query   -> map#query
address-search:choose  -> map#origin
```

## The controllers

| File                           | Purpose                                                                                                                                                                                                                                                                  |
|--------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `map_controller.js`            | Holds the state: the places, the search text, the layers and filters, the chosen address and the selected place. Loads the data from the data proxy, draws the Leaflet map and the markers, and controls the zoom gate. Tells the list and the detail view what to show. |
| `map_list_controller.js`       | The list of the places that the map shows. Fills the rows from a template. A click on a row sends `map-list:select`.                                                                                                                                                     |
| `map_detail_controller.js`     | The detail view of a place, over the list. Shows the fields, the route link and the report link. Controls focus, and closes with Escape. Sends `map-detail:close`.                                                                                                       |
| `map_filters_controller.js`    | The layer checkboxes, the filters and the conditions of each layer in the sidebar. On each change, sends `map-filters:change`.                                                                                                                                           |
| `address_search_controller.js` | The search field. Filters the list as the user types (`address-search:query`), or finds an address with Adressevælger (`address-search:choose`). The address search is an ARIA 1.2 combobox.                                                                             |
| `clipboard_controller.js`      | In the builder: copies the embed code. On a page without https, it uses the old copy command.                                                                                                                                                                            |
| `map_view_controller.js`       | In the builder: keeps the view that the map in the frame sends after each move, and copies it into the view fields of the form.                                                                                                                                          |
| `embed_height_controller.js`   | With the embed script: sends the height of the map to the page around the frame (`enter-map:height`), so that the script can set the height of the frame.                                                                                                                |

## The modules in `assets/lib/`

These modules do not use Stimulus, so the controllers share them.

Do not write geometry or projection calculations here. Use Leaflet, for example `getBounds().getCenter()` and
`map.distance()`, or a package from npm.

| File                | Purpose                                                                                                                                                       |
|---------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `fields.js`         | Reads the fields of the configuration from the properties of a feature, formats them as text, and applies the filters.                                        |
| `adressevaelger.js` | Calls Adressevælger from Klimadatastyrelsen: a search for addresses, and the position of an address. `proj4` changes the position from UTM zone 32N to WGS84. |

## After a change

- If a change adds a Tailwind class to a template, run `task assets:build`.
- If a change adds a package, add it with `bin/console importmap:require` and a pinned version.
- Do not load scripts from a CDN.
