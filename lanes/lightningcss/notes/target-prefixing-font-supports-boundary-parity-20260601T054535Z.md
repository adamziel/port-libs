# Target Prefixing Font Supports Boundary Parity - 2026-06-01

## Scope

- Slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T054535Z`
- Accepted base: `06912dc408a93b4423231b55bdd13f99aa431658`
- Source truth: pinned upstream `parcel-bundler/lightningcss` manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, especially `src/prefixes.rs` `Feature::FontFeatureSettings`, `Feature::FontVariantLigatures`, `Feature::FontLanguageOverride`, and `Feature::FontKerning` prefix ranges.

## Behavior

The PHP target prefixer already emitted and removed font typography declaration prefixes for legacy and modern browser boundaries. This slice wires those same prefix groups into `@supports` declaration guards so guarded CSS expands legacy conditions and prunes stale prefixed alternatives:

- `font-feature-settings`: WebKit and Moz guard alternatives across Chrome 47 / Firefox 33 boundaries.
- `font-variant-ligatures`: WebKit and Moz guard alternatives using the same upstream font feature boundary table.
- `font-language-override`: WebKit and Moz guard alternatives using the same upstream font feature boundary table.
- `font-kerning`: WebKit guard alternatives across Safari 9 / Safari 10 boundaries.

The updated WordPress font typography example now covers both direct declarations and supports-gated font typography blocks without Node/WASM.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `1 test files, 1018 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 6275 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-font-typography-prefixer.php --self-test`
  - Result: `OK`
- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
  - Result: no syntax errors
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-font-typography-prefixer.php`
  - Result: no syntax errors
- `git diff --check -- lanes/lightningcss`
  - Result: passed

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP `TransitionPrefixer` supports-declaration rewrite path and extends only its prefix group table for already implemented font typography target flags.

## Non-Overlap

This does not repeat direct font typography declaration prefixing, print-color-adjust boundaries, box-shadow boundaries, CSS Modules, source maps, or media query redundant wrapper parity. The remaining target-prefixing follow-up should pick a different unmapped browser-boundary family or move to another priority area such as bundle/import graph, source maps, CSS Modules, CSSOM, visitor/custom at-rule, media-query, selector, parser recovery, or property/value parity.
