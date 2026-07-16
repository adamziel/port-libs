# CCITT Fax Indirect Rows Boundary - 2026-06-06

Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260606T030044Z`

Accepted base: `218f7be316686ea5b2005dbccc9e20ca989dc733`

## Source Truth

- Upstream markerPDF native searchable text extraction delegates PDF text blocks through `marker.pdf.extract_text.get_text_blocks` while image review/raster handoff flows through `marker.pdf.images.render_image`.
- Upstream references used for this no-GPU slice:
  - https://raw.githubusercontent.com/datalab-to/marker/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
  - https://raw.githubusercontent.com/datalab-to/marker/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py

## Red-First Boundary

Before the source edit, a focused probe with an XObject image stream declaring `/Filter /CCITTFaxDecode`, `/DecodeParms << /K 0 /Columns 16 /Rows 11 0 R /EndOfLine true /EndOfBlock false >>`, direct `/Height 2`, and a stale `endstream` followed by fake `9 0 obj` text inside the fax payload returned:

```text
array (
  0 => 'Before indirect rows',
  1 => 'Fake indirect rows leak',
  2 => 'After indirect rows',
)
raw_length=132 rows=2
LEAK
```

The later image review pass could resolve indirect `/Rows`, but the early stream-owner pass could not, so it ended ownership at the stale `endstream` and promoted the fake object text.

## Implementation

`PdfTextExtractor` now treats unresolved indirect `/DecodeParms /Rows` differently from direct malformed row counts during CCITT Fax row-EOL stream ownership. If `/Rows` is an indirect reference that cannot be resolved in the early owner pass and the image dictionary has a positive `/Height`, the owner pass uses that height as the row count. Direct invalid rows, zero rows, and negative rows still fail closed.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 818 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-indirect-rows-currentbase.php
emits paragraphs: Before indirect Rows CCITT; After indirect Rows CCITT
metadata: indirect_rows_resolved=true, height_fallback_for_stream_ownership=true, payload_excluded_from_review=true, payload_excluded_from_text=true, executes_python_or_models=false, executes_external_pdf_tools=false
```

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-indirect-rows-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-indirect-rows-currentbase.php

php -r '$json = file_get_contents("lanes/markerpdf/lane-status.json"); json_decode($json, true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status.json valid\n";'
lane-status.json valid

git diff --check -- lanes/markerpdf
no output
```

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF tokenizer, object-reference resolver, and CCITT filter boundary helpers. It does not execute Python, pypdfium, PIL, OCR/model workers, external PDF tools, or live services.

## Non-Overlap

This slice does not repeat the accepted CCITT filter metadata, direct row-EOL ownership, omitted-row image-height fallback, indirect image-height fallback, chained indirect filter/DecodeParms, Type3 CharProc, or native filter-prefix boundary slices. It owns the unresolved indirect `/Rows` stream-ownership boundary only.
