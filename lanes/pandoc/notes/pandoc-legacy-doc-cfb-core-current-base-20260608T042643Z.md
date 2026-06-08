# Pandoc Legacy DOC CFB Current-Base Thumbnail Clipboard Slice

## Behavior Added

- Added bounded native parsing for OLE property-set `VT_CF` clipboard values.
- Mapped SummaryInformation property `PIDSI_THUMBNAIL` into metadata-only thumbnail review data: clipboard tag, format id/name, byte count, SHA-256, extraction policy, and `canExposeBytes=false`.
- The focused fixture and WordPress smoke use the standard Windows `CF_DIB` clipboard format id (`8`) for the thumbnail payload.
- Kept thumbnail payload bytes out of the Pandoc-like AST, flattened metadata, JSON metadata output, and WordPress block output.

## Source Truth

- Microsoft MS-OLEPS PropertyType and typed property-value behavior:
  `https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-oleps/2a4589eb-9a23-4a8b-adbd-3e368233c099`
- Microsoft SummaryInformation property-set behavior:
  `https://learn.microsoft.com/en-us/windows/win32/stg/the-summary-information-property-set`

## Verification

- Rework note check: no `port-pandoc-*.needs-lane-rework.md` file existed for this lane before editing.
- Red-first check: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` failed with `1 test files, 1104 assertions, 1 failures` because SummaryInformation thumbnail metadata was absent.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` passed with `1 test files, 1118 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-thumbnail-handoff.php --self-test` passed.
- PHP lint and diff whitespace checks passed for the changed lane files.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native `CompoundFileBinary` parser, bounded OLE property-set parser, `LegacyDocReader` metadata mapper, Pandoc-like AST handoff, and `WordPressBlockWriter`. Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external office tools, online services, live provider tests, and live-service provider tests were not executed.

## Non-Overlap

This does not repeat accepted legacy DOC/CFB MiniFAT cutoff, surplus DIFAT, directory start-sector, CLSID/state bits, FILETIME, FIB/FibRgLw97, PlcfldEdn, ASK/FILLIN, bookmark/note/comment/subdocument/table, embedded-object, picture-reference, or macro metadata slices. It owns only SummaryInformation `VT_CF` thumbnail metadata extraction.

## Follow-Up

- Non-simple OLE property storages and alternate clipboard tag formats remain separate work.
- Raw image extraction/decoding remains intentionally out of scope unless explicitly authorized.
- Encrypted DOC preflight/decryption policy and broader Word picture extraction handoff remain separate legacy DOC slices.
