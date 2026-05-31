# Bundle External Import String Serialization Parity

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T183240Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/rules/import.rs` serializes external `@import` URLs through `cssparser::serialize_string(&self.url, dest)`.
- `cssparser` string serialization emits quoted CSS strings, escaping literal backslashes as `\\`, quotes as `\"`, NUL as replacement character, and ASCII controls as hex escapes.
- `src/bundler.rs` keeps resolver-marked `ResolveResult::External(url)` imports in the bundle output before bundled local imports while preserving media/supports/layer tails.

## Native Delta

- `CssBundler::externalImportStatement()` now writes resolver-marked external import URLs through a local cssparser-style quoted string serializer instead of escaping only double quotes.
- Added a focused bundler test for an external resolver URL containing a literal backslash followed by a local import, proving import graph order and media-tail preservation.
- Extended `wordpress-bundle-import-graph.php` with a WordPress editor CDN resolver smoke for an external URL containing a literal backslash.

## Evidence

- Red-first check before the implementation produced a single unescaped backslash in the external import URL: `@import "https://cdn.example/fonts\icons.css";.entry{color:red}`.
- `php -l lanes/lightningcss/src/CssBundler.php`: no syntax errors detected.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php`: no syntax errors detected.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php`: no syntax errors detected.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`: `1 test files, 173 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`: `13 test files, 3062 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php`: exits 0 and prints `resolver-external-string: serialized`.
- `git diff --check -- lanes/lightningcss`: passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence moves from `3060` to `3062` assertions with `0` failures.
- Conservative mapped coverage moves from `1684 / 3532` to `1685 / 3532`.

## Dependency Closure

No new support component is needed. This reuses the native PHP `CssBundler`, existing resolver callback contract, existing minifier path, and the existing WordPress import graph smoke.

## Non-Overlap

This slice is limited to external import URL string serialization. It does not repeat the accepted escaped URL delimiter parser work, custom-media import-tail scanner work, radial/conic gradients, CSSOM logical border behavior, CSS Modules grid custom-ident composition, custom visitor returns, source-map buffer snapshots, or border-image target-prefix boundaries.

Follow-up: resolver-marked external URLs containing both quotes and backslashes should be covered after the minifier string-token normalizer stops re-escaping already escaped quote sequences.
