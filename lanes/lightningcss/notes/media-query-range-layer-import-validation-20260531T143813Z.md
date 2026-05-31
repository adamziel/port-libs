# LightningCSS Import Layer Validation Parity

Micro-slice: `lightningcss-media-query-range-layer-parity-20260531T143813Z`

Accepted base: `a187757827b58c999a1fc6cda2f4be5e163b73e9`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine read: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | sed -n '29488,29610p'`.
- Mapped seven focused upstream `src/lib.rs::test_layer` helpers beyond the accepted layer statement/block consolidation cluster.

## Native PHP Delta

- `CssMinifier` now minifies `@import` layer modifiers for bare `layer`, named `layer(foo)`, dotted `layer(foo.bar)`, and escaped `layer(foo\20 bar)` names.
- Invalid `@import` layer name lists such as `layer(foo, bar)` are rejected instead of being serialized.
- Invalid cascade-layer preludes `@layer;` and `@layer foo, bar {}` are rejected instead of being emitted as plausible CSS.
- `wordpress-media-layer-minifier.php --self-test` now guards invalid layered media queries plus invalid layer-list syntax for build-free block theme CSS.

## Evidence

- `php -l lanes/lightningcss/src/CssMinifier.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-media-layer-minifier.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 825 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 1766 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-layer-minifier.php --self-test` -> exits 0.
- `git diff --check -- lanes/lightningcss` -> passed.

## Coverage Delta

- PHP assertion delta: `+7` (`1759 -> 1766`).
- Conservative mapped coverage delta: `+7` (`1232 / 3532 -> 1239 / 3532`) for the focused upstream `src/lib.rs::test_layer` import-layer and invalid-layer helper cases.

## Non-overlap

This avoids repeating accepted media range target-threshold fallbacks, typed invalid media query validation, resolution media-query prefixes, cascade-layer merge/order consolidation, grid-template longhand composition, flex longhand prefixing, custom-media import-tail scanner rework, bundler import-layer wrapping, CSS Modules edge cases, and CSSOM shorthand behavior.

## Dependency Closure

No new support component is needed. The slice reuses the existing native `CssMinifier` top-level at-rule scanner, import modifier parser, layer-name minifier, and focused PHP test harness; no upstream binary, browser service, parser generator, or external CSS engine is required.

Root harness status: not run - isolated micro-slice.
