#!/usr/bin/env node
'use strict';
// Export design/SPARTA.json into a lossless, modular READ representation for agents.
// Custom format: NOT importable by Figcraft. Canonical design source stays
// design/SPARTA.figcraft; design/SPARTA.json keeps the full Figcraft schema.
//
//   node design/export-modules.js               rebuild modules, minify canonical, self-test
//   node design/export-modules.js --verify      re-check modules against canonical
//   node design/export-modules.js --page 6422   print full-schema page slice + dependency closure
//   node design/export-modules.js --frame 6423  print full-schema frame slice + dependency closure
//
// design/modules/
//   index.json                        document metadata, page/frame index, sizes, dependencies
//   pages/<pageId>.json               page node + page nodes outside frames; frame entries with paths
//   frames/<pageId>-<frameId>.json    frame node + subtree nodes
//   blobs/<sha256>.json               one shared payload value; equal payloads stored once
//
// Repeated heavy fields (network/vector paths, fills, stroke, ...) are replaced
// inside module nodes by {"$ref":"sha256:<hash>"}; the blob holds the original
// value. Canonical JSON keeps full values; refs exist only in this read format.

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const assert = require('assert');

const ROOT = path.resolve(__dirname, '..');
const CANON = path.join(ROOT, 'design/SPARTA.json');
const FIG = path.join(ROOT, 'design/SPARTA.figcraft');
const MOD = path.join(ROOT, 'design/modules');
const DIR_PAGES = path.join(MOD, 'pages');
const DIR_FRAMES = path.join(MOD, 'frames');
const DIR_BLOBS = path.join(MOD, 'blobs');
const INDEX_PATH = path.join(MOD, 'index.json');

const SCHEMA = 'sparta-read-modules/1';
const REF_COST = 82; // bytes of {"$ref":"sha256:<64 hex>"}
const MIN_REF_SAVING = 1024;
const STRUCTURAL = new Set(['id', 'parent', 'children', 'source', 'type', 'name']);

const sha = (s) => crypto.createHash('sha256').update(s).digest('hex');
const j = (v) => JSON.stringify(v);
const rel = (p) => path.relative(MOD, p).split(path.sep).join('/');
const readJson = (p) => JSON.parse(fs.readFileSync(p, 'utf8'));
const writeJson = (p, v) => fs.writeFileSync(p, j(v));

// Key-order independent hash so rebuilt docs compare with the parsed canonical.
function stable(v) {
  if (Array.isArray(v)) return '[' + v.map(stable).join(',') + ']';
  if (v && typeof v === 'object') {
    return '{' + Object.keys(v).sort().map((k) => j(k) + ':' + stable(v[k])).join(',') + '}';
  }
  return j(v);
}
const hashOf = (v) => sha(stable(v));

function pageOf(doc, id) {
  let n = doc.nodes[id];
  while (n && n.type !== 'page') n = n.parent === undefined ? undefined : doc.nodes[n.parent];
  return n ? n.id : undefined;
}

function subtree(doc, id) {
  const out = [];
  const st = [id];
  while (st.length) {
    const k = st.pop();
    out.push(k);
    const n = doc.nodes[k];
    if (n && n.children) st.push(...n.children);
  }
  return out;
}

// field -> {count, total bytes, uniq: Map(sha -> json text)}
function payloadStats(doc, ids) {
  const stats = new Map();
  for (const id of ids) {
    const n = doc.nodes[id];
    for (const k of Object.keys(n)) {
      if (STRUCTURAL.has(k)) continue;
      const s = j(n[k]);
      let st = stats.get(k);
      if (!st) stats.set(k, (st = { count: 0, total: 0, uniq: new Map() }));
      st.count++;
      st.total += s.length;
      if (!st.uniq.has(sha(s))) st.uniq.set(sha(s), s);
    }
  }
  return stats;
}

// Externalize a field only when dedupe + shorter refs beat keeping it inline.
function pickRefFields(stats) {
  const out = new Set();
  for (const [k, st] of stats) {
    let uniqBytes = 0;
    for (const s of st.uniq.values()) uniqBytes += s.length;
    if (st.total - uniqBytes - st.count * REF_COST > MIN_REF_SAVING) out.add(k);
  }
  return out;
}

function record(node, refFields) {
  const o = {};
  for (const k of Object.keys(node)) {
    o[k] = refFields.has(k) ? { $ref: 'sha256:' + sha(j(node[k])) } : node[k];
  }
  return o;
}

