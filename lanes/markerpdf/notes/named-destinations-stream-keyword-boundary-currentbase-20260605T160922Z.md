# markerPDF Named Destinations Stream Keyword Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T160922Z`
Session: `port-dev-markerpdf-named-destinations-20260605T160922Z`
Base accepted HEAD: `48a89663839470a7f859e92d82aaf22dbf92f634`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream markerPDF delegates searchable PDF parsing to `pdftext`/PDFium before model/OCR stages. Under the current no-GPU scope, this lane owns native PHP parser boundaries for xref-selected objects, object streams, named destinations, and WordPress navigation review.
- PDF stream payload bytes begin at the stream keyword following the stream dictionary. A `stream` word inside dictionary literal strings, hex strings, names, nested dictionaries, or comments is metadata and must not become the payload start.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfNamedDestinationExtractor` now locates stream payloads after the top-level stream dictionary boundary instead of using the first `stream` substring in an object body.
- The boundary scan skips PDF comments, literal strings with escaped/nested parentheses, hex strings, nested dictionaries, and whitespace before accepting a real stream keyword.
- Added a focused xref-stream/object-stream fixture where the authoritative catalog `/Names /Dests` entries are compressed in an object stream whose dictionary has `/Note (fake stream marker before real payload)`.
- Added a WordPress smoke that renders the recovered named destinations as Gutenberg list metadata while proving stale direct fallback destinations and decoy dictionary text stay hidden from visible page text.

## Evidence

Red-first focused run before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationStreamKeywordBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses the real object-stream payload when dictionary strings contain stream keywords
Expected: array (
  0 => 'Stream Keyword Start',
  1 => 'Stream Keyword Appendix',
  2 => 'LegacyCompressed',
)
Actual: array (
)
PASS keeps stream-keyword decoy dictionary text out of WordPress visible text and metadata

1 test files, 17 assertions, 1 failures
```

Focused gate after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationStreamKeywordBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses the real object-stream payload when dictionary strings contain stream keywords
PASS keeps stream-keyword decoy dictionary text out of WordPress visible text and metadata

1 test files, 24 assertions, 0 failures
```

Adjacent named-destination family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestination*Test.php
Focused test run: 26 selected test files (root lock skipped)
26 test files, 727 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-stream-keyword-boundary-currentbase.php
```

The smoke emits `destination_names=["Stream Keyword Start","Stream Keyword Appendix","LegacyCompressed"]`, `stream_keyword_dictionary_decoy_excluded=true`, `stale_direct_named_destination_bodies_excluded=true`, `visible_text_excludes_destination_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused named-destination assertions: new file at `24` assertions.
- New focused PASS cases: `+2`.
- `phpPass`: `2062 -> 2064`.
- `wordpressScenarios`: `1780 -> 1781`.
- `mappedPdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted named-destination name-tree extraction, legacy `/Dests`, duplicate key precedence, byte-wise `/Limits`, invalid `/Kids`, indirect arrays, exact object generations, action dictionaries, page-only destinations, invalid page operands, view-mode validation, xref-offset selection, xref-stream `/Prev`, object-stream member expansion, parser stream dictionary escape handling, parser name-tree JavaScript stream owner review, or generic text stream stack recovery. The bounded behavior is only named-destination object-stream payload discovery when a stream dictionary contains decoy `stream` text before the real stream keyword.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, xref-stream parser, object-stream decoder, stream-filter decoder, named-destination name-tree resolver, text extractor visible-text isolation, and WordPress smoke path. Full upstream OCR/model/PDFium parity remains intentionally out of scope under the current no-GPU markerPDF directive.
