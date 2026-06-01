# CSS Modules Invalid Composes Math Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T133353Z`

## Source Truth

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream area: `src/properties/css_modules.rs::Composes::parse` invalid-value fallback plus `src/css_modules.rs::CssModule::handle_composes`, which only receives valid parsed `composes` values.
- Pinned native NAPI oracle checks confirmed invalid `composes` values containing math functions remain fallback declarations and do not produce CSS Modules export references:
  - `.test { composes: foo calc(1 + 2); color: red }` prints `composes:foo calc(1 + 2)`.
  - `.test { composes: foo max(1px, 2px); color: red }` prints `composes:foo max(1px, 2px)`.
  - `.test { composes: foo clamp(1px, 2px, 3px); color: red }` prints `composes:foo clamp(1px, 2px, 3px)`.
  - `.test { composes: foo var(--gap, calc(1 + 2)); color: red }` prints `composes:foo var(--gap,calc(1 + 2))`.
  - `.test { composes: foo round(nearest, 10px, 3px); color: red }` prints `composes:foo round(nearest, 10px, 3px)`.

## Behavior

- Invalid CSS Modules `composes` fallback declarations now protect CSS math functions while the generic declaration-value minifier runs.
- Ordinary declarations still evaluate/minify math functions through the existing `CssMinifier` path.
- Valid local/global/dependency `composes` declarations are unchanged: they are removed from emitted CSS and represented in export metadata.
- The WordPress invalid-composes smoke now includes generated block CSS where an invalid migrated `composes` value contains `max()` and `calc()` tokens.

## Red-First Evidence

Before the fix, the new focused PHP case failed:

- Expected: `.EgL3uq_test{composes:foo calc(1 + 2);color:red}`
- Actual: `.EgL3uq_test{composes:foo 3;color:red}`

## Verification

- `php -l lanes/lightningcss/src/CssMinifier.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-invalid-composes.php` -> no syntax errors.
- `php lanes/lightningcss/examples/wordpress-css-modules-invalid-composes.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> `1 test files, 621 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 2015 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 8085 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passed.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused CSS Modules evidence moved from `606` to `621` assertions.
- Full LightningCSS PHP evidence moved from `8070` to `8085` assertions.
- Conservative mapped coverage remains `2393 / 3532`; this deepens the already represented CSS Modules invalid `composes` fallback cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the native PHP CSS Modules transformer, shared minifier, invalid `composes` fallback serializer, and existing example/test harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Non-Overlap

This does not repeat accepted CSS Modules local/global selector-mode validation, escaped selector/property parsing, declaration-priority parsing, top-level comma fallback spacing, source-index dependency composes, unknown at-rule body preservation, nth-child diagnostics, host-context/state/highlight/view-transition selector behavior, bundle/import graph CSS Modules dependency handling, source maps, CSSOM, media-query, target-prefixing, property-value, or custom-at-rule slices. The change is limited to invalid `composes` fallback values containing CSS math functions.

## Next

Continue CSS Modules parity on non-overlapping parser/export graph edges, or pivot to source maps, bundle/import graph, CSSOM read/write, media-query recovery, property values, target-prefixing, or custom at-rule visitor gaps if another worker owns CSS Modules.
