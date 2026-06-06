# markerpdf xref Prev chain zero-width offset current-base

Slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260606T215427Z`

Accepted base: `dee21061aaf1fbb0aab4f4e3f945291f29676e20`

## Behavior

PDF xref-stream type-one rows may omit field two when the `/W` array sets its
offset width to zero, for example `/W [1 0 1]`. In that case the row carries
no explicit byte offset. The text, metadata, and embedded-file extractors
already repaired those current update rows by falling back to the latest
same-generation direct object before stale `/Prev` rows. The attachment
preflight path had a private xref-stream parser that treated the omitted field
as an explicit offset `0`, so `attachmentSummary()` dropped current
FileSpec/EmbeddedFile objects and returned an empty attachment set.

This patch carries `offsetIsExplicit` through `PdfAttachmentExtractor`
xref-stream rows and only fails closed on a missing offset match when the field
was actually present. Omitted offset fields now use the latest same-generation
direct object, preserving current WordPress attachment imports before stale
previous-section objects.

## Evidence

Red-first focused run on the accepted base after adding the fixture:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php`

Result: `1 test files, 541 assertions, 1 failures`; the new zero-width offset
case failed because `attachmentSummary()` returned zero attachments.

Focused run after the source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php`

Result: `1 test files, 554 assertions, 0 failures`.

Adjacent xref/attachment focused run after the source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainOmittedCurrentRowsCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamZeroWidthCarrierCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php`

Result: `4 test files, 1056 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-incremental-update-currentbase.php`

Result: exits `0`; the smoke comment reports
`zero_width_xref_stream_offset_current_attachment_selected`,
`zero_width_xref_stream_offset_attachment_preflight_selected`,
`zero_width_xref_stream_offset_field_omitted`,
`zero_width_xref_stream_offset_stale_prev_excluded`, and
`zero_width_xref_stream_offset_no_runtime_execution` as true.

## Scope And Dependencies

No GPU/model/OCR work was run or required. The implementation reuses the
existing native PHP xref stream parser, direct-object scanner, embedded-file
extractor, metadata extractor, text extractor, and attachment preflight
summary path. No new support component is needed.

Non-overlap: this slice does not repeat DCTDecode marker-fill recovery,
compressed-object `/Prev` helper repair, xref-stream damaged explicit-offset
repair, classic xref-table repair, xref-stream `/Index` direct-offset repair,
or root-fallback suppression. It only covers current xref-stream type-one rows
whose offset field is omitted by `/W [1 0 1]` and the attachment preflight
selection that previously lagged the other markerPDF extractors.

Root harness: not run - isolated micro-slice.
