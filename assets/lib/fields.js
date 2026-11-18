/**
 * @file Reads the fields of the configuration from the properties of a
 * feature. A field is data, not code. The result is plain text, so the
 * callers put it into the page with textContent.
 */

/**
 * A field from map_json.leaflet.
 *
 * @typedef {object} Field
 * @property {string[]} path The keys to the value in the properties. The
 *   converter on the server makes the list from "a.b.c".
 * @property {string|null} label The label, or null.
 * @property {string|null} fallback The text if the value is missing, or null.
 * @property {string|null} format "list", "boolean", "number", "date", "link"
 *   or null for plain text.
 * @property {Object<string, string>} values The text of each code.
 */

/**
 * A formatted value. "items" is set for a list, and "href" for a link.
 *
 * @typedef {object} FieldValue
 * @property {string} text The value as text.
 * @property {string[]} [items] The text of each item of a list.
 * @property {string} [href] The web address of a link.
 */

/**
 * What formatField needs from the page.
 *
 * @typedef {object} FormatContext
 * @property {string} locale The language of the page, for numbers and dates.
 * @property {{yes: string, no: string}} messages The texts of true and false.
 */

/**
 * Gives the value at a path.
 *
 * @param {object} properties The properties of a feature.
 * @param {string[]} path The keys to the value.
 * @returns {*} The value, or undefined.
 */
export function readPath(properties, path) {
  let value = properties;

  for (const key of path) {
    if (value === null || typeof value !== "object") {
      return undefined;
    }
    value = value[key];
  }

  return value;
}

/**
 * @param {*} value A value from the properties.
 * @returns {boolean} True for undefined, null, "" and an empty list.
 */
function isEmpty(value) {
  return (
    value === undefined ||
    value === null ||
    value === "" ||
    (Array.isArray(value) && value.length === 0)
  );
}

/**
 * @param {Field} field The field.
 * @param {*} value A value from the properties.
 * @returns {string} The text of the code in "values", or the value itself.
 */
function text(field, value) {
  const key = String(value);

  return Object.hasOwn(field.values ?? {}, key)
    ? field.values[key]
    : String(value);
}

/**
 * Formats the value of a field. If the value is missing and the field has no
 * fallback, the caller leaves the row out.
 *
 * @param {Field} field The field.
 * @param {object} properties The properties of a feature.
 * @param {FormatContext} context The language and the texts of the page.
 * @returns {FieldValue|null} The value, or null.
 */
export function formatField(field, properties, context) {
  const value = readPath(properties, field.path);

  if (isEmpty(value)) {
    return field.fallback === null || field.fallback === undefined
      ? null
      : { text: field.fallback };
  }

  switch (field.format) {
    case "list": {
      const items = (Array.isArray(value) ? value : [value]).map((item) =>
        text(field, item),
      );

      return { text: items.join(", "), items };
    }
    case "boolean":
      if (Object.hasOwn(field.values ?? {}, String(value))) {
        return { text: text(field, value) };
      }

      return {
        text:
          typeof value === "boolean"
            ? value
              ? context.messages.yes
              : context.messages.no
            : String(value),
      };
    case "number":
      return {
        text:
          typeof value === "number"
            ? new Intl.NumberFormat(context.locale).format(value)
            : text(field, value),
      };
    case "date": {
      const date = new Date(value);

      return {
        text: Number.isNaN(date.getTime())
          ? String(value)
          : new Intl.DateTimeFormat(context.locale, {
              dateStyle: "long",
            }).format(date),
      };
    }
    case "link": {
      const href = String(value);

      // Only web addresses, so that a "javascript:" value cannot be a link.
      return /^https?:\/\//i.test(href) ? { text: href, href } : { text: href };
    }
    default:
      return { text: text(field, value) };
  }
}

/**
 * An open filter from map_json.leaflet.
 *
 * @typedef {object} Filter
 * @property {string} type The type of the control.
 * @property {string} label The label.
 * @property {Object<string, string>} values The text of each code.
 * @property {object[]} layers The layers that the filter applies to.
 * @property {string} layers[].layer The id of the layer.
 * @property {string[]} layers[].path The keys to the value in the
 *   properties.
 * @property {Object<string, *>} layers[].dataValues The value in the data
 *   of each code.
 */

/**
 * A filter with the codes that the user selected.
 *
 * @typedef {object} ActiveFilter
 * @property {Filter} filter The filter.
 * @property {string[]} codes The selected codes. Empty means "all".
 */

/**
 * Tells if a place matches each open filter that has selected codes. A
 * filter does not change the places of a layer that is not in its "layers".
 *
 * @param {import("../controllers/map_controller.js").Place} place The place.
 * @param {ActiveFilter[]} filters The open filters.
 * @returns {boolean} True if the place matches.
 */
export function matchesFilters(place, filters) {
  return filters.every(({ filter, codes }) => {
    const target = filter.layers.find(
      (candidate) => candidate.layer === place.layer.id,
    );

    if (codes.length === 0 || !target) {
      return true;
    }

    const value = readPath(place.properties, target.path);
    const values = Array.isArray(value) ? value : [value];

    return codes.some((code) => values.includes(target.dataValues[code]));
  });
}

/**
 * A condition of an open filter of a layer, from map_json.leaflet.
 *
 * @typedef {object} Condition
 * @property {string} op The operator: "=", "<>", "<", "<=", ">", ">=", "in"
 *   or "isNull".
 * @property {string[]} path The keys to the value in the properties.
 * @property {*[]} values The values: a list for "in", none for "isNull" and
 *   one for the other operators.
 */

/**
 * A condition of a layer that the user can turn on and off.
 *
 * @typedef {object} OpenFilter
 * @property {string} label The text of the checkbox.
 * @property {boolean} checked True if the condition is on at the start.
 * @property {Condition} condition The condition.
 */

/**
 * Tells if the properties of a feature match a condition, as the broker
 * compares them. A missing value matches only "isNull". A list matches if
 * one of its items matches.
 *
 * @param {object} properties The properties of a feature.
 * @param {Condition} condition The condition.
 * @returns {boolean} True if the properties match.
 */
export function matchesCondition(properties, condition) {
  const value = readPath(properties, condition.path);

  if (condition.op === "isNull") {
    return isEmpty(value);
  }
  if (isEmpty(value)) {
    return false;
  }

  const [expected] = condition.values;
  const items = Array.isArray(value) ? value : [value];

  return items.some((item) => {
    switch (condition.op) {
      case "=":
        return item === expected;
      case "<>":
        return item !== expected;
      case "<":
        return item < expected;
      case "<=":
        return item <= expected;
      case ">":
        return item > expected;
      case ">=":
        return item >= expected;
      case "in":
        return condition.values.includes(item);
      default:
        return true;
    }
  });
}
