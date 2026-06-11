# Pandoc JSON/native table helper payloads

2026-06-11 current-base slice for `plib-t9xkl` on main `6673f4a17`.

Pandoc JSON/native AST ingestion already records raw table helper payloads for
column alignment, column width, row-head columns, row span, and column span:

- table attrs: `alignmentNatives`, `columnWidthNatives`
- body attrs: `rowHeadColumnsNative`
- cell attrs: `alignmentNative`, `rowSpanNative`, `colSpanNative`

`PandocJsonWriter` and `NativeWriter` now reuse those helper payloads only when
the matching normalized AST values still match. If an editor changes alignment,
width, row-head columns, row span, or column span, the writers regenerate
canonical Pandoc helpers instead of leaking stale native provenance.

Focused verification:

- `php -l` for `PandocJsonReader.php`, `NativeReader.php`,
  `PandocJsonWriter.php`, `NativeWriter.php`, and
  `PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  passed 1 test file, 1068 assertions, 0 failures
- full `php tools/run-tests.php lanes/pandoc/tests` passed 44 test files,
  65772 assertions, 0 failures

Direct-format parity remains native PHP only. The test path does not invoke
Pandoc, JSON filters, Cabal/Haskell runners, office suites, TeX/PDF engines,
browser renderers, zip/unzip, Jupyter, Node, external validators, online
services, live provider tests, or live-service provider tests.
