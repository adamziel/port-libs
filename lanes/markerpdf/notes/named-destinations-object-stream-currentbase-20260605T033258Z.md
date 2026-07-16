# markerPDF Named Destinations Object Stream Current Base

Session: `port-dev-markerpdf-named-destinations-20260605T033258Z`
Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T033258Z`
Base accepted HEAD: `f5ca135693a249a186cc84ad0e99fc396ff4b3bc`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries searchable-PDF navigation metadata through native PDF parsing before OCR/model stages.
- PDF 1.5 xref streams can select ordinary catalog/name-tree destination dictionaries from `/ObjStm` type-2 rows. Those compressed objects must win before stale direct same-number bodies when WordPress import review emits named-destination metadata.
- This stays inside the current no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, Python model worker, pypdfium/PDFium, browser, or external PDF tool execution.

## Red Evidence

Before the production change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationObjectStreamCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves xref-stream object-stream named destinations before stale direct bodies
Expected: ["Compressed Start","Compressed Appendix","LegacyCompressed"]
Actual: ["LegacyStale"]
FAIL keeps object-stream destination metadata out of visible WordPress text

1 test files, 6 assertions, 2 failures
```

## Implementation

- `PdfNamedDestinationExtractor` now parses latest direct `/Type /XRef` streams with `/W`, optional `/Index`, and direct Flate/ASCIIHex filter stacks.
- Type-1 xref-stream rows select direct objects by exact byte offset and generation.
- Type-2 xref-stream rows expand selected `/ObjStm` members by declared header index or object number, while rejecting malformed carriers and top-level stream members.
- Compressed catalog `/Names /Dests`, destination tree leaves, destination dictionaries, and legacy `/Dests` rows now reach the existing name-tree walker and destination normalizer before stale direct duplicate bodies.

## Verification

```text
php -l lanes/markerpdf/src/PdfNamedDestinationExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfNamedDestinationExtractor.php

php -l lanes/markerpdf/tests/PdfNamedDestinationObjectStreamCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfNamedDestinationObjectStreamCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-named-destination-object-stream-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-named-destination-object-stream-currentbase.php
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationObjectStreamCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves xref-stream object-stream named destinations before stale direct bodies
PASS keeps object-stream destination metadata out of visible WordPress text

1 test files, 17 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php lanes/markerpdf/tests/PdfNamedDestinationGenerationBodyCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationIndirectArraysCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationKidGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationLimitsFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationXrefOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationObjectStreamCurrentBaseTest.php
Focused test run: 8 selected test files (root lock skipped)
23 PASS cases
8 test files, 172 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-object-stream-currentbase.php
```

The smoke emits `destination_names=["Compressed Start","Compressed Appendix","LegacyCompressed"]`, `object_stream_named_destination_objects_resolved=true`, `stale_direct_named_destination_bodies_excluded=true`, `visible_text_excludes_destination_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1359 -> 1361`.
- WordPress scenarios: `1303 -> 1304`.
- `pdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- `mappedPdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, xref-stream decoder, Flate/ASCIIHex stream boundary, object-stream member expander, generation-aware resolver, page-tree indexer, name-tree walker, text extractor, and WordPress smoke path. Full upstream markerPDF runner/model parity remains dependency-gated on pdftext, pypdfium2/PDFium, Surya/OCR/Torch model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers; none were executed here.

## Non-Overlap

This does not repeat accepted name-tree `/Limits` pruning/fallback, generation-exact references, indirect arrays/view operands, PDFDocEncoding keys, trailer `/Root` selection, classic xref direct-offset selection, xref-stream trailer metadata, object-stream page-text extraction, attachment object-stream FileSpec review, outline action destination resolution, PageLabels, encrypted preflight, font/image/filter, or runtime conversion slices. The bounded behavior is specifically xref-stream type-2 object-stream expansion inside the standalone named-destination extractor.
