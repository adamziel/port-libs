# Math/TeX Comment Handoff

Micro-slice: `pandoc-math-tex-conversion-core-current-base-20260606T221539Z`

Accepted base: `e1f112b8ea648ea7e836cfb9bbd4f19dce3d5584`

## Source Truth

The upstream texmath TeX reader treats raw percent comments as ignorable parser input alongside whitespace, labels, tags, nonumber, and allowbreak handling before expression parsing. Source reference: `https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX.hs`.

## Implementation

`MathTexConverter::skipWhitespace()` now skips raw `%` comments through LF, CR, or CRLF. Because this helper is used by expression, group, script, and command-argument parsing, comments are omitted from rendered MathML in ordinary expressions and grouped fraction arguments.

The original TeX source remains preserved in `application/x-tex` annotations, so WordPress review packets keep reviewer comments without exposing comment text as rendered MathML identifiers or operators.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` passed with `1 test files, 568 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` failed with `1 test files, 570 assertions, 1 failures` because raw `%` comments and payload rendered into MathML.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` passed with `1 test files, 576 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test` passed with `math tex handoff self-test ok`.

No Pandoc, texmath, MathJax, KaTeX, TeX/PDF engine, Cabal build/test command, Haskell runner, external converter, online service, live provider test, or live-service provider test was executed.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP `MathTexConverter`, source-TeX MathML annotations, accessible MathML metadata, and the existing WordPress math TeX handoff example.

## Non-Overlap

This is distinct from the accepted alignedat, multline/multlined, array-width column, bangle, modular-command, spacing, delimiter, and allowbreak Math/TeX slices. It only covers bounded raw TeX comment skipping for native MathML handoff.

## Follow-Up

Potential follow-up work is richer comment handling in environment row scanners where comments interact with alignment separators or line breaks. That remains separate from this shared expression/group scanner patch.
