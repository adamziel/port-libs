# Legacy DOC/CFB MERGEFIELD Mail-Merge Source Handoff

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260608T225350Z`
Base accepted HEAD: `79f9f98965689b71a99ad50e1ab3f41478685bb2`

## Source Truth

- MS-DOC `SttbfAssoc` index `0x08` carries the mail-merge data source path and index `0x09` carries the mail-merge header document path.
- MS-DOC `SttbFnm` / `FNIF` records classify external filenames; `FNPI.fnpt = 3` is a mail-merge data-source filename.
- MS-DOC field type `MERGEFIELD` stores a displayed field result separately from the field instruction and external data source.

## Implementation

- `LegacyDocReader` now keeps parsed `SttbfAssoc` records active while building legacy field-result spans.
- `MERGEFIELD` spans now carry inert review metadata linking to the associated mail-merge data-source/header records by table name and index.
- When a matching `SttbFnm` mail-merge data-source record exists, `MERGEFIELD` spans also carry its source table, index, reference type, document index, file-system class, and `canExposeBytes=false`.
- The actual mail-merge source/header filenames remain in document metadata and are not copied into WordPress block attributes.
- The WordPress legacy DOC handoff smoke now asserts the new `MERGEFIELD` source linkage.

## Evidence

- Baseline focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 1753 assertions, 0 failures`
- Red-first after adding the fixture/assertions:
  - `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 1739 assertions, 1 failures`
  - Failure: missing `data-legacy-doc-mail-merge-policy` on the `MERGEFIELD` span.
- Final focused reader test:
  - `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 1772 assertions, 0 failures`
  - Delta: `+19` focused assertions.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - Result: `legacy doc handoff self-test ok`
- PHP lint:
  - `php -l lanes/pandoc/src/LegacyDocReader.php`
  - `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`
  - Result: no syntax errors.
- JSON validation:
  - `lanes/pandoc/lane-status.json`: valid
  - `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: valid
- Whitespace check:
  - `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

## Status Delta

- `lane-status.json` `phpPass`: `1946 -> 1947`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2367 -> 2368`.
- `legacyDocCfbCoreCases`: `7 -> 8`.
- `mappedLegacyDocCfbCoreCases`: `7 -> 8`.
- `legacyDocCfbCoreAssertions`: `64 -> 83`.

## Dependency Closure

No new native PHP support component is needed. This slice reuses the native `CompoundFileBinary` reader, `LegacyDocReader` `SttbfAssoc` / `SttbFnm` parsers, field-result span handoff, `MarkdownWriter`, and `WordPressBlockWriter`.

No Pandoc, Word, LibreOffice, zip/unzip, Cabal solver/build/test command, Haskell runner, external office tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted CFB allocation/preflight work, DOP policy flags, standalone `SttbfAssoc` fallback metadata, standalone `SttbFnm` external filename parsing, include-field-to-SttbFnm linking, RouteSlip, captions, reserved hyperlink metadata, Plcfld story tables, ASK/FILLIN prompts, or field-code hyperlink rendering. It only links visible `MERGEFIELD` result spans to already parsed mail-merge source records without exposing external source bytes.

## Follow-Up

Potential next legacy DOC/CFB gaps: bounded mail-merge settings beyond field/source linkage, master-document subdocument metadata, or another CFB/DOC allocation invariant not already covered by MiniFAT, DIFAT, FAT, and directory preflight slices.
