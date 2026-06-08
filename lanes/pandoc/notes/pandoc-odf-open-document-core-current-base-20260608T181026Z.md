# Pandoc ODF OpenDocument Core Current Base

Slice: `pandoc-odf-open-document-core-current-base-20260608T181026Z`

Base accepted HEAD: `088647638f8d8cae6935e8550e20545d341fc5dc`

## Summary

Implemented bounded native ODF `table:tracked-changes` metadata parsing in
`OdfReader`. The reader now preserves `table:tracked-change` ids,
acceptance/rejection provenance, `office:change-info` creator/date/comments,
table action element names and attributes, and nested `table:previous` cell
snapshots as document `contentDeclarations` and import-report metadata.

This slice is metadata-only: it does not apply spreadsheet/table edits or
attempt accept/reject resolution.

## Source Truth

No hydrated local Pandoc upstream checkout was available for ODF fixture reads
in this worker. The implementation follows the accepted lane ODF reader
contract and OpenDocument XML vocabulary already used by adjacent ODF content
declaration slices.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, online service, live provider test, or
live-service provider test was executed.

## Verification

- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` -> `1 test files, 2070 assertions, 0 failures`
- Red-first focused test after adding the table tracked-change expectation: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` -> `1 test files, 2072 assertions, 1 failures` because `tableTrackedChangeCount` was absent
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` -> `1 test files, 2101 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test` -> `odf open document handoff self-test ok`
- Syntax: `php -l lanes/pandoc/src/OdfReader.php` -> no syntax errors
- Syntax: `php -l lanes/pandoc/tests/OdfReaderTest.php` -> no syntax errors
- Syntax: `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php` -> no syntax errors
- JSON validation: `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'` -> `json ok`
- Diff whitespace: `git diff --check -- lanes/pandoc` -> clean

Root harness: not run - isolated micro-slice.

## Counters

- `lane-status.json` `phpPass`: `1712 -> 1713`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2133 -> 2134`
- ODF/OpenDocument mapped core cases: `13 -> 14`
- ODF/OpenDocument focused assertions: `295 -> 326`

## Dependency Closure

No new native PHP support component is needed. This reuses existing
`OdfReader` content XML parsing, namespace helpers, document
`contentDeclarations`, `MarkdownWriter`, and `WordPressBlockWriter`.

## Non-Overlap And Follow-Up

This avoids the recently mapped ODF database range subtotal-rule, data-pilot,
named-range, typed field, hidden paragraph, and drop-down field slices. A
useful follow-up would be table tracked-change accept/reject application
policy, covered-cell provenance, richer table style semantics, or additional
spreadsheet metadata, still without invoking external office tools.
