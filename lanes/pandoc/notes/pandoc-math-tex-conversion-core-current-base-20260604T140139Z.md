# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260604T140139Z`

Base accepted HEAD: `49f0361dc343ebf21068c5753c26dbe6339d9a8e`

## Behavior Added

- Extended `MathTexConverter` with bounded TeX large-operator MathML handoff
  for `\sum`, `\prod`, and `\int`, reusing the existing subscript/superscript
  parser for limits.
- Added bounded named-function commands for `\sin`, `\cos`, `\tan`, `\log`,
  and `\exp`.
- Added `\operatorname{...}` support for literal operator-name handoff, plus
  explicit malformed guards for missing and empty operator names.
- Updated `examples/wordpress-math-tex-handoff.php` so the WordPress smoke
  preserves a scripted migration formula as source TeX and emits matching
  bounded MathML.

## Source Truth

- Existing accepted inventory maps Pandoc Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`, and
  `test/markdown-reader-more.txt`: Pandoc's Markdown reader emits TeX source as
  math nodes for inline/display math, while the PHP converter owns bounded
  MathML handoff for those math-node strings.
- This slice ports the bounded support-library contract for common TeX
  operators and function names used in imported formulas. It does not attempt
  full `texmath` parity, matrix/alignment parsing, accent parsing, TeX
  rendering, MathJax, KaTeX, Pandoc execution, or a TeX/PDF engine.

## Verification

- `php -l lanes/pandoc/src/MathTexConverter.php` passed.
- `php -l lanes/pandoc/tests/MathTexConverterTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  passed: 1 file, 24 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  passed.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); fwrite(STDOUT, $file . " json ok\n"); }'`
  passed for both changed JSON files.
- `git diff --check -- lanes/pandoc` passed.
- Root harness not run: isolated micro-slice.

## Dependency Closure

No new support component is needed for this math slice. It reuses the existing
native PHP `MathTexConverter`, `MarkdownReader`, `LatexWriter`, and
`WordPressBlockWriter` surfaces. Full upstream Pandoc runner parity remains a
separate Cabal/upstream-checkout blocker recorded in lane status.

## Non-Overlap

- Does not repeat the accepted Math/TeX fraction/root/script/text and
  delimiter/fence slices.
- Does not touch DOCX OMML, ODT embedded formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, or legacy DOC/CFB.

## Follow-Up

- Keep TeX accents, matrices/alignment, additional relation symbols,
  accessibility annotations, and DOCX OMML extraction as separate bounded
  slices.
