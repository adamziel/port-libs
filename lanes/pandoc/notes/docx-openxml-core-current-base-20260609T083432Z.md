# DOCX/OpenXML ADDIN citation-manager field metadata

Slice: `pandoc-docx-openxml-core-current-base-20260609T083432Z`
Base: `436db66ac9717cbf75ff2ec29905ae0ddef22b3a`

## Behavior

- `DocxReader` now recognizes `ADDIN` field instructions used by Zotero,
  Mendeley, and EndNote citation-manager integrations.
- Preserved field variants:
  - `ADDIN ZOTERO_ITEM CSL_CITATION ...`
  - `ADDIN CSL_CITATION ...`
  - `ADDIN ZOTERO_BIBL`
  - `ADDIN Mendeley Bibliography CSL_BIBLIOGRAPHY`
  - `ADDIN EN.CITE ...`
  - `ADDIN EN.REFLIST`
- Visible field results remain visible content, wrapped in inert
  `docx-field-addin` reviewer spans with provider/type metadata. Payloads are
  bounded to byte count and SHA-256 metadata, with CSL JSON validity, citation
  id, item count, and item ids when available.
- The reader does not run citeproc, parse EndNote XML into live citations,
  invoke bibliography managers, or shell out to office/document tools.

## Source Truth

- Upstream pinned Pandoc `Text.Pandoc.Readers.Docx.Fields` parses `ADDIN`
  fields into CSL citation, CSL bibliography, EndNote cite, and EndNote
  reference-list field variants:
  `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Readers/Docx/Fields.hs`
- Upstream pinned Pandoc `Text.Pandoc.Readers.Docx` handles those variants as
  citation/bibliography field content when citation extensions are enabled, and
  otherwise preserves visible field result content:
  `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Readers/Docx.hs`
- The local upstream Pandoc cache was unavailable in this isolated worktree, so
  the source check used pinned upstream raw files only. No upstream runner was
  executed.

## Evidence

- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxGeneratedFieldMetadataTest.php`
  passed with `1 test files, 90 assertions, 0 failures`.
- Baseline DOCX regression:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 4349 assertions, 0 failures`.
- Red-first focused run after adding the ADDIN expectations failed only on the
  Markdown expectation for escaped bracket text in the new EndNote display
  result, after the parser emitted the expected ADDIN span metadata.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxGeneratedFieldMetadataTest.php`
  passed with `1 test files, 128 assertions, 0 failures`.
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-docx-generated-field-handoff.php --self-test`
  passed with `wordpress-docx-generated-field-handoff self-test passed`.

## Delta

- Added 1 focused PHP PASS case.
- Added 38 focused assertions.
- `lanes/pandoc/lane-status.json` moved `phpPass` `2530 -> 2531`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` moved mapped static inventory
  `2898 -> 2899`; DOCX/OpenXML core case counters moved `33 -> 34`; DOCX
  OpenXML focused assertion inventory moved `385 -> 423`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`,
DOM-based `DocxReader` field parsing, `MarkdownWriter`, `WordPressBlockWriter`,
and the focused lane TestRunner. Full upstream Pandoc runner parity remains a
separate upstream-runner dependency task requiring hydrated Pandoc sources and
Haskell test executables.

No Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, citeproc,
Zotero, Mendeley, EndNote, external template engines, TeX/PDF engines, browser
renderers, online services, live provider tests, or live-service provider tests
were executed. Root harness was not run for this isolated micro-slice.

## Non-Overlap

This does not repeat accepted DOCX field-code hyperlinks, PAGE/DATE/REF/
PAGEREF/NOTEREF/SEQ/data-field/form-field metadata, generated TOC/INDEX/
BIBLIOGRAPHY/CITATION displayed-result metadata, hidden `XE` index-entry
metadata, tracked deletion reporting, section/header/footer import, numbering/
style/table/drawing/media handoffs, content controls, chart/drawing metadata,
or OPC relationship preflight work. It only closes bounded citation-manager
`ADDIN` field provenance.

## Next

A next DOCX/OpenXML slice should target a non-overlapping reader gap such as
table-look bitmask gating, direct cell-style inheritance, richer settings
policy, or another upstream-backed field/body behavior that is not ADDIN
metadata.
