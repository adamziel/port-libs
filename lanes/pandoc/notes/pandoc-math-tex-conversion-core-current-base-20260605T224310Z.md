# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T224310Z`

Base accepted HEAD: `ee26489bdb651a4b12ce158e3b8859ff31df6834`

## Behavior Added

- Added bounded native Math/TeX handoff for common TeX delimiter aliases:
  `\lceil`, `\rceil`, `\lfloor`, `\rfloor`, `\lbrack`, `\rbrack`,
  `\lparen`, `\rparen`, `\lvert`, `\rvert`, `\lVert`, `\rVert`, and `\|`.
- The aliases now feed the existing plain delimiter, `\left...\right`, and
  sized delimiter paths instead of leaking literal command identifiers or
  rejecting the fence delimiter.
- Ceiling/floor tokens now contribute bounded accessibility alt text through
  `texToAccessibleMathMl()`.
- Updated the WordPress math handoff example so review packets preserve
  editable source TeX and native MathML for norm bars plus mixed ceiling/floor
  delimiters.

## Source Truth

- The accepted Pandoc inventory maps Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- Prior Math/TeX slices accepted fractions, generalized fractions, infix
  fractions, roots, scripts, source annotations, basic delimiters, sized
  delimiters, `\middle`, large operators, functions, operator names, relation
  commands, negated relation overlays, accents, extensible arrows, macro
  expansion, matrix/array/cases/subarray conversion, AMS row environments,
  equation labels/references, row-number suppression, prime notation,
  text-mode aliases, color/phantom/cancel/layout boxes, math alphabet
  variants, and spacing.
- This slice ports only the bounded delimiter-alias format contract and does
  not attempt full `texmath` parser parity.
- No local Pandoc or texmath checkout is present under this isolated worktree,
  so no upstream Haskell runner was executed.

## Verification

- Red-first focused Math/TeX check after adding delimiter-alias coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 420 assertions, 1 failures`.
  - Failure reason: `Unsupported TeX fence delimiter command \lVert at offset 11`.
- Focused Math/TeX check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 429 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+9` focused assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`MathTexConverter` delimiter map and reuses the current `MarkdownReader`,
`LatexWriter`, `AstNode`, `WordPressBlockWriter`, WordPress Math/TeX example,
and focused PHP test harness.

No Pandoc, Cabal solver/build/test command, Haskell runner, `texmath` runner,
MathJax, KaTeX, TeX/PDF engine, browser renderer, JavaScript, online sanitizer,
online service, or live provider test was executed.

## Status Delta

- `benchmarkDenominator.mapped`: `1552` -> `1553`.
- `phpPass`: `1100` -> `1101` by one newly passing focused Math/TeX case in
  lane status.
- `mathTexConversionCoreCases`: `12` -> `13`.
- `mappedMathTexConversionCoreCases`: `12` -> `13`.
- `mathTexConversionCoreAssertions`: `63` -> `72`.
- Focused `MathTexConverterTest.php`: `53` -> `54` PASS cases and `420` ->
  `429` assertions.

## Non-Overlap

- Does not repeat accepted Math/TeX fractions, generalized fractions, infix
  fractions, roots, scripts, source annotations, existing basic delimiter
  commands, sized delimiter mechanics, `\middle`, large operators, functions,
  operator names, explicit `\limits`/`\displaylimits`, relation/set/logic
  commands, negated relation overlays, accents, extensible arrows, macro
  expansion, matrix/array/cases/subarray conversion, `substack`, AMS
  align/gather/split/alignedat/flalign/multline conversion, equation wrappers,
  row tags, equation references, automatic numbering, `\notag`/`\nonumber`,
  prime notation, text-mode aliases, color/phantom/cancel/layout boxes, math
  alphabet variants, or named/explicit spacing.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, syntax highlighting, EPUB3, ZIP, or
  upstream-runner dependency closure.

## Follow-Up

Keep broader delimiter inventories, package macro expansion, cross-document
equation-reference maps, MathML intent refinements, renderer validation, full
`texmath` parity, and full upstream Pandoc runner dependency planning as
separate bounded slices.
