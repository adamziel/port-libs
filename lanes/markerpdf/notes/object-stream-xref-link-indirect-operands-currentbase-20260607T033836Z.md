# markerpdf object-stream xref link indirect operands current-base

Date: 2026-06-07 UTC
Slice: `markerpdf-object-stream-xref-parser-current-base-20260607T033836Z`
Accepted base: `c68cb830075ef5075ffd6409b99590167b419d49`

## Behavior

PDF object-stream dictionaries may place stream and member-splitting operands in indirect objects. This slice keeps xref-stream type-2 annotation ownership current when a Link annotation is compressed in an `/ObjStm` whose `/Length`, `/Filter`, `/N`, and `/First` values are selected indirect helper objects.

The native annotation review paths now resolve those selected helper operands before object-stream member expansion in:

- `PdfActionReviewExtractor`
- `PdfAnnotationExtractor`
- `PdfLinkAnnotationExtractor`

The focused fixture keeps a stale direct object `7 0 obj` in the file while the current xref stream selects object 7 from object stream 20. WordPress link metadata and span decoration must use the compressed annotation URI, and stale direct annotation text must not appear in review metadata or visible text.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkObjectStreamReviewBoundaryCurrentBaseTest.php`
  - `PASS uses xref-stream object-stream Link annotation bodies for annotation review before stale direct bodies`
  - `PASS resolves indirect object-stream decode operands before WordPress link annotation review`
  - `PASS rejects annotation object-stream member offsets inside literal strings before WordPress link promotion`
  - `1 test files, 81 assertions, 0 failures`
- `php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/Pdf(Annotation|Link).*CurrentBaseTest\.php$')`
  - `46 test files, 1508 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-link-annotation-object-stream-indirect-operands-currentbase.php`
  - exits 0
  - reports `annotation_objects=[7]`
  - reports `link_uris=["https://example.com/indirect-object-stream-link"]`
  - reports `stale_direct_annotation_excluded=true`
  - reports `annotation_payload_text_excluded_from_visible_text=true`
  - reports no Python/model/OCR, external PDF tools, or PDF action execution

## Non-overlap

This does not change the main searchable-text object-stream expansion path, xref `/Prev` repair, hybrid `/XRefStm` owner precedence, malformed `/Index` handling, zero-width carrier semantics, explicit out-of-range member-index rejection, duplicate object-stream offsets, stream-member rejection, or existing member offset token-boundary behavior.

## Dependency closure

No new dependency or support component is needed. The patch reuses the native PHP xref/object-stream decoders and existing Flate/ASCIIHex stream filters. GPU/model OCR, Surya/Texify/Torch execution, external PDF tools, and PDF action execution remain intentionally out of scope for this no-GPU markerPDF lane.
