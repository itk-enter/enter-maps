import { Controller } from "@hotwired/stimulus";
import * as L from "leaflet";
import { formatField, matchesFilters } from "../lib/fields.js";
import { centroid, distanceInMetres } from "../lib/geo.js";

/*
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

  disconnect() {
    this.map.remove();
  }

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

  /* A place holds what the list, the detail view and the markers need. */
  toPlace(feature, layer, index, context) {
    const properties = feature.properties ?? {};
    const point = centroid(feature.geometry);

    return {
      id: feature.id ?? `${layer.id}-${index}`,
      layer,
      properties,
      geometry: feature.geometry,
      lat: point.lat,
      lon: point.lon,
      title: formatField(layer.row.title, properties, context)?.text ?? "",
      subtitle: layer.row.subtitle
        ? (formatField(layer.row.subtitle, properties, context)?.text ?? "")
        : "",
      details: layer.detail
        .map((field) => ({
          label: field.label,
          value: formatField(field, properties, context),
        }))
        .filter((detail) => detail.value !== null),
    };
  }

  /* Zoomed out, the map holds the data back, and the sidebar tells how to
   * get to it. */
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

  /* The list holds what the map shows. */
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
      place.distance = distanceInMetres(this.state.origin, place);
    });

    return places
      .sort((a, b) => a.distance - b.distance)
      .slice(0, NEAREST_COUNT);
  }

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

  /* The markup of a marker comes from the template of its layer. */
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
      this.mapDetailOutlet.open(place, {
        returnFocusTo:
          returnFocusTo ??
          (this.hasMapListOutlet ? this.mapListOutlet.rowFor(id) : null),
      });
    }
  }

  /* Actions --------------------------------------------------------------- */

  select({ detail }) {
    this.selectPlace(detail.id, detail.returnFocusTo);
  }

  deselect() {
    this.state.selected = null;
    this.renderMarkers();
  }

  filter({ detail }) {
    this.state.layers = new Set(detail.layers);
    this.state.filters = detail.filters.map(({ index, codes }) => ({
      filter: this.settingsValue.filters[index],
      codes,
    }));
    this.render();
  }

  query({ detail }) {
    this.state.query = detail.query;
    this.state.origin = null;
    this.render();
  }

  origin({ detail }) {
    this.state.origin = detail;
    this.state.query = "";
    this.map.setView([detail.lat, detail.lon], ADDRESS_ZOOM);
    this.render();
  }
}
