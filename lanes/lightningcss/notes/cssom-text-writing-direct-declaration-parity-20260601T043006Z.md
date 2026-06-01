# CSSOM Text/Writing Direct Declaration Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T043006Z`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream files:
  - `tests/test_cssom.rs` routes CSSOM declaration reads and writes through `Property::parse_string(...)`, `DeclarationBlock::{get,set,remove}`, and `to_css_string(PrinterOptions::default())`.
  - `src/properties/text.rs` defines typed `text-transform`, white-space, word-break, line-break, hyphens, overflow-wrap/word-wrap, text-align, text-align-last, text-justify, text-size-adjust, direction, unicode-bidi, and box-decoration-break values.
  - `src/properties/list.rs` defines `marker-side`.
  - `src/properties/mod.rs` maps prefixed `-webkit-`/`-moz-`/`-ms-` variants for hyphens, text-align-last, text-size-adjust, and box-decoration-break.

## Native PHP Delta

- `DeclarationBlock` now canonicalizes CSSOM direct declaration values for text and writing properties during parse/get/set:
  - `text-transform` lowercases and serializes in upstream order: case, `full-width`, then `full-size-kana`.
  - text flow/alignment enums lowercase upstream keywords for `white-space`, `word-break`, `line-break`, `hyphens`, `overflow-wrap`, `word-wrap`, `text-align`, `text-align-last`, `text-justify`, `direction`, `unicode-bidi`, `box-decoration-break`, and `marker-side`.
  - `text-size-adjust` lowercases `auto`/`none` and normalizes percentage numbers such as `100.0%` to `100%`.
- Added `examples/wordpress-text-writing-cssom.php` to cover block typography and mobile text-size adjustment CSSOM edits without Node/WASM.

## Evidence

- Red probe before implementation:
  - `DeclarationBlock::parse("text-transform: UpperCase full-size-kana full-width; white-space: Pre-Wrap; direction: RTL; text-size-adjust: 100.0%")` preserved authored casing and percentage zeros.
- Focused assertion delta:
  - Before: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 970 assertions, 0 failures`
  - After: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 997 assertions, 0 failures`
- Full lane:
  - `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 6005 assertions, 0 failures`
- Example:
  - `php lanes/lightningcss/examples/wordpress-text-writing-cssom.php --self-test` -> `OK`
- PHP lint:
  - `php -l lanes/lightningcss/src/DeclarationBlock.php` -> pass
  - `php -l lanes/lightningcss/tests/DeclarationBlockTest.php` -> pass
  - `php -l lanes/lightningcss/examples/wordpress-text-writing-cssom.php` -> pass
- Whitespace:
  - `git diff --check -- lanes/lightningcss` -> pass

## Coverage And Non-Overlap

- Conservative mapped coverage remains `2336 / 3532`; this deepens the already represented upstream `DeclarationBlock` CSSOM helper cluster instead of claiming a new denominator row.
- This does not repeat accepted CSSOM shorthand families for background, border, overflow, animation, transition, mask, grid, font, container, caret, text decoration, text emphasis, SVG paint/rendering, UI direct enums, logical boxes/sizes, or source-map/CSS Modules/bundler/media-query/target-prefixing slices.
- The stale May 25 `CustomMediaTransformer` rework note was inspected and is unrelated to this DeclarationBlock text/writing direct declaration cluster.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `DeclarationBlock` parser, CSS identifier/value tokenization helpers, and focused PHP test harness.

## Next Task

Continue CSSOM parity on a non-overlapping typed declaration or shorthand family, or pivot to remaining current-base source-map, CSS Modules, bundle/import graph, media-query, target-prefixing, property-value, or custom-at-rule parity.
