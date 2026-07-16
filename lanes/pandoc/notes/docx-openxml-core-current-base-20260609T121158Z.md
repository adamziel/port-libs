# DOCX/OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260609T121158Z`

Implemented a bounded native PHP DOCX/OpenXML reader under `lanes/pandoc/src/DocxOpenXmlReader.php`. The reader parses OPC content types and relationships, resolves the office document part, maps core properties into document metadata, maps paragraph styles to headings, maps run properties to existing Pandoc-like inline nodes, resolves hyperlink relationships, maps numbering definitions and overrides into ordered/bullet list AST nodes, records media part metadata, maps drawing blips to image nodes, and handles simple table rows/cells with `gridSpan` as `colspan`.

Focused behavior evidence:

- Source-truth scope: OpenXML package parts `[Content_Types].xml`, `_rels/.rels`, `docProps/core.xml`, `word/document.xml`, `word/_rels/document.xml.rels`, `word/styles.xml`, `word/numbering.xml`, and `word/media/*`; this ports the format contract only and does not shell out to Pandoc, Word, LibreOffice, `zip`, `unzip`, or online services.
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` passed: 1 selected test file, 50 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-docx-openxml-import.php` emitted WordPress blocks with the DOCX heading, external source edit link, ordered list item, and image alt/title preserved.
- Root harness: not run - isolated micro-slice.

Dependency closure:

No new support component is needed. This slice uses PHP's native `ZipArchive` and `DOMDocument` extensions already available in the lane environment and keeps package/XML behavior lane-local. It reuses the existing Pandoc AST plus Markdown and WordPress writers for handoff verification. Full upstream Pandoc Haskell runner parity remains out of scope for this isolated micro-slice.

Next task:

Extend DOCX/OpenXML coverage in a separate slice to nested numbering, paragraph style inheritance, or richer Word table semantics. Keep it native and focused on package/AST behavior.
