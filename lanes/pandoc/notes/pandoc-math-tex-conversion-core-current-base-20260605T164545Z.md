# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T164545Z`

Base accepted HEAD: `919366e8669f709c0b980d9bb4babab7e3c4f1cd`

## Behavior Added

- Added bounded native Math/TeX handoff for starred operator names and explicit
  display-limit placement.
- `\operatorname*{...}` now uses lower/upper MathML placement when subscript or
  superscript scripts are present, while plain starred operator names without
  scripts remain ordinary operator identifiers.
- `\displaylimits` now behaves like explicit `\limits`, and `\nolimits`
  continues to force ordinary subscript/superscript placement even after
  `\operatorname*`.
- Bare or malformed `\displaylimits`, empty starred operator names, and
  placement commands without scripts are rejected before exposing review MathML.
- Updated the WordPress math handoff example so importer review packets keep
  editable source TeX and expose native MathML for this operator-limit path.

## Source Truth

- Existing accepted Pandoc inventory maps Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- Prior Math/TeX slices accepted ordinary `\operatorname`, explicit
  `\limits`/`\nolimits` on large operators, equation numbering, alignedat,
  spacing, delimiters, and accessibility annotations. This slice owns only the
  bounded `\operatorname*` default placement plus explicit `\displaylimits`
  handoff.
- No local Pandoc or texmath runner was used.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 345 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 314 assertions, 2 failures`.
  - Failure reasons: `\operatorname*` tried to read its group at the star, and
    bare `\displaylimits` was treated as an identifier.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 356 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+11` focused assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.
- PHP lint:
  - `php -l lanes/pandoc/src/MathTexConverter.php`: no syntax errors.
  - `php -l lanes/pandoc/tests/MathTexConverterTest.php`: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php`: no syntax
    errors.
- JSON validation:
  - `lanes/pandoc/lane-status.json ok`.
  - `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json ok`.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`MathTexConverter` and reuses the current `MarkdownReader`, `LatexWriter`,
`AstNode`, and `WordPressBlockWriter` handoff paths. Full upstream Pandoc
runner parity remains the existing Cabal/upstream-checkout blocker recorded in
lane status.

No Pandoc, Cabal solver/build/test command, Haskell runner, texmath, MathJax,
KaTeX, TeX/PDF engine, browser renderer, JavaScript, online sanitizer, online
service, or live service was executed.

## Status Delta

- `benchmarkDenominator.mapped`: `1459` -> `1460`.
- `phpPass`: `1004` -> `1005` by one newly passing focused Math/TeX case in
  lane status.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `65`.
- Focused `MathTexConverterTest.php`: `45` -> `46` PASS cases and `345` ->
  `356` assertions.

## Non-Overlap

- Does not repeat accepted Math/TeX direct fractions, generalized fractions,
  infix fractions, roots, scripts without explicit placement, source
  annotations, delimiters, sized delimiters, `\middle`, ordinary large-operator
  sub/superscripts, ordinary `\operatorname`, relation/set/logic commands,
  accents, extensible arrows, macro expansion, matrix/array/cases/subarray
  conversion, `substack`, AMS align/gather/split, alignedat, row tags,
  equation labels, equation references, automatic numbering, color/phantom/
  cancel/layout boxes, math alphabet variants, named/explicit spacing, or
  accessibility metadata.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, syntax highlighting, EPUB3, ZIP, or
  upstream-runner dependency closure.

## Follow-Up

Keep display-mode-sensitive movablelimits policy, multiline equation
environments such as `multline`, cross-document equation-reference maps, TeX
package macro expansion, complex accessibility intent grammar, renderer
validation, full texmath parity, and full upstream runner dependency planning
as separate bounded slices.
