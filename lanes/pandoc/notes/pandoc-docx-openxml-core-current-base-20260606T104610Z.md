# pandoc-docx-openxml-core-current-base-20260606T104610Z

Base: `0483ad6511d96484e7de926d9366648dd3a14809`

Source truth:

- Existing lane DOCX/OpenXML contract: parse WordprocessingML from OPC ZIP
  packages into the shared AST and WordPress handoff without shelling out to
  Pandoc, Word, LibreOffice, zip/unzip, browser renderers, or online services.
- The previous DOCX field-provenance slice preserved HYPERLINK fields and
  bounded PAGE/NUMPAGES/DATE-style displayed fields, and explicitly left
  cross-reference fields for a later bounded slice.
- This slice keeps Word-rendered field results as source truth. It preserves
  cross-reference field provenance for reviewer audit but does not evaluate
  fields or recalculate Word layout.

Implementation:

- `DocxReader` now wraps displayed REF, PAGEREF, and NOTEREF results in
  existing `span` AST field nodes.
- Cross-reference spans preserve `.docx-field`, `.docx-field-ref`,
  `.docx-field-pageref`, or `.docx-field-noteref` classes plus the normalized
  field instruction and first non-switch target token.
- Bounded switch metadata is preserved for common Word cross-reference
  switches: `\h` hyperlink, `\p` relative position, `\n` number, `\r`
  relative number, and `\w` full context. Existing `\*` and `\@` format
  switches continue to populate `data-docx-field-format`.
- Existing HYPERLINK field mapping remains unchanged and unsupported fields
  still fall back to their displayed result text.
- The WordPress DOCX body handoff example now includes REF/PAGEREF/NOTEREF
  fields and self-tests their rendered reviewer spans.

Focused evidence:

- Baseline before adding the new case:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 1703 assertions, 0 failures`
- Red-first after adding the new cross-reference fixture:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: failed as expected with `Expected: 'See section '`,
    `Actual: 'See section Source packet target on page 12 and note 3.'`;
    `1 test files, 1705 assertions, 1 failures`
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 1738 assertions, 0 failures`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`

Status delta:

- `phpPass`: `1299 -> 1300`
- mapped native checks: `1713 -> 1714`
- DOCX focused assertions: `1703 -> 1738`

Dependency closure:

- No new support component is needed. This reuses native PHP `ZipPackage`, OPC
  relationship handling, `DocxReader` WordprocessingML field parsing,
  `MarkdownWriter`, `WordPressBlockWriter`, the focused PHP test harness, and
  the existing DOCX body handoff example.

Exclusions:

- Did not execute Pandoc, Cabal, Haskell test binaries, Word, LibreOffice,
  zip/unzip, external office tools, browser renderers, online services, live
  provider tests, or live-service provider tests.
- Did not implement full Word field evaluation, TOC/index/document-property
  fields, SEQ/formula fields, cross-reference target resolution, or Word
  layout recalculation.
- Root harness not run for this isolated micro-slice.

Next:

- Keep TOC/index/document-property/SEQ/formula field families, target
  resolution, broader switch coverage, and layout recalculation as separate
  bounded DOCX/OpenXML slices.
