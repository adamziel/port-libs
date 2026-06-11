# Pandoc JSON/native table helper payloads

2026-06-11 current-base slice for `plib-t9xkl`, rebased on
`origin/main` `caf9a25cb`.

Pandoc JSON/native AST ingestion now preserves raw table helper payloads for
column alignment, column width, row-head columns, row span, and column span:

- table attrs: `alignmentNatives`, `columnWidthNatives`
- body attrs: `rowHeadColumnsNative`
- cell attrs: `alignmentNative`, `rowSpanNative`, `colSpanNative`

`PandocJsonWriter` and `NativeWriter` reuse those payloads only while the
normalized AST values still match. If an editor changes alignment, width,
row-head columns, row span, or column span, the writers regenerate canonical
Pandoc helpers instead of leaking stale native provenance.

`PandocJsonWriter` also normalizes legacy raw JSON helper payloads for
column widths and integer helpers back to tagged constructors, while keeping
tagged helper payloads intact until a semantic edit requires regeneration.

Direct-format parity remains native PHP only. Verification used focused
JSON/native AST fixtures and the full `lanes/pandoc/tests` gate, without
invoking Pandoc, JSON filters, Cabal/Haskell runners, office suites, TeX/PDF
engines, browser renderers, zip/unzip, Jupyter, Node, external validators,
online services, live provider tests, or live-service provider tests.

Verification:

- `php -l` passed for `PandocJsonReader.php`, `NativeReader.php`,
  `PandocJsonWriter.php`, `NativeWriter.php`, and
  `PandocJsonNativeAstTest.php`.
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  passed: 1 test file, 1068 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 65487
  assertions, 0 failures.
