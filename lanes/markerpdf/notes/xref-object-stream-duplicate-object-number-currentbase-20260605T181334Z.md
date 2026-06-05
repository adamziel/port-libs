# Xref Object Stream Duplicate Object Number Current Base

Slice: `markerpdf-object-stream-xref-parser-current-base-20260605T181334Z`

Accepted base: `9ead64905fb753cca25bfab3c1ec066d02d22a57`

## Source Truth

PDF xref-stream type-2 entries resolve object stream members by the selected object stream and member header index, but an object stream header that defines the same object number more than once is ambiguous for a native fail-closed parser. This slice rejects those duplicate header object-number members before WordPress paragraph extraction, even when the xref row explicitly selects one duplicate header slot.

## Behavior

- `PdfTextExtractor::objectsFromObjectStreams()` now skips object stream members whose header object number appears more than once.
- `extractXrefObjectStreamIndexReview()` now records `duplicate_header_object_number_rejection_count` and per-entry `duplicate_header_object_number_rejected`.
- The review selection policy reports `duplicate_header_object_number` for explicit duplicate rows while preserving the existing zero-width ambiguous policy.

## Verification

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateObjectNumberCurrentBaseTest.php` failed with `1 test files, 1 assertions, 1 failures` because `Duplicate object selected member leak` was extracted.
- Focused: `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateObjectNumberCurrentBaseTest.php` passed with `1 test files, 21 assertions, 0 failures`.
- Adjacent family: `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateObjectNumberCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateZeroWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateOffsetCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamStreamDictGenerationCurrentBaseTest.php` passed with `5 test files, 120 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-duplicate-object-number-currentbase.php` emitted `uses_current_guard_page=true`, `rejects_first_duplicate_member=true`, `rejects_xref_selected_duplicate_member=true`, `duplicate_header_object_number_rejection_count=1`, and `selection_policy=duplicate_header_object_number`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP xref-stream parser, FlateDecode stream decoding, and object-stream member-table extraction. It does not run OCR, Python, model workers, GPU code, or external PDF tools.

## Non-Overlap

This avoids accepted object-stream work for zero-width index recovery, duplicate member offsets, stream-member rejection, generation-aware carrier selection, current-carrier repair, and indirect stream filter operands. The new behavior is specifically duplicate header object numbers for xref-selected object-stream members.
