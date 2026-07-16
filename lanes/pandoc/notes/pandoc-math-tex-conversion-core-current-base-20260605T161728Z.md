# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T161728Z`

Base accepted HEAD: `6c78b780d3e7e0af428581dfeac8da16a36ff6cc`

## Behavior Added

- Added bounded native Math/TeX handoff for explicit operator-limit placement.
- `\sum\limits_{i=1}^{n}` and sibling lower/upper placement forms now emit
  MathML `munder`, `mover`, or `munderover` instead of exposing `\limits` as a
  literal identifier.
- `\int\nolimits_{0}^{1}` and sibling no-limits forms now consume `\nolimits`
  and preserve ordinary `msub`, `msup`, or `msubsup` placement.
- Bare `\limits`, `\sum\limits`, and `\int\nolimits` inputs are rejected
  before review MathML is exposed.
- Updated the WordPress math handoff example so import review packets preserve
  editable source TeX and expose native MathML for explicit operator limits.

## Source Truth

- The accepted Pandoc inventory maps Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`.
  Those fixtures preserve inline/display math source as TeX strings in Pandoc
  math nodes.
- Prior Math/TeX slices accepted ordinary large-operator sub/sup placement,
  `substack`, AMS environments, alignedat, compact matrices/subarrays, and
  source-TeX annotations. This slice owns only explicit `\limits`/`\nolimits`
  placement handoff.
- No local Pandoc or texmath checkout was used, so no upstream Haskell runner
  was executed.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 335 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 337 assertions, 1 failures`.
  - Failure reason: `\limits` was emitted as `<mi>\limits</mi>` and received
    the lower/upper scripts.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 345 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+10` focused assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.
- PHP lint, JSON validation, and whitespace checks are recorded in the worker
  final report.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`MathTexConverter` and reuses the current `MarkdownReader`, `LatexWriter`, and
`WordPressBlockWriter` handoff paths. Full upstream Pandoc runner parity remains
the existing Cabal/upstream-checkout blocker recorded in lane status.

No Pandoc, texmath, Cabal solver/build/test command, Haskell runner, MathJax,
KaTeX, TeX/PDF engine, browser renderer, JavaScript, online sanitizer, online
service, or live service was executed.

## Status Delta

- `benchmarkDenominator.mapped`: `1447` -> `1448`.
- `phpPass`: `992` -> `993` by one newly passing focused Math/TeX case in
  lane status.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `64`.
- Focused `MathTexConverterTest.php`: `44` -> `45` PASS cases and `335` ->
  `345` assertions.

## Non-Overlap

- Does not repeat accepted Math/TeX fractions, generalized fractions, infix
  fractions, roots, scripts without explicit placement, source annotations,
  delimiters, sized delimiters, `\middle`, ordinary large-operator
  sub/superscripts, operator names, relation/set/logic commands, accents,
  extensible arrows, macro expansion, matrix/array/cases/subarray conversion,
  `substack`, AMS align/gather/split, alignedat, row tags, equation labels,
  equation references, color/phantom/cancel/layout boxes, math alphabet
  variants, named/explicit spacing, or accessibility metadata.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, syntax highlighting, EPUB3, ZIP, or
  upstream-runner dependency closure.

## Follow-Up

Keep `\displaylimits`, `\operatorname*` limit placement, multiline equation
environments, cross-document equation-reference maps, renderer validation,
complex accessibility intent grammar, full `texmath` parity, and full upstream
runner dependency planning as separate bounded slices.
