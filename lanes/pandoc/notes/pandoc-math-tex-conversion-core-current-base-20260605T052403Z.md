# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T052403Z`

Base accepted HEAD: `f93fdff6e9d21f5d28d637897ad7c1e7dc84cc02`

## Behavior Added

- Extended `MathTexConverter` with bounded TeX `\substack{...}` handoff.
- Converts one-column stacked rows such as
  `\sum_{\substack{i=1 \\ i\ne j}}^{n}` and
  `\lim_{\substack{x \to 0 \\ x > 0}}` into MathML `mtable` lower-limit
  content with centered column alignment and compact row spacing.
- Preserves the existing MathML `semantics` wrapper and escaped
  `application/x-tex` source annotation.
- Supports standalone stacked formulas such as `\substack{p_i \\ m_i}`.
- Rejects missing groups, empty groups, empty final rows, and multi-column
  rows before exposing MathML, without invoking Pandoc, texmath, MathJax,
  KaTeX, TeX/PDF engines, or online services.
- Updated `examples/wordpress-math-tex-handoff.php` so WordPress review
  packets keep editable stacked-limit TeX source and verify matching bounded
  MathML.

## Source Truth

- Existing accepted inventory maps Pandoc Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- The accepted math alphabet note left `\substack`, AMS align/gather/split
  environments, and spacing commands as separate follow-up work. This slice
  ports only the bounded `\substack` support-library contract.
- This does not attempt full `texmath` parity, AMS environment parity, TeX
  spacing commands, optional macro arguments, MathML intent annotations,
  Unicode mathematical alphanumeric rewriting, renderer execution, or upstream
  Haskell runner parity.
- The local upstream Pandoc checkout remains absent under
  `/home/claude/port-libs/.upstream-cache/pandoc`, so no upstream runner was
  executed.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 178 assertions, 0 failures`.
- First focused run after adding substack coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 182 assertions, 1 failures`.
  - Failure reason: trailing empty `\substack{a \\ }` rows were not rejected.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 187 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+9` focused assertions.
- Focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 7445 assertions, 0 failures`.
  - PASS-line count command:
    `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS'`
  - Result: `650`.
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

- `phpPass`: refreshed to the exact focused-suite PASS-line count `650`.
- `benchmarkDenominator.mapped`: `1127` -> `1128`.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `63`.

## Non-Overlap

- Does not repeat accepted Math/TeX direct fractions, style-aware fractions,
  generalized `\genfrac`, explicit-delimiter infix fractions, roots, scripts,
  source annotation, delimiter/fence, large-operator/function/operator-name,
  relation/set/logic/arrow, accent, macro-expansion, indexed-root, matrix/
  aligned environment, cases environment, array column-spec, above/below/style
  wrapper, binomial command, color, phantom, `\cancel`, `\bcancel`, `\xcancel`,
  `\cancelto`, or math alphabet conversion.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, or upstream-runner dependency closure.

## Follow-Up

Keep AMS align/gather/split environments, spacing commands, Unicode
mathematical alphanumeric codepoint rewriting, MathML intent/accessibility
annotations, optional macro arguments, deeper TeX parsing, and full upstream
runner dependency planning as separate bounded slices.
