# markerpdf object stream xref parser current base 20260607T082214Z

## Scope

Lane: `markerpdf`

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260607T082214Z`

Accepted base: `88f1e22fbe8fb31ab1773f697eb872e68d918898`

This slice keeps the work inside the native no-GPU markerPDF parser boundary. It does not run OCR, Surya, Texify, Torch, PDFium, external PDF tools, model workers, or live-service tests.

Upstream markerPDF delegates searchable-PDF extraction through `marker/pdf/extract_text.py` into parser-backed `pdftext` / PDFium behavior before model fallback. The PHP port's equivalent no-GPU boundary is to trust xref-selected object-stream members only when the selected offset starts at a real top-level PDF object token. Offsets that point into another member's literal string are parser ambiguity, not a page or metadata object.

## Behavior

`PdfPagePropertyExtractor` now applies object-stream member token-boundary validation before materializing compressed page-review objects. The same helper also ignores malformed later member offsets when choosing the end of an earlier valid member, so a decoy offset inside a page dictionary's `/Note` literal string cannot truncate the current page body.

The focused fixture builds an xref stream where:

- object `3 0` is the current compressed `/Page` carrying `/PieceInfo` and a page-associated source FileSpec;
- object `30 0` is declared as another compressed member, but its offset points into object `3`'s literal `/Note` string at a fake page dictionary;
- the page tree lists both `3 0 R` and `30 0 R`.

Expected native behavior is one page-review row for object `3`, with the source FileSpec preserved, while object `30` and its hidden content stay excluded.

## Verification

- `php -l lanes/markerpdf/src/PdfPagePropertyExtractor.php`
  - `No syntax errors detected`
- `php -l lanes/markerpdf/tests/PdfPagePropertyObjectStreamOffsetBoundaryCurrentBaseTest.php`
  - `No syntax errors detected`
- `php -l lanes/markerpdf/examples/wordpress-pdf-page-property-object-stream-offset-boundary-currentbase.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfPagePropertyObjectStreamOffsetBoundaryCurrentBaseTest.php`
  - `1 test files, 15 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfPagePropertyObjectStreamOffsetBoundaryCurrentBaseTest.php`
  - `2 test files, 264 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-page-property-object-stream-offset-boundary-currentbase.php`
  - exits `0`
  - smoke flags include `current_page_review_selected=true`, `source_associated_file_preserved=true`, `visible_text_preserved=true`, `decoy_page_review_excluded=true`, `hidden_decoy_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted object-stream text offset boundaries, whitespace-offset rejection, comment-offset rejection, metadata/attachment/AcroForm object-stream offset guards, xref-stream row alignment, xref `/Prev` generation repair, omitted compressed graph repair, object-stream carrier repair, stream filters, page-resource inheritance, annotation/link promotion, or catalog XMP omitted-Type metadata behavior. The bounded behavior here is only page-property/page-review object-stream expansion rejecting selected member offsets inside literal-string decoys and preserving the valid earlier member body when a later offset is malformed.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref-stream decoder, object-stream decoder, page-tree walker, page review metadata extractor, associated-file review path, and text extractor smoke path. Remaining full upstream/model parity stays intentionally out of scope under the current no-GPU markerPDF directive: live OCR, Surya layout/table/OCR models, Texify equation recognition, Torch/model batching, Streamlit/FastAPI workers, and exact upstream benchmark parity.

## Next

Continue with non-overlapping native searchable-PDF parser behavior, preferably remaining xref repair, font/CMap widths, stream filters, metadata, outlines, annotations/forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
