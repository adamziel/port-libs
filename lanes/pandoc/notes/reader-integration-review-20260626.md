# Pandoc Reader Integration Review - 2026-06-26

Reviewed checkpoint `plainmath-parity-20260625` at `399a0e0f22a4349202023a0ffd9cf140148baf19`
against merge base `df4b88a34f0edc616ba8ef077dc8a5172d60f37e`.
Line references below point at the checkpoint snapshot.

## Blocking Status

Blocking issues found: yes. The shared ZIP/OPC helper accepts unbounded inflated
entries and silently normalizes relationship traversal above the package root.
Those two behaviors should be fixed before integrating the package-backed
readers as a shared foundation.

## Findings

1. High: ZIP/OPC reads are unbounded and can inflate attacker-controlled package
   members into memory.
   - Evidence: `lanes/pandoc/src/ZipOpcPackage.php:62-67` wraps
     `ZipArchive::getFromName()` without an entry size or aggregate byte budget,
     while `lanes/pandoc/src/ZipOpcPackage.php:100-114` enumerates every package
     entry without an entry-count budget. The readers immediately call this path
     for core package parts, for example `lanes/pandoc/src/DocxReader.php:56-64`,
     `lanes/pandoc/src/XlsxReader.php:48-56`, `lanes/pandoc/src/XlsxReader.php:69-74`,
     and `lanes/pandoc/src/PptxReader.php:64-79`.
   - Risk: a crafted DOCX/XLSX/PPTX can force large decompression or many package
     entries before the bounded reader logic sees XML content.
   - Recommended owner: supervisor. Put the byte and entry policy in the shared
     package layer, then make DOCX, XLSX, and PPTX consume it.

2. High: relationship path normalization masks traversal above the package root.
   - Evidence: `lanes/pandoc/src/ZipOpcPackage.php:186-200` pops `..` segments
     even when there is no parent left. The test at
     `lanes/pandoc/tests/ZipOpcPackageTest.php:86-90` asserts that
     `../META-INF/./container.xml` becomes `META-INF/container.xml`, which makes
     invalid above-root targets look like valid root package parts. XLSX and PPTX
     route internal relationships through this helper at
     `lanes/pandoc/src/XlsxReader.php:1319-1329` and
     `lanes/pandoc/src/PptxReader.php:1195-1205`. DOCX has a separate partial
     resolver at `lanes/pandoc/src/DocxReader.php:1423-1435` that strips only one
     leading `../` and can still emit unresolved parent traversal in media URLs.
   - Risk: malformed internal relationships can be silently re-anchored to other
     package parts, causing wrong content capture and making traversal fixtures
     pass by accident.
   - Recommended owner: supervisor, with DOCX/XLSX/PPTX follow-through. Shared
     resolution should reject above-root traversal, preserve external targets as
     external, and return a diagnostic for invalid internal targets.

3. Medium: malformed optional DOCX XML parts abort the whole document read.
   - Evidence: optional styles, numbering, relationships, notes, endnotes, and
     comments are loaded directly at `lanes/pandoc/src/DocxReader.php:128-133`;
     `loadXml()` throws on parse failure at `lanes/pandoc/src/DocxReader.php:1330-1342`.
     The DOCX fixture test only covers well-formed optional parts at
     `lanes/pandoc/tests/DocxReaderTest.php:20-31`.
   - Risk: a readable DOCX body can be rejected because one ancillary part is
     malformed, which is brittle compared with the bounded reader goal.
   - Recommended owner: DOCX. Soften optional part failures into metadata
     diagnostics and add malformed `numbering.xml` and `.rels` fixtures.

4. Medium: ragged CSV/TSV inputs lose row-width detail after normalization.
   - Evidence: `lanes/pandoc/src/CsvReader.php:32-34` computes the max width and
     immediately pads short rows; metadata keeps only `csvRaggedRowCount` at
     `lanes/pandoc/src/CsvReader.php:49`. Tests assert the count but not the
     offending row widths at `lanes/pandoc/tests/CsvReaderTest.php:59-61` and
     `lanes/pandoc/tests/CsvReaderTest.php:83-91`.
   - Risk: downstream writers cannot distinguish a genuinely empty trailing cell
     from a padded missing cell, and reviewers cannot inspect which rows were
     repaired.
   - Recommended owner: CSV. Preserve a row-width diagnostic payload before
     normalization and assert it for CSV and TSV.

## Non-Blocking Observations

- BibTeX/BibLaTeX coverage is not a blocker in this checkpoint. The parser has
  expected partial-parity limits, but the reviewed tests cover string macros,
  inheritance, date aliases, name particles, TeX accent cleanup, CSL metadata,
  and writer output (`lanes/pandoc/tests/BibTexReaderTest.php:11-192`).
- XLSX and PPTX relationship helpers duplicate the same target resolution and
  relationship parsing patterns (`lanes/pandoc/src/XlsxReader.php:1269-1336`,
  `lanes/pandoc/src/PptxReader.php:1092-1212`). Once the shared resolver is
  fixed, these should be collapsed or made thin wrappers to avoid future drift.
