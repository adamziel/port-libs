# Page Resource Trailer Root Text Boundary Current Base

Slice: `markerpdf-page-resource-inheritance-current-base-20260605T075231Z`
Base accepted HEAD: `f4a714cee9206bb53c7a3560db2ebdeb5e7daf8e`

## Behavior

The native text extractor now treats an explicit trailer `/Root` as generation-exact before page-resource text extraction. If the trailer points at a missing catalog generation, fallback catalog/page scanning is blocked so stale page text, inherited fonts, and inherited Form XObject resources are not promoted into WordPress text or review metadata.

Valid-root fallback behavior is preserved for existing malformed-but-searchable PDFs that have no stale catalog candidate, including incremental `/Prev` text updates and linearized hint-table fallback extraction.

## Evidence

Red-first probe before implementation:

```text
PdfTextExtractor::extractTextLines() returned:
  Stale trailer root resource text
  Stale trailer root inherited form
PdfPagePropertyExtractor::extractPageBoundaryMetadata() returned []
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
=> 1 test files, 134 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
=> 1 test files, 628 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfOutlineMetadataTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentTrailerRootBoundaryCurrentBaseTest.php
=> 6 test files, 1087 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceCategoryStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEscapedKidsInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceStreamBoundaryCurrentBaseTest.php
=> 5 test files, 102 assertions, 0 failures
```

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP object/xref/trailer parser and page-resource inheritance paths. GPU/OCR/model parity, Surya/Texify/Torch execution, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat the accepted FileSpec filename path boundary, xref `/Prev` metadata recovery, page-resource stream/category boundary, escaped `/Kids`, or page review metadata slices. The change is limited to current-base trailer `/Root` generation selection before text/page fallback.
