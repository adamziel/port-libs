# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T011837Z`

Base accepted HEAD: `d9ba170daace3a1578bca8362a9f23d3e2d9eea0`

## Behavior Added

- Extended `LegacyDocReader` OLE PropertySet parsing so the `CodePage`
  property is treated as an unsigned codepage identifier before decoding
  `VT_LPSTR` metadata.
- Decodes legacy DOC `VT_LPSTR` metadata through the declared property-set
  codepage for bounded UTF-8 and CP1251 review fields, with iconv-backed
  aliases for common Windows codepages when the local PHP build supports them.
- Preserves the Windows-1252 fallback for old ASCII/Latin review packets.
- Updated the WordPress legacy DOC handoff smoke so a UTF-8
  `DocumentSummaryInformation` review category survives the import packet.

## Source Truth

- Microsoft MS-OLEPS `CodePage Property` documents property id `0x00000001`,
  type `VT_I2`, and that the codepage value controls `CodePageString` and
  dictionary entry encoding:
  `https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-oleps/b8910736-7f4a-469a-9644-aed68a71d7d1`.
- Microsoft MS-OLEPS `CodePageString` documents `VT_LPSTR` string bytes as
  null-terminated 8-bit characters from the declared codepage, or UTF-16 when
  the codepage is `CP_WINUNICODE`:
  `https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-oleps/a4c32611-5b79-4965-8f50-50639c138e16`.

This slice is intentionally bounded to legacy DOC/CFB OLE metadata extraction.
It does not implement user-defined property dictionaries, thumbnail clipboard
data, non-simple property set storages, Word styles/list tables,
footnote/endnote PLCs, field-code extraction, image extraction, embedded
objects, macros, encryption/decryption, Word automation, LibreOffice
conversion, Pandoc execution, or upstream Haskell runner parity.

## Verification

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: failed before implementation because CP1251 SummaryInformation
    title bytes decoded as Windows-1252 mojibake (`Èìïîðò îòçûâîâ`).
- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 86 assertions, 0 failures`
  - PASS lines: 15
  - Delta: +1 PASS line / +4 assertions over the prior focused legacy DOC/CFB
    test run.
- `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - Result: `legacy doc handoff self-test ok`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native CFB reader,
legacy DOC reader, Pandoc-like AST, Markdown writer, and WordPress block writer.
It does not invoke Pandoc, Cabal, Haskell test binaries, Word, LibreOffice,
`zip`, `unzip`, external template engines, TeX/PDF engines, browser renderers,
roff, Typst, MathJax, KaTeX, or online services. CP1251 has a local decoder
fallback because this worker's PHP iconv build only proved Windows-1252 and
UTF-16LE decoding support.

## Non-Overlap

This patch does not repeat accepted PDF engine fake-runner diagnostics, EPUB3,
BibTeX/CSL, CSL, YAML, doctemplate resource-map/final-newline rendering,
ZIP/OPC, archive compression, DOCX/ODT, table geometry, math/TeX,
charset/Unicode, Markdown/HTML reader/writer, CFB FAT/MiniFAT parsing, CFB
directory ordering and red-black validation, scalar OLE string/date/count/
security metadata, DocumentSummaryInformation vector metadata, CLX piece-table
extraction, Word FIB encrypted-stream preflight, fExtChar Unicode text-range
decoding, or accepted CFB storage-path hierarchy traversal. It owns only
bounded OLE PropertySet codepage decoding for legacy DOC LPSTR metadata.

## Follow-Up

Keep user-defined property dictionaries/name mapping for custom properties,
broader codepage tables that cannot rely on local iconv support, thumbnail/
clipboard metadata, non-simple OLE property storages, encrypted DOC
password/decryption policy, legacy DOC style and list tables, footnote/endnote
PLCs, field-code extraction, image extraction policy, embedded-object handling,
and full upstream Pandoc runner parity as separate bounded slices.
