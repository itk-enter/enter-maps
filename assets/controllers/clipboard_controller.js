import { Controller } from "@hotwired/stimulus";

/**
 * Copies a text to the clipboard, and tells the result in the status
 * element. The text is the "text" param of the button, or else the text of
 * the source target. Thus one controller can serve many copy buttons.
 *
 * Button targets are hidden until the controller connects, because they do
 * nothing without JavaScript. A button that is not a target is always
 * visible, for example in the builder form, which needs JavaScript anyway.
 *
 * The clipboard API works only on a secure page (https or localhost). On
 * other pages, for example a local site on http, the controller selects the
 * text and uses the old copy command. If that also fails for the source, the
 * source stays selected, so the user can copy it with the keyboard.
 *
 * Values: copied and failed (the texts of the status; "%text%" becomes the
 * copied text).
 * Targets: source (optional), button (optional) and status (a live region).
 */
export default class extends Controller {
  static values = { copied: String, failed: String };

  static targets = ["source", "button", "status"];

  /** Shows the copy buttons. */
  connect() {
    this.buttonTargets.forEach((button) => {
      button.hidden = false;
    });
  }

  /**
   * Action for a copy button.
   *
   * @param {{params: {text?: string}, currentTarget: HTMLElement}} event The
   *   click, with the text to copy, if any.
   * @returns {Promise<void>}
   */
  async copy({ params, currentTarget }) {
    const text = params.text ?? this.sourceTarget.textContent;
    const copied = window.isSecureContext
      ? await this.copyWithClipboard(text)
      : this.copyWithCommand(text, params.text === undefined);

    if (!copied && params.text === undefined) {
      this.select(this.sourceTarget);
    }
    // The hidden text area of the old copy command took the focus.
    if (!window.isSecureContext && params.text !== undefined) {
      currentTarget.focus();
    }
    this.statusTarget.textContent = (
      copied ? this.copiedValue : this.failedValue
    ).replace("%text%", text);
  }

  /**
   * @param {string} text The text.
   * @returns {Promise<boolean>} True if the text is on the clipboard.
   */
  async copyWithClipboard(text) {
    try {
      await navigator.clipboard.writeText(text);

      return true;
    } catch {
      return false;
    }
  }

  /**
   * Copies with the old copy command, which copies the selected text. A text
   * that is not on the page goes into a hidden text area first.
   *
   * @param {string} text The text.
   * @param {boolean} fromSource True if the text is the text of the source.
   * @returns {boolean} True if the browser copied the text.
   */
  copyWithCommand(text, fromSource) {
    const area = fromSource ? null : document.createElement("textarea");

    if (area) {
      area.value = text;
      area.setAttribute("readonly", "");
      area.className = "sr-only";
      document.body.append(area);
      area.select();
    } else {
      this.select(this.sourceTarget);
    }

    try {
      return document.execCommand("copy");
    } catch {
      return false;
    } finally {
      area?.remove();
    }
  }

  /**
   * Selects the text of an element.
   *
   * @param {HTMLElement} element The element.
   */
  select(element) {
    const range = document.createRange();

    range.selectNodeContents(element);
    window.getSelection().removeAllRanges();
    window.getSelection().addRange(range);
  }
}
