# Legacy DOC/CFB SttbfRMark Revision Authors

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260608T191031Z`
Base accepted HEAD: `40e4afa74effef117e3761e0e7b8018882962824`

## Source Truth

- Microsoft MS-DOC `FibRgFcLcb97` defines `fcSttbfRMark` / `lcbSttbfRMark` as the table-stream offset and byte length for an `SttbfRMark` author table.
- Microsoft MS-DOC `SttbfRMark` defines an extended STTB with no extra data, revision/comment/e-mail author names, and a required first `Unknown` entry.
- Source references used:
  - https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/fca5d9de-3eaa-4587-a482-c454e340c070
  - https://learn.microsoft.com/fr-fr/openspecs/office_file_formats/ms-doc/0c9df81f-98d0-454e-ad84-b612cd05b1a4

## Implementation

- Added bounded native `LegacyDocReader` parsing for `SttbfRMark` using `FibRgFcLcb97` offsets `0x0232` / `0x0236`.
- The parser validates the extended STTB marker, zero `cbExtra`, non-empty bounded UTF-16LE strings, the required `Unknown` sentinel, and trailing-byte exhaustion.
- The reader exposes `revisionAuthors` on the result, document attributes, and metadata with `revisionAuthorPolicy: metadata-only-native-review`.
- The WordPress legacy DOC handoff example now includes a `SttbfRMark` table and self-test assertions that reviewer names stay metadata-only.

## Evidence

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 1485 assertions, 0 failures`
- Final focused reader test:
  - `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 1519 assertions, 0 failures`
  - Delta: `+34` focused assertions.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - Result: `legacy doc handoff self-test ok`

## Status Delta

- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2156 -> 2157`.
- `legacyDocCfbCoreCases`: `7 -> 8`.
- `mappedLegacyDocCfbCoreCases`: `7 -> 8`.
- `legacyDocCfbCoreAssertions`: `64 -> 98`.
- `lane-status.json` `phpPass`: `1735 -> 1736`.

## Dependency Closure

No new native PHP support component is needed. This slice reuses `CompoundFileBinary`, `LegacyDocReader` table-stream slicing, `MarkdownWriter`, and `WordPressBlockWriter`.

No Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external office tools, online services, live provider tests, or live-service provider tests were run.

## Non-Overlap

This does not repeat the current-base legacy DOC/CFB include-field or `SttbFnm` external-file slices. It owns only `SttbfRMark` revision-author table metadata and the corresponding WordPress handoff smoke coverage.

## Follow-Up

Potential next legacy DOC/CFB gaps: property revision-mark linkage into `SttbfRMark`, `SttbfCaption` / `SttbfAutoCaption` metadata, or another bounded CFB/DOC preflight behavior.
