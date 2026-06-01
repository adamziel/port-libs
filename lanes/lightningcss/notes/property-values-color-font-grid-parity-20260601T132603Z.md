# LightningCSS Property Values Color Font Grid Parity 2026-06-01T132603Z

## Scope

- Slice: `lightningcss-property-values-color-font-grid-parity-20260601T132603Z`.
- Behavior: font-family duplicate elimination in minified `font-family` and `font` shorthand family lists.
- Upstream source truth: pinned LightningCSS commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/properties/font.rs`, where `FontHandler::flush()` dedupes parsed font family entries before serializing declarations.

## Red-First Probe

Before the fix, the PHP minifier preserved duplicate normalized font families:

```text
.foo{font-family:Helvetica,Helvetica,sans-serif}
.foo{font-family:system-ui,system-ui,sans-serif}
.foo{font-family:Helvetica,Helvetica,sans-serif}
.foo{font:16px Helvetica,Helvetica,sans-serif}
```

## Changes

- `CssMinifier::minifyFontFamilyList()` now dedupes non-empty normalized family names while preserving first occurrence order.
- Added focused assertions for:
  - repeated unquoted families;
  - quoted/unquoted equivalents such as `"Helvetica", Helvetica`;
  - quoted generic family names remaining distinct from generic keyword families;
  - functional family tokens such as `var(--font)` preserving duplicate fallback slots;
  - `font` shorthand family-list dedupe.
- Updated `wordpress-font-family-minifier.php` to self-test duplicate WordPress theme font stacks without Node/WASM.

## Verification

- `php -l lanes/lightningcss/src/CssMinifier.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-font-family-minifier.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 2015 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-font-family-minifier.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 8023 assertions, 0 failures`.

## Non-Overlap

This does not repeat accepted font target fallback, font-stretch/range, font-face descriptor, font-palette, font-feature-values, color-mix, relative-color, grid value, CSSOM, source-map, bundle/import graph, CSS Modules, custom-at-rule, media-query, or target-prefix slices. It is limited to the upstream font handler's duplicate family elimination during minification.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP font-family tokenizer, quoted-family normalization, and font shorthand parser.

## Next Task

Continue property/value parity on a distinct unmapped or weakly mapped color, font, or grid behavior, preferably one that adds focused PHP assertions or removes an upstream runner blocker.
