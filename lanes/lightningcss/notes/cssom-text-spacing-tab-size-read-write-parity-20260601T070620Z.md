# CSSOM Text Spacing And Tab Size Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T070620Z`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream files:
  - `tests/test_cssom.rs` routes declaration `getPropertyValue`, `setProperty`, and `removeProperty` through parsed `Property` values and `to_css_string(PrinterOptions::default())`.
  - `src/properties/mod.rs` maps `tab-size`, `-moz-tab-size`, `-o-tab-size`, `word-spacing`, `letter-spacing`, and `text-indent` to typed property parsers.
  - `src/properties/text.rs` defines `Spacing` as `normal` or a length, and `TextIndent` as a length/percentage plus optional `hanging` and `each-line`.
  - `src/lib.rs` upstream tests cover `tab-size: 8`, prefixed tab-size lengths, `word-spacing`/`letter-spacing` normal and length values, and `text-indent` values where `hanging` and `each-line` serialize after the indentation value regardless of author order.

## Native PHP Delta

- `DeclarationBlock` now normalizes `tab-size`, `-moz-tab-size`, and `-o-tab-size` CSSOM reads/writes as upstream length-or-number values.
- `word-spacing` and `letter-spacing` now lowercase `normal` and normalize simple CSS length tokens during parse/get/set.
- `text-indent` now accepts upstream token ordering for length/percentage, `hanging`, and `each-line`, then serializes as value, `hanging`, `each-line`.
- Custom properties with similar text-indent-shaped values remain unparsed and preserve author casing/order.
- Added `examples/wordpress-text-spacing-cssom.php` to exercise block typography CSSOM edits without Node/WASM.

## Evidence

- Baseline status before this micro-slice: full LightningCSS lane `13 test files, 6655 assertions, 0 failures`.
- Focused:
  - `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 1071 assertions, 0 failures`
- Full lane:
  - `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 6669 assertions, 0 failures`
- Example:
  - `php lanes/lightningcss/examples/wordpress-text-spacing-cssom.php --self-test` -> `OK`
- PHP lint:
  - `php -l lanes/lightningcss/src/DeclarationBlock.php` -> pass
  - `php -l lanes/lightningcss/tests/DeclarationBlockTest.php` -> pass
  - `php -l lanes/lightningcss/examples/wordpress-text-spacing-cssom.php` -> pass
- Whitespace:
  - `git diff --check -- lanes/lightningcss` -> pass

## Coverage And Non-Overlap

- Conservative upstream manifest mapped coverage remains unchanged. This deepens the existing CSSOM `DeclarationBlock` source surface instead of claiming a new upstream denominator row.
- This does not repeat accepted CSSOM direct enum text/writing coverage, border spacing, box spacing, cursor, filter, transform, transition, shadow, SVG paint/rendering, shorthand families, source-map, CSS Modules, bundler/import graph, media-query, custom at-rule, or target-prefixing slices.
- Current rework notes were checked; only stale May 25 LightningCSS notes were present and none targeted this current base or micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP declaration tokenizer, CSSOM priority serializer, and focused PHP harness.

## Next Task

Continue CSSOM parity on a non-overlapping typed declaration family, or pivot to remaining current-base source-map, CSS Modules, bundle/import graph, media-query, target-prefixing, property-value, or custom-at-rule parity.
