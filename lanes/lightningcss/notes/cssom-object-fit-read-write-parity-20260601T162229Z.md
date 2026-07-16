# CSSOM object-fit/object-position read-write parity

Slice: `lightningcss-cssom-declaration-read-write-parity-20260601T162229Z`

Base accepted HEAD: `7e5889a228115ead961c432c28514d2ac6db2dc1`

## Source truth

- Upstream LightningCSS manifest commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream `src/prefixes.rs` maps `Feature::ObjectFit | Feature::ObjectPosition` to the `-o-` prefix for Opera 10.6 through 12.1. The PHP lane already has target-prefixing coverage for this feature; this slice closes the DeclarationBlock CSSOM read/write side for the same declaration names.
- Red-first local probe before the patch preserved mixed-case CSSOM values for `object-fit`, `object-position`, `-o-object-fit`, and `-o-object-position`; `setProperty(..., 'object-fit', 'Scale-Down', true)` serialized `Scale-Down !important` instead of LightningCSS-style canonical `scale-down !important`.

## Implementation

- Added DeclarationBlock canonicalization for:
  - `object-fit` and `-o-object-fit` keywords: `fill`, `contain`, `cover`, `none`, `scale-down`.
  - `object-position` and `-o-object-position` focal-point tokens, lowercasing position keywords and normalizing length/percentage tokens such as `10PX` and `50.000%`.
- Preserved custom property case and existing priority bucket / remove semantics.
- Added the WordPress-facing example `wordpress-object-fit-cssom.php` for featured image/video crop declarations.

## Verification

- `php -l lanes/lightningcss/src/DeclarationBlock.php`
  - `No syntax errors detected in lanes/lightningcss/src/DeclarationBlock.php`
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/DeclarationBlockTest.php`
- `php -l lanes/lightningcss/examples/wordpress-object-fit-cssom.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-object-fit-cssom.php`
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - `1 test files, 1324 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-object-fit-cssom.php --self-test`
  - `OK`

Focused assertion delta: `+12` DeclarationBlock assertions. `phpPass` moves `8694 -> 8706` in lane status. Conservative mapped coverage remains `2398 / 3532`.

## Dependency closure

No new support component is needed. The slice reuses the existing DeclarationBlock tokenizer, property-name normalization, length/percentage normalization, priority bucket handling, and set/remove serialization.

## Non-overlap

This does not repeat the accepted target-prefix object-fit boundary slice, transform CSSOM slice, CSSOM background-clip slice, or source-map/bundle/CSS Modules work. It only deepens DeclarationBlock CSSOM read/write behavior for the object-fit/object-position declaration pair and their upstream `-o-` prefixed forms.
