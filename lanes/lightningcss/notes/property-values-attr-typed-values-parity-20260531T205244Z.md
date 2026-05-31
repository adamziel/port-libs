# Property Values Attr Typed Values Parity - 2026-05-31T20:52Z

## Scope

This isolated LightningCSS property-values slice maps seven pinned upstream helpers from `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/lib.rs::test_custom_properties`:

- `attr(data-color type(<color>))`
- `attr(data-width type(<length>), 100px)`
- `attr(data-foo %)`
- `attr(data-foo %,)`
- `attr(data-foo px)`
- `attr(data-foo number)`
- `attr(data-foo raw-string)`

The direct red-first probe found three PHP gaps before the implementation: `attr(data-width type(<length>), 100px)` serialized as `attr(data-width type(<length>),100px)`, `attr(data-foo %)` serialized as `attr(data-foo%)`, and `attr(data-foo %,)` serialized as `attr(data-foo%,)`.

## Implementation

`CssMinifier` now runs a focused `attr()` value pass after the existing declaration value minifiers. The pass preserves strings, parses only real `attr(...)` functions, restores the required space between an attribute name and `%`, keeps bare-comma empty fallback serialization, and emits the upstream comma-space boundary before non-empty fallback values.

`examples/wordpress-attr-typed-value-minifier.php` models block CSS that reads typed accent color and size values from data attributes without Node/WASM.

Conservative mapped coverage moves from `2093 / 3532` to `2100 / 3532`. Full lane PHP pass count moves from `4262` to `4269`.

## Non-Overlap

This does not touch accepted color-mix, relative-color, gradient, grid shorthand, font shorthand/font-face/font-palette/font-feature-values, color-scheme/light-dark, source-map, visitor, bundler, or custom-media clusters. The stale custom-media rework note in the main handoff directory targets an old `@import` media-tail conflict and is not part of this property-value slice.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` - passes, 1 file / 1535 assertions / 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests` - passes, 13 files / 4269 assertions / 0 failures.
- `php lanes/lightningcss/examples/wordpress-attr-typed-value-minifier.php` - passes and prints the expected minified block CSS.
- `php -l` for changed PHP files - passes.
- `git diff --check -- lanes/lightningcss` - passes.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses `CssMinifier` declaration scanning, top-level function parsing, list splitting, and token helpers.
