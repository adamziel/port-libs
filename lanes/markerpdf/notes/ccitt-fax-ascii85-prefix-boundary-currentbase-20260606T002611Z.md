# markerPDF CCITT Fax ASCII85 Prefix Boundary

Session: `port-dev-markerpdf-ccitt-fax-filter-20260606T002611Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260606T002611Z`
Base accepted HEAD: `4328ba37a1a542cd6b6713fbb7ffa247d9cc68a2`

## Source Truth

Upstream `sddai/markerPDF` at the manifest-pinned commit routes searchable PDF text separately from image rendering. Under the current no-GPU native PHP scope, CCITT Fax image bytes remain preview-only and must not become WordPress paragraph text, but native filters before `/CCITTFaxDecode` still define safe stream ownership.

For `/Filter [/ASCII85Decode /CCITTFaxDecode]`, the ASCII85 `~>` marker only terminates the native prefix member. A stale `/Length`, fake `endstream/endobj`, or fake page-content object immediately after an early ASCII85 member must not close the image stream unless the decoded member reaches the CCITT EOFB/RTC/EOL boundary. A later bounded ASCII85 member that decodes to complete CCITT bytes can own the stream terminator without raster decoding.

## Behavior

`PdfTextExtractor::ccittFaxStreamEndstreamTerminatorOffset()` no longer accepts a prefix-filter EOD marker by itself before validating the decoded CCITT boundary. The CCITT native-prefix fallback now also scans bounded ASCII85 members, including later `<~...~>` members after stale owner decoys, and accepts the candidate only when the decoded fax bytes reach the configured CCITT boundary.

The focused fixture maps:

- an Image XObject with `/Filter [/ASCII85Decode /CCITTFaxDecode]`;
- an early ASCII85 member that decodes to incomplete fax bytes;
- a fake `endstream/endobj` plus `9 0 obj` page-content decoy immediately after that early member;
- a later ASCII85 member whose decoded bytes end with the Group 4 EOFB marker;
- review-only metadata with `native_raster_decode=false` and no payload text leakage.

## Red-First Evidence

Before the production change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
FAIL keeps ASCII85-wrapped CCITT Fax EOD decoys inside image payload boundaries
Actual: array (
  0 => 'Before ASCII85 CCITT stream',
  1 => 'ASCII85 CCITT early EOD leak',
  2 => 'After ASCII85 CCITT stream',
)
1 test files, 709 assertions, 1 failures
```

Baseline before adding this focused case was:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
1 test files, 708 assertions, 0 failures
```

## Verification

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
1 test files, 742 assertions, 0 failures
```

Adjacent image/filter boundary family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
5 test files, 2706 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-ascii85-prefix-boundary-currentbase.php
```

The smoke exits 0 and emits `ascii85_prefix_owned_until_ccitt_eofb=true`, `stale_owner_payload_excluded_from_visible_text=true`, `stale_owner_payload_excluded_from_review=true`, `decoded_with_current_filters=false`, `native_raster_decode=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused PASS cases: `2295 -> 2296`.
- Focused assertions in `PdfCcittFaxFilterBoundaryCurrentBaseTest.php`: `708 -> 742`.
- WordPress scenarios: `1972 -> 1973`.

## Non-Overlap

This does not repeat accepted CCITT image-only payload exclusion, malformed/unresolved/duplicate DecodeParms fail-closed metadata, escaped CCITT names, PDF comment parsing, null-filter DecodeParms alignment, direct EOFB/RTC ownership, row EOL/Rows/Height ownership, ImageMask polarity, nested mask/alternate review, Flate/LZW/RunLength/Crypt prefix ownership, ASCIIHex prefix metadata, DCT/JPX/JBIG2 boundaries, inline image payload exclusion, or OCR/model behavior.

The bounded behavior is specifically ASCII85Decode as the native prefix before preview-only CCITTFaxDecode: an early ASCII85 EOD before incomplete fax bytes cannot reopen fake stream owners, while a later ASCII85 member that decodes to EOFB-complete CCITT bytes owns the stream boundary.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, stream dictionary reader, filter resolver, ASCII85 decoder, CCITT ownership marker logic, Image XObject review metadata path, and WordPress smoke renderer. Full CCITT raster decoding remains intentionally out of scope under the no-GPU markerPDF directive; no Python, OCR, model, PDFium, PIL, external PDF tool, or live-service provider execution was run.
