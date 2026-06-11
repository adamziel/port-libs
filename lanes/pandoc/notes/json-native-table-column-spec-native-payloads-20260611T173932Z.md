# JSON/native table column spec native payloads

Slice: plib-csokl, 2026-06-11.

Pandoc JSON/native AST ingestion now preserves table column spec helper payloads alongside normalized values:

- `alignmentNativePayloads` keeps the original alignment constructor payload per column.
- `columnWidthNativePayloads` keeps the original column-width constructor payload per column, including list-wrapped `ColWidth` content.
- JSON and native writers reuse matching payloads for round trips and regenerate mismatched payloads from edited AST `alignments`/`widths`.

Verification:

- `php -l` passed for `PandocJsonReader.php`, `PandocJsonWriter.php`, `NativeReader.php`, `NativeWriter.php`, and `PandocJsonNativeAstTest.php`.
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`: 1 test file, 883 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 64363 assertions, 0 failures.

No Pandoc binary, JSON filter runner, Cabal/Haskell tooling, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.
