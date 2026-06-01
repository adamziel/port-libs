# CSSOM logical border radius read/write parity

## Source Truth

- Upstream: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Pinned source file: `src/properties/mod.rs`.
- Upstream maps `border-start-start-radius`, `border-start-end-radius`, `border-end-start-radius`, and `border-end-end-radius` to `Size2D<LengthPercentage>` in logical border radius declarations.

## Behavior

Before this slice, CSSOM declaration parsing preserved logical corner radius value spelling such as `+012.00PX 50.0%` on direct logical longhands. This did not match the typed upstream value path used by physical border-radius values.

This patch normalizes direct logical border radius longhands through the existing length/percentage token canonicalizer and applies the same normalization to direct `border-radius` shorthand values. Custom properties remain byte-preserving.

Covered behaviors:

- `border-start-end-radius: +012.00PX 50.0%` parses and reads back as `12px 50%`.
- Duplicate horizontal/vertical logical corners collapse to the single-value form, e.g. `.5rem`.
- `setProperty()` canonicalizes logical corner writes and preserves priority handling.
- `removeProperty()` removes the logical corner without disturbing custom radius tokens.
- `wordpress-border-radius-cssom.php --self-test` now covers logical radius read/write behavior for block/editor CSSOM workflows.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - `1 test files, 1381 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-border-radius-cssom.php --self-test`
  - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 9114 assertions, 0 failures`
- `php -l lanes/lightningcss/src/DeclarationBlock.php`
  - `No syntax errors detected in lanes/lightningcss/src/DeclarationBlock.php`
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/DeclarationBlockTest.php`
- `php -l lanes/lightningcss/examples/wordpress-border-radius-cssom.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-border-radius-cssom.php`
- `git diff --check -- lanes/lightningcss`
  - `passed`

Focused assertion delta:

- DeclarationBlock focused test: `1373 -> 1381` (`+8`).
- Full LightningCSS PHP lane: `9106 -> 9114` (`+8`).
- Mapped coverage remains `2439 / 3532`.

## Non-Overlap

This does not repeat accepted physical border-radius CSSOM shorthand coverage, logical border shorthand/property preservation, border-radius target-prefix/minifier behavior, SVG `image-rendering`, UI direct enum, CSS Modules `:has(:scope)`, source-map, media-query, bundle/import graph, or custom-at-rule visitor slices.

## Dependency Closure

No new support component is needed. The slice reuses the existing `DeclarationBlock` declaration parser, border-radius component parser, shorthand composer, and length/percentage normalization helpers.
