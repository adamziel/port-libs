# Pandoc Legacy DOC/CFB Current-Base Slice

Micro-slice: `pandoc-legacy-doc-cfb-core-current-base-20260608T200201Z`

Accepted base: `e4416a27234df3582c58620f35f477531567f5a3`

## Source Truth

- MS-DOC SttbfCaption table: https://learn.microsoft.com/fr-fr/openspecs/office_file_formats/ms-doc/1aa3c28c-0f14-459a-ae24-47b3c3f33081
- MS-DOC Fib table-stream offsets context: https://learn.microsoft.com/fr-fr/openspecs/office_file_formats/ms-doc/0c9df81f-98d0-454e-ad84-b612cd05b1a4

## Implementation

- Added bounded native parsing for template-only `SttbfCaption` and `SttbfAutoCaption` tables from the WordDocument FIB table-stream offsets.
- Preserved caption labels, CAPI insert-location, no-label policy, chapter-numbering metadata, number-format codes, chapter separators, and ProgID-to-caption AutoCaption rules as metadata-only review data.
- Kept caption definitions and AutoCaption ProgIDs out of rendered Markdown and WordPress blocks.
- Added malformed-table guards for wrong STTB markers, wrong extra-byte widths, oversized labels, invalid insert locations, invalid chapter heading levels, invalid separators, invalid AutoCaption extra widths, missing caption indexes, trailing bytes, and missing table streams.

## Evidence

- Baseline focused test before this patch: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` -> `1 test files, 1522 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` -> `1 test files, 1591 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test` -> `legacy doc handoff self-test ok`.
- PHP lint passed for `LegacyDocReader.php`, `LegacyDocReaderTest.php`, and `wordpress-legacy-doc-handoff.php`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1778` -> `1779`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2196` -> `2197`.
- Legacy DOC/CFB inventory: `7` -> `8` mapped cases.
- Legacy DOC/CFB focused assertions: `64` -> `133`.

## Dependency Closure

No new support component is needed. This slice reuses the lane-local native `CompoundFileBinary` and `LegacyDocReader` table-stream parsing path plus the existing WordPress legacy DOC handoff example. Word, LibreOffice, Pandoc, Cabal/Haskell runners, zip/unzip, external office tools, online services, live provider tests, and live-service provider tests were not executed.

## Non-Overlap

This does not repeat accepted CFB header/FAT/DIFAT/MiniFAT/directory preflight, OLE property metadata, DOP metadata, associated strings, SttbFnm external links, SttbfRMark revision authors, RouteSlip metadata, field/bookmark/comment/note handling, styles, lists, or piece-table extraction. The new behavior is limited to Normal-template caption definitions and AutoCaption rule metadata.

## Follow-Up

Next legacy DOC/CFB work should choose a distinct MS-DOC table or use-site gap, such as caption numbering use sites, table of authorities/captions indexes, or richer revision metadata, while keeping external office tools and Pandoc/Cabal runners out of scope.
