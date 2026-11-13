import { Controller } from "@hotwired/stimulus";
import { isAddress, position, search } from "../lib/adressevaelger.js";

/*
 * The search field of the sidebar.
 *
 * With "local", the text filters the list as the user types, and the
 * controller sends address-search:query with { query }.
 *
 * With "address", the field is an ARIA 1.2 combobox with suggestions from
 * Adressevælger. The arrow keys and Enter choose a suggestion, and Escape
 * and blur close the list. To choose a street puts it back in the field and
 * searches again, and the controller sends address-search:query with an
 * empty text. To choose an address sends address-search:choose with
 * { lat, lon, label }.
 */

const DELAY = 250;
const MIN_LENGTH = 3;

export default class extends Controller {
  static values = { local: Boolean, address: Boolean };

  static targets = ["input", "listbox", "option", "error"];

  connect() {
    this.timer = null;
    this.request = 0;
    this.hits = [];
    this.active = -1;
  }

  disconnect() {
    window.clearTimeout(this.timer);
  }

  input() {
    if (this.localValue) {
      this.dispatch("query", {
        detail: { query: this.inputTarget.value.trim() },
      });
    }

    if (this.addressValue) {
      window.clearTimeout(this.timer);
      this.timer = window.setTimeout(() => this.suggest(), DELAY);
    }
  }

  keydown(event) {
    if (!this.addressValue || this.listboxTarget.hidden) {
      return;
    }

    if (event.key === "Escape") {
      // The list closes first. The next Escape closes the detail view.
      event.stopPropagation();
      this.hide();
    } else if (event.key === "ArrowDown" || event.key === "ArrowUp") {
      event.preventDefault();
      this.move(event.key === "ArrowDown" ? 1 : -1);
    } else if (event.key === "Enter" && this.active !== -1) {
      event.preventDefault();
      this.choose(this.hits[this.active]);
    }
  }

  blur() {
    if (this.addressValue) {
      this.hide();
    }
  }

  async suggest() {
    const text = this.inputTarget.value.trim();
    const current = ++this.request;

    if (text.length < MIN_LENGTH) {
      this.hide();

      return;
    }

    try {
      const found = await search(text);

      // A slow answer to an earlier key must not replace a newer answer.
      if (current !== this.request) {
        return;
      }

      this.errorTarget.hidden = true;
      this.show(found);
    } catch (error) {
      if (current === this.request) {
        this.hide();
        this.errorTarget.hidden = false;
      }
    }
  }

  show(found) {
    if (found.length === 0) {
      this.hide();

      return;
    }

    this.hits = found;
    this.active = -1;
    this.listboxTarget.replaceChildren(
      ...found.map((hit, index) => {
        const item =
          this.optionTarget.content.firstElementChild.cloneNode(true);

        item.id = `${this.listboxTarget.id}-${index}`;
        item.querySelector('[data-field="label"]').textContent = hit.titel;
        if (isAddress(hit)) {
          item.querySelector('[data-field="narrow"]').remove();
        }

        // Keeps focus in the field, so that blur does not close the list
        // before the click.
        item.addEventListener("mousedown", (event) => event.preventDefault());
        item.addEventListener("click", () => this.choose(hit));

        return item;
      }),
    );
    this.listboxTarget.hidden = false;
    this.inputTarget.setAttribute("aria-expanded", "true");
  }

  move(step) {
    const items = this.listboxTarget.children;
    const next = (this.active + step + items.length + 1) % (items.length + 1);

    this.active = next === items.length ? -1 : next;

    [...items].forEach((item, index) => {
      item.setAttribute("aria-selected", String(index === this.active));
    });

    if (this.active === -1) {
      this.inputTarget.removeAttribute("aria-activedescendant");
    } else {
      this.inputTarget.setAttribute(
        "aria-activedescendant",
        items[this.active].id,
      );
      items[this.active].scrollIntoView({ block: "nearest" });
    }
  }

  hide() {
    this.hits = [];
    this.active = -1;
    this.listboxTarget.hidden = true;
    this.listboxTarget.replaceChildren();
    this.inputTarget.setAttribute("aria-expanded", "false");
    this.inputTarget.removeAttribute("aria-activedescendant");
  }

  async choose(hit) {
    this.hide();

    if (!isAddress(hit)) {
      this.inputTarget.value = `${hit.titel} `;
      // A street in the field is not a filter on the list.
      this.dispatch("query", { detail: { query: "" } });
      this.suggest();

      return;
    }

    try {
      const point = await position(hit);

      this.errorTarget.hidden = true;
      this.inputTarget.value = hit.titel;
      this.dispatch("choose", {
        detail: { lat: point.lat, lon: point.lon, label: hit.titel },
      });
    } catch (error) {
      this.errorTarget.hidden = false;
    }
  }
}
