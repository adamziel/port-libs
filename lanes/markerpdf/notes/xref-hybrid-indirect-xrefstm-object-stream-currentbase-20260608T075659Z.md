# Hybrid indirect XRefStm object-stream xref parser current-base slice

## Source truth

- PDF 1.5 hybrid-reference files can pair a compatibility classic xref table with a current xref stream named by the trailer `/XRefStm` entry.
- This lane already accepts direct `/XRefStm` byte offsets, whitespace-normalized offsets, and the existing compatibility policy where same-object classic table rows are preserved unless the stream row marks the object free.
- Native markerPDF import should still follow an indirect integer `/XRefStm` helper when the compressed current catalog/page members are intentionally omitted from the classic table and supplied by the hybrid xref stream.

## Implementation

- Added `PdfTextExtractor::hybridXrefStreamOffsetFromTrailer()` so hybrid xref-table paths resolve `/XRefStm` with the same indirect scalar helper used by `/Prev`, `/W`, `/Index`, object-stream `/N`, and `/First`.
- Rewired xref-entry, xref-review, free-owner, malformed stream-review, and trailer root/info/encrypt table branches to use the helper when current direct objects are available.
- Kept the existing hybrid table-preservation policy intact: type-2 stream rows do not replace same-object table rows; this slice covers compressed current members omitted from the classic table.

## Tests and smoke

- Red-first check before the production edit showed the new indirect helper fixture extracting stale classic-table text instead of current object-stream text. The final fixture was narrowed to avoid changing the accepted table-vs-stream ownership policy.
- Focused passing run:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridIndirectXrefStmObjectStreamCurrentBaseTest.php`
  - Result: `1 test files, 24 assertions, 0 failures`
- Adjacent passing run:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridXrefStmWhitespaceObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamFreeOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridFreeEntryOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedGraphCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridIndirectXrefStmObjectStreamCurrentBaseTest.php`
  - Result: `5 test files, 110 assertions, 0 failures`
- WordPress smoke:
  - `php lanes/markerpdf/examples/wordpress-pdf-xref-hybrid-indirect-xrefstm-currentbase.php`
  - Emits block markup for `Current indirect XRefStm object-stream page` and `Hybrid stream offset helper resolved`.

## Non-overlap

- Does not repeat direct `/XRefStm` whitespace normalization, malformed `/W` or `/Index` review, hybrid stream free-owner suppression, same-object table preservation, omitted current object-stream graph repair, object-stream `/N` or `/First` helper resolution, or encrypted permission preflight.
- Does not execute Python, OCR, Surya/Texify/Torch, raster PDF tools, actions, decryption, or external PDF utilities.

## Dependency closure

- No new support dependency is needed. The slice reuses the native PHP parser, xref stream decoder, object-stream decoder, and existing Flate stream filter support.

## Next task

- Continue native no-GPU markerPDF xref/object-stream work around generation-exact hybrid updates, malformed helper operands, and current update graph repair that preserves the accepted compatibility table policy.
