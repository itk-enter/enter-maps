import { Controller } from "@hotwired/stimulus";
import * as L from "leaflet";
import { formatField, matchesFilters } from "../lib/fields.js";

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

const DEFAULT_VIEW = { center: [56.15355, 10.21432], zoom: 15 };
const NEAREST_COUNT = 5;
const GATE_STEPS = 4;
const SELECTED_ZOOM = 17;
const ADDRESS_ZOOM = 16;
const MARKER_SIZE = 30;
const SELECTED_MARKER_SIZE = 38;

export default class extends Controller {
  static values = { settings: Object, options: Object, messages: Object };

  static targets = ["map", "status", "marker", "gate", "gateBar"];

  static outlets = ["map-list", "map-detail"];

  /** Makes the Leaflet map and starts to load the data. */
  connect() {
    const settings = this.settingsValue;
    const options = this.optionsValue;
    const reducedMotion = window.matchMedia(
      "(prefers-reduced-motion: reduce)",
    ).matches;

    this.state = {
      places: [],
      query: "",
      layers: new Set(
        options.layers ?? settings.layers.map((layer) => layer.id),
      ),
      filters: [],
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

    L.tileLayer(settings.baseMap.url, {
      maxZoom: settings.baseMap.maxZoom,
      attribution: settings.baseMap.attribution,
    }).addTo(this.map);

    this.markerLayer = L.layerGroup().addTo(this.map);

    // A pan changes what the list holds. A zoom also changes the zoom gate.
    // Both end with "moveend".
    this.map.on("moveend", () => {
      this.updateGate();
      this.render();
    });

    this.updateGate();
    this.load();
  }

  /** Removes the Leaflet map. */
  disconnect() {
    this.map.remove();
  }

  /**
   * Loads the data of each layer from the data proxy. Then shows the places
   * and selects the place in the "select" option. If a layer fails, the
   * list shows an error.
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
   * Tells if the list shows a place. The list holds what the map shows.
   *
   * @param {Place} place The place.
   * @returns {boolean} True if the layer is on, the place is in the view, and
   *   the place matches the filters and the search text.
   */
  matches(place) {
    if (!this.state.layers.has(place.layer.id)) {
      return false;
    }
    if (!this.map.getBounds().contains([place.lat, place.lon])) {
      return false;
    }
    if (!matchesFilters(place, this.state.filters)) {
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
   * Gives the places to show. With a chosen address, gives the nearest
   * places, sorted by distance.
   *
   * @returns {Place[]} The places. Empty behind the zoom gate.
   */
  visiblePlaces() {
    if (this.state.gated) {
      return [];
    }

    const places = this.state.places.filter((place) => this.matches(place));

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

  /** Draws a marker for each visible place, and the area of a polygon. */
  renderMarkers() {
    this.markerLayer.clearLayers();

    this.visible.forEach((place) => {
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
          .on("click", () => this.selectPlace(place.id))
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
    this.map.setView(
      [place.lat, place.lon],
      Math.max(this.map.getZoom(), SELECTED_ZOOM),
    );
    // setView renders the list and the markers again on "moveend". If the
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

  /** Action for map-detail:close. */
  deselect() {
    this.state.selected = null;
    this.renderMarkers();
  }

  /**
   * Action for map-filters:change.
   *
   * @param {CustomEvent<{layers: string[],
   *   filters: {index: number, codes: string[]}[]}>} event
   */
  filter({ detail }) {
    this.state.layers = new Set(detail.layers);
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
