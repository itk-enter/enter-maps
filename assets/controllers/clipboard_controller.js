import { Controller } from "@hotwired/stimulus";

/**
 * Copies the text of the source to the clipboard, and tells the result in the
 * status element. The copy button is hidden until the controller connects,
 * because it does nothing without JavaScript.
 *
 * The clipboard API works only on a secure page (https or localhost). On
 * other pages, for example a local site on http, the controller selects the
 * text and uses the old copy command. If that also fails, the text stays
 * selected, so the user can copy it with the keyboard.
 *
 * Values: copied and failed (the texts of the status).
 * Targets: source, button and status (a live region).
 */
export default class extends Controller {
  static values = { copied: String, failed: String };

  static targets = ["source", "button", "status"];

  /** Shows the copy button. */
  connect() {
    this.buttonTarget.hidden = false;
  }

  /**
   * Action for the copy button.
   *
   * @returns {Promise<void>}
   */
  async copy() {
    const copied = window.isSecureContext
      ? await this.copyWithClipboard()
      : this.copyWithSelection();

    if (!copied) {
      this.select();
    }
    this.statusTarget.textContent = copied
      ? this.copiedValue
      : this.failedValue;
  }

  /**
   * @returns {Promise<boolean>} True if the text is on the clipboard.
   */
  async copyWithClipboard() {
    try {
      await navigator.clipboard.writeText(this.sourceTarget.textContent);

      return true;
    } catch {
      return false;
    }
  }

  /**
   * @returns {boolean} True if the browser copied the selected text.
   */
  copyWithSelection() {
    this.select();

    try {
      return document.execCommand("copy");
    } catch {
      return false;
    }
  }

  /** Selects the text of the source. */
  select() {
    const range = document.createRange();

    range.selectNodeContents(this.sourceTarget);
    window.getSelection().removeAllRanges();
    window.getSelection().addRange(range);
  }
}
