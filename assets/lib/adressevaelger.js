/**
 * @file Address search on Adressevælger from Klimadatastyrelsen.
 *
 * There are two calls: a search that gives labels and ids, then a lookup of
 * the access point of the chosen id. Each call sends a token. The token
 * comes from ADRESSEVAELGER_TOKEN in .env, through the search controller.
 * The search gives only addresses in one municipality. Its code comes from
 * ADRESSEVAELGER_MUNICIPALITY_CODE in .env, also through the controller.
 */

import proj4 from "proj4";

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
// The maximum number of suggestions under the search field.
const SUGGESTION_COUNT = 5;
// Adressevælger gives positions in ETRS89 / UTM zone 32N (EPSG:25832), and
// it has no option for WGS84. proj4 changes them to WGS84 for Leaflet.
const UTM_32N = "+proj=utm +zone=32 +ellps=GRS80 +units=m +no_defs";

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
 * Finds addresses and streets in a municipality.
 *
 * @param {string} text The text that the user typed.
 * @param {string} token The token of Adressevælger.
 * @param {string} municipality The code of the municipality, for example
 *   "0751" for Aarhus.
 * @returns {Promise<Hit[]>} The first suggestions.
 * @throws {Error} If Adressevælger gives an error.
 */
export async function search(text, token, municipality) {
  const response = await fetch(
    `${BASE}/husnumre/soeg?kommunekode=${encodeURIComponent(municipality)}&token=${encodeURIComponent(token)}&tekst=${encodeURIComponent(text)}`,
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

  const [lon, lat] = proj4(UTM_32N, "WGS84", [
    point.koordinater.x,
    point.koordinater.y,
  ]);

  return { lat, lon };
}
