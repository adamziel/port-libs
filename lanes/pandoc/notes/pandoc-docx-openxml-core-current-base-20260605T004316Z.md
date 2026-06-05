# pandoc-docx-openxml-core-current-base-20260605T004316Z

Base: `adff3ff4be5f1e1b07ff473e9aa513203236699c`

Source truth:

- Existing lane DOCX/OpenXML contract: parse WordprocessingML from OPC ZIP
  packages into the shared AST and WordPress handoff without shelling out to
  Pandoc, Word, LibreOffice, zip/unzip, browser renderers, or online services.
- WordprocessingML structured document tags (`w:sdt`) wrap content controls
  around runs, paragraphs, tables, and review-packet regions. They carry
  reviewer-relevant metadata in `w:sdtPr`, including `w:id`, `w:alias`,
  `w:tag`, content-control type nodes, locks, placeholders, and data-binding
  attributes.
- The pinned upstream Pandoc checkout was not locally hydrated in this
  isolated worktree, so this remains bounded native OpenXML support, not
  Haskell runner parity.

Implementation:

- `DocxReader` now unwraps block-level `w:sdt` nodes through the existing DOCX
  block walker and preserves the wrapped content as `div` AST nodes.
- Inline/run-level `w:sdt` nodes are preserved as `span` AST nodes instead of
  losing content-control provenance.
- Content-control wrappers carry `.docx-content-control` plus type-specific
  classes and `data-docx-sdt-*` metadata for id, alias, tag, type, lock,
  placeholder, xpath, and store item id.
- Table-cell and note parsing now reuse the block walker so SDT-wrapped
  paragraphs and tables inside those containers are not dropped.
- `WordPressBlockWriter` now preserves div attributes, which keeps DOCX
  content-control provenance visible in WordPress HTML handoff blocks.
- The WordPress DOCX handoff example now includes inline and block content
  controls in its self-test fixture.

Focused evidence:

- Red-first `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  after adding the SDT fixture failed before implementation:
  `Expected: 3`, `Actual: 2`, with `1 test files, 397 assertions, 1 failures`.
- After implementation, `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 433 assertions, 0 failures`.
- Full focused lane directory `php tools/run-tests.php lanes/pandoc/tests`
  passed with `19 test files, 4797 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  reported `docx body handoff self-test ok`.
- Syntax checks passed:
  `php -l lanes/pandoc/src/DocxReader.php`,
  `php -l lanes/pandoc/src/WordPressBlockWriter.php`,
  `php -l lanes/pandoc/tests/DocxReaderTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`.
- `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` decode with `JSON_THROW_ON_ERROR`.
- `git diff --check -- lanes/pandoc` passed.

Status delta:

- `phpPass`: `475 -> 476`
- mapped native checks: `948 -> 949`
- DOCX/OpenXML mapped cases: `31 -> 32`
- DOCX/OpenXML focused assertions: `396 -> 433` for `DocxReaderTest.php`

Dependency closure:

- No new support component is needed. This reuses the existing native
  `ZipPackage`, OPC relationship graph, `DocxReader` XML parser, Markdown
  writer, and WordPress block writer paths.

Exclusions:

- Did not execute Pandoc, Cabal, Haskell test binaries, citeproc, BibTeX,
  Biber, Word, LibreOffice, zip/unzip, external template engines, TeX/PDF
  engines, MathJax, KaTeX, Typst, browser renderers, roff, or online services.
- Did not implement checkbox/dropdown/date value semantics, custom XML part
  evaluation, full field evaluation, charts/diagrams, or Word layout
  recalculation.
- Root harness not run for this isolated micro-slice.

Next:

- Keep checkbox/dropdown/date SDT value semantics, custom XML data-binding
  resolution, charts/diagrams, richer media export policy, and full upstream
  Haskell runner parity as separate bounded slices.
