# CSS Modules invalid composes comma parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T125710Z`

## Source truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Pinned upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Targeted upstream area:
  - `src/properties/css_modules.rs::Composes::parse`, where malformed comma-separated `composes` values fall back to ordinary declaration output instead of export metadata.
  - `src/css_modules.rs::CssModule::handle_composes`, which only receives valid parsed `composes` values.

Pinned NAPI oracle checks with `lightningcss.linux-x64-gnu.node`:

- `.test { composes: foo,bar; color: red }` prints `composes:foo,bar` and creates no compose references.
- `.test { composes: foo, bar; color: red }` prints `composes:foo, bar` and creates no compose references.
- `.test { composes: foo ,bar; color: red }` prints `composes:foo ,bar` and creates no compose references.
- `.test { composes: foo , bar; color: red }` prints `composes:foo , bar` and creates no compose references.
- `.test { composes: foo, url( bar ); color: red }` prints `composes:foo, url(bar)` and creates no compose references.
- `.test { composes: foo, "bar"; color: red }` prints `composes:foo, "bar"` and creates no compose references.

## Implementation

- `CssModulesTransformer::serializeInvalidComposesValue()` now preserves a pending token-boundary space before top-level commas in invalid fallback values.
- `CssMinifier` now detects `composes` declaration values during its initial whitespace pass so top-level comma spacing is not erased before declaration-value minification.
- `CssMinifier::minifyDeclarationValue()` records and restores top-level `composes` comma whitespace after ordinary value normalization, preserving invalid fallback spacing while still minifying nested function tokens such as `url( bar )`.
- Valid `composes` declarations are unchanged: they are still removed from output and represented in CSS Modules export metadata.

## Tests and evidence

- `php -l lanes/lightningcss/src/CssModulesTransformer.php` passed.
- `php -l lanes/lightningcss/src/CssMinifier.php` passed.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-invalid-composes.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` passed: `1 test files, 598 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-invalid-composes.php --self-test` passed: `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 7945 assertions, 0 failures`.

## Status delta

- Focused transformer assertions increased by 18 through six invalid comma fallback cases.
- Full LightningCSS lane evidence moved from `13 files / 7927 assertions / 0 failures` to `13 files / 7945 assertions / 0 failures`.
- Conservative mapped coverage remains `2392 / 3532`; this deepens the existing CSS Modules local/global/composes fallback cluster rather than claiming a new denominator unit.

## Dependency closure

No new support component is needed. This reuses the existing native PHP CSS Modules transformer, nesting transformer, shared minifier, invalid `composes` fallback serializer, and WordPress invalid-composes smoke path.

## Non-overlap

This does not repeat accepted CSS Modules escaped local/global pseudos, selector-list validation, functional-local composes rejection, escaped identifier/specifier compose parsing, declaration-priority valid `composes`, source-index compose flattening, unknown at-rule body preservation, nth-child local/global diagnostics, host-context/state/highlight/view-transition selector behavior, bundle/import graph CSS Modules dependency handling, source maps, CSSOM, media-query, target-prefix, property/value, or custom at-rule slices. The patch is limited to invalid comma-separated `composes` fallback serialization.

## Follow-up

A separate CSS Modules fallback slice can target invalid `composes` math-function serialization such as `calc()` / `max()` values. This slice intentionally stays on top-level comma whitespace parity.
