# Plan: Enter maps

A separate Symfony project that shows data from [Enter](https://github.com/itk-enter/enter) on embeddable maps, and
a form that makes the embed code.

Working name: **enter-maps**. Change it before the repository is made.

## Goal

1. **The map.** A full-page map with an accessible sidebar, made to sit in an `<iframe>`. All settings come from the
   URL. Two renderers: Leaflet and Septima Widget.
2. **The builder.** A form that makes a map configuration, stores it as JSON in the database of this project, shows a
   preview, and gives the embed code.

The design and the behaviour come from the prototypes in
[itk-enter/enter-map-examples](https://github.com/itk-enter/enter-map-examples), published at
<https://itk-enter.github.io/enter-map-examples/>. Read its `README.md`, `docs/variants.md` and
`docs/septima-overrides.md` before you start. They hold the measured results that this plan builds on.

## Non-goals

- No login for citizens and no reports to Enter. A report link can point to a form in another system.
- No writes to Enter or to its broker. This project only reads.
- No editor for the data itself.

## Stack

- Symfony (current LTS or latest), on the ITK Dev Docker template for Symfony (`itk-dev/devops_itkdev-docker`):
  `phpfpm`, `nginx`, `mariadb`, Traefik. Taskfile and the ITK Dev GitHub Actions workflows from the same templates.
- Doctrine ORM and migrations, MariaDB.
- Twig, `symfony/ux-twig-component`, `symfony/ux-live-component` (live preview in the builder), Stimulus,
  AssetMapper with `importmap.php`. No Node build step.
- Symfony Form and Validator for the builder. `symfony/http-client` with a scoped client for Enter. Symfony Cache
  for the data proxy.
- Leaflet 1.9.x from the importmap. Septima Widget as an ES module from its CDN
  (`https://widget.cdn.septima.dk/<version>/widgetapi.mjs`), the version pinned (the prototypes use 3.23.0).
- Address search: Adressevælger from Klimadatastyrelsen, as in `js/adressevaelger.js` of the prototypes. No key; the
  `token` parameter is `adressevaelger123` until KDS starts user management.
- Copy the conventions of Enter's `CLAUDE.md` that apply: coding standards, PHPStan level 6 without a baseline,
  CHANGELOG, PR template, commit style, ASD-STE100.

## Architecture

```text
Browser (iframe on aarhus.dk)                 enter-maps (Symfony)                    Enter
---------------------------------            ------------------------------          ----------------------
/embed?...  map page  ──────────────────────> EmbedController (Twig)
   Leaflet or Septima                         reads the settings from the URL
   └─ GET /data/{dataSet}.geojson ──────────> DataProxyController ──── HTTP ───────> /sources.json
                                              pages, caches, returns GeoJSON          /ngsi-ld/v1/entities
/builder  form  ────────────────────────────> BuilderController, Live Component
   preview, embed code                        MapConfiguration (Doctrine, MariaDB)
```

### Why a data proxy

The map does not call Enter's broker from the browser. It calls `/data/{dataSet}.geojson` in this project. The
proxy:

- **Pages through the broker.** Enter returns at most 1000 entities per request, and
  `merged-handicap-parking-spots` has more than that. A Septima layer reads one URL and cannot page.
- **Caches.** The data changes only when Enter imports or merges. A cache of 5 to 15 minutes keeps the map traffic
  away from Scorpio.
- **Removes the CORS problem.** The browser stays on one origin, so Enter needs no CORS headers and no change for
  `OPTIONS` requests.
- **Holds credentials on the server.** The staging site of Enter is behind basic auth. The proxy sends it from an
  environment variable. The browser never sees it.
- **Allows only known data sets.** The proxy reads Enter's `/sources.json` and refuses an id that is not in it.

The request that the proxy sends to Enter for one page, as `bin/fetch-sources.sh` in the prototypes does:

```text
GET /ngsi-ld/v1/entities?q=sourceId=="<dataSet>"&format=simplified&limit=1000&offset=<n>
Accept: application/geo+json
```

Add `geometryProperty=geometry` when a data set has areas and the map must draw them, not only their centre points.

## The configuration

### Three layers of JSON

| Layer | Holds | Stored |
| --- | --- | --- |
| `config` | The map as we describe it: data sets, fields, filters, sidebar, interaction. The same for all renderers. | Yes |
| `map_json.leaflet` | The settings that our Leaflet app reads, made from `config`. | Yes, made again on each save |
| `map_json.septima` | A valid Septima Widget configuration, made from `config`. | Yes, made again on each save |

`map_json` holds only the renderers that the form selects. A converter class for each renderer makes its part from
`config`. Store the result, so that it is easy to compare and to debug. Make it again from `config` on each save, so
that the two cannot drift.

`config` can hold settings that one renderer does not support. The converter leaves them out and returns a list of
warnings. The builder shows the warnings.

**Overrides (decide in phase 3).** A field `map_json_overrides.<renderer>` can hold raw JSON that the converter merges
over its result, for Septima options that `config` does not model. Leave it out of the first version if possible.

### Fields are data, not code

In the prototypes, `describe(props)` in `js/maps/*.js` is a JavaScript function. A function cannot be stored as JSON
or put in a URL. `config` therefore describes each field as data:

```json
{ "path": "address.streetAddress", "label": "Adresse", "fallback": "Adresse ikke registreret" }
{ "path": "wheelchairAccessible", "label": "Kørestolsadgang",
  "values": { "yes": "Ja", "limited": "Delvist", "no": "Nej" }, "fallback": "Ikke registreret" }
{ "path": "openingHours", "label": "Åbningstider", "format": "list" }
```

- `path` is a dot path into the GeoJSON properties (`format=simplified`).
- `values` maps a code to a text.
- `format` is one of a small fixed list: `text`, `list`, `boolean`, `number`, `date`, `link`.
- `fallback` is the text for a missing value. Without it, the row is left out.

The Leaflet app reads these fields directly. The Septima converter makes them into the widget's `<%= %>` templates and
`computedProperties`. Guard each missing key with `typeof … === 'undefined'`, because a Septima template that reads a
missing key throws (see the README of the prototypes).

The shared texts for a data model, such as the PublicToilet or ParkingSpot fields, become presets in the builder.
They are not code.

### `config`, version 1

```json
{
  "version": 1,
  "title": "Toiletter i Aarhus",
  "locale": "da-DK",
  "view": { "center": [10.21432, 56.15355], "zoom": 14 },
  "zoomGate": { "minZoom": 15 },
  "sidebar": { "position": "left", "list": true, "categories": true, "search": { "local": true, "address": true } },
  "layers": [
    {
      "dataSet": "merged-public-toilets",
      "label": "Offentlige toiletter",
      "colour": "#6b3fa0",
      "icon": "toilet",
      "where": "isAccessibleForFree==true",
      "row": {
        "title": { "path": "name", "fallback": "Toilet" },
        "subtitle": { "path": "address.streetAddress", "fallback": "Adresse ikke registreret" }
      },
      "detail": [
        { "path": "description", "label": "Beskrivelse" },
        { "path": "openingHours", "label": "Åbningstider", "format": "list" }
      ]
    }
  ],
  "filters": [
    { "path": "wheelchairAccessible", "type": "select", "label": "Kørestolsadgang",
      "values": { "yes": "Ja", "limited": "Delvist", "no": "Nej" } }
  ],
  "report": {
    "label": "Ret eller tilføj oplysninger",
    "url": "https://example.aarhus.dk/form?entity={id}&sources={seeAlso}",
    "target": "_blank"
  }
}
```

| Setting | Meaning |
| --- | --- |
| **Closed filters** | `layers[]` and `layers[].where`. The map shows only these data sets, and only the entities that match `where`. The proxy applies `where` as an NGSI-LD `q` together with the `sourceId` condition. The data is public, so a closed filter shapes the map; it is not access control. |
| **Open filters** | `filters[]`. Controls in the sidebar. Types: `toggle`, `select`, `multiselect`. Each needs explicit values, because a Septima filter without them silently matches nothing (see the README of the prototypes). |
| **Attributes on each point** | `layers[].row` for the list row and `layers[].detail` for the detail view. |
| **Interaction** | `report`. A link or a button in the detail view. `{id}` and `{seeAlso}` are replaced from the entity. `target` is `_blank`, `_top`, or `postMessage`. With `postMessage`, the map sends `{type: "enter-map:report", id, seeAlso}` to the page around the iframe, and that page decides what happens. |
| **Other** | `view`, `zoomGate`, `sidebar`, `locale`, and later a theme with colour tokens. |

Validate `config` against a JSON Schema in the repository (`config/schema/map-config.v1.json`). The builder, the
converters and the embed page use the same schema. A new major version gets a new schema file, and the embed page
keeps support for the old ones, because embed codes live a long time on other sites.

## Part 1: the map

### URL format

```text
/embed/{renderer}?c=<config>&<overrides>
/embed/{renderer}/{id}?<overrides>
```

- `renderer` is `leaflet` or `septima`.
- `c` is the full `config`, as JSON, compressed (deflate) and base64url-encoded.
- `{id}` loads a stored configuration from the database instead of `c`.
- Overrides are short parameters that win over the configuration: `zoom`, `center=lon,lat`, `layers=a,b` (which
  layers are on at the start), `select=<entity id>` (open one place).

The embed page decodes and validates the configuration on the server. It renders the page with Twig and gives the
renderer its `map_json` part in a `data-` attribute or a JSON `<script>` element. The converters run in PHP, so a
URL with `c` and a stored configuration give the same result.

**Decide in phase 1:** whether the builder gives embed codes with `c` (self-contained, cannot change after it is
published) or with `{id}` (short, can change from the builder). A Septima configuration is large, so a `c` URL can get
long. Measure a real configuration against a limit of about 8 KB. Supporting both is cheap, because only the source of
`config` differs.

### The page

Port `public/leaflet.html`, `public/js/leaflet-app.js`, `public/css/leaflet-app.css` and `public/css/embed.css` from
the prototypes:

- **Full page.** The map fills the frame (`--map-height: 100vh`). The page around the iframe sets the height. No
  visible heading; the `h1` stays for screen readers.
- **Sidebar.** Search, categories (one checkbox for each layer), the open filters, a zoom gate, and the list. Below
  820 px the sidebar goes under the map.
- **The list holds what the map shows**, with the count in one "Resultater" heading in a `role="status"` element.
- **Detail view over the list,** not a balloon on the map. Focus goes into it, Escape closes it, and focus returns to
  the row.
- **Zoom gate:** above the configured zoom, the map holds the data back and the sidebar asks the user to search or zoom
  in.
- **Address search** with Adressevælger, and "nearest places" from the chosen address.
- **Map controls:** keyboard panning on, mouse-wheel zoom off, so that the map does not catch page scroll.

Split the Leaflet app into Stimulus controllers (map, list, detail, search, filters) and Twig components (sidebar,
list row, detail, zoom gate). The Septima renderer reuses the Twig components where the widget allows `detach`, and
otherwise uses its own panel. Port the patches from `public/js/a11y-fixes.js` and `public/css/enter-widget.css`, as
`docs/septima-overrides.md` lists them.

### Accessibility

- Zero axe-core violations on both renderers, with the detail open and closed, and with the suggestion list open. The
  plain Septima build has violations that configuration cannot fix, so the Septima renderer needs the patches above.
- A skip link to the list and the map.
- All functions work without a pointer: the list is in the tab order, and each row has a button that shows the place
  on the map.
- Check with a screen reader before the first release.

### Security headers for the embed

- `Content-Security-Policy: frame-ancestors` with the sites that may embed the map, from configuration (for example
  `https://aarhus.dk https://*.aarhus.dk`).
- No session and no cookies on `/embed` and `/data`. HTTP cache headers on both.

## Part 2: the builder

### Entity

`MapConfiguration`:

| Field | Type | Note |
| --- | --- | --- |
| `id` | UUID, or a slug that the user can edit | Used in `/embed/{renderer}/{id}` |
| `title` | string | Shown in the list of configurations |
| `renderers` | list of `leaflet`, `septima` | The checkboxes in the form |
| `config` | JSON | Validated against the schema |
| `mapJson` | JSON | `{ "leaflet": {…}, "septima": {…} }`, made on save |
| `schemaVersion` | int | |
| `createdAt`, `updatedAt` | datetime, UTC | |

Add a version table later if editors need history and undo.

### Form

- A Live Component with Symfony Form. Sections: data sets (from Enter's `/sources.json` through the proxy), closed
  filters, fields for rows and the detail view (presets for each data model), open filters, sidebar, view (choose it
  on a small map), interaction, renderers.
- **Live preview:** an iframe of `/embed/{renderer}?c=…` from the current form state. Updated with a short delay.
- **On save:** validate `config`, run the converters, store `mapJson`, and show the converter warnings.
- **Embed code:** one `<iframe>` for each selected renderer, with `title`, `width="100%"`, a height field, and
  `loading="lazy"`. A copy button.

```html
<iframe src="https://maps.example.dk/embed/leaflet/toiletter" title="Kort over offentlige toiletter i Aarhus"
        width="100%" height="700" loading="lazy" style="border:0"></iframe>
```

### Access to the builder

The builder changes what other sites show. Protect it from the first deploy: basic auth in nginx to start with, and
the municipality's login (OpenID Connect) if editors outside ITK Dev need it.

## Phases

Each phase ends with a deploy to staging and a PR to `develop`.

1. **Scaffold.**
   - The ITK Dev Symfony template, Docker, Taskfile, GitHub Actions and Woodpecker.
   - Coding standards, PHPStan, CLAUDE.md, CHANGELOG and the PR template.
   - Doctrine with MariaDB, and an empty `MapConfiguration` migration.
2. **Data proxy.**
   - The scoped client for Enter, with basic auth from `ENTER_BASIC_AUTH`.
   - `/data/{dataSet}.geojson`: paging, `where`, cache, and only data sets from `/sources.json`.
   - Tests with `MockHttpClient`.
3. **Configuration and the Leaflet map.**
   - The JSON Schema for v1, and the `config` value object with validation.
   - The Leaflet converter.
   - `/embed/leaflet` with `c` and the overrides.
   - The port of the prototype to Twig components and Stimulus. axe-core in CI.
4. **Builder.**
   - The `MapConfiguration` entity, the form and the live preview.
   - Save, the converter warnings, and the embed code.
   - `/embed/{renderer}/{id}`. Basic auth on `/builder`.
5. **Septima renderer.**
   - The Septima converter, from the field definitions to templates and `computedProperties`.
   - `/embed/septima`, and the a11y patches.
   - Compare with the prototypes, and decide on `map_json_overrides`.
6. **Hardening.**
   - `frame-ancestors`, cache headers, and error pages inside the frame.
   - Monitoring of the proxy, and documentation for editors.

## Tests

- **PHPUnit:** the converters (`config` in, `map_json` out, snapshot files), the schema validation, the URL
  encoding, the proxy with `MockHttpClient`, and the entity.
- **Browser tests** with Playwright and axe-core: each renderer with a fixed configuration and fixed data. Serve the
  data from fixture files, so that the tests do not need Enter.
- **Contract test against Enter:** a scheduled CI job that reads `/sources.json` and one page of each data set that is
  in use, and checks the fields that the presets use. It warns when Enter changes an attribute.

## Open questions

1. **Septima licence.** Septima Widget is commercial. A licence that covers aarhus.dk must be in place before a Septima
   map is published. Without it, build the Leaflet renderer only.
2. **Base map tiles.** OpenStreetMap needs attribution and a check of its usage policy. Datafordeler or Septima tiles
   are an alternative. If a tile service needs a token, serve it through this project, so that the token stays on the
   server.
3. **`c` or `{id}` in the embed code** (see "URL format").
4. **Enter on production.** Is the public NGSI-LD API of Enter's production site open for `GET`, or behind basic auth
   as on staging? The proxy supports both.
5. **Who uses the builder,** and does it need the municipality's login?
6. **Names of the data sets.** The embed codes depend on the ids in Enter's `/sources.json`. Agree with the Enter
   team that a published id does not change, or that a change is announced.

## References

- Prototypes: <https://github.com/itk-enter/enter-map-examples>, published at
  <https://itk-enter.github.io/enter-map-examples/>.
- Enter: <https://github.com/itk-enter/enter>. `/sources.json` lists the data sets with their type, JSON-LD context
  and title.
- Septima Widget: <https://widget.cdn.septima.dk/>.
- Leaflet: <https://leafletjs.com/>.
- Symfony UX Twig Components: <https://symfony.com/bundles/ux-twig-component/current/index.html>.
- Symfony UX Live Components: <https://symfony.com/bundles/ux-live-component/current/index.html>.
- ITK Dev Docker templates: <https://github.com/itk-dev/devops_itkdev-docker>.
