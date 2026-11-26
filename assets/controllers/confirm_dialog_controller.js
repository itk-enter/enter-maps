import { Controller } from "@hotwired/stimulus";

/**
 * Opens a dialog as a modal when the server renders it. A modal dialog is in
 * the top layer, above all of the page, and the page behind it is inert. The
 * browser moves the focus into the dialog, to the element with autofocus.
 *
 * The server removes the dialog to close it. Escape sends a "cancel" event,
 * which the template connects to an action.
 */
export default class extends Controller {
  connect() {
    if (!this.element.open) {
      this.element.showModal();
    }
  }
}
