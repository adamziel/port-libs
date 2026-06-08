# pandoc-legacy-doc-cfb-core-current-base-20260608T092810Z

## Scope

Lane: pandoc
Slice: pandoc-legacy-doc-cfb-core-current-base-20260608T092810Z
Base: 16e3cf329a6d9812e5d3d6b3fcfc7c4538e6287d

Implemented one bounded legacy DOC/CFB support-library cluster: MS-DOC DOP
DopBase Copts60 compatibility-option bit handoff. The native reader now keeps
the raw `compatibilityOptions` word and also exposes stable
`compatibilityOptionFlags` names plus a top-level
`documentCompatibilityOptionFlags` metadata copy for review packets. These
policy names remain metadata-only and are not rendered into WordPress blocks.

Source truth:

- MS-DOC Dop: https://learn.microsoft.com/en-us/openspecs/office_file_formats/MS-DOC/841b5f72-487e-4fe7-8657-ec90d5af8750
- MS-DOC DopBase/Copts60: https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/f885b87a-15b3-460e-aecc-213bd17f960e

## Evidence

No `port-pandoc-*.needs-lane-rework.md` note existed for this slice.

Baseline focused run:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: 1 test files, 1227 assertions, 0 failures.

Red-first focused run after adding the Copts60 expectations and before the
implementation:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: 1 test files, 1212 assertions, 2 failures. The failures were the
missing `compatibilityOptionFlags` metadata.

Final focused run:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: 1 test files, 1237 assertions, 0 failures.

Example smoke:

`php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`

Result: legacy doc handoff self-test ok.

## Mapping Delta

- `phpPass`: 1596 -> 1597
- `benchmarkDenominator.mapped`: 2015 -> 2016
- `legacyDocCfbCoreCases`: 7 -> 8
- `mappedLegacyDocCfbCoreCases`: 7 -> 8
- `legacyDocCfbCoreAssertions`: 64 -> 74
- Focused assertions: +10 in LegacyDocReaderTest.php.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
CompoundFileBinary parsing, selected table-stream slicing, LegacyDocReader DOP
metadata parsing, AstNode attributes, WordPressBlockWriter, the existing
legacy DOC WordPress handoff example, and the focused lane PHP harness.

No Pandoc, Cabal, Haskell runner, Word, LibreOffice, zip/unzip, external
office tool, online service, live provider test, or live-service provider test
was executed.

## Non-Overlap

This avoids accepted legacy DOC/CFB clusters for CFB header/FAT/DIFAT/MiniFAT
preflight, FIB/CLX and piece-table text extraction, existing DOP policy flags,
DTTM timestamps, statistics, view metadata, document variables, fields, notes,
bookmarks, OLE/macro metadata, pictures, and INCLUDE/ASK/FILLIN field-result
handoffs. The patch is limited to Copts60 compatibility-option names inside
the already-parsed DOP/DopBase metadata.

## Follow-Up

Keep follow-up work bounded to non-overlapping native MS-DOC support surfaces
such as additional DOP policy structures, stylesheet/name-table metadata,
list/table structure handoff, or safe CFB stream validation. Full upstream
Pandoc runner parity remains out of this slice because external
Pandoc/Haskell/office runners were not authorized or needed for this bounded
support-library case.
