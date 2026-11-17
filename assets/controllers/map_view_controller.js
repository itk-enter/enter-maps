import { Controller } from "@hotwired/stimulus";

/** The number of decimals of a longitude or a latitude: about 1 m. */
const DECIMALS = 5;

/**
 * In the builder: copies the view of the map in the frame into the view
 * fields of the form. The map controller in the frame sends the view in a
 * message at the start and after each move. The first view of each new map
 * goes into the fields at once, so that the fields show what the map shows.
 * After that, the "use" action writes the last view into the fields.
 *
 * The controller accepts only messages from its own frame, on the same
 * origin. It sends "input" and "change" events on the fields, so that the
 * Live Component gets the new values.
 *
 * Targets: frame (optional), longitude, latitude and zoom.
 */
export default class extends Controller {
  static targets = ["frame", "longitude", "latitude", "zoom"];

  /** Starts to receive the messages of the frame. */
  connect() {
    this.view = null;
    // The address of the last map that filled in the fields.
    this.filledSource = null;
    this.receive = this.receive.bind(this);
    window.addEventListener("message", this.receive);
  }

  /** Stops to receive the messages of the frame. */
  disconnect() {
    window.removeEventListener("message", this.receive);
  }

  /**
   * Keeps the view from a message of the frame.
   *
   * @param {MessageEvent} event The message.
   */
  receive(event) {
    if (
      event.origin !== window.location.origin ||
      !this.hasFrameTarget ||
      event.source !== this.frameTarget.contentWindow ||
      event.data?.type !== "enter-maps:view"
    ) {
      return;
    }
    this.view = event.data;

    const source = this.frameTarget.getAttribute("src");
    if (source !== this.filledSource) {
      this.filledSource = source;
      this.use();
    }
  }

  /** Writes the last view of the map into the view fields. */
  use() {
    if (this.view === null) {
      return;
    }
    this.set(this.longitudeTarget, this.view.longitude.toFixed(DECIMALS));
    this.set(this.latitudeTarget, this.view.latitude.toFixed(DECIMALS));
    this.set(this.zoomTarget, String(Math.round(this.view.zoom)));
  }

  /**
   * Sets the value of a field, and tells the Live Component.
   *
   * @param {HTMLInputElement} field The field.
   * @param {string} value The value.
   */
  set(field, value) {
    field.value = value;
    field.dispatchEvent(new Event("input", { bubbles: true }));
    field.dispatchEvent(new Event("change", { bubbles: true }));
  }
}
