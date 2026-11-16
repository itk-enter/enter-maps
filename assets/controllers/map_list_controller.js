import { Controller } from "@hotwired/stimulus";

/**
 * The list of the places that the map shows. The map controller calls
 * render(). The markup of a row comes from the row template, and the
 * controller fills it with textContent.
 *
 * A click on a row sends map-list:select with the id of the place and the
 * button, so that the detail view can give focus back to the row.
 *
 * Values: messages (the texts of the list).
 * Targets: box, heading, count, list, empty and row (the row template).
 */
export default class extends Controller {
  static values = { messages: Object };

  static targets = ["box", "heading", "count", "list", "empty", "row"];

  /**
   * Shows the places.
   *
   * @param {import("./map_controller.js").Place[]} places The places.
   * @param {object} state What the list must show.
   * @param {boolean} state.gated True behind the zoom gate. Hides the list.
   * @param {{label: string}|null} state.origin The chosen address, or null.
   * @param {string|null} state.selected The id of the selected place.
   * @param {boolean} state.showLayer True if the rows show the layer.
   */
  render(places, { gated, origin, selected, showLayer }) {
    // Zoomed out, the zoom gate has the message, so the list is hidden.
    this.boxTarget.hidden = gated;
    this.headingTarget.textContent = origin
      ? this.messagesValue.nearest.replace("%label%", origin.label)
      : this.messagesValue.results;
    this.countTarget.textContent = String(places.length);
    this.emptyTarget.hidden = places.length > 0;
    this.listTarget.replaceChildren(
      ...places.map((place) => this.row(place, selected, showLayer)),
    );
  }

  /** Tells that the data did not load. */
  showError() {
    this.boxTarget.hidden = false;
    this.headingTarget.textContent = this.messagesValue.loadError;
    this.countTarget.textContent = "0";
    this.emptyTarget.hidden = true;
    this.listTarget.replaceChildren();
  }

  /**
   * @param {string} id The id of a place.
   * @returns {HTMLButtonElement|null} The button of the row, or null.
   */
  rowFor(id) {
    return (
      this.listTarget.querySelector(`button[data-id="${CSS.escape(id)}"]`) ??
      null
    );
  }

  /**
   * Makes a row from the row template.
   *
   * @param {import("./map_controller.js").Place} place The place.
   * @param {string|null} selected The id of the selected place.
   * @param {boolean} showLayer True if the row shows the layer.
   * @returns {HTMLElement} The row.
   */
  row(place, selected, showLayer) {
    const item = this.rowTarget.content.firstElementChild.cloneNode(true);
    const button = item.querySelector("button");
    const field = (name) => item.querySelector(`[data-field="${name}"]`);

    button.dataset.id = place.id;
    button.setAttribute("aria-pressed", String(selected === place.id));
    field("title").textContent = place.title;
    field("subtitle").textContent = place.subtitle;
    field("subtitle").hidden = !place.subtitle;

    if (showLayer) {
      field("layer").style.setProperty("--layer-colour", place.layer.colour);
      field("layer-label").textContent = place.layer.label;
    } else {
      field("layer").remove();
    }

    if (place.distance === undefined) {
      field("distance").remove();
    } else {
      field("distance").textContent = this.formatDistance(place.distance);
    }

    return item;
  }

  /**
   * Formats a distance as "120 m" or "1,4 km", in the language of the page.
   * Distances under 1 km are rounded to 10 m.
   *
   * @param {number} metres The distance, in metres.
   * @returns {string} The text.
   */
  formatDistance(metres) {
    const locale = document.documentElement.lang;

    if (metres < 1000) {
      return new Intl.NumberFormat(locale, {
        style: "unit",
        unit: "meter",
        maximumFractionDigits: 0,
      }).format(Math.round(metres / 10) * 10);
    }

    return new Intl.NumberFormat(locale, {
      style: "unit",
      unit: "kilometer",
      minimumFractionDigits: 1,
      maximumFractionDigits: 1,
    }).format(metres / 1000);
  }

  /**
   * Action for a click on a row. Sends map-list:select.
   *
   * @param {Event} event The click.
   */
  choose(event) {
    this.dispatch("select", {
      detail: {
        id: event.currentTarget.dataset.id,
        returnFocusTo: event.currentTarget,
      },
    });
  }
}
