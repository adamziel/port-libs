# CSSOM custom property case read/write parity - 2026-05-31

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T190100Z`

## Source Truth

- Upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/custom.rs` stores author-defined custom property names as `CustomPropertyName::Custom(DashedIdent(...))`, preserving the exact dashed identifier.
- `src/declaration.rs` parses declaration names into `PropertyId`, then `DeclarationBlock::get`, `set`, and `remove` compare the resulting property ids. That makes `--Block-Accent` and `--block-accent` distinct custom properties while ordinary CSS property names still match ASCII-insensitively.

## Red-First Probe

Before the patch, `DeclarationBlock::parseEntries()` lowercased every property name:

- `parse('--Theme-Color: red; --theme-color: blue')` collapsed the first declaration into the lowercase key.
- `getProperty(..., '--Theme-Color')` returned the lowercase declaration.
- `setProperty(..., '--Theme-Color', ...)` rewrote the lowercase declaration name.
- `removeProperty(..., '--Theme-Color')` removed both case variants.

## Implementation

- Added `normalizeDeclarationPropertyName()` in `DeclarationBlock`.
- Known and unknown non-custom property names remain normalized to lowercase for ordinary CSS property matching.
- Property names starting with `--` are now preserved exactly in parsing, `getProperty`, `setProperty`, and `removeProperty`.
- Added focused CSSOM assertions covering parse output, exact-case reads, non-matching uppercase reads, priority-bucket reads, same-case writes, priority promotion, and exact-case removal.
- Added `examples/wordpress-custom-property-cssom.php` to cover block/theme design-token workflows that use case-sensitive custom property names in `var()` references.

## Verification

- `php -l lanes/lightningcss/src/DeclarationBlock.php`
  - `No syntax errors detected in lanes/lightningcss/src/DeclarationBlock.php`
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/DeclarationBlockTest.php`
- `php -l lanes/lightningcss/examples/wordpress-custom-property-cssom.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-custom-property-cssom.php`
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - `1 test files, 588 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 3217 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-custom-property-cssom.php --self-test`
  - `OK`
- `git diff --check -- lanes/lightningcss`
  - clean

## Status Delta

- DeclarationBlock focused test evidence increased from `577` to `588` assertions, a `+11` assertion delta.
- Full LightningCSS PHP lane evidence increased from `3206` to `3217` assertions with `0` failures.
- Conservative mapped coverage remains `1721 / 3532` because this deepens the already represented DeclarationBlock CSSOM cluster.

## Non-Overlap

- This does not repeat accepted `-webkit-mask`, caret, container, background, border, border-image, font, grid, list-style, logical-axis, outline, text-decoration, or text-emphasis CSSOM shorthand clusters.
- This does not touch the historical `CustomMediaTransformer.php` rework note; that stale conflict is unrelated to DeclarationBlock custom-property identity.

## Dependency Closure

No new support component is needed. This reuses the native declaration parser, priority-bucket ordering, CSSOM get/set/remove helpers, and serializer already present in the LightningCSS lane.

## Root Harness

Not run; this is an isolated lane micro-slice.
