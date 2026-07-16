# Stream Filter Stack Inner ASCII85 EOD Boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260606T082852Z`
Base accepted HEAD: `d4fda378b493c1d4cfb146e3047adc8146f5470a`
Lane: `markerpdf`

## Source Truth

This slice stays inside the native no-GPU searchable-PDF parser scope. Upstream markerPDF delegates PDF stream decoding to parser/PDF dependencies; for the PHP port, the relevant contract is the PDF filter boundary itself: explicit-terminator filters such as ASCII85Decode must provide their EOD marker before decoded bytes are trusted as page content. This is a native parser behavior and does not require OCR, model workers, Surya, Texify, Torch, pypdfium, PIL, or external PDF tools.

## Red-First Evidence

Before the source edit, a focused probe built a page with `/Filter [ /FlateDecode /ASCII85Decode ]` where the outer Flate stream decoded successfully but the inner ASCII85 bytes lacked `~>`. The parser imported the unterminated payload:

```text
Missing Inner ASCII85 EOD Leak
Visible After Missing EOD
```

That showed bounded filter-stack validation was still allowing missing explicit terminators for inner stack stages when `requireBoundedExplicitFilterEndMarkers` was active without the older explicit-marker flag.

## Patch

- `PdfTextExtractor::decodeStream()` now calls bounded filter-end validation without the legacy missing-explicit-marker allowance.
- Added a focused page-content regression where the missing inner ASCII85 EOD stream is skipped, a valid Flate-to-ASCII85 stream with `~>` still imports, and later unfiltered page content remains visible.
- Updated the WordPress stream-filter stack smoke with the same fail-closed boundary and explicit metadata flags.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
=> 1 test files, 335 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterTrailingPayloadBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterTerminatorBoundaryCurrentBaseTest.php
=> 4 test files, 553 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
=> missing_inner_ascii85_eod_stack_fail_closed=yes
=> valid_inner_ascii85_eod_stack_preserved=yes
=> requires_inner_ascii85_eod_after_flate_stack_boundary=yes
=> Missing Inner ASCII85 EOD Leak=no
=> Valid Inner ASCII85 EOD Import=yes
=> Visible After Missing Inner EOD Boundary=yes
```

## Non-Overlap

This does not repeat accepted CMap missing inner ASCII85 EOD coverage, attachment filter terminator handling, trailing payload rejection, missing-Length fake `endstream` recovery, null DecodeParms alignment, Crypt identity filtering, RunLength EOD, LZW EOD, or named destination behavior. The new coverage is specifically page-content `/FlateDecode` then `/ASCII85Decode` where the decoded inner ASCII85 stage lacks `~>`.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP PDF stream parser and filter-stack validators. Remaining out-of-scope gaps are model/OCR-dependent scanned-PDF layout and exact upstream model benchmark parity under the current no-GPU markerPDF scope.
