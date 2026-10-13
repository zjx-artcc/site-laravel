// Convert raw CRC video-map geojson into the processed sector-map format
// used by high.json/low.json:
//   - closed sector Polygons with properties {id, shape: "Polygon"}
//   - leftover/unclosed lines as {id: 200+, shape: "Line"}
//
// The raw export is sloppy: divider endpoints miss each other and the
// boundary by up to ~0.008 deg, so we snap-node the arrangement first.
import { readFileSync, writeFileSync } from 'fs';

const SRC = process.argv[2];
const OUT = process.argv[3];
const TOL = 0.009; // snap tolerance in degrees

const fc = JSON.parse(readFileSync(SRC, 'utf8'));
let lines = fc.features.map((f) => f.geometry.coordinates.map((c) => [c[0], c[1]]));

// ---------- 1. snap endpoints to endpoints (cluster within TOL) ----------
const ends = [];
lines.forEach((cs, li) => {
  ends.push({ li, pi: 0 });
  ends.push({ li, pi: cs.length - 1 });
});
const dist = (a, b) => Math.hypot(a[0] - b[0], a[1] - b[1]);
const pt = (e) => lines[e.li][e.pi];
const eparent = [...ends.keys()];
const efind = (x) => (eparent[x] === x ? x : (eparent[x] = efind(eparent[x])));
for (let i = 0; i < ends.length; i++)
  for (let j = i + 1; j < ends.length; j++)
    if (dist(pt(ends[i]), pt(ends[j])) < TOL) eparent[efind(i)] = efind(j);
const clusters = {};
ends.forEach((e, i) => {
  const r = efind(i);
  (clusters[r] = clusters[r] || []).push(e);
});
let snapped = 0;
for (const group of Object.values(clusters)) {
  if (group.length < 2) continue;
  const cx = group.reduce((s, e) => s + pt(e)[0], 0) / group.length;
  const cy = group.reduce((s, e) => s + pt(e)[1], 0) / group.length;
  for (const e of group) lines[e.li][e.pi] = [cx, cy];
  snapped += group.length;
}
console.log(`endpoint clusters merged: ${snapped} endpoints`);

// ---------- 2. explode to mini segments ----------
let minis = []; // {a, b, src}
lines.forEach((cs, li) => {
  for (let j = 0; j + 1 < cs.length; j++)
    if (dist(cs[j], cs[j + 1]) > 1e-9) minis.push({ a: cs[j], b: cs[j + 1], src: li });
});

// ---------- 3. collect split points: T-junctions and proper crossings ----------
const projT = (p, a, b) => {
  const dx = b[0] - a[0], dy = b[1] - a[1];
  const L2 = dx * dx + dy * dy;
  if (!L2) return 0;
  return ((p[0] - a[0]) * dx + (p[1] - a[1]) * dy) / L2;
};
const splits = minis.map(() => []); // list of {t, p}

// 3a. endpoint-onto-segment snapping (T junctions)
const endpoints = lines.flatMap((cs) => [cs[0], cs[cs.length - 1]]);
endpoints.forEach((p) => {
  minis.forEach((m, mi) => {
    let t = projT(p, m.a, m.b);
    if (t <= 0 || t >= 1) return;
    const proj = [m.a[0] + t * (m.b[0] - m.a[0]), m.a[1] + t * (m.b[1] - m.a[1])];
    if (dist(p, proj) < TOL && dist(p, m.a) > 1e-9 && dist(p, m.b) > 1e-9) {
      splits[mi].push({ t, p }); // snap the segment THROUGH the endpoint itself
    }
  });
});

// 3b. proper segment-segment crossings
const segX = (p, p2, q, q2) => {
  const r = [p2[0] - p[0], p2[1] - p[1]];
  const s = [q2[0] - q[0], q2[1] - q[1]];
  const denom = r[0] * s[1] - r[1] * s[0];
  if (Math.abs(denom) < 1e-12) return null;
  const t = ((q[0] - p[0]) * s[1] - (q[1] - p[1]) * s[0]) / denom;
  const u = ((q[0] - p[0]) * r[1] - (q[1] - p[1]) * r[0]) / denom;
  const eps = 1e-6;
  if (t <= eps || t >= 1 - eps || u <= eps || u >= 1 - eps) return null;
  return { t, u, p: [p[0] + t * r[0], p[1] + t * r[1]] };
};
let crossings = 0;
for (let i = 0; i < minis.length; i++) {
  for (let j = i + 1; j < minis.length; j++) {
    const x = segX(minis[i].a, minis[i].b, minis[j].a, minis[j].b);
    if (x) {
      splits[i].push({ t: x.t, p: x.p });
      splits[j].push({ t: x.u, p: x.p });
      crossings++;
    }
  }
}
console.log(`T-junction splits: ${splits.flat().length - 2 * crossings}, proper crossings: ${crossings}`);