function dependencies(doc, ids, contained, pageOfNode) {
  const nodeIds = new Set();
  for (const id of ids) {
    const n = doc.nodes[id];
    for (const c of n.children || []) if (!contained.has(c)) nodeIds.add(c);
    if (n.source !== undefined && !contained.has(n.source)) nodeIds.add(n.source);
  }
  const pageIds = new Set();
  for (const id of nodeIds) pageIds.add(pageOfNode.get(id));
  return { nodeIds: [...nodeIds].sort((a, b) => a - b), pageIds: [...pageIds].sort((a, b) => a - b) };
}

// An external node needs its owning page (page node, frames, shared masters) to
// form a valid Figcraft slice. Cross-page pulls are whole shared pages, never
// another role's frame out of context.
function closure(doc, contained, pageOfNode) {
  const add = new Set();
  const ext = dependencies(doc, [...contained], contained, pageOfNode).nodeIds;
  for (const e of ext) {
    const pageId = pageOfNode.get(e);
    if (pageId !== undefined) for (const k of subtree(doc, pageId)) add.add(k);
  }
  return add;
}

function build(doc) {
  fs.mkdirSync(DIR_PAGES, { recursive: true });
  fs.mkdirSync(DIR_FRAMES, { recursive: true });
  fs.mkdirSync(DIR_BLOBS, { recursive: true });

  const pageOfNode = new Map(Object.keys(doc.nodes).map((n) => [Number(n), pageOf(doc, Number(n))]));
  const frameIds = Object.keys(doc.nodes).map(Number).filter((id) => doc.nodes[id].type === 'frame');
  const frameSet = new Set();
  for (const fid of frameIds) for (const id of subtree(doc, fid)) frameSet.add(Number(id));

  const allStats = payloadStats(doc, Object.keys(doc.nodes).map(Number));
  const refFields = pickRefFields(allStats);
  const blobs = new Map();
  for (const k of refFields) for (const [h, s] of allStats.get(k).uniq) blobs.set(h, s);
  for (const [h, text] of blobs) fs.writeFileSync(path.join(DIR_BLOBS, h + '.json'), text);

  const write = (file, obj, base) => {
    const text = j(obj);
    writeJson(file, obj);
    const entry = Object.assign(base, {
      path: rel(file),
      bytes: Buffer.byteLength(text),
      sha256: sha(text),
      nodeCount: Object.keys(obj.nodes).length + 1,
      dependencies: obj.dependencies,
    });
    return entry;
  };
  const moduleObj = (kind, ids, rootId, meta) => {
    const root = record(doc.nodes[rootId], refFields);
    const nodes = {};
    for (const id of ids) if (id !== rootId) nodes[id] = record(doc.nodes[id], refFields);
    return Object.assign({ format: SCHEMA, kind }, meta, {
      root,
      nodes,
      dependencies: dependencies(doc, ids, new Set(ids), pageOfNode),
    });
  };

  const summaries = [];
  for (const pid of doc.pages) {
    const page = doc.nodes[pid];
    const frames = (page.children || []).filter((c) => doc.nodes[c].type === 'frame');
    const frameEntries = [];
    for (const fid of frames) {
      const obj = moduleObj('frame', subtree(doc, fid).map(Number), fid, { pageId: pid, frameId: fid, frameName: doc.nodes[fid].name });
      frameEntries.push(
        write(path.join(DIR_FRAMES, `${pid}-${fid}.json`), obj, { id: fid, name: doc.nodes[fid].name })
      );
    }
    const nonFrame = subtree(doc, pid).map(Number).filter((id) => !frameSet.has(id));
    const obj = moduleObj('page', nonFrame, pid, { pageId: pid, pageName: page.name, frames: frameEntries });
    summaries.push(
      write(path.join(DIR_PAGES, `${pid}.json`), obj, {
        id: pid,
        name: page.name,
        frameCount: frameEntries.length,
        frames: frameEntries,
      })
    );
  }
  return { summaries, refFields: [...refFields].sort(), blobCount: blobs.size };
}

