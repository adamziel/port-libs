# Bundle Source Map Comment Boundary Parity - 2026-06-01T015909Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T015909Z`

Upstream source truth: pinned LightningCSS commit `22bdda3d190f1cd321d98026225cfc964af64ad9`. The upstream bundler loads source maps from the parsed stylesheet source-map URL (`src/bundler.rs::load_file` via `stylesheet.source_map_url(0)`), so source-map-looking text inside CSS string values is not treated as an input source-map directive.

Implemented behavior:

- `CssBundler` now scans `sourceMappingURL` only from actual CSS comments outside quoted string literals before importing inline data URL source maps.
- Added focused import-graph/source-map coverage for an imported file whose generated content contains `/*# sourceMappingURL=... */` text inside a CSS string literal.
- Updated the WordPress bundle import-graph example with the same generated-content boundary so theme/block CSS does not lose the imported CSS source in its source map.

Verification:

- `php -l lanes/lightningcss/src/CssBundler.php` - passed
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` - passed
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` - passed
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` - passed, 1 file / 484 assertions / 0 failures
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` - passed
- `php tools/run-tests.php lanes/lightningcss/tests` - passed, 13 files / 5455 assertions / 0 failures
- `git diff --check -- lanes/lightningcss` - passed

Status delta:

- Conservative mapped coverage: 2297 -> 2298 / 3532
- Native PHP pass evidence: 5451 -> 5455 assertions in the full LightningCSS lane
- Full upstream Rust/Node/WASM runners were not executed for this isolated micro-slice.

Dependency closure:

- No new support component is needed. This reuses the native PHP `CssBundler` and `SourceMap` path with a bounded comment scanner.

Non-overlap:

- This avoids the stale CustomMediaTransformer rework note and does not touch nested import rejection, import supports validation, path identity, CSS Modules dependency graphs, source-map VLQ offsets, CSSOM, target-prefixing, custom at-rule traversal, or property-value color/range slices.
