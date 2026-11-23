import { Controller } from "@hotwired/stimulus";
import * as L from "leaflet";
import { fly } from "../lib/flight.js";
import {
  formatField,
  matchesCondition,
  matchesFilters,
} from "../lib/fields.js";

/**
 * A layer from map_json.leaflet.
 *
 * @typedef {object} Layer
 * @property {string} id The id of the layer.
 * @property {string} label The label.
 * @property {string} url The URL of the data proxy for the data set.
 * @property {string} colour The colour, as a CSS value.
 * @property {{image: string}|{glyph: string}} icon The icon.
 * @property {{title: import("../lib/fields.js").Field,
 *   subtitle: import("../lib/fields.js").Field|null}} row The fields of a
 *   row in the list.
 * @property {import("../lib/fields.js").Field[]} detail The fields of the
 *   detail view.
 * @property {import("../lib/fields.js").OpenFilter[]} [openFilters] The
 *   conditions that the user can turn on and off. A map_json from before
 *   the open filters has none.
 */

/**
 * The settings of the map, from map_json.leaflet.
 *
 * @typedef {object} Settings
 * @property {string} title The title.
 * @property {{center: number[], zoom: number}|null} view The start view.
 * @property {number|null} minZoom Below this zoom, the map holds the data
 *   back.
 * @property {{url: string, attribution: string, maxZoom: number}} baseMap
 *   The tiles.
 * @property {Layer[]} layers The layers.
 * @property {import("../lib/fields.js").Filter[]} filters The open filters.
 * @property {{label: string, url: string, target: string}|null} report The
 *   report link.
 */

/**
 * The embed options from the query string. Null means "use the settings".
 *
 * @typedef {object} Options
 * @property {number|null} zoom The start zoom.
 * @property {number[]|null} center The start centre, as [lat, lon].
 * @property {string[]|null} layers The ids of the layers that are on.
 * @property {string|null} select The id of the place to select.
 */

/**
 * A feature of a layer, with what the list, the detail view and the markers
 * need.
 *
 * @typedef {object} Place
 * @property {string} id The id of the feature, or "<layer>-<index>".
 * @property {Layer} layer The layer.
 * @property {object} properties The properties of the feature.
 * @property {object} geometry The GeoJSON geometry.
 * @property {number} lat The latitude of the centre.
 * @property {number} lon The longitude of the centre.
 * @property {string} title The title.
 * @property {string} subtitle The subtitle, or "".
 * @property {{label: string|null,
 *   value: import("../lib/fields.js").FieldValue}[]} details The fields of
 *   the detail view that have a value.
 * @property {number} [distance] The distance to the chosen address, in
 *   metres.
 */

/**
 * The map of an embed page. It holds the state: the places, the search
 * text, the layers and filters that are on, the chosen address and the
 * selected place. It draws the markers, and it tells the list and the
 * detail view what to show.
 *
 * The other controllers send events to this one:
 * - map-list:select and map-detail:close change the selected place.
 * - map-filters:change changes the layers and the open filters.
 * - address-search:query and address-search:choose change the search text
 *   and the chosen address.
 *
 * Values: settings ({@link Settings}), options ({@link Options}) and
 * messages (the texts of the page).
 * Targets: map, status, marker (a template for each layer), gate and
 * gateBar.
 * Outlets: map-list and map-detail.
 */

// The start view if the settings and the options have none, in Aarhus.
const DEFAULT_VIEW = { center: [56.15355, 10.21432], zoom: 15 };
// The number of places in the list for a chosen address.
const NEAREST_COUNT = 5;
// The bar of the zoom gate fills over this number of zoom steps.
const GATE_STEPS = 4;
// The smallest zoom after a selection.
const SELECTED_ZOOM = 17;
// The zoom after the user chooses an address.
const ADDRESS_ZOOM = 16;
// The size of a marker, in pixels.
const MARKER_SIZE = 30;
// The size of the marker of the selected place, in pixels.
const SELECTED_MARKER_SIZE = 38;
// The width of the ring of extra tiles around the view, in tiles.
const LOOK_AHEAD_TILES = 1;
// The time of a flight to a selected place, in seconds.
const FLIGHT_SECONDS = 1;
// The time of a pan to a selected place, in seconds.
const PAN_SECONDS = 0.7;
// The margin around the view where the map draws places, as a part of the view.
const MARKER_MARGIN = 0.5;