function buildIndex(doc, canonical, meta, summaries, refFields) {
  const blobFiles = fs.readdirSync(DIR_BLOBS).filter((f) => f.endsWith('.json'));
  const blobsBytes = blobFiles.reduce((a, f) => a + fs.statSync(path.join(DIR_BLOBS, f)).size, 0);
  const pagesBytes = summaries.reduce((a, p) => a + p.bytes, 0);
  const framesBytes = summaries.reduce((a, p) => a + p.frames.reduce((b, f) => b + f.bytes, 0), 0);

  // Nodes referenced across pages (shared masters, e.g. the logo component) are
  // listed with the module that actually owns them, so a reader knows what a
  // dependency closure will pull without opening the module.
  const shared = new Map();
  for (const n of Object.values(doc.nodes)) {
    if (n.source === undefined || pageOf(doc, n.source) === pageOf(doc, n.id)) continue;
    let top = doc.nodes[n.source];
    while (top.type !== 'page' && top.parent !== undefined && doc.nodes[top.parent].type !== 'page') top = doc.nodes[top.parent];
    const s = shared.get(top.id) || { id: top.id, name: top.name, type: top.type, ownedByPage: pageOf(doc, top.id), module: 'pages/' + pageOf(doc, top.id) + '.json', referencedByNodes: 0 };
    s.referencedByNodes++;
    shared.set(top.id, s);
  }

  return {
    format: SCHEMA,
    note: 'Custom READ index for agents. NOT a Figcraft import. Read a page or frame module; resolve modules/blobs/<hash>.json only when a $ref needs it. Never parse design/SPARTA.json in full.',
    canonical: {
      figcraft: 'design/SPARTA.figcraft',
      figcraftBytes: fs.statSync(FIG).size,
      json: 'design/SPARTA.json',
      jsonBytes: canonical.bytes,
      jsonBytesPrettyBefore: canonical.pretty,
      jsonSha256: canonical.sha,
      semanticSha256: canonical.semantic,
    },
    document: meta,
    counts: {
      pages: summaries.length,
      frames: summaries.reduce((a, p) => a + p.frameCount, 0),
      nodes: Object.keys(doc.nodes).length,
      blobs: blobFiles.length,
    },
    sharedNodes: [...shared.values()].map((s) => Object.assign(s, { notes: 'Shared master dependency. --page/--frame slices include its owning page subtree; read only that module if you need the master payload.' })),
    pages: summaries,
    payloadRef: {
      syntax: '{"$ref":"sha256:<hex>"}',
      blobPath: 'modules/blobs/<hex>.json',
      refFields,
      note: 'Refs appear only in module files; canonical design/SPARTA.json keeps full values.',
    },
    totals: { moduleFiles: pagesBytes + framesBytes + blobsBytes, pagesBytes, framesBytes, blobsBytes },
  };
}

function readRaw(file) {
  const obj = readJson(path.join(MOD, file));
  const nodes = {};
  nodes[obj.root.id] = obj.root;
  for (const [id, n] of Object.entries(obj.nodes)) nodes[id] = n;
  return nodes;
}

function expandRefs(value) {
  if (Array.isArray(value)) return value.map(expandRefs);
  if (value && typeof value === 'object') {
    const keys = Object.keys(value);
    if (keys.length === 1 && keys[0] === '$ref') {
      return readJson(path.join(DIR_BLOBS, String(value.$ref).replace(/^sha256:/, '') + '.json'));
    }
    const o = {};
    for (const k of keys) o[k] = expandRefs(value[k]);
    return o;
  }
  return value;
}

function readModule(file) {
  const out = {};
  for (const [id, n] of Object.entries(readRaw(file))) out[id] = expandRefs(n);
  return out;
}

function reassemble(index) {
  const nodes = {};
  for (const p of index.pages) {
    Object.assign(nodes, readModule(p.path));
    for (const f of p.frames) Object.assign(nodes, readModule(f.path));
  }
  return Object.assign({}, index.document, { nodes, pages: index.pages.map((p) => p.id) });
}

function walkRefs(v, visit) {
  if (Array.isArray(v)) return v.forEach((x) => walkRefs(x, visit));
  if (v && typeof v === 'object') {
    const ks = Object.keys(v);
    if (ks.length === 1 && ks[0] === '$ref') return visit(String(v.$ref).replace(/^sha256:/, ''));
    for (const k of ks) walkRefs(v[k], visit);
  }
}

