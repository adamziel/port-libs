# pandoc-docx-openxml-core-current-base-20260605T001500Z

Base: `9da799fe6613030b08e1d758e8e0520b8039a915`

Source truth:

- Existing lane DOCX/OpenXML contract: parse WordprocessingML from OPC ZIP
  packages into the shared AST and WordPress handoff without shelling out to
  Pandoc, Word, LibreOffice, zip/unzip, browser renderers, or online services.
- WordprocessingML fields are represented by `w:fldChar`/`w:instrText`
  complex fields or `w:fldSimple`. Hyperlink fields were already mapped; this
  slice covers bounded non-hyperlink displayed fields that commonly appear in
  Word headers/footers and source review packets.
- The pinned upstream Pandoc checkout was not locally hydrated in this
  isolated worktree, so this remains bounded native OpenXML support, not
  Haskell runner parity.

Implementation:

- `DocxReader` now wraps displayed results for bounded PAGE, NUMPAGES,
  SECTIONPAGES, DATE, TIME, CREATEDATE, SAVEDATE, and PRINTDATE fields in
  `span` AST nodes.
- The spans preserve rendered result children and expose reviewer metadata:
  `.docx-field`, `.docx-field-{name}`, `data-docx-field`,
  `data-docx-field-instruction`, and `data-docx-field-format` when a `\*` or
  `\@` field format switch is present.
- Unsupported non-hyperlink fields still fall back to their displayed result
  text, and existing HYPERLINK field mapping remains unchanged.
- The WordPress DOCX body handoff example now includes PAGE/NUMPAGES footer
  fields and self-tests their preserved field provenance.

Focused evidence:

- Baseline before adding the new case:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 368 assertions, 0 failures`
- Red-first after adding the new field-provenance fixture:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: failed as expected with `Expected: 'Page '`, `Actual: 'Page 7 of 12 updated June 5, 2026.'`; `1 test files, 370 assertions, 1 failures`
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 396 assertions, 0 failures`
- Full focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 4539 assertions, 0 failures`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`
- Syntax and metadata:
  - `php -l lanes/pandoc/src/DocxReader.php`: no syntax errors
  - `php -l lanes/pandoc/tests/DocxReaderTest.php`: no syntax errors
  - `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`: no syntax errors
  - `lanes/pandoc/lane-status.json` and
    `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` decode with
    `JSON_THROW_ON_ERROR`
  - `git diff --check -- lanes/pandoc`: passed

Status delta:

- `phpPass`: `454 -> 455`
- mapped native checks: `922 -> 923`
- DOCX/OpenXML focused cases: `31 -> 32`
- DOCX/OpenXML focused assertions: `368 -> 396`

Dependency closure:

- No new support component is needed. This reuses the existing native
  `ZipPackage`, OPC relationship graph, `DocxReader` XML parser, Markdown
  writer, and WordPress block writer paths.

Exclusions:

- Did not execute Pandoc, Cabal, Haskell test binaries, citeproc, BibTeX,
  Biber, Word, LibreOffice, zip/unzip, external template engines, TeX/PDF
  engines, MathJax, KaTeX, Typst, browser renderers, roff, or online services.
- Did not implement formula field evaluation, TOC/index fields, document
  property fields, cross-reference fields, or Word layout recalculation.
- Root harness not run for this isolated micro-slice.

Next:

- Keep DOCX nested numbering, richer media extraction/export policy,
  charts/diagrams, full field evaluation, and broader non-hyperlink field
  families as separate bounded DOCX/OpenXML slices.
