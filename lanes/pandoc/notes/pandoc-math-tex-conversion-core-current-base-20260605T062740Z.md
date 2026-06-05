# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T062740Z`

Base accepted HEAD: `5f8b8c0d546a115699c0adf82d3e94d711c1439a`

## Behavior Added

- Extended `MathTexConverter` with bounded TeX spacing-command handoff.
- Converts `\,`, `\:`, `\;`, `\!`, `\>`, `\quad`, `\qquad`, `\enspace`,
  `\thinspace`, `\medspace`, `\thickspace`, `\negthinspace`,
  `\negmedspace`, and `\negthickspace` to MathML `mspace` widths.
- Preserves escaped source TeX in the existing `application/x-tex` annotation.
- Updated `examples/wordpress-math-tex-handoff.php` so WordPress review
  packets keep editable spacing-control formulas and verify matching MathML.

## Source Truth

- Existing accepted inventory maps Pandoc Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- The accepted AMS row-environment note left TeX spacing commands as a
  separate follow-up after direct fractions, generalized/infix fractions,
  roots, scripts, fences, operators, matrices, cases, arrays, above/below/style
  wrappers, color/phantom/cancel, math alphabets, stacked limits, and AMS
  align/gather/split layouts.
- This slice ports only the bounded support-library handoff for common TeX
  spacing controls. It does not attempt full texmath parity, explicit
  `\hspace` or `\mspace` dimension parsing, MathML intent annotations,
  optional macro arguments, renderer execution, or upstream Haskell runner
  parity.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 197 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 199 assertions, 1 failure`.
  - Failure reason: spacing commands emitted literal identifiers such as
    `<mi>\,</mi>`, `<mi>\;</mi>`, `<mi>\!</mi>`, `<mi>\quad</mi>`, and
    `<mi>\qquad</mi>`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 204 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+7` focused assertions.
- Focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 8038 assertions, 0 failures`.
  - PASS-line count: `687`.
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
`MathTexConverter` and reuses the current `MarkdownReader`, `LatexWriter`, and
`WordPressBlockWriter` handoff paths. Full upstream Pandoc runner parity remains
the existing Cabal/upstream-checkout blocker recorded in lane status.

## Status Delta

- `phpPass`: `685` -> `687` using the exact local lane PASS-line count.
- `benchmarkDenominator.mapped`: `1165` -> `1166`.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `61`.

## Non-Overlap

- Does not repeat accepted Math/TeX direct fractions, style-aware fractions,
  generalized `\genfrac`, infix fractions, explicit-delimiter infix fractions,
  roots, scripts, source annotation, delimiter/fence, large-operator/function/
  operator-name, relation/set/logic/arrow, accent, macro-expansion,
  indexed-root, matrix/aligned environment, cases environment, array
  column-spec, above/below/style wrapper, binomial command, color, phantom,
  `\cancel`, `\bcancel`, `\xcancel`, `\cancelto`, math alphabet conversion,
  `\substack`, or AMS align/gather/split environment conversion.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, or upstream-runner dependency closure.

## Follow-Up

Keep `alignedat`, equation numbering/labels, explicit `\hspace`/`\mspace`
dimension parsing, color declaration scoping, Unicode mathematical
alphanumeric rewriting, MathML intent/accessibility annotations, optional macro
arguments, deeper TeX parsing, and full upstream runner dependency planning as
separate bounded slices.
