# LightningCSS Bundle Resolver Object Shape Parity

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T055031Z`

Source truth: pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.

This slice tightens the PHP `CssBundler` resolver-result validation for ambiguous JavaScript-style resolver objects. Native LightningCSS rejects resolver objects that contain both `external` and `file`, and also rejects otherwise valid resolver objects when extra keys are present, with the same `data did not match any variant of untagged enum ResolveResult` diagnostic used for non-string resolver returns.

Implementation:

- `CssBundler::resolveImport()` now accepts array resolver objects only when they contain exactly one supported key.
- `['external' => string]` remains the external import result.
- `['file' => string]` remains the lane-local file result shape already covered by existing PHP tests, but now rejects extra keys to match upstream untagged-enum strictness for object shape.
- Ambiguous `['external' => string, 'file' => string]` results now fail before the bundler can silently treat them as external.
- The WordPress bundle import graph smoke now covers the ambiguous resolver-object diagnostic for block-theme CSS.

Verification:

- Upstream native probe through `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node`:
  - `{"external":"https://cdn.example/a.css","file":"/b.css"}` -> `data did not match any variant of untagged enum ResolveResult` at `/a.css` line 1 column 1.
  - `{"external":"https://cdn.example/a.css","extra":true}` -> same diagnostic.
  - `{"file":"/b.css","extra":true}` -> same diagnostic.
- `php -l lanes/lightningcss/src/CssBundler.php`: no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`: `1 test files, 581 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test`: exited 0 and printed `resolver-ambiguous-shape: rejected`.
- `php tools/run-tests.php lanes/lightningcss/tests`: `13 test files, 6337 assertions, 0 failures`.

Status delta:

- Focused `CssBundlerTest.php` assertions move `566 -> 581`.
- Full LightningCSS lane evidence moves `6322 -> 6337` assertions.
- Conservative mapped upstream manifest coverage is unchanged; this deepens the existing bundle resolver diagnostics/import graph cluster.

Dependency closure: no new support component is needed. This reuses the lane-local PHP resolver callback boundary, import graph loader, diagnostic wrapper, PHP test harness, and WordPress example smoke.

Non-overlap: this does not repeat accepted escaped import source parsing, URL comment trimming, layer/media/supports composition, external import ordering, CSS Modules dependency hoisting, source-map import remapping, CSSOM, media-query, target-prefixing, custom at-rule, or property-value clusters. The patch is limited to resolver object shape parity at the import graph boundary.

Next task: continue bundle/import graph work on a distinct upstream-backed edge, preferably source-map resolver evidence, CSS Modules import/export graph boundaries, or additional resolver/read diagnostics that are not already covered by this object-shape strictness.
