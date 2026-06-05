# markerPDF Named Destinations Xref Offset Boundary

Session: `port-dev-markerpdf-named-destinations-20260605T030038Z`
Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T030038Z`
Base accepted HEAD: `0e1c7450f9f0796f4ed58554b7c1857240e788c6`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries searchable-PDF navigation metadata through PDF parser extraction before OCR/model stages.
- PDF classic xref tables select direct object bodies by byte offset. A native named-destination parser should not let later duplicate same-generation scanned bodies replace the object bodies selected by the current xref table.
- This stays inside the current no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, Python model worker, pypdfium/PDFium, browser, or external PDF tool execution.

## Implementation

- `PdfNamedDestinationExtractor` now records direct object definitions with byte offsets.
- When the latest `startxref` points at a classic xref table, the extractor builds the live object map from xref-selected `n` rows before falling back to the existing scan behavior for unreferenced objects.
- Current xref rows win over `/Prev` rows and suppress fallback duplicate bodies for the same object number, preventing later scanned decoy `/Names /Dests` and destination dictionary objects from replacing WordPress navigation metadata.
- Existing xref-stream-root, generation-exact, indirect-array, `/Limits`, PDFDocEncoding, and legacy `/Dests` fallback behavior is unchanged.

## Verification

Red-focused evidence before the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationXrefOffsetBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses classic xref object offsets before later duplicate named-destination bodies
Actual names: Stale Scanned Duplicate, Stale Dict Duplicate, LegacyCurrent
FAIL keeps later duplicate named-destination bodies out of WordPress review metadata
```

Passing focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationXrefOffsetBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses classic xref object offsets before later duplicate named-destination bodies
PASS keeps later duplicate named-destination bodies out of WordPress review metadata

1 test files, 16 assertions, 0 failures
```

Named-destination family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php lanes/markerpdf/tests/PdfNamedDestinationLimitsFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationGenerationBodyCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationIndirectArraysCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationKidGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationXrefOffsetBoundaryCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
21 PASS cases
7 test files, 155 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-xref-offset-boundary-currentbase.php
```

Emits `destination_names=["Current Xref Start","Current Xref Appendix","LegacyCurrent"]`, `xref_selected_named_destination_objects=true`, `later_duplicate_named_destination_bodies_excluded=true`, `visible_text_excludes_destination_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1327 -> 1329`.
- WordPress scenarios: `1278 -> 1279`.
- `pdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- `mappedPdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, classic xref-table tokenizer, trailer `/Prev` chain reader, generation-aware resolver, name-tree walker, destination normalizer, text extractor, and WordPress smoke path. Full upstream markerPDF runner/model parity remains dependency-gated on pdftext, pypdfium2/PDFium, Surya/OCR/Torch model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers; none were executed here.

## Non-Overlap

This does not repeat accepted current trailer `/Root` catalog selection, xref-stream `/Root` lookup, generation-exact destination references, same-object name-tree `/Kids` generation traversal, indirect `/Kids`/`/Names`/`/Limits` arrays, malformed `/Limits` fallback, PDFDocEncoding keys, indirect Fit operands, outline action destination resolution, metadata catalog destination summaries, PageLabels, attachment, encrypted preflight, font/stream/image, or runtime conversion slices. The bounded behavior is specifically classic xref direct object-offset ownership inside the standalone named-destination extractor before scan fallback.
