# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260606T070906Z`

Base accepted HEAD: `2c3874187ed49e9686f363014a3a498e09dbcd73`

## Behavior Added

- Extended `MathTexConverter` array preamble parsing with bounded TeX
  repeated-column support for forms such as `*{2}{c|}`.
- Repeated preambles are expanded before the existing column metadata parser,
  so `columnalign`, `columnlines`, `columnwidth`, and
  `data-tex-column-valign` continue to use the canonical array handoff path.
- Braced column-width groups such as `p{2cm}` are skipped during repeat
  expansion and remain validated by the existing width-column logic.
- Invalid zero, oversized, empty, and subarray width-repeat preambles are
  rejected before MathML handoff rather than leaking literal TeX.
- Updated the WordPress Math/TeX handoff example so repeated array preambles
  remain editable as source TeX and visible as native MathML review metadata.

## Source Truth

- The lane's accepted Pandoc inventory maps math preservation from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  inline and display math source remains recoverable as TeX in Pandoc math
  nodes.
- Prior Math/TeX slices already covered fractions, roots, generalized and
  infix fractions, delimiters, AMS row environments, equation metadata,
  `alignedat`, `flalign`, `multline`, array column lines, `\hline`,
  `\cline`, and `p`/`m`/`b` width columns.
- This slice ports only bounded array repeated-preamble metadata. It does not
  attempt full `texmath` preamble parity.

## Verification

- Baseline focused check:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 457 assertions, 0 failures`.
- Red-first direct conversion:
  `php -r 'require "tools/bootstrap.php"; $c = new PortLibs\Pandoc\MathTexConverter(); echo $c->texToMathMl("\\begin{array}{*{2}{c|}r}p_1 & m_1 & 1 \\\\ p_2 & m_2 & 2\\end{array}");'`
  - Result: fatal `InvalidArgumentException`, `Unsupported TeX array column
    specifier * at offset 0`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 466 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+9` focused assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `MathTexConverter`
array parsing plus the existing `MarkdownReader`, `LatexWriter`, `AstNode`,
`WordPressBlockWriter`, WordPress Math/TeX handoff example, and lane-local
manifest/status tracking.

No Pandoc, Cabal solver/build/test command, Haskell runner, texmath, MathJax,
KaTeX, TeX/PDF engine, browser renderer, online sanitizer, online service, or
live provider test was executed.

## Status Delta

- `phpPass`: `1238` -> `1239`.
- `benchmarkDenominator.mapped`: `1681` -> `1682`.
- `mathTexConversionCoreCases`: `12` -> `13`.
- `mappedMathTexConversionCoreCases`: `12` -> `13`.
- `mathTexConversionCoreAssertions`: `63` -> `72`.
- Focused `MathTexConverterTest.php`: `57` -> `58` PASS cases and `457` ->
  `466` assertions.

## Non-Overlap

This does not repeat accepted Math/TeX roots, fractions, generalized
fractions, infix fractions, scripts, source annotations, delimiter commands,
sized delimiter mechanics, `\middle`, large operators, functions, explicit
limits, relation/set/logic commands, negated relation overlays, accents,
extensible arrows, macro expansion, matrix/cases/subarray conversion, AMS row
environments, equation wrappers, row tags, equation references, automatic
numbering, prime notation, text-mode aliases, color/phantom/cancel/layout
boxes, math alphabet variants, spacing, alignedat, flalign, multline,
ceiling/floor/norm delimiter aliases, array column lines, `\hline`, `\cline`,
or paragraph-width array column metadata.

This does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff,
OPC, archive compression, citations, YAML, doctemplates, tables, legacy
DOC/CFB, XML/HTML5 DOM, charset/Unicode, syntax highlighting, EPUB3, ZIP, or
upstream-runner dependency closure.

## Follow-Up

Keep renderer-safe pre/post array column hooks, decimal alignment, richer
array rule styling, package macro expansion, MathML intent refinements,
renderer validation, full `texmath` parity, and full upstream Pandoc runner
dependency planning as separate bounded slices.
