# markerPDF CCITT Fax RunLength Prefix Boundary

Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T134700Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` reaches searchable PDF text through structured PDF text extraction and delegates image rendering/OCR to separate preview/model paths. In the no-GPU native PHP lane, CCITTFaxDecode image data remains preview-only and must not become visible WordPress text, but native filters that appear before CCITTFaxDecode still define the stream payload boundary.

PDF RunLengthDecode semantics use packet control bytes. A byte value of `128` is the explicit EOD marker only when it is read as a RunLength packet header; the same byte can appear inside a literal packet and must remain payload data. For `/Filter [/RunLengthDecode /CCITTFaxDecode]`, stale `/Length` or embedded `endstream/endobj` bytes before the actual RunLength EOD cannot close the image stream early or promote fake objects to page contents.

## Behavior

`PdfTextExtractor::ccittFaxStreamEndstreamTerminatorOffset()` now asks the first native filter for its parsed end byte before accepting an `endstream` terminator for a preview-only CCITT stream. ASCIIHexDecode and ASCII85Decode still use their literal filter terminators. RunLengthDecode now reuses the existing `runLengthExplicitEndOffset()` parser, so literal data bytes equal to `0x80` are ignored until the real RunLength EOD packet is reached.

The focused fixture builds a page whose image XObject has `/Filter [/RunLengthDecode /CCITTFaxDecode]`, a stale `/Length`, and a RunLength literal packet containing:

- a raw `0x80` byte that is payload, not EOD;
- an embedded `endstream/endobj` pair;
- a fake `9 0 obj` text stream referenced by the page `/Contents`;
- a real CCITT EOFB marker followed by the actual RunLength EOD packet.

Before the fix, the first raw `0x80` closed the native prefix boundary and exposed the fake `RL CCITT leak` object. After the fix, the stream remains owned until the actual RunLength EOD, the raw image bytes stay review-only, and WordPress text extraction keeps only the real before/after page text.

## Evidence

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
FAIL keeps RunLength literal EOD bytes from closing CCITT prefix streams early
1 test files, 415 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
1 test files, 429 assertions, 0 failures
```

Adjacent image/filter/parser family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
7 test files, 3319 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-runlength-prefix-boundary-currentbase.php
source=native-pdf-ccitt-fax-runlength-prefix-boundary-currentbase
visible_text="Before RunLength CCITT import\nAfter RunLength CCITT import"
stream_filters=["RunLengthDecode","CCITTFaxDecode"]
preview_only_filters=["CCITTFaxDecode"]
native_prefix_filters=["RunLengthDecode"]
runlength_literal_eod_ignored_until_actual_eod=true
stale_owner_payload_excluded_from_visible_text=true
stale_owner_payload_excluded_from_review=true
decoded_with_current_filters=false
native_raster_decode=false
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

Status delta: `lane-status.json` `phpPass` `1882 -> 1883`, WordPress scenarios `1705 -> 1706`; CCITT manifest behaviors `1 -> 2`.

Required local checks passed:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-runlength-prefix-boundary-currentbase.php
php -r 'json_decode(...)' lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
git diff --check -- lanes/markerpdf
```

## Non-Overlap

This does not repeat accepted image-only CCITT exclusion, malformed or unresolved DecodeParms fail-closed handling, compact DecodeParms, escaped names, Flate/Crypt/ASCIIHex prefixes, direct CCITT EOFB/RTC ownership, row-EOL ownership, inline CCITT boundaries, alias metadata, nested mask/alternate/polarity metadata, DCT/JPX/JBIG2 image boundaries, or generic filter-stack recovery.

The new behavior is specifically RunLengthDecode as the native prefix before preview-only CCITTFaxDecode: a literal-packet data byte equal to `0x80` cannot act as the filter EOD or reopen fake objects before WordPress import.

## Dependency Closure

No new support component is needed. This slice reuses the native object parser, stream filter resolver, RunLengthDecode packet parser, CCITT owner-boundary detector, image-XObject review metadata path, and WordPress smoke renderer. Full CCITT fax raster decoding remains intentionally out of scope for this no-GPU markerPDF lane; the boundary still records preview-only metadata without invoking Python, PDFium, OCR, models, or external PDF tools.
