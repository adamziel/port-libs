# LightningCSS Bundle Import Graph Parity - 2026-06-01T04:06:04Z

## Source truth

- Upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source read: `src/bundler.rs::test_bundle` via `git show` from the pinned commit.
- Behavior pinned: an entry stylesheet that imports a dependency with anonymous `layer`, where that dependency imports a child with a named `layer(foo)`, must reject with `UnsupportedLayerCombination` at the nested child import location before reading the child stylesheet.

## Native delta

- Added focused PHP coverage in `CssBundlerTest.php` for anonymous parent layer plus nested named child layer rejection.
- Extended `wordpress-bundle-import-graph.php` smoke coverage to prove the diagnostic maps to the parent dependency file and rejects before the child token file is read.
- No production source edit was required; the current `CssBundler` layer-composition path already matched upstream, so this slice pins the upstream behavior against regressions.

## Verification

- `php -l lanes/lightningcss/tests/CssBundlerTest.php` - pass.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` - pass.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` - `1 test files, 539 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` - pass, including `anonymous-child-layer: rejected-before-child-read`.
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 5883 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "manifest ok\n"; json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "status ok\n";'` - `manifest ok`; `status ok`.
- `git diff --check -- lanes/lightningcss` - pass.

## Status delta

- Focused `CssBundlerTest.php` grew from 534 to 539 assertions.
- Full LightningCSS lane grew from 5878 to 5883 assertions.
- Conservative mapped coverage remains `2320 / 3532`; this deepens the represented bundle/import graph cluster instead of adding a new denominator unit.

## Dependency closure

- No new support component is needed. The slice reuses the native PHP `CssBundler` import parser, resolver, reader, and layer-composition diagnostic plumbing.

## Non-overlap

- Avoided the stale `port-lightningcss-current-rebase` note for `CustomMediaTransformer.php`; that is unrelated to this bundle/import graph slice.
- Does not overlap accepted source-map, CSS Modules, namespace-ordering, repeated named layer merge, nested import rejection, supports validation, or resolver-shape diagnostic clusters.

## Follow-up

- Continue non-overlapping bundle/import graph parity around source maps, CSS Modules dependency graphs, resolver/read diagnostics, media/supports condition propagation, and source identity behavior.
