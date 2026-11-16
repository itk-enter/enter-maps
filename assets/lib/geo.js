/**
 * @file Positions and distances, computed in the browser.
 */

/**
 * A position in WGS84.
 *
 * @typedef {object} LatLon
 * @property {number} lat The latitude, in degrees.
 * @property {number} lon The longitude, in degrees.
 */

/**
 * Gives the centre of a geometry. Areas use the mean of their points, which
 * is close enough for the list and the markers.
 *
 * @param {{type: string, coordinates: Array}} geometry A GeoJSON geometry.
 * @returns {LatLon} The centre.
 */
export function centroid(geometry) {
  if (geometry.type === "Point") {
    return { lat: geometry.coordinates[1], lon: geometry.coordinates[0] };
  }

  const points =
    geometry.type === "MultiPoint" || geometry.type === "LineString"
      ? geometry.coordinates
      : geometry.type === "Polygon" || geometry.type === "MultiLineString"
        ? geometry.coordinates.flat()
        : geometry.coordinates.flat(2);
  const sum = points.reduce(
    (total, point) => ({
      lat: total.lat + point[1],
      lon: total.lon + point[0],
    }),
    { lat: 0, lon: 0 },
  );

  return { lat: sum.lat / points.length, lon: sum.lon / points.length };
}

/**
 * Gives the distance on the surface of the earth, with the haversine formula.
 *
 * @param {LatLon} from The first position.
 * @param {LatLon} to The second position.
 * @returns {number} The distance, in metres.
 */
export function distanceInMetres(from, to) {
  const radius = 6371000;
  const toRadians = (degrees) => (degrees * Math.PI) / 180;
  const deltaLat = toRadians(to.lat - from.lat);
  const deltaLon = toRadians(to.lon - from.lon);
  const a =
    Math.sin(deltaLat / 2) ** 2 +
    Math.cos(toRadians(from.lat)) *
      Math.cos(toRadians(to.lat)) *
      Math.sin(deltaLon / 2) ** 2;

  return 2 * radius * Math.asin(Math.sqrt(a));
}

/**
 * Formats a distance as "120 m" or "1,4 km", with the decimal separator of
 * the locale.
 *
 * @param {number} metres The distance, in metres.
 * @param {string} locale The language of the page, for example "da".
 * @returns {string} The text.
 */
export function formatDistance(metres, locale) {
  if (metres < 1000) {
    return `${Math.round(metres / 10) * 10} m`;
  }

  return `${new Intl.NumberFormat(locale, { maximumFractionDigits: 1, minimumFractionDigits: 1 }).format(metres / 1000)} km`;
}
