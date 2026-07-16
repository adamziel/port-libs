# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T133050Z`

Base accepted HEAD: `f142d7b9b18cd05cbd5f51482c8462a8ab4294f0`

## Behavior Added

- Added bounded Unicode mathematical alphanumeric rewriting for accepted TeX
  math alphabet commands in `MathTexConverter`.
- ASCII letters and integer digit runs inside `\mathbf`, `\mathbb`,
  `\mathcal`, `\mathscr`, `\mathfrak`, `\mathit`, `\mathsf`, `\mathtt`, and
  `\boldsymbol` now emit the corresponding Unicode mathematical alphanumeric
  codepoints when the mapping is safe.
- Existing MathML `mathvariant` metadata remains in place, and non-ASCII or
  unsupported glyphs fall back to the original parsed MathML content.
- The WordPress math handoff example now includes a review audit for Unicode
  math alphabet glyphs while preserving the editable source TeX annotation.

## Source Truth

- Existing accepted Pandoc inventory maps Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- Prior math notes accepted `mathvariant` command wrappers and left Unicode
  mathematical alphanumeric codepoint rewriting as a bounded follow-up.
- No local Pandoc checkout is present under
  `/home/claude/port-libs/.upstream-cache/pandoc`, so no upstream Haskell
  runner was executed.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 308 assertions, 0 failures`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 315 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+7` focused assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/MathTexConverter.php`
  `php -l lanes/pandoc/tests/MathTexConverterTest.php`
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
`MathTexConverter` and reuses the current `MarkdownReader`, `LatexWriter`, and
`WordPressBlockWriter` handoff paths. Full upstream Pandoc runner parity
remains the existing Cabal/upstream-checkout blocker recorded in lane status.

No Pandoc, texmath, Cabal solver/build/test command, Haskell runner, MathJax,
KaTeX, TeX/PDF engine, browser renderer, JavaScript, online sanitizer, or
online service was executed.

## Status Delta

- `phpPass`: `922` -> `923` by one newly passing focused math test case.
- `benchmarkDenominator.mapped`: `1379` -> `1380`.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `61`.

## Non-Overlap

- Does not repeat accepted Math/TeX direct fractions, style-aware fractions,
  generalized `\genfrac`, infix fractions, explicit-delimiter infix fractions,
  roots, scripts, source annotation, delimiter/fence, sized delimiter,
  `\middle`, large-operator/function/operator-name, relation/set/logic/arrow,
  ordinary/extensible arrow accents, simple/optional macro expansion,
  indexed-root, matrix/aligned/cases/array/alignedat conversion,
  above/below/style wrappers, binomial commands, color, phantom, cancel,
  layout boxes, math alphabet command recognition, `\substack`, named spacing,
  explicit `\hspace`/`\mspace`, equation label/tag metadata, row-level
  equation metadata, equation-reference handoff, or document label-map
  resolution.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, syntax highlighting, EPUB3, ZIP, or
  upstream-runner dependency closure.

## Follow-Up

Keep automatic equation numbering without explicit tags, cross-document
equation references, MathML intent/accessibility annotations, nested macro-body
admission, deeper TeX parsing, full `texmath` parity, and full upstream runner
dependency planning as separate bounded slices.
