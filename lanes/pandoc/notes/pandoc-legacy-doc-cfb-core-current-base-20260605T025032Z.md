# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T025032Z`
Base accepted HEAD: `84b4fdc8b82661383bb21f4378bf49906170954b`

## Behavior Added

- Added bounded legacy Word complex field-code handling in `LegacyDocReader`
  after CFB stream extraction and WordDocument text decoding.
- The reader now recognizes MS-DOC field begin/separator/end characters
  `0x13`, `0x14`, and `0x15` in extracted text.
- Field instructions are hidden from rendered output while displayed field
  results remain visible:
  - `HYPERLINK` fields become normal `link` AST nodes, including external
    targets, internal `\l` anchors, and `\o` title text.
  - `PAGE`, `NUMPAGES`, and `DATE` results become provenance `span` nodes with
    `legacy-doc-field` classes and `data-legacy-doc-field-*` attributes.
- Malformed field boundaries, including unterminated fields, separators outside
  fields, duplicate separators, and nested field begins, are rejected before
  exposing text.
- Updated the WordPress legacy DOC handoff smoke so a CLX piece-table fixture
  includes a HYPERLINK field and a PAGE result field.

## Source Truth

- Microsoft MS-DOC `Plcfld` specifies field begin, separator, and end
  characters as `0x13`, `0x14`, and `0x15`, and describes field instructions
  and field results:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/751b09bb-72f0-45ef-8e87-666dea68219f`
- Microsoft MS-DOC `HFD` documents legacy DOC hyperlink field data as a Word
  binary structure linked to a location in the document or an external document
  or webpage:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/f41daffe-fb3b-4373-a071-d3e86c59409d`

This slice intentionally uses the already-decoded text control characters
rather than implementing full Plcfld PLC/Fld table loading. It does not attempt
Word automation, LibreOffice conversion, nested field expansion, XE/TC/RD/TA/
PRIVATE field semantics, HFD binary hyperlink object parsing, full field update
evaluation, macros, embedded objects, decryption, or upstream Haskell runner
parity.

## Verification

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 95 assertions, 0 failures`
- Red-first after adding field-code expectations:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 98 assertions, 3 failures`
  - Expected failures:
    - HYPERLINK instructions leaked into paragraph text.
    - PAGE/NUMPAGES/DATE provenance spans were absent.
    - Unterminated field boundaries were not rejected.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 126 assertions, 0 failures`
  - PASS lines: `22`
  - Delta: `+3` PASS lines / `+31` assertions over the prior focused legacy
    DOC/CFB test run.
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
BibTeX/CSL, CSL, YAML, doctemplate rendering, ZIP/OPC, archive compression,
DOCX/ODT, table geometry, math/TeX, charset/Unicode, XML/HTML5 DOM,
Markdown/HTML reader/writer, CFB FAT/MiniFAT parsing, CFB storage hierarchy
traversal, CFB directory ordering/red-black validation, standard and custom
OLE property metadata, Word FIB encrypted-stream preflight, fExtChar Unicode
direct text-range decoding, CLX text extraction, or CLX PCD flag validation.
It owns only bounded field-code result handoff after legacy DOC text has
already been extracted.

## Follow-Up

Keep full Plcfld PLC/Fld table loading, nested fields, XE/TC index/table fields,
HFD binary hyperlink object parsing, field update/evaluation, legacy DOC style
and list tables, footnote/endnote PLCs, revision-mark format property
inspection, image extraction policy, embedded-object handling, encrypted DOC
password/decryption policy, and full upstream Pandoc runner parity as separate
bounded slices.
