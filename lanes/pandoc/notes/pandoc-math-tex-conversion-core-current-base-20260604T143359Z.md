# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260604T143359Z`

Base accepted HEAD: `9ee581b4aae1eb0ef589e2935c98eaa3d0e8fc4e`

## Behavior Added

- Extended `MathTexConverter` with bounded TeX accent MathML handoff for
  `\hat`, `\widehat`, `\bar`, `\overline`, `\dot`, `\ddot`, `\tilde`, and
  `\vec`.
- Added bounded `\underline` handoff through MathML `munder`.
- Reused the existing atom/group parser for accent targets, so braced groups,
  single-token targets, `\operatorname{...}`, and scripts following an accent
  remain deterministic without invoking a TeX engine.
- Added malformed guards for missing accent targets such as `\hat`, `\vec_1`,
  and `\underline^2`.
- Updated `examples/wordpress-math-tex-handoff.php` so the WordPress smoke
  preserves an accented quality/vector review formula as source TeX and emits
  matching bounded MathML.

## Source Truth

- Existing accepted inventory maps Pandoc Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`, and
  `test/markdown-reader-more.txt`: Pandoc's Markdown reader keeps TeX source in
  inline/display math nodes while this PHP support component owns bounded
  MathML handoff for those math-node strings.
- Prior math notes explicitly left TeX accents as a follow-up. This slice ports
  that bounded format contract only; it does not attempt full `texmath` parity,
  matrices/alignment, TeX rendering, MathJax, KaTeX, Pandoc execution, or a
  TeX/PDF engine.

## Verification

- `php -l lanes/pandoc/src/MathTexConverter.php` passed.
- `php -l lanes/pandoc/tests/MathTexConverterTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  passed: 1 file, 33 assertions, 0 failures, 7 PASS lines.
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

- Does not repeat the accepted Math/TeX fraction/root/script/text,
  delimiter/fence, or large-operator/function/operator-name slices.
- Does not touch DOCX OMML, ODT embedded formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, or legacy DOC/CFB.

## Follow-Up

- Keep TeX matrices/alignment, additional relation commands, accessibility
  annotations, and DOCX OMML extraction as separate bounded slices.
