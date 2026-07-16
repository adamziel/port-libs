# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T055723Z`

Base accepted HEAD: `53ebd321947feaf9182f7b52290f5f26750e7000`

## Behavior Added

- Extended `MathTexConverter` with bounded AMS row-environment handoff for
  `align`, `align*`, `gather`, `gather*`, `gathered`, and `split`.
- Renders align/split rows as MathML `mtable columnalign="right left"` and
  gather/gathered rows as `mtable columnalign="center"` while preserving the
  existing `semantics` wrapper and escaped `application/x-tex` annotation.
- Validates row shape before exposing MathML: align/split rows must have two
  cells, gather rows must have one cell, rows cannot be entirely empty, and a
  dangling final `\\` row separator is rejected.
- Updated `examples/wordpress-math-tex-handoff.php` so WordPress review
  packets keep an editable AMS layout formula and verify matching bounded
  MathML without invoking external renderers.

## Source Truth

- Existing accepted inventory maps Pandoc Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- The accepted `\substack` math note left AMS align/gather/split environments
  as follow-up after direct fractions, generalized/infix fractions, roots,
  scripts, fences, operators, matrices, cases, arrays, above/below/style
  wrappers, color/phantom/cancel, math alphabets, and stacked limits.
- This slice ports only that bounded support-library contract. It does not
  attempt full `texmath` parity, `alignedat`, equation numbering, TeX spacing
  commands, optional macro arguments, MathML intent annotations, Unicode
  mathematical alphanumeric rewriting, renderer execution, or upstream Haskell
  runner parity.
- The local upstream Pandoc checkout remains absent under
  `/home/claude/port-libs/.upstream-cache/pandoc`, so no upstream runner was
  executed.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 187 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 191 assertions, 1 failures`.
  - Failure reason: `\begin{align}...` raised
    `Unsupported TeX environment align at offset 13`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 197 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+10` focused assertions.
- Focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 7796 assertions, 0 failures`.
  - PASS-line count command:
    `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS'`
  - Result: `673`.
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

- `phpPass`: `672` -> `673`.
- `benchmarkDenominator.mapped`: `1150` -> `1151`.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `64`.

## Non-Overlap

- Does not repeat accepted Math/TeX direct fractions, style-aware fractions,
  generalized `\genfrac`, explicit-delimiter infix fractions, roots, scripts,
  source annotation, delimiter/fence, large-operator/function/operator-name,
  relation/set/logic/arrow, accent, macro-expansion, indexed-root, matrix/
  aligned environment, cases environment, array column-spec, above/below/style
  wrapper, binomial command, color, phantom, `\cancel`, `\bcancel`, `\xcancel`,
  `\cancelto`, math alphabet conversion, or `\substack` conversion.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, or upstream-runner dependency closure.

## Follow-Up

Keep `alignedat`, equation numbering/labels, TeX spacing commands, Unicode
mathematical alphanumeric codepoint rewriting, MathML intent/accessibility
annotations, optional macro arguments, deeper TeX parsing, and full upstream
runner dependency planning as separate bounded slices.
