# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260606T032458Z`

Base accepted HEAD: `48c802ea8046e77fb772cdde5b23074ce89ff045`

## Behavior Added

- Extended `MathTexConverter` with bounded TeX delimiter aliases for
  `\uparrow`, `\downarrow`, `\updownarrow`, `\Uparrow`, `\Downarrow`,
  `\Updownarrow`, `\lgroup`, `\rgroup`, `\lmoustache`, `\rmoustache`,
  `\arrowvert`, `\Arrowvert`, and `\bracevert`.
- The aliases now flow through existing direct delimiter, `\left...\right`,
  `\middle`, and sized delimiter paths instead of leaking literal command
  identifiers or rejecting the fence delimiter.
- Added bounded accessibility text for the new arrow, group, moustache, and
  brace-extension glyphs so `texToAccessibleMathMl()` remains reviewable.
- Updated the WordPress Math/TeX handoff example so review packets preserve
  editable source TeX and native MathML for these delimiter aliases.

## Source Truth

- The accepted Pandoc inventory maps Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- Prior Math/TeX slices accepted fractions, roots, scripts, source
  annotations, basic delimiter commands, sized delimiters, `\middle`, AMS
  environments, equation metadata, text mode aliases, layout controls, math
  alphabets, spacing, and the first delimiter-alias batch.
- This slice ports only the bounded TeX delimiter-inventory contract and does
  not attempt full `texmath` parser parity.
- No local Pandoc or texmath checkout is present under this isolated worktree,
  so no upstream Haskell runner was executed.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 429 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 429 assertions, 1 failures`.
  - Failure reason: `\uparrow` was rejected as an unsupported TeX fence
    delimiter.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 437 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+8` focused assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/MathTexConverter.php`
  - Result: no syntax errors.
  `php -l lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: no syntax errors.
  `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php`
  - Result: no syntax errors.
- JSON validation:
  `php -r '$files=["lanes/pandoc/lane-status.json","lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"]; foreach ($files as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both JSON files parsed successfully.
- Whitespace check:
  `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`MathTexConverter` delimiter map and reuses `MarkdownReader`, `LatexWriter`,
`AstNode`, `WordPressBlockWriter`, and the WordPress Math/TeX example. Full
upstream Pandoc runner parity remains the existing Cabal/upstream-checkout
blocker recorded in lane status.

No Pandoc, Cabal solver/build/test command, Haskell runner, texmath, MathJax,
KaTeX, TeX/PDF engine, browser renderer, online sanitizer, online service, or
live provider test was executed.

## Status Delta

- `phpPass`: `1174` -> `1175` by one newly passing focused Math/TeX case.
- `benchmarkDenominator.mapped`: `1624` -> `1625`.
- `mathTexConversionCoreCases`: `12` -> `13`.
- `mappedMathTexConversionCoreCases`: `12` -> `13`.
- `mathTexConversionCoreAssertions`: `63` -> `71`.
- Focused `MathTexConverterTest.php`: `54` -> `55` PASS cases and `429` ->
  `437` assertions.

## Non-Overlap

This does not repeat accepted Math/TeX fractions, generalized fractions,
infix fractions, roots, scripts, source annotations, existing basic delimiter
commands, sized delimiter mechanics, `\middle` validation, large operators,
functions, operator names, explicit limits, relation/set/logic commands,
negated relation overlays, accents, extensible arrows, macro expansion,
matrix/array/cases/subarray conversion, AMS row environments, equation
wrappers, row tags, equation references, automatic numbering, `\notag`/
`\nonumber`, prime notation, text-mode aliases, color/phantom/cancel/layout
boxes, math alphabet variants, named/explicit spacing, or the accepted
ceiling/floor/norm delimiter-alias batch.

This does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff,
OPC, archive compression, citations, YAML, doctemplates, tables, legacy
DOC/CFB, XML/HTML5 DOM, charset/Unicode, syntax highlighting, EPUB3, ZIP, or
upstream-runner dependency closure.

## Follow-Up

Keep broader package macro expansion, cross-document equation-reference maps,
MathML intent refinements, renderer validation, full `texmath` parity, and
full upstream Pandoc runner dependency planning as separate bounded slices.
