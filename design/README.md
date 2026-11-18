# SPARTA design files — read this first

`design/SPARTA.figcraft` is the canonical design source. `design/SPARTA.json` is the
full Figcraft JSON export (minified, ~61.5 MiB). **Do not load `SPARTA.json` to edit or
inspect one Admin area** — use `design/modules/` instead.

## Start here

Read `design/modules/index.json` (96 KB). It lists:

- every page (`pages[]`) and frame (`frames[]` inside each page) with node IDs, names,
  module path, byte size, sha256, node count, and explicit dependencies;
- `document` metadata (name, current page, styles, variables, version, ...);
- `sharedNodes[]` — cross-page masters. The logo component (node `2`) is referenced by
  21,200 nodes and lives in `modules/pages/1.json`; read that only when you need the
  master payload;
- `canonical` — sizes and sha256 of `SPARTA.figcraft` / `SPARTA.json` (source integrity);
- `payloadRef` — the `$ref` convention described below.

## Custom read format (not a Figcraft import)

Each module is a valid fragment of the Figcraft schema: page/frame `root` node,
`nodes` map keyed by Figcraft node ID, and `dependencies`. Repeated heavy fields
(`network` vector paths, `fills`, `stroke`, `strokes`, `child_layout`, `style`) are
replaced by content-addressed references:

```json
"network": { "$ref": "sha256:<hex>" }
```

The original value is in `design/modules/blobs/<hex>.json`. Equal payloads are stored
once; resolve a blob only when the field is actually needed. This is a read view for
agents — it is **not** importable by Figcraft. Round-trip is lossless through the
exporter, but canonical edits go through the `.figcraft` source and generator.

## Common queries

```bash
# 1. Admin page index only (no heavy payloads)
node -e "const i=require('./design/modules/index.json'); const p=i.pages.find(p=>p.id===6422); console.log(p.frames)"

# 2. One Admin frame (name, IDs, layout, refs) — ~270 KB
node -e "console.log(JSON.stringify(require('./design/modules/frames/6422-8830.json'),null,1))"

# 3. Resolve one referenced payload when needed
node -e "const n=require('./design/modules/frames/6422-8830.json');console.log(n.nodes[8831])"
# then read design/modules/blobs/<hex>.json for the $ref value

# 4. Full-schema page/frame slice with dependency closure (SVG payloads expanded)
node design/export-modules.js --page 6422 > /tmp/admin-page.json
node design/export-modules.js --frame 8830 > /tmp/admin-frame.json

# 5. Integrity: modules reassemble deep-equal to canonical JSON
node design/export-modules.js --verify
```

Rebuild after intentional canonical changes (normally the generator does this):

```bash
node design/export-modules.js
```

The exporter runs automatically at the end of `design/generate_sparta.js`, after the
existing build assertions.

## Admin CRUD editing path

| Need | Read |
|---|---|
| Admin frame list / names / IDs | `modules/index.json` → page `6422` |
| One screen | `modules/frames/6422-<frameId>.json` (~270 KB each) |
| Logo master geometry | `modules/pages/1.json` + blobs (shared, avoid unless required) |
| Text/style fields | inline in the frame module (not externalized) |

Current sizes: `SPARTA.json` 160,752,140 B pretty → 64,458,192 B minified (−59.90%).
Payload modules total 18,071,541 B (pages 336,857 + frames 11,323,272 + blobs 6,411,412)
plus a 97,873 B index; 2,932 blobs, 8 page modules, 44 frame modules. Admin page module is
37,925 B; largest Admin frame is 285,240 B.
