/**
 * @file Address search on Adressevælger from Klimadatastyrelsen.
 *
 * There are two calls: a search that gives labels and ids, then a lookup of
 * the access point of the chosen id. Each call sends a token. The token
 * comes from ADRESSEVAELGER_TOKEN in .env, through the search controller.
 */

/**
 * A suggestion from the search.
 *
 * @typedef {object} Hit
 * @property {string} id The id of the house number or the street.
 * @property {string} titel The label, for example "Rådhuspladsen 2, 8000
 *   Aarhus C".
 * @property {string} type "husnummer" for an address. Other values are
 *   streets.
 */

// The address of the API of Adressevælger.
const BASE = "https://adressevaelger.dk";
// The search gives only addresses in this municipality. 0751 is Aarhus.
const MUNICIPALITY_CODE = "0751";
// The maximum number of suggestions under the search field.
const SUGGESTION_COUNT = 5;

/**
 * Tells if a suggestion is an address. A street has no position and no house
 * number. To choose a street makes the search narrower. It does not choose
 * an address.
 *
 * @param {Hit} hit The suggestion.
 * @returns {boolean} True for an address.
 */
export function isAddress(hit) {
  return hit.type === "husnummer";
}

/**
 * Finds addresses and streets in the municipality.
 *
 * @param {string} text The text that the user typed.
 * @param {string} token The token of Adressevælger.
 * @returns {Promise<Hit[]>} The first suggestions.
 * @throws {Error} If Adressevælger gives an error.
 */
export async function search(text, token) {
  const response = await fetch(
    `${BASE}/husnumre/soeg?kommunekode=${MUNICIPALITY_CODE}&token=${encodeURIComponent(token)}&tekst=${encodeURIComponent(text)}`,
  );

  if (!response.ok) {
    throw new Error(`Adressevælger answered ${response.status}`);
  }

  const payload = await response.json();

  return (payload.fund || []).slice(0, SUGGESTION_COUNT);
}

/**
 * Gives the access point of an address.
 *
 * @param {Hit} hit An address. See isAddress().
 * @param {string} token The token of Adressevælger.
 * @returns {Promise<{lat: number, lon: number}>} The access point, in WGS84.
 * @throws {Error} If Adressevælger gives an error, or if the address has no
 *   access point.
 */
export async function position(hit, token) {
  const response = await fetch(
    `${BASE}/husnumre/${encodeURIComponent(hit.id)}?token=${encodeURIComponent(token)}`,
  );

  if (!response.ok) {
    throw new Error(`Adressevælger answered ${response.status}`);
  }

  const payload = await response.json();
  const point = payload.husnummer && payload.husnummer.adgangspunkt;

  if (!point || !point.koordinater) {
    throw new Error("The address has no access point");
  }

  return utm32ToWgs84(point.koordinater.x, point.koordinater.y);
}

/**
 * Changes a position from ETRS89 / UTM zone 32N (EPSG:25832), which
 * Adressevælger gives, to WGS84. This is the inverse transverse Mercator on
 * the GRS80 ellipsoid.
 *
 * @param {number} east The easting, in metres.
 * @param {number} north The northing, in metres.
 * @returns {{lat: number, lon: number}} The position.
 */
function utm32ToWgs84(east, north) {
  const a = 6378137;
  const f = 1 / 298.257222101;
  const k0 = 0.9996;
  const e2 = f * (2 - f);
  const ep2 = e2 / (1 - e2);
  const x = east - 500000;
  const m = north / k0;
  const mu =
    m / (a * (1 - e2 / 4 - (3 * e2 * e2) / 64 - (5 * e2 * e2 * e2) / 256));
  const e1 = (1 - Math.sqrt(1 - e2)) / (1 + Math.sqrt(1 - e2));
  const phi =
    mu +
    (1.5 * e1 - (27 * e1 ** 3) / 32) * Math.sin(2 * mu) +
    ((21 * e1 * e1) / 16 - (55 * e1 ** 4) / 32) * Math.sin(4 * mu) +
    ((151 * e1 ** 3) / 96) * Math.sin(6 * mu) +
    ((1097 * e1 ** 4) / 512) * Math.sin(8 * mu);
  const sin = Math.sin(phi);
  const cos = Math.cos(phi);
  const tan = Math.tan(phi);
  const c = ep2 * cos * cos;
  const t = tan * tan;
  const n = a / Math.sqrt(1 - e2 * sin * sin);
  const r = (a * (1 - e2)) / (1 - e2 * sin * sin) ** 1.5;
  const d = x / (n * k0);
  const lat =
    phi -
    ((n * tan) / r) *
      ((d * d) / 2 -
        ((5 + 3 * t + 10 * c - 4 * c * c - 9 * ep2) * d ** 4) / 24 +
        ((61 + 90 * t + 298 * c + 45 * t * t - 252 * ep2 - 3 * c * c) *
          d ** 6) /
          720);
  const lon =
    (d -
      ((1 + 2 * t + c) * d ** 3) / 6 +
      ((5 - 2 * c + 28 * t - 3 * c * c + 8 * ep2 + 24 * t * t) * d ** 5) /
        120) /
    cos;

  return { lat: (lat * 180) / Math.PI, lon: 9 + (lon * 180) / Math.PI };
}
