# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T004920Z`

Base accepted HEAD: `291caa6ad56f611338659462f05dd5385605317b`

## Behavior Added

- Extended `LegacyDocReader` OLE property set parsing for the bounded vector
  metadata forms used by legacy Word `\005DocumentSummaryInformation` streams:
  - `VT_VECTOR | VT_VARIANT` (`0x100c`) for alternating heading-name and part
    count values;
  - `VT_VECTOR | VT_LPSTR` (`0x101e`) and `VT_VECTOR | VT_LPWSTR` (`0x101f`)
    for document part title lists.
- Mapped DocumentSummaryInformation property `0x0000000c` into
  `metadata['headingPairs']`, grouping each heading with its declared count and
  the corresponding titles from property `0x0000000d`.
- Mapped DocumentSummaryInformation property `0x0000000d` into
  `metadata['documentParts']`.
- Updated the WordPress legacy DOC handoff smoke so review packets expose the
  same heading-pair/document-part inventory metadata alongside extracted text.

## Source Truth

- Microsoft OLE Property Set Data Structures `PropertyType`
  (`https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-oleps/2a4589eb-9a23-4a8b-adbd-3e368233c099`)
  documents `VT_VECTOR | VT_VARIANT`, `VT_VECTOR | VT_LPSTR`, and
  `VT_VECTOR | VT_LPWSTR`.
- Microsoft OLEPS variable-typed vector rules
  (`https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-oleps/8e6b33cd-3fb2-4329-9c6b-cb3a6c976e0f`)
  define the allowed scalar types inside `VT_VECTOR | VT_VARIANT`, including
  `VT_LPSTR`, `VT_LPWSTR`, and `VT_I4`.
- Microsoft Win32 DocumentSummaryInformation documentation
  (`https://learn.microsoft.com/en-us/windows/win32/stg/the-documentsummaryinformation-and-userdefined-property-sets`)
  identifies `HeadingPairs` as repeating heading-name/count pairs and
  `TitlesofParts` as document part names.

This slice is intentionally bounded to legacy DOC/CFB OLE metadata extraction.
It does not implement user-defined property dictionaries, thumbnail clipboard
data, non-simple property set storages, Word styles/list tables, footnote/
endnote PLCs, field-code extraction, image extraction, embedded objects,
macros, encryption/decryption, Word automation, LibreOffice conversion,
Pandoc execution, or upstream Haskell runner parity.

## Verification

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: failed before implementation because `documentParts` metadata was
    absent in the new focused heading-pair test.
- `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 82 assertions, 0 failures`
  - PASS lines: 14
  - Delta: +1 PASS line / +3 assertions over the prior focused legacy DOC/CFB
    test run.
- `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - Result: `legacy doc handoff self-test ok`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native CFB reader,
legacy DOC reader, Pandoc-like AST, Markdown writer, and WordPress block writer.
It does not invoke Pandoc, Cabal, Haskell test binaries, Word, LibreOffice,
`zip`, `unzip`, external template engines, TeX/PDF engines, browser renderers,
roff, Typst, MathJax, KaTeX, or online services.

## Non-Overlap

This patch does not repeat accepted PDF engine fake-runner diagnostics, EPUB3,
BibTeX/CSL, CSL, YAML, doctemplate resource-map rendering, ZIP/OPC, archive
compression, DOCX/ODT, table geometry, math/TeX, charset/Unicode,
Markdown/HTML reader/writer, CFB FAT/MiniFAT parsing, CFB directory ordering
and red-black validation, scalar OLE string/date/count/security metadata, CLX
piece-table extraction, Word FIB encrypted-stream preflight, fExtChar Unicode
text-range decoding, or accepted CFB storage-path hierarchy traversal. It owns
only bounded DocumentSummaryInformation vector metadata needed for legacy DOC
review inventories.

## Follow-Up

Keep user-defined property sets, dictionary/name mapping for custom properties,
thumbnail/clipboard metadata, non-simple OLE property storages, encrypted DOC
password/decryption policy, legacy DOC style and list tables, footnote/endnote
PLCs, field-code extraction, image extraction policy, embedded-object handling,
and full upstream Pandoc runner parity as separate bounded slices.
