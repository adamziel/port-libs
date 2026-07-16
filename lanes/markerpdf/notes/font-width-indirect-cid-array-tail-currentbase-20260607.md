# markerpdf font width indirect CID array tail current-base

Slice: `markerpdf-font-width-advance-boundary-current-base-20260607T063714Z`

Accepted base: `5676fbf8a969a7c59e6fe2b0891edf6d0b0a69e1`

Behavior:

- Tightened native CIDFont width resolution so top-level `/W`, `/W2`, and `/DW2` arrays use the existing strict single-array guard.
- Tightened CID `/W` and `/W2` array-form segment helpers so indirect helper objects with trailing operands are rejected before text advance geometry is calculated.
- Clean indirect CID width helpers still resolve; tailed helpers fail closed to `/DW` or `/DW2`, preserving positioned WordPress paragraph word gaps instead of collapsing text runs.

Red-first evidence:

- Before the source edit, a synthetic Type0 CIDFont with `/W [1 6 0 R 5 9 1000]` and `6 0 obj [1000 1000 1000 1000] /Tail endobj` extracted `WideBlock` with first-span bbox `[0,0,48,12]`.
- After the fix, the same malformed helper extracts `Wide Block` with first-span bbox `[0,0,24,12]`, proving the tailed helper is rejected and `/DW 500` is used.

Focused verification:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php` => `1 test files, 631 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontMalformedWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php` => `2 test files, 674 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-font-width-indirect-cid-array-tail-currentbase.php` exits 0 and emits `Wide Block` with `indirect_cid_w_array_tail_rejected=true`.

Dependency closure:

- No new support component is needed. This reuses the existing native PDF object resolution, strict array reader, CID width parser, CMap text extraction, and WordPress paragraph smoke path. No Python, OCR, CUDA, model workers, external PDF tools, or live services are used.

Non-overlap:

- This does not change the existing clean indirect `/W` or `/W2` helper behavior, simple-font `/Widths` tail handling, encrypted permissions, xref repair, annotations, forms, image filters, OCR/model handoffs, or dashboard/progress files.