function verify(canonicalText) {
  const doc = JSON.parse(canonicalText);
  assert.ok(fs.existsSync(INDEX_PATH), 'modules/index.json missing; run without --verify first');
  const index = readJson(INDEX_PATH);
  assert.strictEqual(index.format, SCHEMA, 'module format marker');
  assert.strictEqual(index.canonical.jsonSha256, sha(canonicalText), 'canonical file hash');
  assert.strictEqual(index.canonical.jsonBytes, Buffer.byteLength(canonicalText), 'canonical file bytes');
  assert.strictEqual(index.canonical.semanticSha256, hashOf(doc), 'canonical semantic hash');

  const rebuilt = reassemble(index);
  assert.deepStrictEqual(rebuilt, doc, 'module reassembly must deep-equal canonical document');
  assert.strictEqual(hashOf(rebuilt), hashOf(doc), 'reassembled semantic hash');
  assert.strictEqual(Object.keys(rebuilt.nodes).length, index.counts.nodes, 'node count');

  const used = new Set();
  for (const p of index.pages) {
    for (const f of [p.path, ...p.frames.map((x) => x.path)]) {
      walkRefs(readJson(path.join(MOD, f)), (h) => {
        const bp = path.join(DIR_BLOBS, h + '.json');
        assert.ok(fs.existsSync(bp), 'missing blob for ref ' + h);
        assert.strictEqual(sha(fs.readFileSync(bp, 'utf8')), h, 'blob hash mismatch ' + h);
        used.add(h);
      });
    }
  }
  const blobFiles = fs.readdirSync(DIR_BLOBS).filter((f) => f.endsWith('.json'));
  assert.strictEqual(blobFiles.length, used.size, 'unused or missing blob files');

  for (const p of index.pages) {
    assert.strictEqual(p.bytes, fs.statSync(path.join(MOD, p.path)).size, 'page module bytes ' + p.id);
    for (const f of p.frames) assert.strictEqual(f.bytes, fs.statSync(path.join(MOD, f.path)).size, 'frame module bytes ' + f.id);
  }
  return { doc, index, refCount: used.size };
}

function slice(doc, index, kind, id) {
  const pmeta =
    kind === 'page'
      ? index.pages.find((p) => p.id === id)
      : index.pages.find((p) => p.frames.some((f) => f.id === id));
  assert.ok(pmeta, kind + ' not in module index: ' + id);
  const nodes = {};
  const contained = new Set();
  const add = (m) => {
    for (const [nid, n] of Object.entries(m)) {
      nodes[nid] = n;
      contained.add(Number(nid));
    }
  };
  if (kind === 'page') {
    add(readModule(pmeta.path));
    for (const f of pmeta.frames) add(readModule(f.path));
  } else {
    const fmeta = pmeta.frames.find((f) => f.id === id);
    assert.ok(fmeta, 'frame not in index: ' + id);
    add(readModule(fmeta.path));
  }
  const pageOfNode = new Map(Object.keys(doc.nodes).map((n) => [Number(n), pageOf(doc, Number(n))]));
  const close = closure(doc, contained, pageOfNode);
  for (const nid of close) {
    nodes[nid] = doc.nodes[nid];
    contained.add(nid);
  }
  const pagesInSlice = [...new Set([...contained].map((nid) => pageOfNode.get(nid)))].sort((a, b) => a - b);
  return {
    doc: Object.assign({}, index.document, { nodes, pages: pagesInSlice, current_page: pmeta.id }),
    pagesInSlice,
    dependencyNodeCount: close.size,
  };
}

function usage() {
  console.log('usage: node design/export-modules.js [--verify] [--page <pageId>] [--frame <frameId>]');
}

