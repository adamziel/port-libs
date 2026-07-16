# Stream Filter Stack EarlyChange Boundary - 2026-06-06

Slice: `markerpdf-stream-filter-stack-boundary-current-base-20260606T004221Z`

Accepted base: `dfbe19b18b25966b701cf815e7f2abbcc322da8f`

## Source Truth

- Upstream markerPDF decodes searchable PDF page streams natively before model/OCR stages; this no-GPU lane keeps the behavior inside the PHP parser rather than invoking OCR/model workers.
- PDF stream `DecodeParms` are filter-local. `/EarlyChange` is the LZWDecode early code-width parameter; a non-default value on an ASCII85/Flate stage is malformed for that stage and should fail closed without aborting later valid content streams.

## Behavior

- Added a page-content stream stack where `/ASCII85Decode /FlateDecode` carries `/DecodeParms [ << /EarlyChange 0 >> null ]`.
- The malformed non-LZW stream is rejected before WordPress text import, so its payload cannot leak into Gutenberg paragraphs.
- A sibling `/LZWDecode /FlateDecode` stream with the same `/EarlyChange 0` parameter still decodes, proving valid LZW EarlyChange handoff remains supported.

## Evidence

Red-first focused probe before source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php`

Result: `1 test files, 281 assertions, 1 failures`; the failing case showed `EarlyChange Non LZW Leak` in extracted text.

After source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php`

Result: `1 test files, 291 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-stream-filter-earlychange-boundary-currentbase.php`

Result: emits `non_lzw_earlychange_rejected=true`, `lzw_earlychange_preserved=true`, `visible_fallback_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted stream filter work for ASCII85 EOD recovery, stale/short Length recovery, null filter slots, compact DecodeParms alignment, Crypt Identity/default handling, indirect filter object boundaries, duplicate stream keys, duplicate DecodeParms parameters, malformed CMap filters, attachment stream filter stacks, or object-stream/xref/form boundaries. It only closes the remaining LZW-only `/EarlyChange` DecodeParms applicability gap for page-content stream stacks.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP stream filter stack, DecodeParms parser, ASCII85/Flate/LZW decoders, and WordPress smoke harness. GPU/OCR/model parity remains intentionally out of scope for this no-GPU markerPDF lane.
