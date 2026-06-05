# markerPDF CCITT Fax LZW Prefix Boundary

Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T192315Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable PDF text extraction separate from image/OCR model paths. In this no-GPU native PHP lane, `CCITTFaxDecode` image bytes remain preview-only and must not be promoted to WordPress text, but native filters that precede the CCITT filter still determine image-stream ownership.

PDF `LZWDecode` streams have an explicit EOD code. For `/Filter [/LZWDecode /CCITTFaxDecode]`, a stale declared `/Length` or embedded `endstream/endobj` decoy after an early incomplete LZW member cannot close the image stream unless the native LZW member also decodes to fax bytes that reach the CCITT EOFB or row boundary.

## Behavior

`PdfTextExtractor::ccittFaxStreamEndstreamTerminatorOffset()` now has a CCITT-specific LZW prefix repair path. After the normal native-prefix decode fails, the parser scans bounded LZW member starts before a candidate `endstream` terminator, verifies the member has an explicit LZW EOD with only whitespace after it, decodes that member, and accepts the candidate only when the decoded bytes reach the CCITT ownership boundary.

The focused fixture builds an image XObject with `/Filter [/LZWDecode /CCITTFaxDecode]`, stale `/Length`, an early LZW member that does not contain a CCITT EOFB, an embedded fake `9 0 obj` text stream referenced from page `/Contents`, and a later LZW member whose decoded bytes end with a CCITT EOFB marker. Before the fix, text extraction reopened the fake object. After the fix, the whole encoded image payload remains owned by the image stream and WordPress text contains only the real before/after text.

## Evidence

Red-first local probe before source edit:

```text
lines=["Before LZW CCITT stream","LZW CCITT early EOD leak","After LZW CCITT stream"]
raw_length=6
encoded_length=137
filters=["LZWDecode","CCITTFaxDecode"]
native_prefix_filters=["LZWDecode"]
```

Focused green after source/test edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
1 test files, 608 assertions, 0 failures
```

Adjacent DCT/LZW boundary family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
1 test files, 523 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-lzw-prefix-boundary-currentbase.php
source=native-pdf-ccitt-fax-lzw-prefix-boundary-currentbase
visible_text="Before LZW CCITT import\nAfter LZW CCITT import"
stream_filters=["LZWDecode","CCITTFaxDecode"]
preview_only_filters=["CCITTFaxDecode"]
native_prefix_filters=["LZWDecode"]
lzw_member_eod_ignored_until_ccitt_boundary=true
stale_owner_payload_excluded_from_visible_text=true
stale_owner_payload_excluded_from_review=true
decoded_with_current_filters=false
native_raster_decode=false
executes_python_or_models=false
executes_external_pdf_tools=false
```

Required local checks passed:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-lzw-prefix-boundary-currentbase.php
php -r 'json_decode(...)' lanes/markerpdf/lane-status.json
git diff --check -- lanes/markerpdf
```

Root harness status: not run - isolated micro-slice.

Status delta: `lane-status.json` `phpPass` `2169 -> 2170`, WordPress scenarios `1869 -> 1870`.

## Non-Overlap

This does not repeat accepted CCITT image-only exclusion, malformed/unresolved/extra DecodeParms fail-closed handling, compact null-filter DecodeParms arrays, escaped CCITT names, Flate/Crypt/RunLength/ASCIIHex native prefixes, direct CCITT EOFB/RTC ownership, row-EOL ownership, inline CCITT tokenizer boundaries, alias metadata, nested mask/alternate/polarity metadata, DCT LZW prefix behavior, or generic LZW stream filter stack recovery.

The new behavior is specifically LZWDecode as the native prefix before preview-only CCITTFaxDecode: an incomplete early LZW member and embedded stale owner decoy cannot close the image stream unless a bounded LZW member decodes to bytes that reach the CCITT boundary.

## Dependency Closure

No new support component is needed. This slice reuses the native object parser, stream filter resolver, LZW decoder/EOD scanner, CCITT ownership marker logic, image-XObject review metadata path, and WordPress smoke renderer. Full CCITT fax raster decoding remains intentionally out of scope for the no-GPU markerPDF lane; the boundary remains review-only and does not invoke Python, PDFium, OCR, models, or external PDF tools.