/**
 * A tile layer that also loads a ring of tiles around the view. Thus a tile
 * is often ready before a pan shows it. The ring is one tile wide, because
 * the tile policy of OpenStreetMap permits only a short look-ahead.
 *
 * Leaflet has no option for this, so the layer changes
 * _getTiledPixelBounds, a private method of Leaflet 1.9. If you update
 * Leaflet, make sure that the method still exists.
 */
const LookAheadTileLayer = L.TileLayer.extend({
  _getTiledPixelBounds(center) {
    const bounds = L.TileLayer.prototype._getTiledPixelBounds.call(
      this,
      center,
    );
    const margin = this.getTileSize().multiplyBy(LOOK_AHEAD_TILES);

    return L.bounds(bounds.min.subtract(margin), bounds.max.add(margin));
  },
});

export default class extends Controller {
  static values = { settings: Object, options: Object, messages: Object };

  static targets = ["map", "status", "marker", "gate", "gateBar", "spinner"];

  static outlets = ["map-list", "map-detail"];

  /** Makes the Leaflet map and starts to load the data. */
  connect() {
    const settings = this.settingsValue;
    const options = this.optionsValue;
    const reducedMotion = window.matchMedia(
      "(prefers-reduced-motion: reduce)",
    ).matches;
    this.reducedMotion = reducedMotion;

    this.state = {
      places: [],
      query: "",
      layers: new Set(
        options.layers ?? settings.layers.map((layer) => layer.id),
      ),
      filters: [],
      // The open filters of the layers that are on, as "<layer>:<index>".
      openFilters: new Set(
        settings.layers.flatMap((layer) =>
          (layer.openFilters ?? [])
            .map((filter, index) =>
              filter.checked ? `${layer.id}:${index}` : null,
            )
            .filter((key) => key !== null),
        ),
      ),
      origin: null,
      selected: null,
      gated: false,
    };

    this.map = L.map(this.mapTarget, {
      center: options.center ?? settings.view?.center ?? DEFAULT_VIEW.center,
      zoom: options.zoom ?? settings.view?.zoom ?? DEFAULT_VIEW.zoom,
      // The map must not catch the scroll of the page around the frame.
      scrollWheelZoom: false,
      keyboard: true,
      zoomAnimation: !reducedMotion,
      fadeAnimation: !reducedMotion,
      markerZoomAnimation: !reducedMotion,
    });

    // The map loads the tiles while it moves, also on a phone, and keeps more
    // of the tiles that the user saw.
    new LookAheadTileLayer(settings.baseMap.url, {
      maxZoom: settings.baseMap.maxZoom,
      attribution: settings.baseMap.attribution,
      updateWhenIdle: false,
      updateInterval: 100,
      keepBuffer: 4,
    }).addTo(this.map);

    this.markerLayer = L.layerGroup().addTo(this.map);

    // A pan changes what the list holds. A zoom also changes the zoom gate.
    // Both end with "moveend".
    this.map.on("moveend", () => {
      this.updateGate();
      this.render();
      this.postView();
    });

    // A click on the map removes the selection. A click on a place does not
    // get to the map, because the markers and the areas stop it.
    this.map.on("click", () => this.clearSelection());

    this.updateGate();
    this.postView();
    this.load();
  }

  /**
   * Tells the page around the frame the view of the map. The builder uses it
   * to fill in the view fields. The message goes only to a page on the same
   * origin, so other sites that show the map do not get it.
   */
  postView() {
    if (window.parent === window) {
      return;
    }
    const center = this.map.getCenter();
    window.parent.postMessage(
      {
        type: "enter-maps:view",
        longitude: center.lng,
        latitude: center.lat,
        zoom: this.map.getZoom(),
      },
      window.location.origin,
    );
  }

  /** Removes the Leaflet map. */
  disconnect() {
    this.map.remove();
  }

