import { Controller } from "@hotwired/stimulus";

/*
 * The layer checkboxes and the open filters in the sidebar. The server
 * renders the controls. On each change, the controller sends
 * map-filters:change with the ids of the layers that are on, and the
 * selected codes of each open filter: { layers, filters: [{ index, codes }] }.
 *
 * Without the layer checkboxes, the layers in "initialLayers" stay on.
 */
export default class extends Controller {
  static values = { initialLayers: Array };

  static targets = ["layer", "filter"];

  change() {
    const layers = this.hasLayerTarget
      ? this.layerTargets
          .filter((input) => input.checked)
          .map((input) => input.value)
      : this.initialLayersValue;

    const filters = this.filterTargets.map((group) => ({
      index: Number(group.dataset.index),
      codes: [...group.querySelectorAll("input:checked, option:checked")]
        .map((element) => element.value)
        // The empty option of a select means "all".
        .filter((code) => code !== ""),
    }));

    this.dispatch("change", { detail: { layers, filters } });
  }
}
