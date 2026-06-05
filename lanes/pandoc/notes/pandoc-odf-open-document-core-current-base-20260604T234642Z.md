# Pandoc ODF OpenDocument Core Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260604T234642Z`
- Accepted base: `7ee8282ed13496488f24fb8a4690627cbb0ae84a`
- Rework notes: no `port-pandoc-*.needs-lane-rework.md` file was present before editing.

## Implementation

Extended the native `OdfReader` OpenDocument Text mapping for ODT text-reference content:

- Maps `text:note` footnotes and endnotes into existing `note` AST nodes, preserving `text:id`, `text:note-class`, note citation, and paragraph/link body content from `text:note-body`.
- Maps `text:bookmark-start` and single-position `text:bookmark` anchors into empty anchor `span` nodes with stable HTML ids plus source `data-odf-bookmark-name` metadata.
- Maps `text:bookmark-ref` into internal `link` nodes pointing at the normalized bookmark id while preserving `text:ref-name` and `text:reference-format` as review metadata.
- Adds ODT import-report content counters for notes, bookmark anchors, and bookmark references.
- Updates the WordPress ODT handoff smoke so source footnotes and internal references render as WordPress footnote blocks and internal links.

This is bounded to OpenDocument Text XML mapping. It does not invoke Pandoc, LibreOffice, Word, zip/unzip, browser renderers, external template engines, or online services.

## Evidence

- `php -l lanes/pandoc/src/OdfReader.php`: no syntax errors.
- `php -l lanes/pandoc/tests/OdfReaderTest.php`: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`: no syntax errors.
- JSON validation for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: ok.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`: 1 test file, 139 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 16 test files, 4,102 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`: `odf open document handoff self-test ok`.
- `git diff --check -- lanes/pandoc`: no whitespace errors.

Root harness was not run; this is an isolated Pandoc micro-slice.

## Status Delta

- `phpPass`: 420 -> 421.
- `benchmarkDenominator.mapped`: 885 -> 886.
- `odfOpenDocumentCoreCases`: 10 -> 11.
- `mappedOdfOpenDocumentCoreCases`: 10 -> 11.
- Focused `OdfReaderTest.php`: 105 -> 139 assertions.
- `odfOpenDocumentCoreAssertions`: 217 -> 251.

## Dependency Closure

No new support component is needed. This slice reuses the existing native `ZipPackage`, `AstNode`, `MarkdownWriter`, and `WordPressBlockWriter` components. Full upstream Pandoc runner parity remains blocked on hydrating/building the Haskell Pandoc checkout at the manifest commit, but ODT-local note/bookmark parsing is not blocked by that runner.

## Non-Overlap / Exclusions

This slice avoids the accepted ODT mimetype/manifest/content/styles/meta/media/table/list/annotation/text-box/image cluster and adds only OpenDocument text notes and bookmark references. It does not attempt DOCX, EPUB3, BibTeX/CSL, legacy DOC/CFB, PDF engine, archive compression, table-geometry, tracked changes, formulas, charts, linked sections, encrypted package preflight, forms, richer style cascades, or full Pandoc ODT reader parity.
