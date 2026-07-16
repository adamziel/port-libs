# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260604T161804Z`

Base accepted HEAD: `ddd839325438faa16acb14b1f9671e87bc871631`

## Behavior Added

- Extended `MathTexConverter` with bounded native MathML handoff for TeX
  `matrix`, `pmatrix`, `bmatrix`, and `aligned` environments.
- Added top-level TeX alignment splitting for `&` cells and `\\` rows while
  preserving nested cell expressions such as `\frac`, `\sqrt`, scripts,
  Greek symbols, and `\operatorname`.
- Emits MathML `mtable` / `mtr` / `mtd` rows, fenced MathML for parenthesized
  and bracketed matrices, and `columnalign="right left"` for aligned equations.
- Added deterministic malformed-environment guards for unsupported
  environments, unclosed environments, unclosed groups inside cells, empty
  environments, and stray `\end{...}` commands.
- Updated `examples/wordpress-math-tex-handoff.php` so the WordPress review
  smoke preserves matrix/aligned formula source and emits matching bounded
  MathML without MathJax, KaTeX, or TeX engines.

## Source Truth

- Existing accepted inventory maps Pandoc Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`, `test/markdown-reader-more.txt`,
  and `test/markdown-reader-more.native`: Pandoc preserves inline/display math
  source as TeX strings in math nodes.
- This slice ports the bounded support-library contract for converting those
  math-node strings into deterministic handoff MathML. It does not attempt full
  `texmath` parity, nested environment parity, TeX rendering, MathJax, KaTeX,
  Pandoc execution, or a TeX/PDF engine.

## Verification

- `php -l lanes/pandoc/src/MathTexConverter.php` passed.
- `php -l lanes/pandoc/tests/MathTexConverterTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  passed: 1 file, 44 assertions, 0 failures, 9 PASS lines.
- `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  passed.
- JSON validation for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
- `git diff --check -- lanes/pandoc` passed.
- Root harness not run: isolated micro-slice.

## Dependency Closure

No new support component is needed for this math slice. It reuses the existing
native PHP `MathTexConverter`, `MarkdownReader`, `LatexWriter`, and
`WordPressBlockWriter` surfaces. Full upstream Pandoc runner parity remains a
separate Cabal/upstream-checkout blocker recorded in lane status.

## Non-Overlap

- Does not repeat the accepted Math/TeX fraction/root/script/text,
  delimiter/fence, large-operator/function/operator-name, accent, vector, or
  underline slices.
- Does not touch DOCX OMML, ODT embedded formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, or legacy DOC/CFB.

## Follow-Up

- Keep additional relation commands, accessibility annotations, nested matrix
  environments, and DOCX OMML extraction as separate bounded slices.
