# Bundle Resolution Import Graph Parity 2026-06-01T021921Z

## Scope

Ported one bounded LightningCSS bundle/import graph parity slice for repeated same-file imports with named layer state.

Upstream source truth:
- `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`
- `src/bundler.rs::test_bundle` repeated `@import "b.css" layer(foo)` behavior
- `Bundler::load_file` merge logic where a cached stylesheet keeps or adopts a named layer, while incompatible/anonymous combinations remain rejected

## Behavior Covered

- Repeated imports of the same file with the same named layer emit one named layer wrapper.
- Repeated imports where one edge is unlayered and the other has a named layer preserve the named layer.
- Reader-backed bundle loading still reads a repeated dependency once while preserving the merged layer state.
- The WordPress bundle smoke now covers duplicate block stylesheet imports where a shared block CSS file is first imported unlayered and then through `layer(theme.blocks)`.

No production source change was needed; the native PHP bundler already matched this upstream behavior. This slice pins it with focused tests and example evidence so later import-graph work cannot regress it.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`
  - `1 test files, 485 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 5456 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
  - exited `0`, includes `duplicate-layer-imports: merged`
- `php -l lanes/lightningcss/tests/CssBundlerTest.php`
  - no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
  - no syntax errors
- JSON status/manifest lint
  - `UPSTREAM_TEST_MANIFEST.json ok`
  - `lane-status.json ok`
- `git diff --check -- lanes/lightningcss`
  - passed

## Coverage Delta

- Focused `CssBundlerTest.php`: `480 -> 485` assertions.
- Full LightningCSS lane: `5451 -> 5456` assertions.
- Conservative mapped denominator: `2297 -> 2298 / 3532`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP `CssBundler` parser, resolver, reader, import cache, layer wrapping, and minifier paths.

## Non-Overlap

This does not repeat the accepted nested `@import` rejection, invalid `supports()` validation, import-first CSS Modules resolution, duplicate media/supports merge, anonymous layer rejection, conflicting named-layer rejection, source-map collection, or CSS Modules dependency graph slices. It covers the successful repeated named-layer merge branch that was not separately pinned.
