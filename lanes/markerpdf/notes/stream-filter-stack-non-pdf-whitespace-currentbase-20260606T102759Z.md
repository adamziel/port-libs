# markerPDF Stream Filter Stack Non-PDF Whitespace Boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260606T102759Z`

Base accepted HEAD: `42fdc6ac8852fb015d719b5c26ba483c909bd979`

## Source Truth

PDF lexical whitespace is limited to NUL, horizontal tab, line feed, form feed, carriage return, and space. ASCIIHexDecode and ASCII85Decode stream filters may ignore PDF whitespace inside encoded data, but a vertical tab byte (`0x0B`) is not PDF whitespace and must not be silently stripped before native page-content text extraction.

This patch keeps the native no-GPU parser path in scope only. It does not invoke OCR, Surya, Texify, Torch, PDFium, PIL, external PDF tools, or live services.

## Behavior

- `PdfTextExtractor::decodeAsciiHexStream()` now strips only exact PDF filter whitespace and rejects any other non-hex byte, so vertical tab no longer turns malformed ASCIIHex page content into visible WordPress text.
- ASCII85Decode uses the same exact filter-whitespace helper, so vertical tab no longer disappears inside ASCII85 filter groups.
- Bounded ASCIIHex streams without a `>` EOD marker remain accepted only when the bounded payload contains hex digits plus exact PDF filter whitespace, preserving declared `/Length` stack behavior while rejecting vertical-tab payloads.
- The focused WordPress smoke now proves malformed ASCIIHex/ASCII85 vertical-tab payload text stays excluded while a sibling visible stream still imports.

## Red-First Evidence

Before the source edit, two focused `php -r` probes against current base imported malformed visible text:

```text
array (
  0 => 'Vertical Tab ASCIIHex Leak',
  1 => 'Visible After Vertical Tab Filter Whitespace',
)
array (
  0 => 'Vertical Tab ASCII85 Leak',
  1 => 'Visible After Vertical Tab ASCII85',
)
```

Baseline focused stack-boundary test before edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
1 test files, 335 assertions, 0 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
1 test files, 347 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfParserInlineStreamLengthFilterRepairCurrentBaseTest.php
3 test files, 651 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
```

The smoke exits 0 and emits `vertical_tab_asciihex_filter_data_rejected=true`, `vertical_tab_ascii85_filter_data_rejected=true`, `non_pdf_filter_whitespace_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted ASCIIHex success-path decoding, ASCII85 EOD recovery, stale/missing stream length recovery, null-filter DecodeParms alignment, compact DecodeParms arrays, duplicate `/Filter` or `/DecodeParms` declarations, duplicate DecodeParms parameters, parser-comment split references, malformed indirect filter helper objects, CMap filter operand/EOD boundaries, inline-image ASCIIHex/ASCII85 tokenizer boundaries, image XObject review metadata, DCT/CCITT/JPX/JBIG2 image-filter exclusion, xref/object-stream filter recovery, or pdftext dictionary work. The bounded behavior is only non-PDF vertical-tab bytes inside visible page-content ASCIIHex/ASCII85 stream-filter data, plus the adjacent declared-length ASCIIHex guard needed to preserve existing stack-boundary behavior under the stricter classifier.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream dictionary parser, filter-stack resolver, ASCIIHex/ASCII85 decoders, visible content tokenizer, and existing WordPress smoke renderer. Broader OCR/model parity remains intentionally out of scope under the current no-GPU markerPDF directive.
