# markerPDF xref object-stream named-destination header comment current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T212021Z`
Session: `port-dev-markerpdf-object-xref-20260605T212021Z`
Base accepted HEAD: `773fccc96bdf33d1c76679f0bbe94a6e82e54e4b`

## Source Truth

- Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable PDF parsing to `pdftext`/PDFium before OCR/model stages. In the native no-GPU PHP lane, xref-selected object streams and named-destination review metadata are parser dependency boundaries before WordPress import.
- PDF comments are lexical whitespace. A `/Type /ObjStm` header may contain `%` comments between object-number/member-offset integer pairs; numeric bytes inside comments must not shift explicit xref-stream type-2 member indexes.

## Implementation

`PdfNamedDestinationExtractor` now tokenizes object-stream header integer pairs with the same comment-aware PDF boundary rules used elsewhere in the lane. It ignores comment numeric decoys, accepts optional leading `+` on non-negative integer tokens, and fails closed if the declared `/N` pairs cannot be consumed cleanly before `/First`.

The focused fixture stores the current catalog, name-tree node, destination leaf, and destination dictionary in an xref-selected object stream whose header contains `% 99 123` decoy numbers after the first pair. Before the fix, the named-destination extractor only recovered the compressed catalog legacy destination and lost the name-tree entries because explicit member indexes were shifted.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationObjectStreamHeaderCommentCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps named-destination object-stream indexes aligned across commented header rows (lanes/markerpdf/tests/PdfNamedDestinationObjectStreamHeaderCommentCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Commented Start',
  1 => 'Commented Appendix',
  2 => 'LegacyCommented',
)
Actual: array (
  0 => 'LegacyCommented',
)

1 test files, 1 assertions, 1 failures
```

## Verification

Focused new test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationObjectStreamHeaderCommentCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps named-destination object-stream indexes aligned across commented header rows

1 test files, 23 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-object-stream-header-comment-currentbase.php
```

The smoke exits `0` and reports `destination_count=3`, `destination_names=["Commented Start","Commented Appendix","LegacyCommented"]`, `commented_object_stream_header_ignored_numeric_decoys=true`, `stale_direct_named_destination_bodies_excluded=true`, `visible_text_excludes_destination_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted text-side object-stream header comments, attachment object-stream header comments, comment-owned member offsets, `/First` boundary validation, incomplete headers, skipped zero rows, explicit type-2 index selection, zero-width member recovery, duplicate offset guards, stream-member rejection, xref-stream `/Prev` repair, or metadata/attachment object-stream offset boundaries. The bounded behavior is only the local `PdfNamedDestinationExtractor` object-stream header parser used for xref-stream type-2 named-destination metadata.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, xref-stream parser, object-stream decoder, named-destination extractor, text extractor, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.

## Next Task

Continue native markerPDF work on non-overlapping searchable-PDF parser behavior: fonts/CMaps, stream filters, xref repair, metadata, outlines, annotations/forms, page geometry, image/filter metadata, security preflight, and supplied-boundary table/equation handoffs.
