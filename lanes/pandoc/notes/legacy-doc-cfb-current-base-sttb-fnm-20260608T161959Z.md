# Legacy DOC CFB SttbFnm current-base slice

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260608T161959Z`
Base: `bc9489e331853d7b5b38ea37ea420a29310b5ae4`

## Source truth

- Microsoft MS-DOC `SttbFnm` describes an STTB of external filenames referenced by a Word binary document:
  `https://learn.microsoft.com/fr-ch/openspecs/office_file_formats/ms-doc/996b1475-4a09-4893-be9c-13a71b1f3935`
- MS-DOC `FNIF` defines the 8-byte extra record appended to each filename, including `fnpi`, `ichRelative`, `fnfb`, and ignored `unused` bytes:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/17f5604f-c6ea-4cb6-b3c7-e06ccda51d64`
- MS-DOC `FNPI` maps `fnpt=3` to mail-merge data-source filenames and `fnpt=5` to subdocument filenames, with `fnpd != 0xFFF`:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/3bac1319-0f5c-4734-8a79-229c63bd6109`
- MS-DOC `FNFB` records FAT, NTFS, and non-file-system path validity bits; native file-system bits must be clear when `fNonFileSys` is set:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/4a6bb77f-a245-46dd-b163-850fcadf74cf`

## Implementation

- Added bounded `FibRgFcLcb97` `fcSttbFnm`/`lcbSttbFnm` handling in `LegacyDocReader`.
- Parses extended `SttbFnm` strings with `cbExtra=8` `FNIF` records.
- Exposes `externalFileReferences` in result metadata, document attributes, and `meta` as review-only data.
- Preserves filename, basename, `fnpi`, `referenceTypeCode`, `referenceType`, `documentIndex`, `ichRelative`, optional `relativePath`, `fnfb`, file-system flags, and `metadata-only-native-review` policy.
- Rejects truncated tables, non-extended tables, wrong `cbExtra`, empty filenames, invalid FNPI type, invalid `fnpd=0xFFF`, invalid relative offsets, missing table stream, non-file-system/native file-system flag conflicts, and trailing bytes before metadata exposure.
- Keeps `FNIF.unused` ignored rather than exposed.

## Evidence

- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  failed with `1 test files, 1376 assertions, 1 failures` because `externalFileReferences` was absent.
- Final focused:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 1425 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  passed with `legacy doc handoff self-test ok`.

## Status delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1695 -> 1696`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2115 -> 2116`.
- `mappedLegacyDocCfbCoreCases`: `7 -> 8`.
- `legacyDocCfbCoreAssertions`: `64 -> 113`.

## Dependency closure

No new support component is needed. The slice reuses the existing native `CompoundFileBinary` stream reader, `LegacyDocReader` FIB/table-stream slicing and UTF-16LE decoding, `MarkdownWriter`, and `WordPressBlockWriter`.

Out of scope: executing Pandoc, Word, LibreOffice, zip/unzip, external office tools, external filename fetching/expansion, Cabal/Haskell runners, online services, live provider tests, and live-service provider tests.

## Non-overlap

This does not touch already accepted legacy DOC/CFB clusters for CFB header/FAT/DIFAT/MiniFAT/directory preflight, SummaryInformation, DOP, `SttbfAssoc`, `StwUser`, `SttbSavedBy`, `RouteSlip`, FIB text flags, CLX pieces, subdocuments, notes/comments/bookmarks, field tables/results, embedded OLE objects, picture placeholders, macro metadata, styles, formatting, lists, or sections.

## Follow-up

- Classify additional structures that reference `FNPI` values when needed, such as deeper mail-merge or master-document metadata.
- Optionally relate SttbFnm records to include-field source metadata without fetching or expanding external files.
