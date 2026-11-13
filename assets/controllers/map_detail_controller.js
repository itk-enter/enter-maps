import { Controller } from "@hotwired/stimulus";

/*
 * The detail view of a place, over the list. The map controller calls
 * open(). Focus goes to the title, Escape and the close button close the
 * view, and focus goes back to where it came from.
 *
 * The report link replaces {id} and {seeAlso} with the values of the place.
 * With the target "postMessage", the link is a button that sends
 * { type: "enter-map:report", id, seeAlso } to the page around the frame.
 */
export default class extends Controller {
  static values = { report: Object };

  static targets = ["host", "template"];

  connect() {
    this.onKeydown = (event) => {
      if (event.key === "Escape" && !this.hostTarget.hidden) {
        this.close();
      }
    };
    document.addEventListener("keydown", this.onKeydown);
  }

  disconnect() {
    document.removeEventListener("keydown", this.onKeydown);
  }

  open(place, { returnFocusTo }) {
    const view = this.templateTarget.content.firstElementChild.cloneNode(true);
    const field = (name) => view.querySelector(`[data-field="${name}"]`);

    this.place = place;
    this.returnFocusTo = returnFocusTo;

    field("title").textContent = place.title;
    field("subtitle").textContent = place.subtitle;
    field("subtitle").hidden = !place.subtitle;
    field("route").href =
      `https://www.google.com/maps/dir/?api=1&destination=${place.lat},${place.lon}`;
    this.renderDetails(field("details"), field("detail-template"), place);
    this.renderReport(field("report-link"), field("report-button"), place);

    this.hostTarget.replaceChildren(view);
    this.hostTarget.hidden = false;
    document.body.toggleAttribute("data-detail-open", true);
    field("title").focus();
  }

  renderDetails(list, template, place) {
    list.replaceChildren(
      ...place.details.map(({ label, value }) => {
        const item = template.content.firstElementChild.cloneNode(true);
        const valueElement = item.querySelector('[data-field="value"]');

        item.querySelector('[data-field="label"]').textContent = label ?? "";

        if (value.href) {
          const link = document.createElement("a");

          link.href = value.href;
          link.target = "_blank";
          link.rel = "noopener";
          link.className = "text-link underline";
          link.textContent = value.text;
          valueElement.replaceChildren(link);
        } else if (value.items) {
          const items = document.createElement("ul");

          items.className = "list-disc pl-5";
          items.replaceChildren(
            ...value.items.map((text) => {
              const entry = document.createElement("li");

              entry.textContent = text;

              return entry;
            }),
          );
          valueElement.replaceWith(items);
        } else {
          valueElement.textContent = value.text;
        }

        return item;
      }),
    );
  }

  renderReport(link, button, place) {
    const report = this.hasReportValue ? this.reportValue : null;

    if (!report || !report.url) {
      link?.remove();
      button?.remove();

      return;
    }

    const seeAlso = [place.properties.seeAlso ?? []].flat().join(",");

    if (report.target === "postMessage") {
      link.remove();
      button.addEventListener("click", () => {
        window.parent.postMessage(
          { type: "enter-map:report", id: place.id, seeAlso },
          "*",
        );
      });

      return;
    }

    button.remove();
    link.href = report.url
      .replaceAll("{id}", encodeURIComponent(place.id))
      .replaceAll("{seeAlso}", encodeURIComponent(seeAlso));
    link.target = report.target ?? "_blank";
    if (link.target === "_blank") {
      link.rel = "noopener";
    }
  }

  close() {
    this.hostTarget.hidden = true;
    this.hostTarget.replaceChildren();
    document.body.toggleAttribute("data-detail-open", false);
    this.dispatch("close", { detail: { id: this.place?.id } });

    if (this.returnFocusTo && document.contains(this.returnFocusTo)) {
      this.returnFocusTo.focus();
    }
  }
}
