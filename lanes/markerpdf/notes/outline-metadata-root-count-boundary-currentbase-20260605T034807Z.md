# markerPDF outline root count boundary current-base slice

Session: `port-dev-markerpdf-outline-meta-20260605T034807Z`
Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260605T034807Z`
Base accepted HEAD: `dfd2a039184babbb5f9961ff16e1ece203be51a2`

## Source Truth

- Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates PDF TOC rows through the PDF parser/PDFium boundary and treats outline/bookmark rows as document navigation metadata, not visible page text. Prior lane notes cite `marker/cleaners/toc.py::get_pdf_toc` for the title/level/page handoff.
- PDF outline `/Count` is signed state metadata: the sign indicates open versus collapsed state, while the absolute value is the descendant count magnitude. WordPress import should preserve both the raw count and the collapsed/open review state without treating outline titles as body text.

## Implementation

- `PdfMetadataExtractor::documentOutlineMetadata()` now mirrors existing item-level count semantics at the outline root:
  - raw `outline_count`
  - absolute `declared_visible_count`
  - `descendant_count`
  - `has_children`
  - `is_open`
  - `is_collapsed`
  - `structure_state`
- The new fixture uses a catalog `/Outlines` root with `/Count -2`, two valid current children, and a stale sibling after the declared `/Last`. The root remains collapsed in review metadata, the two outline rows remain importable through TOC/navigation review, and the stale tail stays out of metadata.
- Added `wordpress-pdf-outline-root-count-boundary-currentbase.php` to prove Gutenberg-facing import metadata exposes the collapsed-root review state while paragraph text contains only page content.

## Red-First Evidence

Before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataRootCountBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL preserves collapsed outline root Count state in document metadata (lanes/markerpdf/tests/PdfOutlineMetadataRootCountBoundaryCurrentBaseTest.php)
Values are not identical
Expected: true
Actual: NULL
PASS keeps collapsed root outline metadata out of visible WordPress text while TOC rows remain importable

1 test files, 19 assertions, 1 failures
```

## Verification

Focused slice:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataRootCountBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS preserves collapsed outline root Count state in document metadata
PASS keeps collapsed root outline metadata out of visible WordPress text while TOC rows remain importable

1 test files, 35 assertions, 0 failures
```

Adjacent outline metadata boundary family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataRootCountBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataLastBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataPrevBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataTitleBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
PASS summarizes current xref-selected catalog Outlines in document metadata
PASS preserves outline text color metadata without promoting it to page text
PASS keeps outline metadata and stale appended objects out of visible WordPress text
PASS does not resolve remote outline action destinations as current-document metadata targets
PASS bounds document outline metadata traversal by declared Last item
PASS applies Last boundary to TOC navigation and remote outline action review
PASS bounds document outline metadata traversal by each item parent object
PASS applies the same outline parent boundary to TOC and navigation review rows
PASS bounds document outline metadata when sibling Prev points outside the current chain
PASS applies the Prev backlink boundary to TOC navigation and remote action review
PASS preserves collapsed outline root Count state in document metadata
PASS keeps collapsed root outline metadata out of visible WordPress text while TOC rows remain importable
PASS treats untitled outline items as child traversal boundaries in document metadata
PASS applies untitled outline boundaries to TOC navigation and remote action review

6 test files, 313 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-root-count-boundary-currentbase.php
```

Passed and emitted `outline_count=-2`, `declared_visible_count=2`, `descendant_count=2`, `root_is_collapsed=true`, `root_is_open=false`, `root_structure_state=collapsed`, `stale_outline_tail_excluded=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Final local checks:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfMetadataExtractor.php

php -l lanes/markerpdf/tests/PdfOutlineMetadataRootCountBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfOutlineMetadataRootCountBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-outline-root-count-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-outline-root-count-boundary-currentbase.php

git diff --check -- lanes/markerpdf
passed with no output
```

## Delta

- Focused PHP PASS cases: `+2`.
- Focused assertions: `+35` in the new test file.
- WordPress scenario: `+1`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted outline metadata color preservation, title encoding, required-title gating, declared `/Last` traversal, missing/wrong `/Parent` boundaries, `/Prev` mismatch boundaries, EOF/trailer-root outline selection, generation-exact outline references, xref owner boundaries, named-destination action context, PageLabels, page transition/action metadata, outline `/SE` structure metadata, remote GoTo/GoToE review, or rich action-chain review. The bounded behavior is only root-level signed `/Count` state in `PdfMetadataExtractor::document_outline`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, catalog outline resolver, destination name-tree resolver, metadata extractor, TOC/navigation review paths, text extractor, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch, PDFium execution, model downloads, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
