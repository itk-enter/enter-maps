/**
 * @file Moves a Leaflet map to a new centre and zoom in a movement that
 * starts slowly and stops slowly.
 *
 * If the zoom stays the same and the new centre is less than one screen
 * away, the map pans, in the same way as a drag. The markers move with the
 * map. Otherwise, the map flies. In a flight, Leaflet puts each marker on a
 * whole pixel in each frame, so the markers shake a little against the
 * tiles. Between two places that are near, the shake is all that the user
 * sees, so a pan is better there.
 *
 * Map.flyTo in Leaflet starts at full speed and slows down only at the end.
 * The flight here uses the same path as Map.flyTo in Leaflet 1.9.4
 * (BSD-2-Clause), from "Smooth and efficient zooming and panning" by van
 * Wijk and Nuij. Only the timing is different.
 *
 * The movement uses private parts of Leaflet 1.9, as Map.flyTo does: the
 * methods _stop, _moveStart, _move and _moveEnd, and the property
 * _flyToFrame. If you update Leaflet, make sure that they still exist.
 */

import * as L from "leaflet";

const RHO = 1.42;

/**
 * Eases in and out on a cubic curve.
 *
 * @param {number} t The time, from 0 to 1.
 * @returns {number} The progress, from 0 to 1.
 */
function easeInOut(t) {
  return t < 0.5 ? 4 * t ** 3 : 1 - (-2 * t + 2) ** 3 / 2;
}

/**
 * Moves the map to a centre and a zoom. A drag, a zoom or a new movement
 * stops it. At the end, the map sends "moveend", as Map.flyTo does. If the
 * map does not move, it sends no "moveend".
 *
 * @param {L.Map} map The map.
 * @param {L.LatLngExpression} center The new centre.
 * @param {number} zoom The new zoom.
 * @param {object} options The options.
 * @param {number} options.flightSeconds The time of a flight.
 * @param {number} options.panSeconds The time of a pan.
 * @param {boolean} options.animate False to move at once.
 */
export function fly(map, center, zoom, { flightSeconds, panSeconds, animate }) {
  if (!animate || !L.Browser.any3d) {
    map.setView(center, zoom, { animate: false });

    return;
  }

  // _stop cancels the frame in _flyToFrame, so a drag or a zoom stops the
  // movement.
  map._stop();

  const target = L.latLng(center);
  const offset = map
    .project(target)
    .subtract(map.project(map.getCenter()))
    .round();

  if (zoom === map.getZoom() && map.getSize().contains(offset)) {
    pan(map, offset, panSeconds);
  } else {
    flight(map, target, zoom, flightSeconds);
  }
}

/**
 * Moves the map pane by an offset, in the same way as a drag.
 *
 * @param {L.Map} map The map.
 * @param {L.Point} offset The offset, in pixels.
 * @param {number} duration The time of the pan, in seconds.
 */
function pan(map, offset, duration) {
  if (!offset.x && !offset.y) {
    return;
  }

  const pane = map.getPane("mapPane");
  const from = L.DomUtil.getPosition(pane);
  const start = performance.now();

  const frame = () => {
    const t = Math.min(1, (performance.now() - start) / (duration * 1000));

    L.DomUtil.setPosition(pane, from.subtract(offset.multiplyBy(easeInOut(t))));
    map.fire("move");

    if (t < 1) {
      map._flyToFrame = L.Util.requestAnimFrame(frame);
    } else {
      map.fire("moveend");
    }
  };

  map.fire("movestart");
  frame();
}

/**
 * Flies the map to a centre and a zoom on the path of Map.flyTo.
 *
 * @param {L.Map} map The map.
 * @param {L.LatLng} target The new centre.
 * @param {number} zoom The new zoom.
 * @param {number} duration The time of the flight, in seconds.
 */
function flight(map, target, zoom, duration) {
  const startZoom = map.getZoom();
  const from = map.project(map.getCenter());
  const to = map.project(target);
  const size = map.getSize();
  const w0 = Math.max(size.x, size.y);
  const w1 = w0 * map.getZoomScale(startZoom, zoom);
  const u1 = to.distanceTo(from) || 1;
  const rho2 = RHO * RHO;

  const r = (end) => {
    const b =
      (w1 * w1 - w0 * w0 + (end ? -1 : 1) * rho2 * rho2 * u1 * u1) /
      (2 * (end ? w1 : w0) * rho2 * u1);
    const sq = Math.sqrt(b * b + 1) - b;

    // If sq is 0, the log is -Infinity, and the flight never ends.
    return sq < 1e-9 ? -18 : Math.log(sq);
  };
  const r0 = r(false);
  const length = (r(true) - r0) / RHO;
  const width = (s) => w0 * (Math.cosh(r0) / Math.cosh(r0 + RHO * s));
  const travel = (s) =>
    (w0 * (Math.cosh(r0) * Math.tanh(r0 + RHO * s) - Math.sinh(r0))) / rho2;

  const start = performance.now();

  const frame = () => {
    const t = (performance.now() - start) / (duration * 1000);

    if (t >= 1) {
      map._move(target, zoom)._moveEnd(true);

      return;
    }

    const s = easeInOut(t) * length;

    map._flyToFrame = L.Util.requestAnimFrame(frame);
    map._move(
      map.unproject(
        from.add(to.subtract(from).multiplyBy(travel(s) / u1)),
        startZoom,
      ),
      map.getScaleZoom(w0 / width(s), startZoom),
      { flyTo: true },
    );
  };

  map._moveStart(true, false);
  frame();
}
