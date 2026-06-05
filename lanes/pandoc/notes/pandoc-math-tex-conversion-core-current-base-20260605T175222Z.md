# Pandoc Math/TeX Multline Slice

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T175222Z`
Base accepted HEAD: `f0c994757ade1bf76847121ddfe3ea0faea2f48b`

## Behavior Added

- Added bounded native Math/TeX handoff for `multline`, `multline*`, and `multlined`.
- Renders each row as a centered one-column MathML `mtable`, preserving row breaks in the presentation tree and the original TeX in the semantics annotation.
- Consumes optional AMS row-spacing brackets after row separators, such as `\\[.5em]`, before parsing the next row.
- Rejects malformed empty rows, ampersands in one-column multline rows, and unclosed optional row-spacing brackets.
- Reuses the existing AMS row metadata path for row `\label`, `\tag`, and document label-map auto-numbering.
- Updates the WordPress math handoff example so review packets carry multline MathML plus source annotations without invoking renderers.

## Source Truth

- Upstream texmath TeX reader maps `multline` and `multlined` with the gathered-row environment family and consumes optional bracketed row spacing after AMS row separators:
  `https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX.hs`
- Existing Pandoc lane inventory maps math as preserved source TeX in AST/math handoff nodes. This slice ports the bounded support-library contract, not full texmath or Pandoc runner parity.

## Verification

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  Result: `1 test files, 356 assertions, 0 failures`.
- Red-first after adding multline expectations:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  Result: `1 test files, 360 assertions, 1 failure`; failure was `Unsupported TeX environment multline at offset 16`.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  Result: `1 test files, 366 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  Result: `math tex handoff self-test ok`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1025 -> 1026`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1477 -> 1478`.
- `mathTexConversionCoreCases`: `11 -> 12`.
- `mappedMathTexConversionCoreCases`: `11 -> 12`.
- `mathTexConversionCoreAssertions`: `54 -> 64`.
- Focused MathTexConverter coverage: `46 -> 47` PASS cases and `356 -> 366` assertions.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `MathTexConverter`, `MarkdownReader`, `LatexWriter`, `AstNode`, and `WordPressBlockWriter` behavior. No Pandoc, Cabal solver/build/test command, Haskell runner, texmath, MathJax, KaTeX, TeX/PDF engine, browser renderer, JavaScript, online sanitizer, or online service was executed.

## Non-Overlap

This does not repeat the accepted Math/TeX alignedat handoff. It adds the separate AMS multline/multlined gathered-row family plus optional row-spacing bracket handling, and leaves full texmath parity, `flalign`, equation wrapper policy, package macro expansion, complex MathML intent grammar, and renderer validation for later bounded slices.
