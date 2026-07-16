# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T200639Z`

Base accepted HEAD: `b04f57c7230c881432b7183ac804ada5839368dd`

## Behavior Added

- Added bounded native Math/TeX handoff for TeX `\not` relation overlays.
- `MathTexConverter` now maps common negated relations such as `\not\in`,
  `\not=`, `\not\leq`, `\not\subseteq`, `\not\approx`, and
  `\not\Rightarrow` to direct MathML relation operators.
- Unsupported `\not` atoms remain visible through MathML `menclose` with an
  up-diagonal strike instead of leaking `\not` as a literal identifier.
- Accessibility text covers the new direct negated relation glyphs so
  `texToAccessibleMathMl()` can expose reviewable alt text for the bounded
  relation cases.
- Updated the WordPress math handoff example so import review packets include
  negated-relation MathML and escaped source-TeX annotations without invoking
  renderers.

## Source Truth

- The accepted Pandoc inventory maps Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- The texmath TeX reader source includes a dedicated `negated` parser branch
  for `\not`, maps common relation symbols to Unicode negated relations, and
  falls back to a combining negation mark for other symbols:
  https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX.hs
- This PHP slice ports the bounded format contract for common relation
  overlays and a safe review fallback. It does not attempt full `texmath`
  parser parity.
- No local Pandoc or texmath checkout is present under this isolated worktree,
  so no upstream Haskell runner was executed.

## Verification

- Focused Math/TeX check:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 394 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+9` focused assertions over the latest
    accepted math note baseline (`385` assertions).
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.
- PHP lint:
  - `php -l lanes/pandoc/src/MathTexConverter.php`: no syntax errors.
  - `php -l lanes/pandoc/tests/MathTexConverterTest.php`: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php`: no syntax
    errors.
- JSON validation:
  `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: ok.
- Whitespace check:
  `git diff --check -- lanes/pandoc`: clean.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`MathTexConverter` and reuses the current `MarkdownReader`, `LatexWriter`,
`AstNode`, `WordPressBlockWriter`, WordPress Math/TeX example, and focused PHP
test harness.

No Pandoc, Cabal solver/build/test command, Haskell runner, `texmath` runner,
MathJax, KaTeX, TeX/PDF engine, browser renderer, JavaScript, online sanitizer,
online service, or live service was executed.

## Status Delta

- `benchmarkDenominator.mapped`: `1513` -> `1514`.
- `phpPass`: `1060` -> `1061` by one newly passing focused Math/TeX case in
  lane status.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `63`.
- Focused `MathTexConverterTest.php`: `49` -> `50` PASS cases and `385` ->
  `394` assertions.

## Non-Overlap

- Does not repeat accepted Math/TeX fractions, generalized fractions, infix
  fractions, roots, scripts, source annotations, delimiters, sized delimiters,
  `\middle`, large operators, functions, operator names, explicit
  `\limits`/`\displaylimits`, direct relation/set/logic commands, accents,
  extensible arrows, macro expansion, matrix/array/cases/subarray conversion,
  `substack`, AMS align/gather/split/alignedat/flalign conversion,
  multline/multlined conversion, equation wrappers, row tags, equation
  references, automatic numbering, color/phantom/cancel/layout boxes, math
  alphabet variants, named/explicit spacing, or general accessible MathML
  metadata.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, syntax highlighting, EPUB3, ZIP, or
  upstream-runner dependency closure.

## Follow-Up

Keep broader negated relation inventory, TeX prime handling, `\notag`/
`\nonumber` row numbering policy, finer display numbering and starred
environment policy, cross-document equation-reference maps, package macro
expansion, renderer validation, complex accessibility intent grammar, full
`texmath` parity, and full upstream runner dependency planning as separate
bounded slices.
