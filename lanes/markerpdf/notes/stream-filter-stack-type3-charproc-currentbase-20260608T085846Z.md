# markerPDF Type3 CharProc stream-filter stack boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260608T085846Z`

## Source Truth

- Upstream `sddai/markerPDF` delegates searchable-PDF text and glyph/font handling to PDF parser/pdftext-style native stream decoding before OCR/model fallback.
- Under the current no-GPU markerPDF lane, the PHP port owns this native parser boundary. Type3 `/CharProcs` are content streams used for glyph width/resource review, but their stream filter stacks must still end cleanly before their decoded prefix is trusted.

## Behavior

This slice tightens Type3 CharProc stream decoding to use the same bounded native filter-stack policy already used for page content. A filtered CharProc such as:

```text
/Filter /ASCIIHexDecode
stream
  <hex for "1000 0 d0">> 250 0 d0 BT ... tail ... ET
endstream
```

now fails closed for CharProc width extraction and Type3 image-resource traversal because non-whitespace bytes appear after the explicit filter EOD marker. Clean filtered CharProcs still provide glyph advances, while malformed tailed CharProcs fall back to declared font widths. CharProc payload text remains excluded from visible WordPress paragraphs.

## Red Probe

Before the source change, the focused fixture accepted the tailed CharProc prefix as a wide glyph and grouped the second line incorrectly:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackType3CharProcCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects tailed Type3 CharProc stream-filter EOD bytes before WordPress text grouping
Expected: array (
  0 => 'GoodWide',
  1 => 'Bad Gap',
)
Actual: array (
  0 => 'GoodWide',
  1 => 'BadGap',
)
1 test files, 1 assertions, 1 failures
```

## Evidence

Focused test after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackType3CharProcCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects tailed Type3 CharProc stream-filter EOD bytes before WordPress text grouping
1 test files, 12 assertions, 0 failures
```

Adjacent stream-filter and Type3 family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackType3CharProcCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsWidthVectorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFontMatrixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDuplicateMetricBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsInlineImageBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterTrailingPayloadBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackOverdeclaredLengthCurrentBaseTest.php
Focused test run: 9 selected test files (root lock skipped)
9 test files, 556 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-type3-charproc-currentbase.php
```

The smoke emits `clean_type3_width_preserved=true`, `tailed_type3_filter_stack_rejected=true`, `post_eod_charproc_tail_excluded=true`, `charproc_payload_text_excluded=true`, `executes_pdf_actions=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, and renders only:

```html
<p>GoodWide</p>
<p>Bad Gap</p>
```

## Non-Overlap

This does not repeat accepted page-content stream-filter stack recovery, image XObject filter-tail review, attachment filter-stack decoding, inline-image tokenizer boundaries, CMap malformed filter boundaries, Type3 duplicate metric, Type3 graphics-state/path/inline-image metric validation, Type3 CharProc fallback exclusion, object-stream/xref repair, annotations, forms, metadata, OCR, Surya/Texify/Torch, or model-worker behavior.

The bounded behavior is specifically Type3 CharProc stream decoding with non-whitespace bytes after an explicit native filter EOD marker.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream dictionary reader, filter-stack decoder, bounded filter-end enforcement, Type3 CharProc width parser, Type3 image-resource review path, and WordPress smoke harness. Full upstream OCR/model/PDFium parity remains intentionally out of scope under the current no-GPU markerPDF direction; no Python, pypdfium2/PDFium, PIL, OCR, Surya/Torch/Texify, Streamlit/FastAPI workers, online services, or external PDF tools were executed.
