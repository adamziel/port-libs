# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T083859Z`

Base accepted HEAD: `ac12c42e994f416d094241f7a93c82358b0383b8`

## Behavior Added

- Extended `MathTexConverter` with bounded AMS `alignat`, `alignat*`,
  `alignedat`, and `alignedat*` environment handoff.
- Reads the required positive column-pair count and emits MathML `mtable`
  output with repeated `right left` column alignment pairs.
- Validates row shape before exposing MathML: each row must contain exactly
  `2 * pair-count` cells, the pair count is bounded to `1..4`, and a dangling
  final row separator is rejected.
- Preserves the existing `semantics` wrapper and escaped
  `application/x-tex` source annotation.
- Updated `examples/wordpress-math-tex-handoff.php` so WordPress review
  packets keep editable `alignedat` source and verify matching bounded MathML.

## Source Truth

- Existing accepted Pandoc inventory maps Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- The accepted `\middle` math note left `alignedat` as follow-up after direct
  fractions, generalized/infix fractions, roots, scripts, fences, operators,
  matrices, cases, arrays, AMS align/gather/split rows, spacing, and explicit
  spacing dimensions. This slice owns only bounded `alignat`/`alignedat` table
  handoff.
- This does not attempt full `texmath` parity, equation numbering, labels,
  optional macro arguments, Unicode mathematical alphanumeric rewriting,
  MathML intent annotations, renderer execution, or upstream Haskell runner
  parity.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 234 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 239 assertions, 1 failures`.
  - Failure reason: `\begin{alignedat}...` raised
    `Unsupported TeX environment alignedat at offset 17`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 244 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+10` focused assertions.
- Focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 9264 assertions, 0 failures`.
  - PASS-line count command:
    `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS'`
  - Result: `763`.
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

No Pandoc, Cabal solver/build/test command, Haskell runner, texmath, MathJax,
KaTeX, TeX/PDF engine, browser renderer, online sanitizer, or online service
was executed.

## Status Delta

- `benchmarkDenominator.mapped`: `1240` -> `1241`.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `64`.
- `phpPass`: unchanged at `781` in lane status; this patch adds one focused
  MathTexConverter PASS case and the local lane test PASS-line count is `763`.

## Non-Overlap

- Does not repeat accepted Math/TeX direct fractions, style-aware fractions,
  generalized `\genfrac`, infix fractions, explicit-delimiter infix fractions,
  roots, scripts, source annotation, delimiter/fence, sized delimiter,
  `\middle`, large-operator/function/operator-name, relation/set/logic/arrow,
  accent, macro-expansion, indexed-root, matrix/aligned environment, cases
  environment, array column-spec, above/below/style wrapper, binomial command,
  color, phantom, `\cancel`, `\bcancel`, `\xcancel`, `\cancelto`, math
  alphabet conversion, `\substack`, AMS align/gather/split environment
  conversion, named spacing command conversion, or explicit `\hspace`/
  `\mspace` dimension parsing.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, or upstream-runner dependency closure.

## Follow-Up

Keep equation numbering/labels, optional macro arguments, Unicode mathematical
alphanumeric rewriting, MathML intent/accessibility annotations, deeper TeX
parsing, full `texmath` parity, and full upstream runner dependency planning as
separate bounded slices.
