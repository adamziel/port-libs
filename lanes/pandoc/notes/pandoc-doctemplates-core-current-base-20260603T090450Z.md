# Pandoc doctemplates core current-base 2026-06-03T09:04:50Z

## Slice

- Extended `PortLibs\Pandoc\DocTemplate` with bounded parameter-free Pandoc
  doctemplate pipes.
- Added slash-delimited pipe parsing and chaining for variables used in
  ordinary interpolation, `if(...)` tests, and `for(...)` loop expressions.
- Implemented native PHP support for `pairs`, `uppercase`, `lowercase`,
  `length`, `reverse`, `first`, `last`, `rest`, `allbutlast`, `chomp`, and
  `nowrap`.
- Kept parameterized pipes such as `alpha`, `roman`, `left`, `right`, and
  `center`, plus partial inclusion, outside this slice.
- Updated the WordPress review-packet example so uppercase packet labels,
  warning counts, and indexed warning rows are rendered through the same
  doctemplate pipe path.

## Source Truth

- Pandoc User's Guide `Template syntax`, `Pipes`: pipes are slash-delimited,
  may be chained, and predefined pipes include `pairs`, case conversion,
  `length`, array/text reversal, first/last/rest/allbutlast array selection,
  `chomp`, `nowrap`, and parameterized formatting/enumeration pipes.
- The local upstream Pandoc cache path recorded in the manifest was unavailable
  in this isolated worker, so no Haskell source checkout or runner was used.
- No Pandoc binary, Haskell test binary, Cabal build, external template engine,
  online service, Word, LibreOffice, zip/unzip, or TeX/PDF engine was executed.

## Evidence

- Red-first check before implementation:
  `php -r 'require "tools/bootstrap.php"; $renderer = new PortLibs\Pandoc\DocTemplate(); echo $renderer->render("\$title/uppercase\$", ["title" => "review"]);'`
  failed with `Unsupported doctemplate directive title/uppercase`.
- `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed:
  1 test file, 16 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed:
  5 test files, 2,575 assertions, 0 failures, 276 PASS lines.
- `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed.
- Syntax and whitespace checks are recorded in the final worker handoff.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters, variables,
conditionals, loops, separators, `$it$`, `$^$`, or automatic multiline nesting.
It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
Markdown/HTML readers, Markdown/WordPress writers, DOCX document-part parsing,
or upstream-runner dependency audit behavior.

## Dependency Closure

No new external support component is needed. This reuses the active
`pandoc-doctemplates-core` support row and implements a bounded native PHP
template-pipe subset inside the lane-local renderer. Remaining doctemplate
support gaps are partial inclusion with recursion/depth guards and
parameterized pipes; they should be separate activation slices with focused
tests. Full upstream Pandoc runner parity remains out of scope for this
isolated micro-slice.

Root harness: not run - isolated micro-slice.
