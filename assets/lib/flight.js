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
 * The flight here uses the same path as Map.flyTo in Leaflet 1.9.4, from
 * "Smooth and efficient zooming and panning" by van Wijk and Nuij. Only the
 * speed along the path is different. The symbols in the comments, for
 * example w0 and r0, are the symbols of the paper.
 *
 * The movement uses private parts of Leaflet 1.9, as Map.flyTo does: the
 * methods _stop, _moveStart, _move and _moveEnd, and the property
 * _flyToFrame. If you update Leaflet, make sure that they still exist.
 */

/*
 * flight() is a changed copy of Map.flyTo in Leaflet 1.9.4, which has this
 * license:
 *
 * BSD 2-Clause License
 *
 * Copyright (c) 2010-2023, Volodymyr Agafonkin
 * Copyright (c) 2010-2011, CloudMade
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice, this
 *    list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

import * as L from "leaflet";

// How much a flight zooms out on the way (rho). Map.flyTo uses the same value.
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
 * Moves the map to a centre and a zoom. A drag, a zoom or a new call of
 * fly() stops it. At the end, the map sends "moveend", as Map.flyTo does. If
 * the map does not move, it sends no "moveend".
 *
 * If the map has no size, for example in a frame that is hidden, the map
 * moves at once.
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
  // The size of the map, in pixels. With no size, the math of the flight
  // divides by 0.
  const size = map.getSize();

  if (!animate || !L.Browser.any3d || !size.x || !size.y) {
    map.setView(center, zoom, { animate: false });

    return;
  }

  // _stop cancels the frame in _flyToFrame, so a drag or a zoom stops the
  // movement.
  map._stop();

  // The new centre.
  const target = L.latLng(center);
  // The distance from the centre to the new centre, in pixels.
  const offset = map
    .project(target)
    .subtract(map.project(map.getCenter()))
    .round();

  if (zoom === map.getZoom() && size.contains(offset)) {
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

  // The pane that holds the layers. A drag moves this pane too.
  const pane = map.getPane("mapPane");
  // The position of the pane at the start.
  const from = L.DomUtil.getPosition(pane);
  // The time at the start, in milliseconds.
  const start = performance.now();

  const frame = () => {
    // The part of the time that has gone, from 0 to 1.
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
 * Flies the map to a centre and a zoom on the path of Map.flyTo. All the
 * lengths are in pixels at the start zoom.
 *
 * @param {L.Map} map The map.
 * @param {L.LatLng} target The new centre.
 * @param {number} zoom The new zoom.
 * @param {number} duration The time of the flight, in seconds.
 */
function flight(map, target, zoom, duration) {
  // The zoom at the start.
  const startZoom = map.getZoom();
  // The centre at the start.
  const from = map.project(map.getCenter());
  // The new centre.
  const to = map.project(target);
  // The size of the map.
  const size = map.getSize();
  // The width of the view at the start (w0).
  const w0 = Math.max(size.x, size.y);
  // The width of the view at the end (w1).
  const w1 = w0 * map.getZoomScale(startZoom, zoom);
  // The distance to the new centre (u1). The math divides by it, so 0
  // becomes 1.
  const u1 = to.distanceTo(from) || 1;
  // RHO squared.
  const rho2 = RHO * RHO;

  // Gives r0 for the start or r1 for the end. The width of the view follows
  // the curve 1 / cosh(r) from r0 to r1, and it is largest at r = 0.
  const r = (end) => {
    // b0 or b1 of the paper.
    const b =
      (w1 * w1 - w0 * w0 + (end ? -1 : 1) * rho2 * rho2 * u1 * u1) /
      (2 * (end ? w1 : w0) * rho2 * u1);
    // The value in the log.
    const sq = Math.sqrt(b * b + 1) - b;

    // If sq is 0, the log is -Infinity, and the flight never ends.
    return sq < 1e-9 ? -18 : Math.log(sq);
  };
  // Where the flight starts on the curve (r0).
  const r0 = r(false);
  // The length of the path (S). The flight goes from 0 to this length.
  const length = (r(true) - r0) / RHO;
  // The width of the view at the point s of the path (w(s)).
  const width = (s) => w0 * (Math.cosh(r0) / Math.cosh(r0 + RHO * s));
  // The distance from the start centre at the point s of the path (u(s)).
  const travel = (s) =>
    (w0 * (Math.cosh(r0) * Math.tanh(r0 + RHO * s) - Math.sinh(r0))) / rho2;
  // The time at the start, in milliseconds.
  const start = performance.now();

  const frame = () => {
    // The part of the time that has gone, from 0 to 1.
    const t = (performance.now() - start) / (duration * 1000);

    if (t >= 1) {
      map._move(target, zoom)._moveEnd(true);

      return;
    }

    // The point of the path for this frame.
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
