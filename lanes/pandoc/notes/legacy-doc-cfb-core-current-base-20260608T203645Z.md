# Legacy DOC/CFB Reserved Hyperlink Metadata

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260608T203645Z`
Base: `e76c4cc82ad1172514b0791041ad64c954f9e499`

## Source Truth

- Microsoft MS-OSHARED Document Summary/User Defined property-set records:
  - `_PID_LINKBASE`: https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-oshared/c0e0190a-0828-4638-ab74-6fc190da3582
  - `_PID_HLINKS`: https://learn.microsoft.com/es-mx/openspecs/office_file_formats/ms-oshared/4df4d1a8-bb47-46bd-acd7-944e817338e7
  - `VtHyperlinks`: https://learn.microsoft.com/es-mx/openspecs/office_file_formats/ms-oshared/f65d9521-5c8d-4888-a23e-cc1b9f9d7da5
  - `VtHyperlink`: https://learn.microsoft.com/es-mx/openspecs/office_file_formats/ms-oshared/754880cc-03d4-48e7-94fc-d9f6573f11af
  - `VtString`: https://learn.microsoft.com/es-mx/openspecs/office_file_formats/ms-oshared/2225d544-e063-44ff-9f70-739f1c14d592

## Change

- `LegacyDocReader` now parses user-defined DocumentSummaryInformation reserved `_PID_LINKBASE` and `_PID_HLINKS` values when they are encoded as bounded `VT_BLOB` properties.
- `_PID_LINKBASE` is decoded as a null-terminated UTF-16LE hyperlink base and exposed only as document metadata.
- `_PID_HLINKS` is decoded as bounded `VtHyperlinks` records with target, location, target kind, hash/app/shape/info fields, fixup status, and metadata-only/no-byte-exposure policy.
- Malformed link bases, non-`VT_BLOB` reserved values, invalid VtHyperlinks element counts, truncated VtHyperlink records, and trailing reserved hyperlink bytes now throw before metadata exposure.
- The WordPress legacy DOC handoff example now carries reserved hyperlink metadata and verifies the reserved base/targets do not render into block output.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 1625 assertions, 0 failures`
- Red-first after adding the reserved hyperlink fixture:
  - Result: failed as expected at missing `hyperlinkBase`, `1629 assertions, 1 failures`
- Final focused run: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 1661 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - Result: `legacy doc handoff self-test ok`
- PHP lint:
  - `php -l lanes/pandoc/src/LegacyDocReader.php`: no syntax errors
  - `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`: no syntax errors
  - `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`: no syntax errors
- JSON validation: `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` decoded with `JSON_THROW_ON_ERROR`
- Whitespace check: `git diff --check -- lanes/pandoc` passed with no output

## Dependency Closure

No new support component is needed. This reuses the existing native CFB reader, OLE property-set parser, `LegacyDocReader` metadata handoff, and `WordPressBlockWriter` smoke path. No Pandoc, Word, LibreOffice, Cabal solver/build/test command, Haskell runner, zip/unzip, external office tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat accepted legacy DOC/CFB work for MiniFAT cutoff, surplus DIFAT, directory start-sector guards, FibRgLw97 text ranges, Plcfld field metadata, ASK/FILLIN prompt fields, SttbfCaption/AutoCaption, RouteSlip, custom property dictionaries, or field-code hyperlink rendering. It is limited to reserved OLE user-defined hyperlink property metadata.
