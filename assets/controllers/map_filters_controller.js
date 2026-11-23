import { Controller } from "@hotwired/stimulus";

/**
 * The layer checkboxes and the open filters in the sidebar. The server
 * renders the controls. On each change, the controller sends
 * map-filters:change with the ids of the layers that are on, the selected
 * codes of each open filter, and the conditions of the layers that are on:
 * { layers, filters: [{ index, codes }], openFilters: ["<layer>:<index>"] }.
 *
 * Without the layer checkboxes, the layers in "initialLayers" stay on.
 *
 * Values: initialLayers (the ids of the layers that are on at the start).
 * Targets: layer (a checkbox), filter (the group of a filter, with
 * data-index) and openFilter (the checkbox of a condition of a layer, with
 * the value "<layer>:<index>").
 */
export default class extends Controller {
  static values = { initialLayers: Array };

  static targets = ["layer", "filter", "openFilter"];

  /** Action for a change of a control. Sends map-filters:change. */
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

    const openFilters = this.openFilterTargets
      .filter((input) => input.checked)
      .map((input) => input.value);

    this.dispatch("change", { detail: { layers, filters, openFilters } });
  }
}
