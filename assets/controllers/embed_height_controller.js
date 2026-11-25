import { Controller } from "@hotwired/stimulus";

/**
 * Sends the height of the map to the page around the frame, so that the
 * embed script can set the height of the frame.
 *
 * The embed page adds this controller only with the embed option
 * "autoheight=1", which the embed script puts in the address of the frame.
 * The message is { type: "enter-map:height", height, wide }. "wide" is true
 * when the sidebar is next to the map. Then the frame keeps a fixed height,
 * and the list scrolls in the sidebar. The map sends a new message each time
 * that the height or the layout changes.
 *
 * The message has no data that is not public, so the target origin is "*".
 * The map does not know the page around it.
 */
export default class extends Controller {
  connect() {
    if (window.parent === window) {
      return;
    }

    this.wide = window.matchMedia("(min-width: 51.25rem)");
    this.send = this.send.bind(this);
    this.observer = new ResizeObserver(this.send);
    this.observer.observe(this.element);
    this.wide.addEventListener("change", this.send);
    this.send();
  }

  disconnect() {
    this.observer?.disconnect();
    this.wide?.removeEventListener("change", this.send);
  }

  send() {
    const height = Math.ceil(this.element.getBoundingClientRect().height);
    const wide = this.wide.matches;
    if (height === this.last?.height && wide === this.last?.wide) {
      return;
    }

    this.last = { height, wide };
    window.parent.postMessage({ type: "enter-map:height", height, wide }, "*");
  }
}
