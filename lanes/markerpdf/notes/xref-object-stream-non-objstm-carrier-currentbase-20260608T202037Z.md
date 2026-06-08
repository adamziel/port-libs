# markerPDF xref object-stream non-ObjStm carrier current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260608T202037Z`
Session: `port-dev-markerpdf-object-xref-20260608T202037Z`
Base accepted HEAD: `e804d88dd32d5db061bbd8258db113c523e8f8c3`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to pdftext/PDFium before OCR/model fallback. This no-GPU PHP lane ports the native parser boundary: a PDF 1.5 xref-stream type-2 row points to an object inside a direct `/ObjStm` carrier. A direct `/Metadata` or other non-`/ObjStm` stream must not become a compressed-object carrier merely because it has `/N`, `/First`, `/Filter`, and decodable object-stream-shaped bytes.

## Change

`PdfTextExtractor::extractXrefObjectStreamIndexReview()` now requires the selected type-2 carrier body to be typed `/ObjStm` before decoding an object-stream member table. Non-`/ObjStm` direct streams remain unresolved object-stream carriers in review metadata, with `object_stream_carrier_is_objstm=false`, `object_stream_carrier_resolved=false`, `invalid_object_stream_carrier_rejected=true`, `object_stream_owner_policy=missing_object_stream_carrier`, and `selection_policy=missing_object_stream_member`.

The focused fixture builds a current xref stream where page object `4 0` is a type-2 row targeting object `6 0`, but object `6 0` is a `/Type /Metadata` stream whose payload looks like an object stream. WordPress-visible text imports only the valid current guard page; fake compressed-object page bytes inside the metadata stream stay excluded.

## Evidence

Red-first focused run before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamNonObjStmCarrierCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects xref-selected non object-stream carriers with object-stream-shaped bytes (lanes/markerpdf/tests/PdfXrefObjectStreamNonObjStmCarrierCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 0
1 test files, 13 assertions, 1 failures
```

Focused run after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamNonObjStmCarrierCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects xref-selected non object-stream carriers with object-stream-shaped bytes
1 test files, 25 assertions, 0 failures
```

Adjacent xref/object-stream parser family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStream*ObjectStream*CurrentBaseTest.php
Focused test run: 59 selected test files (root lock skipped)
...
59 test files, 1385 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-non-objstm-carrier-currentbase.php
```

Result: exits 0 and emits `uses_current_guard_page=true`, `metadata_stream_not_imported_as_page=true`, `metadata_member_note_suppressed=true`, `unresolved_object_stream_carrier_count=1`, `invalid_explicit_object_stream_carrier_count=0`, `object_stream_carrier_is_objstm=false`, `object_stream_carrier_resolved=false`, `invalid_object_stream_carrier_rejected=true`, `object_stream_owner_policy=missing_object_stream_carrier`, `selection_policy=missing_object_stream_member`, `page_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted explicit-zero carrier handling, zero-width carrier defaults, zero-width member-index recovery, duplicate header/member-offset rejection, stream-member rejection, indirect wrapper rejection, malformed `/W` and `/Index` handling, carrier repair, omitted carrier inference, explicit type-2 member-index selection, hybrid/free precedence, object-stream generation repair, or direct `/ObjStm` base preservation. The bounded behavior here is only xref-stream type-2 rows whose selected carrier is a direct non-`/ObjStm` stream with object-stream-shaped operands and bytes.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref stream parser, stream filter decoder, object-stream member parser, page-tree walker, content-token extractor, and WordPress smoke path. GPU/OCR/model execution, pdftext, pypdfium2/PDFium, PIL, Surya/Torch, Texify, Streamlit/FastAPI model workers, benchmark/model downloads, and external PDF tools were not run and remain intentionally out of scope for this markerPDF lane.

## Next

Continue with non-overlapping native searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
