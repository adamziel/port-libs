# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T021912Z`
Base accepted HEAD: `a175cabd679ccca994e830f85a8667633082f21c`

## Behavior Added

- Extended `LegacyDocReader` OLE property-set handling for legacy Word `.doc`
  files with bounded user-defined custom document properties.
- The reader now recognizes the second
  `FMTID_UserDefinedProperties` section in `\005DocumentSummaryInformation`,
  decodes its OLE Dictionary property, and exposes typed values under
  `metadata['customProperties']` and the document `meta` attribute.
- This prevents custom property id `2` from being misclassified as standard
  DocumentSummaryInformation `category`, preserving both standard review
  metadata and user-defined migration metadata for WordPress import queues.
- Updated the WordPress legacy DOC handoff example so its self-test includes
  dictionary-backed custom metadata such as migration batch id, review flag,
  and source id.

## Source Truth

- Microsoft MS-OLEPS Dictionary Property:
  `https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-oleps/4177a4bc-5547-49fe-a4d9-4767350fd9cf`
- Microsoft MS-OLEPS Dictionary/DictionaryEntry packets:
  `https://learn.microsoft.com/en-us/openspecs/windows_protocols/MS-OLEPS/99127b7f-c440-4697-91a4-c853086d6b33`
  and
  `https://learn.microsoft.com/en-us/openspecs/windows_protocols/MS-OLEPS/333959a3-a999-4eca-8627-48a224e63e77`
- Microsoft Office User Defined Property Set:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-oshared/54514afb-ff19-4348-be71-395854d4432d`
- Microsoft Win32 DocumentSummaryInformation/UserDefined property set FMTIDs:
  `https://learn.microsoft.com/en-us/windows/win32/stg/the-documentsummaryinformation-and-userdefined-property-sets`

This slice is intentionally bounded to metadata extraction from already-opened
CFB property-set streams. It does not implement Word automation, LibreOffice
conversion, full OLEPS non-simple property sets, linked custom properties,
custom property reserved-name policy, macros, embedded objects, decryption,
or upstream Haskell runner parity.

## Verification

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 91 assertions, 0 failures`
- Red-first after adding the custom-property expectations:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 92 assertions, 1 failures`
  - Expected failure: user-defined property id `2` overwrote standard
    `category` instead of landing in `customProperties`.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 95 assertions, 0 failures`
  - PASS lines: `19`
  - Delta: `+1` PASS line / `+4` assertions over the prior focused legacy
    DOC/CFB test run.
- `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - Result: `legacy doc handoff self-test ok`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native CFB reader,
legacy DOC reader, OLE property-set parser, Pandoc-like AST, Markdown writer,
and WordPress block writer. It does not invoke Pandoc, Cabal, Haskell test
binaries, Word, LibreOffice, `zip`, `unzip`, external template engines,
TeX/PDF engines, browser renderers, roff, Typst, MathJax, KaTeX, or online
services.

## Non-Overlap

This patch does not repeat accepted PDF engine fake-runner diagnostics, EPUB3,
BibTeX/CSL, CSL, YAML, doctemplate rendering, ZIP/OPC, archive compression,
DOCX/ODT, table geometry, math/TeX, charset/Unicode, XML/HTML5 DOM,
Markdown/HTML reader/writer, CFB FAT/MiniFAT parsing, CFB storage hierarchy
traversal, CFB directory ordering/red-black validation, standard OLE
SummaryInformation/DocumentSummaryInformation metadata, Word FIB encrypted
stream preflight, fExtChar Unicode direct text-range decoding, basic CLX text
extraction, or CLX PCD flag validation. It owns only bounded OLE dictionary
custom-property metadata handoff for legacy DOC review packets.

## Follow-Up

Keep linked custom properties, reserved custom-property name policy,
case-sensitive dictionary behavior, non-simple OLEPS stream/storage property
types, legacy DOC style and list tables, footnote/endnote PLCs, revision-mark
format property inspection, field-code extraction, image extraction policy,
embedded-object handling, encrypted DOC password/decryption policy, and full
upstream Pandoc runner parity as separate bounded slices.
