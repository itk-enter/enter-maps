# The map configuration

A map configuration (`config`) describes one map: the data sets, the fields, the filters and the sidebar. The
converters make the settings of each renderer from it. The embed page and the builder use the same configuration.

The schema files in this directory define the configuration. Each property in a schema file has a `description`.
Your editor shows it if you connect the schema to a JSON file.

| File | Holds |
| --- | --- |
| [`map-config.v1.json`](map-config.v1.json) | The map configuration, version 1 |
| [`cql2-filter.json`](cql2-filter.json) | The part of CQL2-JSON that the data proxy supports. The closed filters use it. |

## Examples

- [`toilets.json`](../../tests/Fixtures/map-config/toilets.json): one data set, with all the settings.
- [`toilets-and-parking.json`](../../tests/Fixtures/map-config/toilets-and-parking.json): two data sets, with
  different fields and a filter for both.

## The structure

```text
version, title, description, locale   The map
view, zoomGate                         The start view and the minimum zoom level
sidebar                                The list, the categories and the search
layers[]                               The data sets, each with its own fields
filters[]                              The open filters that the user can change
report                                 A link for each feature
leaflet, septima                       Settings that only one renderer uses
```

Most settings are the same for all renderers. Put a setting in `leaflet` or `septima` only if one renderer uses it.
Do not put a configuration in the format of a renderer into these sections. If Septima changes its format, only the
Septima converter must change, not the stored configurations.

## Layers and filters

Each layer has an `id`, a data set from Enter's `/sources.json`, a closed filter (`filter`) and its own fields for the
list row (`row`) and the detail view (`detail`). Two data sets can have different attributes, because each layer
describes its own fields.

The closed filter of a layer is in [CQL2-JSON](https://docs.ogc.org/is/21-065r2/21-065r2.html), not in the query
language of the broker. The user cannot change it. The data proxy makes the query for the broker from it:

```json
{ "op": "=", "args": [{ "property": "isAccessibleForFree" }, true] }
```

The proxy supports `and`, `or`, `=`, `<>`, `<`, `<=`, `>`, `>=`, `in` and `isNull`, with five levels or fewer.
[`cql2-filter.json`](cql2-filter.json) defines this part. The data proxy validates its `filter` parameter against the
same file.

The open filters in `filters[]` are different. The user changes them, and the map applies them to the features that
it has.

A filter has its own codes in `values`. `appliesTo` tells which layers the filter changes, and where each layer keeps
the value:

```json
{
  "type": "toggle",
  "label": "Kun gratis",
  "values": { "free": "Kun gratis" },
  "appliesTo": [
    { "layer": "toilets", "path": "isAccessibleForFree", "codes": { "free": true } },
    { "layer": "parking", "path": "fee", "codes": { "free": "no" } }
  ]
}
```

- Without `codes`, the value in the data is the code of the filter.
- A filter does not change the features of a layer that is not in `appliesTo`.

## Validation

`App\MapConfig\MapConfigParser` reads `version`, validates the configuration against the schema of that version and
makes an `App\MapConfig\MapConfig`. It also checks the rules that a schema cannot express:

- Each layer has its own `id`.
- Each filter refers to layers and codes that exist.

An error tells where the problem is, as a JSON pointer:

```text
/layers/0/colour: The string should match pattern: ^#[0-9a-fA-F]{6}$
/filters/0/appliesTo/0/layer: There is no layer with the id "toilets".
```

## Rules

- Texts are plain text. A configuration holds no HTML and no JavaScript.
- A configuration holds no queries in the language of the broker. Closed filters are in CQL2-JSON.
- A base map is a name from a list on the server, not a URL.
- `report.url` must start with `https://`.
- Do not change the meaning of a property in a version that is in use, and do not remove an old schema file. Embed
  codes stay on other sites for a long time. A change that old configurations do not pass needs a new version and a
  new schema file. Add the file to `MapConfigParser::SCHEMAS`.
