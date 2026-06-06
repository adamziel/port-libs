# MarkerPDF Classic Fallback Trailer Stream Boundary

Slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260606T155302Z`

Accepted base: `bb0379a1bd259483ad4081f32c8d1179a07f099f`

## Source Truth

Upstream markerPDF's searchable-PDF path delegates native PDF parsing to PDFium/pdftext before model stages. In the no-GPU PHP lane, fallback trailer scans must preserve the same PDF token boundary: a `trailer` dictionary inside a direct object's stream payload is stream data, not a top-level trailer root for WordPress import.

## Behavior

`PdfTextExtractor::trailerDictionaryBodies()` now uses token-aware scanning for textual trailer fallback. It skips trailer candidates inside PDF comments, direct object bodies, and composite tokens before reading a fallback dictionary. This keeps damaged no-`startxref` imports rooted at the current top-level trailer and prevents a later stream payload from redirecting searchable text to decoy pages.

The existing `PdfMetadataExtractor` and `PdfEmbeddedFileExtractor` fallback trailer paths were already token-aware for this fixture; the patch aligns text extraction with them.

## Evidence

Red-first focused test after adding the fixture:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicFallbackTrailerStreamBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL skips stream-owned fallback trailer dictionaries before WordPress text extraction (lanes/markerpdf/tests/PdfXrefClassicFallbackTrailerStreamBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Current fallback trailer stream page',
  1 => 'Top-level trailer kept',
)
Actual: array (
  0 => 'Stream-owned fallback trailer decoy page',
  1 => 'Stream trailer root leak',
)

1 test files, 3 assertions, 1 failures
```

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicFallbackTrailerStreamBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS skips stream-owned fallback trailer dictionaries before WordPress text extraction

1 test files, 29 assertions, 0 failures
```

Adjacent classic-xref fallback/rebuild family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicFallbackTrailerStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildMissingStartxrefEofBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicUnterminatedLiteralBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicUnterminatedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicPdfWhitespaceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicCommentDelimiterBoundaryCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)

7 test files, 839 assertions, 0 failures
```

The runner printed 33 PASS lines for the selected adjacent cases.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-fallback-trailer-stream-currentbase.php
```

The smoke reports current text, metadata, and EmbeddedFiles attachment selection; excludes stream-owned trailer text, metadata, and attachment data; and reports no Python/model or external PDF tool execution.

## Non-Overlap

This does not repeat accepted damaged numeric `startxref` repair, stale valid `startxref` repair, EOF-bounded missing-startxref rebuild, object-owned ignored-startxref boundaries, stream-owned xref-table trailers, comment/composite/name/literal xref decoys, malformed or trailing xref subsections, overdeclared counts, plus headers, generation-offset repair, forward `/Prev`, xref-stream/object-stream repair, CMap/filter work, OCR/model execution, or supplied-boundary table/equation work. The bounded behavior is only textual fallback trailer dictionary scanning when no selected xref chain supplies the page-tree root.

## Dependency Closure

No new support component is needed. This reuses native PHP PDF token scanners already present in `PdfTextExtractor`; no PDFium, OCR, model, GPU, Pandoc, TeX, browser, or external PDF tool execution was added.
