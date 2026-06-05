# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T002203Z`

Base accepted HEAD: `04601fccb73f368391c2e620cdcef5f5e2269b59`

## Behavior Added

- Extended `MathTexConverter` with bounded TeX `cases` environment handoff.
- Emits MathML as a left-braced `mtable` with `columnalign="left left"` for
  value and condition columns.
- Reuses the existing environment row/cell parser, so nested fractions,
  indexed roots, scripts, relation commands, and `\text{...}` cells stay
  deterministic without invoking texmath, Pandoc, MathJax, KaTeX, or a TeX
  engine.
- Added malformed guards for unclosed and empty `cases` environments.
- Updated `examples/wordpress-math-tex-handoff.php` so the WordPress review
  smoke preserves an editable piecewise TeX formula and emits matching bounded
  MathML with source annotations.

## Source Truth

- Existing accepted inventory maps Pandoc Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`, `test/markdown-reader-more.txt`,
  and `test/markdown-reader-more.native`: Pandoc preserves inline/display math
  source as TeX strings in math nodes.
- Prior math notes explicitly left `cases` / array-like environments as a
  follow-up after matrix/aligned and indexed-root support. This slice ports the
  bounded support-library contract for common piecewise math handoff only.
- This does not attempt full `texmath` parity, TeX rendering, MathJax, KaTeX,
  Pandoc execution, TeX/PDF engines, `array` column-spec parsing, or broader
  macro expansion.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 69 assertions, 0 failures`.
- Red check after adding the focused test:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: failed the new cases-environment case with
    `Unsupported TeX environment cases at offset 13`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 75 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+6` focused assertions.
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
  `php -r '$files = ["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"]; foreach ($files as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); fwrite(STDOUT, $file . " json ok\n"); }'`
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
  relation/set/logic/arrow, accent, macro-expansion, indexed-root, or
  matrix/aligned environment conversion.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, or upstream-runner dependency closure.

## Follow-Up

Keep binomial commands, `array` column-spec parsing, optional-argument macros,
richer MathML intent annotations, deeper TeX parsing, and full upstream runner
dependency planning as separate bounded slices.