  /**
   * Loads the data of each layer from the data proxy. Then shows the places
   * and selects the place in the "select" option. If a layer fails, the
   * list shows an error. At the end, the spinner goes away, if the map has
   * one.
   *
   * @returns {Promise<void>}
   */
  async load() {
    const settings = this.settingsValue;
    // Numbers and dates use the language of the page, which "lang" can change.
    const context = {
      locale: document.documentElement.lang,
      messages: this.messagesValue,
    };

    try {
      const collections = await Promise.all(
        settings.layers.map(async (layer) => {
          const response = await fetch(layer.url);

          if (!response.ok) {
            throw new Error(`The data proxy answered ${response.status}`);
          }

          return (await response.json()).features;
        }),
      );

      this.state.places = collections.flatMap((features, layerIndex) =>
        features.map((feature, index) =>
          this.toPlace(feature, settings.layers[layerIndex], index, context),
        ),
      );
    } catch (error) {
      if (this.hasMapListOutlet) {
        this.mapListOutlet.showError();
      }

      return;
    } finally {
      if (this.hasSpinnerTarget) {
        this.spinnerTarget.remove();
      }
    }

    this.render();

    if (this.optionsValue.select) {
      this.selectPlace(this.optionsValue.select);
    }
  }

  /**
   * Makes a place from a feature.
   *
   * @param {{id?: string, properties?: object, geometry: object}} feature A
   *   GeoJSON feature.
   * @param {Layer} layer The layer of the feature.
   * @param {number} index The index of the feature in the layer.
   * @param {import("../lib/fields.js").FormatContext} context The language
   *   and the texts of the page.
   * @returns {Place} The place.
   */
  toPlace(feature, layer, index, context) {
    const properties = feature.properties ?? {};
    // The centre of the bounds. For an area, the centre can be outside a
    // shape that is not convex. That is close enough for the list and the
    // markers.
    const point = L.geoJSON(feature.geometry).getBounds().getCenter();

    return {
      id: feature.id ?? `${layer.id}-${index}`,
      layer,
      properties,
      geometry: feature.geometry,
      lat: point.lat,
      lon: point.lng,
      title: formatField(layer.row.title, properties, context)?.text ?? "",
      subtitle: this.rowText(layer.row.subtitle, properties, context),
      details: layer.detail
        .map((field) => ({
          label: field.label,
          value: formatField(field, properties, context),
        }))
        .filter((detail) => detail.value !== null),
    };
  }

  /**
   * Gives the subtitle of a row, with its label first if it has one.
   *
   * @param {import("../lib/fields.js").Field|null} field The field.
   * @param {object} properties The properties of the feature.
   * @param {import("../lib/fields.js").FormatContext} context The language
   *   and the texts of the page.
   * @returns {string} The text, or "".
   */
  rowText(field, properties, context) {
    const value = field ? formatField(field, properties, context) : null;

    if (!value) {
      return "";
    }

    return field.label ? `${field.label}: ${value.text}` : value.text;
  }

  /**
   * Updates the zoom gate. Zoomed out, the map holds the data back, and the
   * sidebar tells how to get to it.
   */
  updateGate() {
    const minZoom = this.settingsValue.minZoom;
    const steps = minZoom === null ? 0 : minZoom - this.map.getZoom();
    const gated = steps > 0;
    const progress = Math.min(1, Math.max(0, 1 - steps / GATE_STEPS));

    if (this.hasGateTarget) {
      this.gateTarget.hidden = !gated;
    }
    if (this.hasGateBarTarget) {
      this.gateBarTarget.style.setProperty("--progress", String(progress));
      this.gateBarTarget.setAttribute(
        "aria-valuenow",
        String(Math.round(progress * 100)),
      );
    }

    if (gated === this.state.gated) {
      return;
    }
    this.state.gated = gated;

    // A live region that is hidden announces nothing when it comes back, so
    // a separate status element tells about the gate.
    if (this.hasStatusTarget) {
      this.statusTarget.textContent = gated ? this.messagesValue.zoomGate : "";
    }
  }

