# LightningCSS Bundle Resolution Import Graph Parity - 2026-06-01

## Slice

- Lane: `lightningcss`
- Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T053314Z`
- Base accepted HEAD: `663e16b4022673e2529b925ce20b45f0a578189e`
- Source truth: upstream `parcel-bundler/lightningcss` manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Upstream Evidence

Static source reads from pinned upstream `src/bundler.rs` drive this slice:

- `inline()` hoists CSS Modules dependency stylesheets before it scans ordinary top-level `@import` rules.
- The bundled-import ordering guard is updated only when a normal bundled import is emitted, not when a CSS Modules dependency was hoisted.
- A resolver-marked external import remains valid before the first normal bundled import even if CSS Modules dependency CSS has already been emitted.
- A resolver-marked external import after a normal bundled import still raises the upstream `ExternalImportAfterBundledImport` diagnostic at the authored `@import` location.

## Native Delta

- `CssBundler` now snapshots original top-level `@import` source locations before CSS Modules rewriting and restores them after the CSS Modules transform and reference reparse.
- Added focused `CssBundlerTest.php` coverage for an external import before a normal bundled import when CSS Modules dependencies have already hoisted output.
- The same test keeps the rejection path pinned: an external import after a normal bundled import still throws `external-import-after-bundled-import` at authored line 2, column 1.
- Extended `wordpress-bundle-import-graph.php` with a block-style smoke proving composed CSS Modules tokens hoist before an external editor reset without triggering the external-order guard.

## Verification

- Red-first probe before the source fix: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` failed because the new diagnostic-location assertion expected source line `2` and native PHP reported line `1`.
- `php -l lanes/lightningcss/src/CssBundler.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` -> `1 test files, 566 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` -> exits 0 and prints `css-modules-external-after-hoist: preserved`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 6245 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused `CssBundlerTest.php` evidence moves from `558` to `566` assertions.
- Full LightningCSS lane evidence moves from `6237` to `6245` assertions.
- Conservative mapped upstream coverage remains `2359 / 3532`; this deepens the already represented `src/bundler.rs::test_bundle` / `test_css_module` bundle and CSS Modules import graph cluster.

## Dependency Closure

No new support component is needed. This reuses native PHP `CssBundler`, `CssModulesTransformer`, source-location scanning, resolver handling, `CssMinifier`, and the existing WordPress bundle smoke. No Node, Rust, WASM, parser generator, browser, or external service dependency is introduced.

## Non-Overlap

This does not touch the stale `CustomMediaTransformer.php` current-rebase note, repeated import-position external siblings, CSS Modules first-instance dependency ordering, conditional composes validation, source-map import graph behavior, media-query parsing, target-prefixing, property/value minification, CSSOM read/write, or custom at-rule visitor clusters.

## Follow-Up

Remaining bundle/CSS Modules parity work includes source-map interactions after CSS Modules dependency hoists, more diagnostic-location edges through CSS Modules rewriting, and additional resolver/source-provider ordering cases that do not depend on upstream parallel callback order.
