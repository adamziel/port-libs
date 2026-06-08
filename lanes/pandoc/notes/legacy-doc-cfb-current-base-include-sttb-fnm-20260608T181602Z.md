# Legacy DOC CFB Include/SttbFnm current-base slice

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260608T181602Z`
Base: `534fb2b945ebdc32c342cdd704645a51abaea864`

## Source truth

- Microsoft MS-DOC `SttbFnm` stores external filenames referenced by a binary Word document.
- MS-DOC `FNIF` appends `fnpi`, `ichRelative`, and `fnfb` metadata to each SttbFnm filename record.
- MS-DOC field codes such as INCLUDETEXT and INCLUDEPICTURE preserve displayed results separately from field instructions and external sources.

## Implementation

- `LegacyDocReader` now keeps the parsed SttbFnm records active while building visible paragraph AST nodes.
- INCLUDETEXT and INCLUDEPICTURE spans now receive review-only attributes when their field source matches a SttbFnm `path` or `relativePath`:
  - external reference index;
  - match kind (`path` or `relative-path`);
  - reference type;
  - document index;
  - file-system provenance;
  - extraction policy;
  - `can-expose-bytes=false`.
- File-path matching normalizes slash direction and case for legacy Word path strings; URL matching remains exact.
- The reader does not fetch, expand, or expose external file bytes.
- The WordPress legacy DOC smoke now includes a matching SttbFnm record for an INCLUDETEXT source and verifies the serialized relationship attributes.

## Evidence

- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  failed with `1 test files, 1464 assertions, 1 failures` because include-field spans lacked external-reference attributes.
- Final focused:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 1485 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  passed with `legacy doc handoff self-test ok`.

## Status delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1712 -> 1713`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2133 -> 2134`.
- `mappedLegacyDocCfbCoreCases`: `7 -> 8`.
- `legacyDocCfbCoreAssertions`: `64 -> 124`.

## Dependency closure

No new support component is needed. This reuses the native `CompoundFileBinary` reader, `LegacyDocReader` FIB/table-stream slicing and SttbFnm metadata, `MarkdownWriter`, and `WordPressBlockWriter`.

Out of scope: executing Pandoc, Word, LibreOffice, zip/unzip, external office tools, fetching external include sources, expanding included files, Cabal/Haskell runners, online services, live provider tests, and live-service provider tests.

## Non-overlap

This does not repeat accepted CFB header/FAT/DIFAT/MiniFAT/directory preflight, SummaryInformation, DOP, `SttbfAssoc`, `StwUser`, `SttbSavedBy`, `RouteSlip`, FIB text flags, CLX pieces, subdocuments, notes/comments/bookmarks, Plcfld field tables, field-result rendering, include-field source parsing, embedded OLE objects, picture placeholders, macro metadata, styles, formatting, lists, sections, or the prior SttbFnm table parser. It only relates already-parsed SttbFnm records to visible include-field review spans.

## Follow-up

Continue Legacy DOC/CFB with non-overlapping native MS-DOC metadata such as deeper master-document subdocument classification, mail-merge metadata, or additional PLC metadata. Keep external converters and source fetching out of this lane unless explicitly authorized.
