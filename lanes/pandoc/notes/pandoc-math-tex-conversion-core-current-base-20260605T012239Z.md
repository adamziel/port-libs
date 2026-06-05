# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T012239Z`

Base accepted HEAD: `9a81647b982b902735992b9b04cc479f9539d7f2`

## Behavior Added

- Extended `MathTexConverter` with bounded TeX above/below annotation handoff:
  `\overset`, `\underset`, `\overbrace`, and `\underbrace`.
- Added bounded TeX style wrappers for `\displaystyle`, `\textstyle`,
  `\scriptstyle`, and `\scriptscriptstyle`, emitted as MathML `mstyle`
  attributes around the next parsed math atom.
- Added `\infty` symbol handoff for common limit annotations.
- Added malformed guards for missing or empty above/below/style arguments.
- Updated `examples/wordpress-math-tex-handoff.php` so the WordPress review
  smoke preserves editable above/below TeX source and emits matching bounded
  MathML with source annotations.

## Source Truth

- Existing accepted inventory maps Pandoc Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`, `test/markdown-reader-more.txt`,
  and `test/markdown-reader-more.native`: Pandoc preserves inline/display math
  source as TeX strings in math nodes.
- Prior math slices already accepted fractions, scripts, roots, fences,
  operators, relation symbols, accents, macros, matrices, cases, and array
  column specs. This slice ports a separate bounded texmath-style handoff
  cluster for annotated formulas common in reviewer equations.
- This does not attempt full `texmath` parity, TeX rendering, MathJax, KaTeX,
  Pandoc execution, TeX/PDF engines, binomial commands, color/phantom/cancel
  commands, richer array column specs, or full MathML intent annotations.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 81 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 84 assertions, 2 failures`.
  - Failure reason: `\overset` / `\underset` emitted literal identifiers and
    malformed above/below/style commands were not rejected.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 94 assertions, 0 failures`.
  - Delta: `+2` focused PASS cases and `+13` focused assertions.
- Full focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5124 assertions, 0 failures`.
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

## Non-Overlap

- Does not repeat accepted Math/TeX fraction/root/script/text, source
  annotation, delimiter/fence, large-operator/function/operator-name,
  relation/set/logic/arrow, accent, macro-expansion, indexed-root,
  matrix/aligned environment, cases environment, or array column-spec
  conversion.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, or upstream-runner dependency closure.

## Follow-Up

Keep binomial commands, richer array column specs such as `p`/`m`/`b`,
optional macro arguments, color/phantom/cancel policy, richer MathML
intent/accessibility annotations, deeper TeX parsing, and full upstream runner
dependency planning as separate bounded slices.