// ---------- 4. apply splits, build graph (nodes keyed at ~TOL/3 grid via 3dp... use 4dp) ----------
const key = (c) => c[0].toFixed(4) + ',' + c[1].toFixed(4);
const nodes = new Map(); // key -> {c, nbrs:Set}
const addNode = (c) => {
  const k = key(c);
  if (!nodes.has(k)) nodes.set(k, { c: [c[0], c[1]], nbrs: new Set() });
  return k;
};
const addEdge = (p, q) => {
  const a = addNode(p), b = addNode(q);
  if (a === b) return;
  nodes.get(a).nbrs.add(b);
  nodes.get(b).nbrs.add(a);
};
minis.forEach((m, mi) => {
  const ss = splits[mi].sort((x, y) => x.t - y.t);
  let prev = m.a;
  for (const s of ss) {
    addEdge(prev, s.p);
    prev = s.p;
  }
  addEdge(prev, m.b);
});

// ---------- 5. trim dangles ----------
let trimmed = 0, changed = true;
while (changed) {
  changed = false;
  for (const [k, node] of nodes) {
    if (node.nbrs.size <= 1) {
      for (const nbr of node.nbrs) nodes.get(nbr).nbrs.delete(k);
      nodes.delete(k);
      trimmed++;
      changed = true;
    }
  }
}
console.log(`trimmed ${trimmed} dangling vertices; graph nodes: ${nodes.size}`);

// ---------- 6. angular face traversal ----------
const angle = (from, to) => Math.atan2(to[1] - from[1], to[0] - from[0]);
const sorted = new Map();
for (const [k, node] of nodes)
  sorted.set(k, [...node.nbrs].sort((a, b) => angle(node.c, nodes.get(a).c) - angle(node.c, nodes.get(b).c)));
const visited = new Set();
const faces = [];
for (const [a, node] of nodes) {
  for (const b of node.nbrs) {
    if (visited.has(a + '|' + b)) continue;
    const ring = [];
    let u = a, v = b;
    while (!visited.has(u + '|' + v)) {
      visited.add(u + '|' + v);
      ring.push(v);
      const nbrs = sorted.get(v);
      const idx = nbrs.indexOf(u);
      const next = nbrs[(idx - 1 + nbrs.length) % nbrs.length];
      u = v;
      v = next;
    }
    faces.push(ring);
  }
}
const signedArea = (ring) => {
  let s = 0;
  for (let i = 0; i < ring.length; i++) {
    const p = nodes.get(ring[i]).c;
    const q = nodes.get(ring[(i + 1) % ring.length]).c;
    s += p[0] * q[1] - q[0] * p[1];
  }
  return s / 2;
};
const withArea = faces
  .map((ring) => ({ ring, area: signedArea(ring) }))
  .sort((x, y) => Math.abs(y.area) - Math.abs(x.area));
console.log(`faces: ${faces.length}; areas: ${withArea.map((f) => f.area.toFixed(4)).join(', ')}`);

const interior = withArea.slice(1).filter((f) => Math.abs(f.area) > 0.5);
console.log(`kept ${interior.length} sector faces (dropped ${withArea.length - 1 - interior.length} slivers)`);

// ---------- 7. emit ----------
const centroid = (ring) => {
  let x = 0, y = 0;
  for (const k of ring) { x += nodes.get(k).c[0]; y += nodes.get(k).c[1]; }
  return [x / ring.length, y / ring.length];
};
interior.sort((p, q) => centroid(p.ring)[0] - centroid(q.ring)[0]);

const round6 = (c) => [parseFloat(c[0].toFixed(6)), parseFloat(c[1].toFixed(6))];
const features = interior.map((f, i) => {
  let coords = f.ring.map((k) => round6(nodes.get(k).c));
  coords.push(coords[0]);
  if (f.area < 0) coords.reverse();
  return {
    type: 'Feature',
    properties: { id: i + 1, shape: 'Polygon' },
    geometry: { type: 'Polygon', coordinates: [coords] },
  };
});

writeFileSync(OUT, JSON.stringify({ type: 'FeatureCollection', features }, null, 2));
console.log(`wrote ${OUT}: ${features.length} polygons`);
