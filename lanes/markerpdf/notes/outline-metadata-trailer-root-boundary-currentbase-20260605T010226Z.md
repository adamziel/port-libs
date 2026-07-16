# Outline Metadata Trailer Root Boundary

Slice: `markerpdf-outline-metadata-boundary-current-base-20260605T010226Z`
Base accepted HEAD: `70e9bdea1f1089cd9383d550be07b1b0df456263`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` obtains searchable PDF text from pdftext and document TOC rows from the PDFium-backed `doc.get_toc(...)` boundary. Native PHP markerPDF must therefore treat the current PDF trailer `/Root` catalog and `/Info` dictionary as authoritative review metadata inputs before stale unreferenced catalog, outline, action, or Info dictionaries.

## Behavior

`PdfOutlineExtractor` now promotes the current classic-xref trailer `/Root` catalog object before catalog scanning. This preserves existing fallback behavior for malformed PDFs without xref trailers, while current trailer-root PDFs no longer let lower-numbered stale catalog objects own outline TOC/navigation review.

`PdfTextExtractor::extractOutlineMetadata()` now reads lightweight document info from the current trailer `/Info` reference before falling back to broad legacy scans. This prevents stale `/Info` references embedded in earlier catalog-like dictionaries from replacing current WordPress import metadata.

The focused fixture keeps stale catalog, page, outline, JavaScript action, and Info dictionaries in the file while the final xref trailer points `/Root` to the current catalog and `/Info` to the current Info dictionary. Current outline rows, action review rows, visible page text, and document metadata are preserved; stale outline/action/Info text is excluded.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataTrailerRootBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses current trailer Root and Info for lightweight outline metadata
Expected: 'Current Trailer Info'
Actual: 'Stale Info Title'
FAIL uses current trailer Root for outline navigation review and document metadata
Expected: ['Current Trailer Chapter', 'Current Trailer Appendix']
Actual: ['Stale Root Outline']

1 test files, 4 assertions, 2 failures
```

## Verification

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataTrailerRootBoundaryCurrentBaseTest.php
=> 1 test files, 28 assertions, 0 failures
```

Adjacent outline/metadata family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataXrefOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
=> 5 test files, 854 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-trailer-root-boundary-currentbase.php
=> stale_catalog_excluded=true; stale_info_excluded=true; stale_action_excluded=true; visible_text_excludes_stale_catalog=true; executes_python_or_models=false; executes_external_pdf_tools=false
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted outline `/Prev`, `/Last`, missing-parent, generation-exact, xref-selected duplicate outline object owner, EOF, named-destination, action-context, page-transition, or DCTDecode/parser stream boundaries. The new behavior is specifically current trailer `/Root` catalog ownership for outline extraction and current trailer `/Info` ownership for lightweight outline metadata.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF token parser, classic xref-table parser, trailer walker, outline extractor, metadata extractor, text extractor, and WordPress smoke path. Live OCR, Surya/Texify/Torch, pypdfium/PDFium, PIL, Streamlit/FastAPI model workers, and external PDF tools remain intentionally out of scope for the markerPDF no-GPU lane.
