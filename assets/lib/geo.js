/* Positions and distances, computed in the browser. */

/* The centre of a geometry, as { lat, lon }. Areas use the mean of their
 * points, which is close enough for the list and the markers. */
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

/* "120 m" or "1,4 km", with the decimal separator of the locale. */
export function formatDistance(metres, locale) {
  if (metres < 1000) {
    return `${Math.round(metres / 10) * 10} m`;
  }

  return `${new Intl.NumberFormat(locale, { maximumFractionDigits: 1, minimumFractionDigits: 1 }).format(metres / 1000)} km`;
}