  /**
   * Tells if a place is in an area and matches what the user chose.
   *
   * @param {Place} place The place.
   * @param {L.LatLngBounds} bounds The area, for example the view.
   * @returns {boolean} True if the layer is on, the place is in the area, and
   *   the place matches the filters and the search text.
   */
  matches(place, bounds) {
    if (!this.state.layers.has(place.layer.id)) {
      return false;
    }
    if (!bounds.contains([place.lat, place.lon])) {
      return false;
    }
    if (!matchesFilters(place, this.state.filters)) {
      return false;
    }
    if (!this.matchesOpenFilters(place)) {
      return false;
    }
    if (!this.state.query) {
      return true;
    }

    const text =
      `${place.title} ${place.subtitle} ${place.layer.label}`.toLowerCase();

    return text.includes(this.state.query.toLowerCase());
  }
  /**
   * Tells if a place matches each open filter of its layer that is on.
   *
   * @param {Place} place The place.
   * @returns {boolean} True if the place matches.
   */
  matchesOpenFilters(place) {
    return (place.layer.openFilters ?? []).every(
      (filter, index) =>
        !this.state.openFilters.has(`${place.layer.id}:${index}`) ||
        matchesCondition(place.properties, filter.condition),
    );
  }

  /**
   * Gives the places of the list. The list holds what the map shows. With a
   * chosen address, gives the nearest places, sorted by distance.
   *
   * @returns {Place[]} The places. Empty behind the zoom gate.
   */
  visiblePlaces() {
    if (this.state.gated) {
      return [];
    }

    const bounds = this.map.getBounds();
    const places = this.state.places.filter((place) =>
      this.matches(place, bounds),
    );

    if (!this.state.origin) {
      places.forEach((place) => delete place.distance);

      return places;
    }

    places.forEach((place) => {
      place.distance = this.map.distance(
        [this.state.origin.lat, this.state.origin.lon],
        [place.lat, place.lon],
      );
    });

    return places
      .sort((a, b) => a.distance - b.distance)
      .slice(0, NEAREST_COUNT);
  }

  /** Renders the list and the markers from the state. */
  render() {
    this.visible = this.visiblePlaces();

    if (this.hasMapListOutlet) {
      this.mapListOutlet.render(this.visible, {
        gated: this.state.gated,
        origin: this.state.origin,
        selected: this.state.selected,
        showLayer: this.settingsValue.layers.length > 1,
      });
    }

    this.renderMarkers();
  }

  /**
   * Gives the places that get a marker. These are the places that match in
   * the view and in a margin around it. Thus the markers are there before a
   * pan or a flight shows them. With a chosen address, only the places of
   * the list get a marker.
   *
   * @returns {Place[]} The places. Empty behind the zoom gate.
   */
  markedPlaces() {
    if (this.state.gated || this.state.origin) {
      return this.visible;
    }

    const bounds = this.map.getBounds().pad(MARKER_MARGIN);

    return this.state.places.filter((place) => this.matches(place, bounds));
  }

  /** Draws a marker for each marked place, and the area of a polygon. */
  renderMarkers() {
    this.markerLayer.clearLayers();

    this.markedPlaces().forEach((place) => {
      const selected = this.state.selected === place.id;

      if (
        place.geometry.type === "Polygon" ||
        place.geometry.type === "MultiPolygon"
      ) {
        L.geoJSON(place.geometry, {
          style: {
            color: place.layer.colour,
            weight: 2,
            fillColor: place.layer.colour,
            fillOpacity: selected ? 0.45 : 0.25,
          },
        })
          .on("click", (event) => {
            // Only the click stops here. A double click on the area still
            // zooms the map.
            L.DomEvent.stopPropagation(event);
            this.selectPlace(place.id);
          })
          .addTo(this.markerLayer);
      }

      L.marker([place.lat, place.lon], {
        icon: this.markerIcon(place, selected),
        // The list is the way to the places with a keyboard.
        keyboard: false,
        title: [place.title, place.subtitle].filter(Boolean).join(", "),
        zIndexOffset: selected ? 1000 : 0,
      })
        .on("click", () => this.selectPlace(place.id))
        .addTo(this.markerLayer);
    });
  }

