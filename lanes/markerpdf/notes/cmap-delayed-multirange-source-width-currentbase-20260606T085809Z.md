# markerpdf-cmap-source-width-fallback-current-base-20260606T085809Z

Accepted base: `f3d402271e67e16c0f92a8d2b6bb491f3a9233bd`

## Behavior

Native PHP CMap source-width extraction now handles CID and ToUnicode ranges
whose declared source range starts before multiple valid code-space windows.
The multi-code-space sequence rank starts at the first valid code-space window
at or after the declared range start, matching the accepted single-window
delayed-start behavior.

This keeps searchable-PDF imports on the no-GPU path aligned with marker/pdftext
style structured text extraction: valid CMap source codes drive Unicode text,
CIDFont `/W` widths, word-spacing decisions, and styled spans without running
OCR, models, pypdfium, or external PDF tools in this port lane.

## Evidence

- Red-first focused inline probe before the source edit did not complete within
  the focused probe window on a two-window delayed CMap range; it was stopped
  after the current fallback scanned the invalid gap instead of ranking through
  the valid code-space windows.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php`
  - Result: `1 test files, 378 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthOrderCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeToUnicodeBfrangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapMultiRangeSparseSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeCidRangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapNotdefCharSourceWidthCurrentBaseTest.php`
  - Result: `6 test files, 448 assertions, 0 failures`

## WordPress Smoke

Added `examples/wordpress-pdf-cmap-delayed-multirange-source-width-currentbase.php`.
It emits Gutenberg paragraph text `ABCD` and validates:

- `delayed_multi_codespace_bfrange_resolved=true`
- `first_codespace_source_width_word_spacing_applied=true`
- `second_codespace_uses_compact_sequence_offset=true`
- `visible_text_clean=true`
- no Python, model, OCR, pypdfium, PIL, or external PDF tool execution

## Non-Overlap

This slice does not repeat accepted source-width work for zero padding,
Identity/UCS2 defaults, partial metric misses, TJ adjustments, vertical W2,
odd hex padding, UseCMap order, explicit high/low CID ranges, notdef rows,
single-window delayed code-space starts, sparse ranges whose CMap range starts
inside the first valid code-space window, invalid later CID ranges, or bytewise
code-space boundary checks.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
CMap parser, ToUnicode lazy bfrange fallback, CID CMap lazy range fallback,
CIDFont `/W` metrics, and styled text grouping. The remaining out-of-scope gap
is the supervisor-defined no-GPU/model boundary for OCR and upstream model
benchmark parity.
