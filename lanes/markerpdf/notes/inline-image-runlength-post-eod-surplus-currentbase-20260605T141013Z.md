# markerPDF inline image RunLength post-EOD surplus boundary current base

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T141013Z`
Base accepted HEAD: `52394894fe770269b8e2ae4edf4a1b9535bc8e02`

## Source Truth

Upstream `sddai/markerPDF` routes searchable PDF text extraction through parser-backed page text before image rendering, OCR, table models, or equation models. Under the current no-GPU markerPDF scope, the PHP lane owns native content-stream tokenization for `BI ... ID ... EI` inline image boundaries and must keep raster bytes out of WordPress paragraphs.

PDF RunLengthDecode data terminates at the `128` EOD control byte. If malformed non-whitespace bytes after that EOD contain delimiter-looking `EI`, the tokenizer should not reopen text parsing at the fake delimiter. It should keep those bytes image-owned until a later real inline-image terminator while preserving the following visible page text.

## Behavior

`PdfTextExtractor::inlineImageCandidateMatchesDictionary()` now checks RunLength post-EOD surplus alongside the existing ASCIIHex, ASCII85, Flate, LZW, and stacked native filter surplus recovery paths.

The new helper parses the RunLength control stream to the real EOD byte, requires non-whitespace post-EOD surplus containing a fake `EI`, decodes only through the EOD byte, and accepts the later real `EI` only after the decoded bytes reach the declared inline image sample floor.

The WordPress smoke adds the same malformed RunLength inline image and reports `runlength_post_eod_surplus_payload_excluded_until_real_ei=true` while keeping surplus payload text out of Gutenberg paragraphs.

## Evidence

Red-first focused run before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 388 assertions, 1 failures
```

The failing case extracted only `Before RunLength Post EOD Inline Image`; the paragraph after the real inline-image terminator was swallowed.

Focused green after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 396 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

The smoke emits `runlength_post_eod_surplus_payload_excluded_until_real_ei=true`, `fake_ei_inside_runlength_post_eod_surplus_payload=true`, `visible_text_imported=true`, `excluded_inline_image_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted RunLength preview post-EOD rejection, RunLength ordinary EOD sample decoding, ASCIIHex/ASCII85/LZW post-EOD tokenizer recovery, Flate post-stream surplus recovery, stacked native filter surplus recovery, filtered sample-floor acceptance, inline ImageMask/Indexed/JBIG2/JPX/DCT/CCITT review-only boundaries, unsupported-filter fallback, image XObject review, xref repair, metadata, annotations, forms, page geometry, OCR/model execution, or supplied-boundary table/equation handoffs.

The bounded behavior is specifically text-tokenizer recovery for single-filter RunLength inline images whose valid EOD byte is followed by malformed surplus containing fake `EI` bytes before the later real inline-image terminator.

## Dependency Closure

No new support component is needed. This reuses native PHP content-stream tokenization, inline image dictionary parsing, RunLength EOD parsing, decoded sample-floor calculation, text extraction, and the existing WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium/PIL rasterization, external PDF tools, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
