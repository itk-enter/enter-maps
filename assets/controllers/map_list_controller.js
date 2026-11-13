import { Controller } from "@hotwired/stimulus";
import { formatDistance } from "../lib/geo.js";

/*
 * The list of the places that the map shows. The map controller calls
 * render(). The markup of a row comes from the row template, and the
 * controller fills it with textContent.
 *
 * A click on a row sends map-list:select with the id of the place and the
 * button, so that the detail view can give focus back to the row.
 */
export default class extends Controller {
  static values = { messages: Object };

  static targets = ["box", "heading", "count", "list", "empty", "row"];

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

  showError() {
    this.boxTarget.hidden = false;
    this.headingTarget.textContent = this.messagesValue.loadError;
    this.countTarget.textContent = "0";
    this.emptyTarget.hidden = true;
    this.listTarget.replaceChildren();
  }

  rowFor(id) {
    return (
      this.listTarget.querySelector(`button[data-id="${CSS.escape(id)}"]`) ??
      null
    );
  }

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
      field("distance").textContent = formatDistance(
        place.distance,
        document.documentElement.lang,
      );
    }

    return item;
  }

  choose(event) {
    this.dispatch("select", {
      detail: {
        id: event.currentTarget.dataset.id,
        returnFocusTo: event.currentTarget,
      },
    });
  }
}
