# pandoc-odf-open-document-core-current-base-20260607T113903Z

Base accepted HEAD: `1d32bf7438c6252c48b840d4e31b05d4350e0698`

## Behavior

This slice adds bounded ODF/OpenDocument generated-index entry-template component metadata handoff in native PHP. `OdfReader` now preserves component-level metadata for table-of-content and generated-index templates, including:

- `text:name`, `text:display`, `text:outline-level`, `text:chapter-format`
- `text:bibliography-data-field`
- `style:leader-text`
- `xlink:href`, `xlink:type`, `xlink:show`, and `xlink:actuate`

The metadata stays on the existing review-packet source arrays for table of contents and generated indexes, so Markdown and WordPress handoff can keep rendering the same visible blocks while import review still has the layout/link/chapter/bibliography provenance.

## Evidence

- Baseline before this slice: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 1464 assertions, 0 failures`
- Focused ODF test after implementation: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 1500 assertions, 0 failures`
- Coupled ODF/ODT focused check: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php`
  - `2 test files, 1595 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`
- Syntax and JSON checks:
  - `php -l lanes/pandoc/src/OdfReader.php`
  - `php -l lanes/pandoc/tests/OdfReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`

## Delta

- `phpPass`: `1493` -> `1494`
- `benchmarkDenominator.mapped`: `1913` -> `1914`
- `odfOpenDocumentCoreCases`: `11` -> `12`
- `mappedOdfOpenDocumentCoreCases`: `11` -> `12`
- `odfOpenDocumentCoreAssertions`: `251` -> `287`
- Focused ODF test growth: `+1` PASS case and `+36` assertions

## Dependency Closure

No new support component is needed. The slice reuses the existing native `OdfReader`, `ZipPackage` fixture builder, `AstNode` metadata handoff, `MarkdownWriter`, and `WordPressBlockWriter`. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external converter, office tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat the accepted ODF database-field, chart metadata, heading source-id, table caption, conditional/hidden text field, tab normalization, or generated-index body handoff work. It is limited to source-template component metadata for ODF table-of-content and generated-index review packets.

## Follow-Up

Useful next ODF work should stay on non-overlapping native reader gaps such as conditional sections/hidden paragraphs, database-range policy metadata, remaining generated-index layout policy metadata, or export-side ODT writing with focused PHP fixtures.
