# markerPDF object-stream stale carrier replacement current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260608T162504Z`
Session: `port-dev-markerpdf-object-xref-20260608T162504Z`
Base accepted HEAD: `2dfac60b3ecf5cade7f5a6e43e0b3b9fc6d479f4`

## Source Truth

Upstream markerPDF routes searchable-PDF text through parser layers before OCR/model execution. In the native no-GPU PHP scope, PDF 1.5 object-stream xref rows must preserve parser ownership: `/ObjStm` carriers are direct stream objects, and malformed type-2 xref rows for the carrier object itself must not resurrect an older same-number object-stream carrier after a newer direct non-ObjStm replacement appears.

## Behavior

This patch tightens `PdfTextExtractor::liveDirectObjectDefinition()` for type-2 rows. The previous repair path intentionally preserved direct `/ObjStm` or `/XRef` owners when malformed xref rows tried to hide them. It now preserves that direct owner only when it is the latest same-number direct object definition. If a later direct non-ObjStm replacement exists, the carrier stays unresolved and stale compressed page members remain excluded from WordPress paragraphs.

## Red-First Evidence

Before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamStaleCarrierReplacementCurrentBaseTest.php`

Result: `1 test files, 1 assertions, 1 failures`

Failure excerpt: expected only `Current carrier replacement guard page`, but actual text also included `Stale resurrected carrier page leak` and `Old ObjStm must stay suppressed`.

## Verification

After the fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamStaleCarrierReplacementCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamCurrentFreeCarrierRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamInheritedCarrierCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamObjectOwnerCycleCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamCarrierType2MetadataAttachmentCurrentBaseTest.php`

Result: `5 test files, 116 assertions, 0 failures`

`php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-stale-carrier-replacement-currentbase.php`

Result: exits 0 and emits `uses_current_guard_page=true`, `suppresses_stale_carrier_member=true`, `suppresses_replacement_metadata_text=true`, `compressed_entry_count=2`, `unresolved_object_stream_carrier_count=2`, `carrier_resolved=false`, `owner_policy=compressed_object_stream_carrier_unavailable`, `selection_policy=missing_object_stream_member`, `page_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

`php -l lanes/markerpdf/src/PdfTextExtractor.php && php -l lanes/markerpdf/tests/PdfXrefObjectStreamStaleCarrierReplacementCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-stale-carrier-replacement-currentbase.php`

Result: no syntax errors.

`git diff --check -- lanes/markerpdf`

Result: exits 0.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted current carrier repair, free carrier repair, inherited `/Prev` carrier reuse, previous type-2 carrier storage review, xref-stream owner-cycle preservation, explicit zero/zero-width carrier rejection, omitted graph repair, object-stream offset/token-boundary rejection, stream-member rejection, row-alignment/overflow guards, or stream filter DecodeParms work. The bounded behavior is only stale direct `/ObjStm` carrier resurrection when a later same-number direct non-ObjStm replacement exists and the current xref stream marks the carrier object itself as type-2.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, xref-stream decoder, object-stream expander, page-tree text extraction, review metadata, and WordPress smoke harness. Live OCR, Surya/Texify/Torch model execution, PDFium/pypdfium runtime rendering, decryption, action execution, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
