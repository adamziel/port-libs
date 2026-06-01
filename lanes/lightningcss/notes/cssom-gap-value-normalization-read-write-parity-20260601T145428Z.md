# CSSOM Gap Value Normalization Read Write Parity 2026-06-01T14:54Z

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T145428Z`

Upstream source truth:

- `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/align.rs` defines `GapValue` as either `Normal` or `LengthPercentage(LengthPercentage)`, and defines `gap` as the shorthand over `row-gap` and `column-gap` with omitted column values copying the row value.
- `src/properties/mod.rs` registers `row-gap`, `column-gap`, and shorthand `gap` against those value types.
- `src/lib.rs` minifier coverage includes `row-gap: 10px; column-gap: 20px` composing to `gap: 10px 20px` and `row-gap: normal; column-gap: 20px` composing to `gap: normal 20px`.

Implemented behavior:

- `DeclarationBlock::parse()` now normalizes real `gap`, `row-gap`, and `column-gap` values through LightningCSS-like `normal` and length-percentage component handling.
- `DeclarationBlock::getProperty()` now reads normalized `normal`, zero lengths, decimals, units, and percentages when extracting longhands from `gap` or composing `gap` from longhands.
- `DeclarationBlock::setProperty()` now writes normalized gap components when updating a shorthand or appending direct gap declarations.
- `DeclarationBlock::removeProperty()` now splits normalized surviving `gap` components when removing one longhand from the shorthand.
- Custom properties such as `--Block-Gap` intentionally keep their authored value bytes.
- `examples/wordpress-gap-cssom.php` now covers WordPress editor gap read/set/remove paths that mix `normal`, decimals, percentages, and `var(--wp--style--block-gap)` tokens without Node.

Pre-fix probe:

- `DeclarationBlock::parse('gap: NORMAL; row-gap: 0px; column-gap: 0.500rem')` returned `NORMAL`, `0px`, and `0.500rem` for real gap declarations before this slice.

Evidence:

- `php -l lanes/lightningcss/src/DeclarationBlock.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-gap-cssom.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` => `1 test files, 1272 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-gap-cssom.php --self-test` => `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 8316 assertions, 0 failures`.

Coverage/status:

- Focused DeclarationBlock evidence adds 11 assertions for CSSOM gap value normalization.
- Full LightningCSS PHP evidence moves from `8305` to `8316` assertions.
- Conservative mapped coverage remains `2393 / 3532`; this deepens the already represented upstream DeclarationBlock/CSSOM gap family rather than adding a new upstream inventory unit.

Dependency closure:

- No new support component is needed. This reuses the existing native PHP declaration parser, CSSOM shorthand helpers, top-level whitespace splitter, and length-percentage normalizer.

Non-overlap:

- This does not repeat the accepted 2026-05-31 gap shorthand read/write slice, which added extraction, setting, and removal mechanics. This slice specifically covers upstream `GapValue` normalization for `normal` and length-percentage components across those existing read/write paths.
- This also avoids the latest CSSOM container escape, SourceMap, CSS Modules, custom at-rule, selector, media query, and target-prefix surfaces.