  /**
   * Makes the icon of a marker. The markup comes from the template of its
   * layer.
   *
   * @param {Place} place The place.
   * @param {boolean} selected True if the place is selected.
   * @returns {L.DivIcon} The icon.
   */
  markerIcon(place, selected) {
    const template = this.markerTargets.find(
      (candidate) => candidate.dataset.layerId === place.layer.id,
    );
    const element = template.content.firstElementChild.cloneNode(true);
    const size = selected ? SELECTED_MARKER_SIZE : MARKER_SIZE;

    element.toggleAttribute("data-selected", selected);

    return L.divIcon({
      className: "map-marker",
      html: element,
      iconSize: [size, size],
      iconAnchor: [size / 2, size / 2],
    });
  }

  /**
   * Selects a place, moves the map to it and opens the detail view.
   *
   * @param {string} id The id of the place. An unknown id does nothing.
   * @param {HTMLElement|null} [returnFocusTo] The element that gets focus
   *   when the detail view closes. Without it, the row of the place gets
   *   focus.
   */
  selectPlace(id, returnFocusTo = null) {
    const place = this.state.places.find((candidate) => candidate.id === id);

    if (!place) {
      return;
    }

    this.state.selected = id;
    // A slow movement is softer than a jump. With reduced motion, the map
    // jumps.
    fly(
      this.map,
      [place.lat, place.lon],
      Math.max(this.map.getZoom(), SELECTED_ZOOM),
      {
        flightSeconds: FLIGHT_SECONDS,
        panSeconds: PAN_SECONDS,
        animate: !this.reducedMotion,
      },
    );
    // The list and the markers render again on "moveend", at the end of the
    // movement. Render them now too, so the selection shows at once. If the
    // view does not change, there is no "moveend".
    this.render();

    if (this.hasMapDetailOutlet) {
      // The list renders again after the selection, so the detail view asks
      // for the row when it closes.
      this.mapDetailOutlet.open(place, {
        returnFocus: () => {
          if (returnFocusTo && document.contains(returnFocusTo)) {
            return returnFocusTo;
          }

          return this.hasMapListOutlet ? this.mapListOutlet.rowFor(id) : null;
        },
      });
    }
  }

  /* Actions --------------------------------------------------------------- */

  /**
   * Action for map-list:select.
   *
   * @param {CustomEvent<{id: string, returnFocusTo: HTMLElement}>} event
   */
  select({ detail }) {
    this.selectPlace(detail.id, detail.returnFocusTo);
  }

  /**
   * Removes the selection after a click on the map. The detail view closes
   * and sends map-detail:close, which calls deselect(). Focus stays on the
   * map.
   */
  clearSelection() {
    if (this.state.selected === null) {
      return;
    }

    if (this.hasMapDetailOutlet) {
      this.mapDetailOutlet.hide();
    } else {
      this.deselect();
    }
  }

  /** Action for map-detail:close. */
  deselect() {
    this.state.selected = null;
    this.renderMarkers();
  }

  /**
   * Action for map-filters:change.
   *
   * @param {CustomEvent<{layers: string[],
   *   filters: {index: number, codes: string[]}[],
   *   openFilters: string[]}>} event
   */
  filter({ detail }) {
    this.state.layers = new Set(detail.layers);
    this.state.openFilters = new Set(detail.openFilters);
    this.state.filters = detail.filters.map(({ index, codes }) => ({
      filter: this.settingsValue.filters[index],
      codes,
    }));
    this.render();
  }

  /**
   * Action for address-search:query. Removes the chosen address.
   *
   * @param {CustomEvent<{query: string}>} event
   */
  query({ detail }) {
    this.state.query = detail.query;
    this.state.origin = null;
    this.render();
  }

  /**
   * Action for address-search:choose. Removes the search text.
   *
   * @param {CustomEvent<{lat: number, lon: number, label: string}>} event
   */
  origin({ detail }) {
    this.state.origin = detail;
    this.state.query = "";
    this.map.setView([detail.lat, detail.lon], ADDRESS_ZOOM);
    this.render();
  }
}
