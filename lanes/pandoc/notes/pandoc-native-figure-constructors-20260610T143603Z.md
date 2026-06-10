# Pandoc Native AST Figure Constructors

Slice: `pandoc-native-figure-constructors-20260610T143603Z`
Bead: `plib-4msf`

## Scope

This bounded JSON/native AST slice closes the native `Figure` constructor gap.

- `NativeReader` maps Pandoc native JSON `Figure` blocks into shared `figure`
  AST nodes with attributes, short/long caption metadata, and single-image
  child collapse.
- `NativeWriter` emits generated shared `figure` nodes as native `Figure`
  constructors.
- Existing exact native round trips remain preserved through stored native
  constructor payloads.

## Evidence

- Red-first focused check:
  `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php`
  failed before implementation because `NativeWriter` could not emit shared
  `figure` nodes.
- Focused green:
  `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php`
  passed with `1 test files, 245 assertions, 0 failures`.
- Focused JSON/native pair:
  `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php lanes/pandoc/tests/NativeReaderTest.php`
  passed with `2 test files, 632 assertions, 0 failures`.
- Full Pandoc PHP suite:
  `php tools/run-tests.php lanes/pandoc/tests`
  passed with `44 test files, 60340 assertions, 0 failures`.

## Mapping Delta

- `phpPass`: `2978 -> 2979`
- `phpFail`: `0`
- Focused `NativeReaderTest.php`: one new passing case.
- Direct-format parity accounting is not affected by this native constructor
  completeness slice.

## Dependency Closure

No new support component is needed. This reuses native PHP JSON handling,
shared `AstNode` figure/image/caption structures, existing `NativeReader`
caption helpers, existing `NativeWriter` inline/block emission, and the lane
PHP harness.

No Pandoc, Cabal/Haskell runner, JSON filter, browser renderer, external
validator, online service, live provider test, or live-service provider test
was executed.
