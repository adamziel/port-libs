# Bundle Resolution Import Graph Parity 2026-06-01

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T063919Z`

Source truth: pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`. In `src/bundler.rs`, import graph resolution stores parsed `LayerName` values and rejects only non-equal layer combinations when an already-loaded file is imported again.

Upstream probe:
- `@import "b.css" layer(foo\2e bar); @import "b.css" layer(foo\.bar);` bundles once as `@layer foo\.bar{.b{color:green}}`.
- `@import "b.css" layer(\31 theme); @import "b.css" layer(\000031 theme);` bundles once as `@layer \31 theme{.b{color:green}}`.
- `@import "b.css" layer(foo\2e bar); @import "b.css" layer(foo.bar);` still rejects `unsupported-layer-combination`, proving escaped single-segment names remain distinct from nested layer paths.

Implementation:
- `CssBundler` now compares repeated import layer names by decoded layer segments instead of raw source text.
- Equivalent escaped spellings keep the first imported layer spelling in the merged graph, matching upstream first-load behavior.
- Anonymous layers and genuinely different layer paths still reject through the existing `unsupported-layer-combination` diagnostic.

WordPress scenario:
- `examples/wordpress-bundle-import-graph.php` now verifies a block stylesheet imported as `layer(plugin\2e cards)` and `layer(plugin\.cards)` is emitted once under `@layer plugin\.cards` for build-free block-theme delivery.

Verification:
- `php -l lanes/lightningcss/src/CssBundler.php`
- `php -l lanes/lightningcss/tests/CssBundlerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` -> `1 test files, 589 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` -> passed, including `escaped-equivalent-layer-imports: merged`
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 6506 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss` -> passed

Status delta: full LightningCSS lane PHP evidence moves from `6500` to `6506` assertions with no failures.

Dependency closure: no new support component is needed. This reuses `CssBundler` layer-name validation, CSS escape decoding, source-location diagnostics, and existing minifier serialization.

Non-overlap: this is bundle/import graph layer identity parity only. It does not repeat recent source-map VLQ offsets, CSSOM filter/backdrop-filter read-write parity, CSS Modules double-colon local/global behavior, custom at-rule exit-array traversal, target-prefix placeholder-shown behavior, or custom-media import-tail decoding.

Root harness: not run - isolated micro-slice.
