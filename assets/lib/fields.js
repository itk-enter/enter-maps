/*
 * Reads the fields of the configuration from the properties of a feature.
 *
 * A field is data, not code: { path, label, fallback, format, values }. The
 * converter on the server gives "path" as a list of keys. The result is
 * plain text, so the callers put it into the page with textContent.
 */

/* The value at a path, or undefined. */
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

function isEmpty(value) {
  return (
    value === undefined ||
    value === null ||
    value === "" ||
    (Array.isArray(value) && value.length === 0)
  );
}

/* The text of a code in "values", or the value itself. */
function text(field, value) {
  const key = String(value);

  return Object.hasOwn(field.values ?? {}, key)
    ? field.values[key]
    : String(value);
}

/*
 * Gives { text } for a field, { text, items } for a list and { text, href }
 * for a link. Gives null if the value is missing and the field has no
 * fallback, so that the caller leaves the row out.
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

/*
 * True if the place matches each open filter that has selected codes. A
 * filter does not change the places of a layer that is not in its "layers".
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
