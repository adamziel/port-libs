# markerPDF lightweight outline Count boundary

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260605T121003Z`

Base accepted HEAD: `295120098a86970c9ff6f0c0719d64afe0c9dda9`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF outline/TOC rows as metadata through `marker/cleaners/toc.py::get_pdf_toc`, delegated to the PDF document backend before model execution.
- PDF outline item `/Count 0` declares no visible descendants for that item. Native lightweight `pdf_toc` extraction should match the richer outline extractor and not traverse contradictory `/First` children into WordPress navigation metadata.
- Outline strings and remote actions remain metadata only; they must not become visible Gutenberg paragraph text.

## Red Baseline

After adding the lightweight direct-destination case to `PdfOutlineMetadataZeroCountChildBoundaryCurrentBaseTest.php`, the accepted base behavior failed when the new guard was temporarily removed:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataZeroCountChildBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS does not traverse outline children when item Count declares zero descendants in document metadata
PASS applies zero Count child boundary to TOC navigation and remote outline actions
FAIL applies zero Count child boundary to lightweight upstream pdf_toc metadata
Expected: Lightweight Zero Count Chapter, Lightweight Zero Count Appendix
Actual: Lightweight Zero Count Chapter, Lightweight Zero Count Hidden Child, Lightweight Zero Count Appendix
1 test files, 38 assertions, 1 failures
```

## Implementation

- `PdfTextExtractor::outlineItemsFromLinkedList()` now checks a lightweight `/Count` value before recursing into an outline item's `/First` child.
- Direct and indirect integer `/Count` operands reuse the existing native PDF integer/object resolver.
- The existing richer `PdfOutlineExtractor` and `PdfMetadataExtractor` behavior is unchanged and remains aligned with the lightweight upstream-style `extractOutlineMetadata()` path.

## Verification

Focused after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataZeroCountChildBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS does not traverse outline children when item Count declares zero descendants in document metadata
PASS applies zero Count child boundary to TOC navigation and remote outline actions
PASS applies zero Count child boundary to lightweight upstream pdf_toc metadata
1 test files, 43 assertions, 0 failures
```

Adjacent outline metadata family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataZeroCountChildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataLightweightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 486 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-lightweight-zero-count-boundary-currentbase.php
```

The smoke emits `hidden_child_unpromoted_from_lightweight_metadata=true`, `hidden_child_action_unpromoted_from_navigation=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Full root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass` moves `1806 -> 1807` from the new focused lightweight `pdf_toc` PASS case.
- Focused assertion coverage for `PdfOutlineMetadataZeroCountChildBoundaryCurrentBaseTest.php` moves to 43 assertions.
- WordPress scenario count moves `1643 -> 1644` from the added smoke.

## Non-Overlap

This does not repeat accepted outline trailer-root ownership, root type/count metadata, rich zero-count metadata/navigation, Prev/Last/parent/title/comment/generation/EOF/xref-owner boundaries, named-destination, page-label/transition/thread enrichment, action-chain review, metadata/XMP, page geometry, annotations, forms, security, image, font, CMap, or stream-filter slices. The bounded behavior is only the lightweight `PdfTextExtractor::extractOutlineMetadata()` linked-list child traversal boundary for `/Count 0`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, lightweight outline linked-list parser, integer/reference resolver, existing rich outline review paths, metadata extractor, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch models, PDFium/pypdfium rendering, PIL, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the markerPDF no-GPU directive.
