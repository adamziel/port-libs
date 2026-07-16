# markerPDF Direct CCITT Fax End-Block Boundary

Session: `port-dev-markerpdf-ccitt-fax-filter-20260605T015859Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T015859Z`
Base accepted HEAD: `09fbc2a24fe927b19b42b5b4f66665df69b3373f`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable PDF text extraction separate from raster image rendering. Image pixels are routed through PDFium/PIL-backed RGB handoff in `marker/pdf/images.py`, while this no-GPU native PHP lane keeps CCITT Fax raster bytes review-only.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://tcpdf.org/docs/srcdoc/tc-lib-pdf-filter/classes-Com-Tecnick-Pdf-Filter-Type-CcittFax/

PDF CCITT Fax streams with valid/default `/DecodeParms` use `EndOfBlock=true` by default. For direct, preview-only `/CCITTFaxDecode` and `/CCF` image streams that omit or stale `/Length`, this slice uses the encoded end-block marker as the ownership boundary before accepting the following `endstream` token. It does not raster-decode fax data.

## Native Behavior Added

`PdfTextExtractor::streamContentEnd()` now delegates first-filter direct CCITT Fax image streams to a bounded end-block scanner when ordinary stream-length recovery would otherwise stop at a fake `endstream` embedded in fax bytes.

The new boundary scanner:

- honors default `/DecodeParms` `EndOfBlock=true`;
- uses Group 4 EOFB when effective `K < 0`;
- uses Group 3 RTC when effective `K >= 0`;
- requires the marker to be followed by an actual `endstream` token;
- keeps malformed `/DecodeParms` and `EndOfBlock=false` fail-closed;
- preserves raw payload ownership without adding visible WordPress text;
- keeps `native_raster_decode=false` and `decoded_with_current_filters=false`.

## Evidence

Baseline focused CCITT file before the new current-base case:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 109 assertions, 0 failures
```

Focused gate after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 131 assertions, 0 failures
```

Adjacent renderer/text/filter gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 1357 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php
```

The smoke emits `direct_ccitt_eofb_boundary_repaired=true`, `direct_ccitt_rtc_boundary_repaired=true`, `direct_ccitt_payload_excluded=true`, `direct_ccitt_eofb_effective_k=-1`, `direct_ccitt_rtc_effective_k=0`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax/JSON/diff checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php

php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo $f, " ok\n"; }'
lanes/markerpdf/lane-status.json ok
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json ok

git diff --check -- lanes/markerpdf
```

`git diff --check -- lanes/markerpdf` passed with no output.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused PASS cases: `1270 -> 1271`.
- Focused assertions in `PdfCcittFaxFilterBoundaryCurrentBaseTest.php`: `109 -> 131`.
- WordPress scenarios: `1235 -> 1236`.
- Manifest current-base CCITT boundary behaviors: `1 -> 2`.

## Non-Overlap

This does not repeat accepted CCITT image-only stream exclusion, raw DecodeParms extraction, malformed DecodeParms fail-closed metadata, effective DecodeParms geometry metadata, inline CCITT review-only notes, inline invalid DecodeParms review, Flate-prefix CCITT boundary recovery, DCT/JPX/JBIG2 preview-only image filters, or generic filter-stack length recovery. The new bounded behavior is direct CCITT EOFB/RTC stream ownership before fake `endstream` decoys on the current base.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, stream filter resolver, DecodeParms parser, stream boundary recovery, image review metadata, and WordPress smoke renderer. Full CCITT raster parity remains gated on PDFium/PIL or a future native raster backend; no Python, OCR, model, external PDF tool, pypdfium, or PIL execution was run.
