# markerPDF CCITT Fax Height Row Boundary

Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T153604Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps native searchable PDF text extraction separate from image rendering/OCR. In this no-GPU PHP lane, CCITTFaxDecode image data remains preview-only, but native stream ownership must still identify the complete image payload before importing WordPress-visible text.

PDF CCITTFaxDecode DecodeParms defaults `Rows` to `0` when omitted. For image XObjects and inline images, the surrounding image dictionary still carries `/Height` or `/H`. When `/EndOfBlock false` and `/EndOfLine true` are used, row-EOL boundaries must require the image height worth of rows when `/Rows` is omitted; accepting the first row EOL reopens fake `endstream/endobj` bytes as page content.

## Behavior

`PdfTextExtractor` now passes image height into CCITT ownership checks for both XObject streams and inline images. The row-EOL owner boundary still honors explicit positive `/Rows` first, still fails closed for invalid or non-positive `/Rows`, and only uses image height when `/Rows` is absent.

The focused XObject fixture builds `/Filter /CCITTFaxDecode` with `/DecodeParms << /K 0 /Columns 16 /EndOfLine true /EndOfBlock false >>`, `/Height 2`, a stale `/Length`, and a fake `9 0 obj` after the first row EOL. Before the fix, the first row EOL closed the image with `raw_length=5` and exposed `Fake height-derived CCITT owner leak` in visible text. After the fix, stream ownership waits for the second row EOL, the raw image span is `145` bytes, and the fake owner stays inside the image payload.

The inline fixture mirrors the same boundary with `BI /W 16 /H 2 ... /DP << /K 0 /Columns 16 /EndOfLine true /EndOfBlock false >> ID`, so a tokenizer `EI` after the first row cannot reopen text before the second row completes.

## Evidence

Red baseline before source repair:

```text
red-first probe:
extractTextLines => ["Before height-derived CCITT rows","Fake height-derived CCITT owner leak","After height-derived CCITT rows"]
stale=5 full=145 raw=5
plain contains fake=yes
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
1 test files, 482 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-height-row-boundary-currentbase.php
source=native-pdf-ccitt-fax-height-row-boundary-currentbase
visible_text="Before Height CCITT import\nAfter Height CCITT import"
stream_filters=["CCITTFaxDecode"]
preview_only_filters=["CCITTFaxDecode"]
decode_parms_rows_default=0
image_height_used_for_row_ownership=true
stale_owner_payload_excluded_from_visible_text=true
stale_owner_payload_excluded_from_review=true
decoded_with_current_filters=false
native_raster_decode=false
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

Status delta: `lane-status.json` `phpPass` `2038 -> 2040`, WordPress scenarios `1763 -> 1764`.

## Non-Overlap

This does not repeat accepted direct CCITT EOFB/RTC ownership, explicit `/Rows` row-EOL ownership, inline explicit `/Rows` tokenizer boundaries, Flate/Crypt/RunLength native-prefix CCITT boundaries, escaped/null DecodeParms alignment, malformed owner fail-closed behavior, ImageMask polarity, CCF aliases, post-CCITT filter metadata, or renderer-only DecodeParms geometry defaults.

The new behavior is specifically `/EndOfBlock false` + `/EndOfLine true` CCITT Fax stream ownership when `/Rows` is omitted and the image dictionary supplies `/Height` or inline `/H`.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF dictionary parser, image-dimension extraction, CCITT preview-only stream boundary checks, inline image tokenizer, image-XObject review metadata path, and WordPress smoke renderer. Full CCITT fax raster decoding remains intentionally out of scope under the current no-GPU markerPDF direction; the native path records preview-only metadata without Python, PDFium, OCR, models, or external PDF tools.
