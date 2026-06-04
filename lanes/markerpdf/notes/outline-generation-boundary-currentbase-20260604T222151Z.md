# markerPDF outline generation-boundary current-base slice

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260604T222151Z`

Base accepted HEAD: `ff6eb92d98d4cde3d47b91b176bd50c33acab518`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF TOC/bookmark metadata separate from page text through `marker/cleaners/toc.py::get_pdf_toc` and PDF text extraction through `marker/pdf/extract_text.py::get_text_blocks`.
- PDF indirect references are generation-qualified `N G R` references. A stale object with the same object number but a different generation must not satisfy outline `/First`, `/Next`, `/Last`, `/Parent`, `/A`, `/D`, page, page-tree kid, or thread bead references.
- WordPress import needs malformed outline navigation to fail bounded: preserve current-generation bookmark rows, but do not import stale bookmark titles, remote actions, or destination targets into TOC/navigation review metadata or visible text.

## Red Baseline

After adding `PdfOutlineMetadataGenerationBoundaryCurrentBaseTest.php`, the current base failed:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects mismatched generation outline item references in document metadata
Expected: 2
Actual: 4
FAIL applies generation exact outline boundaries to TOC and navigation review rows
Expected: Generation Boundary Chapter, Generation Boundary Appendix
Actual: Generation Boundary Chapter, Stale Generation Child, Generation Boundary Appendix, Stale Generation Sibling
1 test files, 8 assertions, 2 failures
```

## Implementation

- `PdfOutlineExtractor` now preserves object generations while parsing indirect references.
- Outline traversal resolves `/First`, `/Next`, `/Last`, and `/Parent` only when the referenced generation matches the selected object generation.
- Action and destination resolution now fail closed for stale-generation `/A`, `/D`, direct page, page-tree kid, and thread bead references.
- `PdfMetadataExtractor::documentOutlineItemMetadataRows()` applies the same generation-exact boundary for document-outline metadata traversal while leaving raw referenced object numbers reviewable on accepted rows.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataGenerationBoundaryCurrentBaseTest.php
PASS rejects mismatched generation outline item references in document metadata
PASS applies generation exact outline boundaries to TOC and navigation review rows
1 test files, 39 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfOutline*CurrentBaseTest.php
27 test files, 1771 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadata*Test.php
16 test files, 1504 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-generation-boundary-currentbase.php
```

Smoke emitted `stale_child_excluded=true`, `stale_sibling_excluded=true`, `stale_action_excluded=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Full root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object parser, metadata extractor, outline resolver, destination name-tree resolver, TOC/navigation review paths, and WordPress smoke renderer. GPU/model/OCR/PDFium/PIL execution remains intentionally out of scope under the markerPDF no-GPU directive.

## Non-Overlap

This does not repeat accepted named-destination generation filtering, EOF-bounded outline selection, parent-scoped outline `/Next` traversal, `/Last` terminal traversal, remote GoTo/GoToE review, outline action-chain context, PageLabels, page transition/action metadata, outline style/color metadata, outline `/SE` structure metadata, xref repair, attachment, AcroForm, image, font, or stream-filter slices. The bounded behavior is generation-exact outline metadata traversal and action/destination resolution for stale same-object-number PDF references.