function main() {
  const argv = process.argv.slice(2);
  if (argv.includes('--help') || argv.includes('-h')) return usage();
  const canonicalText = fs.readFileSync(CANON, 'utf8');
  const doc = JSON.parse(canonicalText);

  if (argv.includes('--verify')) {
    const r = verify(canonicalText);
    console.log(
      `verify OK: ${r.index.counts.pages} pages, ${r.index.counts.frames} frames, ${r.index.counts.nodes} nodes, ${r.refCount} payload refs, ${r.index.counts.blobs} blobs`
    );
    console.log(
      `bytes: canonical json ${Buffer.byteLength(canonicalText)} (semantic ${r.index.canonical.semanticSha256}), modules ${r.index.totals.moduleFiles} (pages ${r.index.totals.pagesBytes}, frames ${r.index.totals.framesBytes}, blobs ${r.index.totals.blobsBytes})`
    );
    return;
  }

  const pi = argv.indexOf('--page');
  const fi = argv.indexOf('--frame');
  if (pi >= 0 || fi >= 0) {
    const index = readJson(INDEX_PATH);
    const kind = pi >= 0 ? 'page' : 'frame';
    const id = Number(argv[(pi >= 0 ? pi : fi) + 1]);
    assert.ok(Number.isFinite(id), 'missing numeric id after --' + kind);
    const r = slice(doc, index, kind, id);
    process.stdout.write(j(r.doc));
    console.error(`${kind} ${id}: ${Object.keys(r.doc.nodes).length} nodes (${r.dependencyNodeCount} from dependency closure); pages ${JSON.stringify(r.pagesInSlice)}`);
    return;
  }

  // 1. rebuild modules from scratch
  fs.rmSync(MOD, { recursive: true, force: true });
  const built = build(doc);
  const meta = Object.assign({}, doc);
  delete meta.nodes;
  delete meta.pages;
  const compact = j(doc);
  const canonical = { pretty: Buffer.byteLength(canonicalText), bytes: Buffer.byteLength(compact), sha: sha(compact), semantic: hashOf(doc) };
  writeJson(INDEX_PATH, buildIndex(doc, canonical, meta, built.summaries, built.refFields));

  // 2. in-memory integrity check before touching the canonical file
  assert.deepStrictEqual(reassemble(readJson(INDEX_PATH)), doc, 'module reassembly deep-equal before minify');

  // 3. lossless minify: same parsed document, no node removal
  fs.writeFileSync(CANON, compact);
  const minified = fs.readFileSync(CANON, 'utf8');
  assert.deepStrictEqual(JSON.parse(minified), doc, 'minified canonical parses deep-equal');
  assert.strictEqual(hashOf(JSON.parse(minified)), canonical.semantic, 'semantic hash unchanged by minify');

  // 4. verify from disk, then scope/size self-tests
  const final = verify(minified);
  const adminMeta = final.index.pages.find((p) => p.name === '04 — Admin CRUD');
  assert.ok(adminMeta, 'Admin page in module index');
  const admin = slice(doc, final.index, 'page', adminMeta.id);
  assert.deepStrictEqual(admin.pagesInSlice, [doc.pages[0], adminMeta.id].sort((a, b) => a - b), 'Admin page slice holds Admin page + shared component page only');
  const requiredAdminFrames = ['Admin — Pengguna Tambah', 'Admin — Pengguna Edit', 'Admin — Pengguna Detail', 'Admin — Kategori Tambah', 'Admin — Kategori Edit', 'Admin — Alat Tambah', 'Admin — Alat Edit', 'Admin — Alat Detail'];
  for (const name of requiredAdminFrames) assert.ok(adminMeta.frames.some((f) => f.name === name), 'Admin frame in slice ' + name);
  for (const other of final.index.pages.filter((p) => ['05 — Petugas Ops', '06 — Peminjam Exp', '07 — Responsive Shell'].includes(p.name))) assert.ok(!admin.pagesInSlice.includes(other.id), 'Admin slice must not pull role page ' + other.id);
  assert.ok(adminMeta.bytes < 1024 * 1024, 'Admin page module under 1MB');
  const addFrame = adminMeta.frames.find((f) => f.name === 'Admin — Pengguna Tambah');
  const editFrame = adminMeta.frames.find((f) => f.name === 'Admin — Pengguna Edit');
  const frameSlice = slice(doc, final.index, 'frame', addFrame.id);
  assert.ok(frameSlice.pagesInSlice.every((p) => p === doc.pages[0] || p === adminMeta.id), 'Admin frame slice stays in Admin + shared page');
  assert.ok(!frameSlice.doc.nodes[editFrame.id], 'Admin frame slice must not pull unrelated frames');

  const report = {
    canonicalPrettyBytes: canonical.pretty,
    canonicalMinifiedBytes: canonical.bytes,
    canonicalReductionPct: +((1 - canonical.bytes / canonical.pretty) * 100).toFixed(2),
    figcraftBytes: final.index.canonical.figcraftBytes,
    modulesIndexBytes: fs.statSync(INDEX_PATH).size,
    modulesTotalBytes: final.index.totals.moduleFiles,
    modulesReductionVsPrettyPct: +((1 - final.index.totals.moduleFiles / canonical.pretty) * 100).toFixed(2),
    pagesBytes: final.index.totals.pagesBytes,
    framesBytes: final.index.totals.framesBytes,
    blobsBytes: final.index.totals.blobsBytes,
    blobCount: final.index.counts.blobs,
    payloadRefCount: final.refCount,
    refFields: final.index.payloadRef.refFields,
    adminPageModuleBytes: adminMeta.bytes,
    adminLargestFrameBytes: Math.max(...adminMeta.frames.map((f) => f.bytes)),
    semanticSha256: canonical.semantic,
    jsonSha256: canonical.sha,
    frames: final.index.counts.frames,
    pages: final.index.counts.pages,
    nodes: final.index.counts.nodes,
  };
  console.log('export OK: lossless reassembly deep-equal, refs resolve, canonical minified, Admin scope isolated');
  console.log(j(report));
}

if (require.main === module) main();

module.exports = { verify, slice, reassemble, main };
