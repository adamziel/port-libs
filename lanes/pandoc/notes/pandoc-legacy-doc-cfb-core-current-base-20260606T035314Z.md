# Legacy DOC/CFB SttbfAssoc Slice

Micro-slice: `pandoc-legacy-doc-cfb-core-current-base-20260606T035314Z`
Base: `0dbb9e710d82b35945f8866ed52e371ba14ab293`

## Source Truth

- Microsoft MS-DOC `FibRgFcLcb97` defines `fcSttbfAssoc` and `lcbSttbfAssoc` as the Table Stream offset and byte size for the associated-string table.
- Microsoft MS-DOC `SttbfAssoc` defines a fixed 18-string extended STTB. The title, subject, keywords, author, and last-author entries yield to SummaryInformation values when present; mail-merge/template paths are associated metadata; the write-reservation password is capped at 15 characters.

## Patch

- `LegacyDocReader` now reads `SttbfAssoc` from the FIB-advertised Table Stream range.
- Non-secret entries are exposed as bounded review metadata and as `associatedStrings` on the result/document attrs.
- SummaryInformation title/creator values are preserved over SttbfAssoc fallbacks.
- Write-reservation password values are never exposed; only `hasWriteReservationPassword` and character count are reported.
- Malformed associated-string tables reject before metadata exposure.
- The WordPress legacy DOC handoff example now embeds and self-tests this metadata without rendering the mail-merge paths or password text into blocks.

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` passed with `1 test files, 646 assertions, 0 failures`.
- After patch: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` passed with `1 test files, 673 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test` passed with `legacy doc handoff self-test ok`.
- PHP lint passed for changed PHP files.
- Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is required. This reused the native PHP `LegacyDocReader`, `CompoundFileBinary`, FIB/table-stream slicing helpers, and existing Markdown/WordPress writers. No Pandoc, Cabal, Haskell runner, Word, LibreOffice, zip/unzip, external converter, online service, or live provider test was executed.

## Non-Overlap

This slice owns only MS-DOC SttbfAssoc associated-string metadata. It avoids the accepted legacy DOC/CFB work for CFB header/version/directory validation, directory timestamp/CLSID/state-bit provenance, encrypted FIB and `lKey` preflight, `fExtChar` direct Unicode text, FibRgLw97 subdocument trimming, CLX PCD flag validation, OLE property sets, ObjectPool reports, macros, fields, styles, sections, notes, bookmarks, lists, and formatting runs.
