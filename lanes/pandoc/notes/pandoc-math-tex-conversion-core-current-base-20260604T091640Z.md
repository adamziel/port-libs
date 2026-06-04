# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260604T091640Z`

Base accepted HEAD: `15b7deab75f302d4a04af204fa015a039e92981e`

## Behavior Added

- Extended `MathTexConverter` with bounded TeX delimiter command support for
  `\langle`, `\rangle`, escaped braces, `\vert`, and `\Vert`.
- Added bounded `\left` / `\right` fence handling for single-character
  delimiters, escaped brace delimiters, and invisible `\left.` delimiters.
- Kept malformed fence tokens explicit: missing delimiters and unsupported
  fence delimiter commands throw `InvalidArgumentException` instead of emitting
  misleading MathML.
- Updated `examples/wordpress-math-tex-handoff.php` so the WordPress smoke
  emits inline MathML for the existing macro-expanded angle-bracket formula in
  addition to the prior display MathML handoff.

## Source Truth

- Existing accepted inventory maps Pandoc Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`, and
  `test/markdown-reader-more.txt`.
- The directly reused upstream-shaped case is the recorded
  `markdown-reader-more` macro expansion where
  `\newcommand{\tuple}[1]{\langle #1 \rangle}` later becomes math text
  `\langle x,y \rangle`.
- This ports a bounded format contract for MathML handoff. It does not attempt
  full `texmath` parity, matrix/alignment parsing, TeX rendering, MathJax,
  KaTeX, Pandoc execution, or a TeX/PDF engine.

## Verification

- `php -l lanes/pandoc/src/MathTexConverter.php` passed.
- `php -l lanes/pandoc/tests/MathTexConverterTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  passed: 1 file, 16 assertions, 0 failures, 5 PASS lines.
- `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  passed.
- `php tools/run-tests.php lanes/pandoc/tests` was attempted and failed outside
  this slice with 3 pre-existing archive-compression failures because
  `PortLibs\Pandoc\GzipStream` is referenced by `ZipPackageTest.php` but absent
  in this worktree.

## Dependency Closure

No new support component is needed for this math slice. It reuses the existing
native PHP `MathTexConverter`, `MarkdownReader`, `LatexWriter`, and
`WordPressBlockWriter` surfaces. Full upstream Pandoc runner parity remains a
separate Cabal/upstream-checkout blocker recorded in lane status, and the
current broader PHP lane blocker is the unrelated missing archive-compression
`GzipStream` class.

## Follow-Up

- Keep richer TeX parsing separate: matrices/alignment, accents, named
  functions, more relation/operator commands, and MathML accessibility
  metadata.
- Keep DOCX OMML math extraction as a separate DOCX/OpenXML gate.
