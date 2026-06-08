# markerPDF AcroForm Fields PDFDocEncoding Boundary

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260608T154045Z`
Session: `port-dev-markerpdf-acroform-fields-20260608T154045Z`
Base accepted HEAD: `514053c7d86aad395662bad8b28dd55f8e398a73`

## Source Truth

- Upstream `sddai/markerPDF` delegates searchable-PDF object/text parsing before OCR/model fallback; under the current no-GPU scope this lane owns native PHP parser/review metadata for AcroForm fields.
- PDF AcroForm `/T`, `/TU`, `/TM`, `/V`, `/DV`, and `/Opt` entries are PDF text strings. Without a UTF-16 BOM they use PDFDocEncoding, matching the existing markerPDF lane behavior for metadata, page labels, named destinations, outlines, and attachments.
- Form field values and labels are review metadata for WordPress import. They must not become visible Gutenberg paragraph text, and form actions/XFA must not execute.

No OCR, Surya, Texify, Torch, Python model worker, pypdfium/PDFium, browser, JavaScript/form action execution, or external PDF tool execution was used.

## Behavior

- `PdfAcroFormExtractor::decodePdfStringBytes()` now falls back to PDFDocEncoding instead of returning raw bytes for non-UTF-16 PDF strings.
- The new focused fixture proves PDFDocEncoding bytes decode in field names, alternate labels, mapping names, current/default values, and choice option export/label strings.
- The visible page text remains only the page content stream; decoded form values and labels stay review-only metadata.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsPdfDocEncodingBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL decodes PDFDocEncoding AcroForm text strings before WordPress field review
Values are not identical
Expected: ["workflow\u2022title","workflow\u2020status"]
Actual: ["workflow\ufffdtitle","workflow\ufffdstatus"]
1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsPdfDocEncodingBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS decodes PDFDocEncoding AcroForm text strings before WordPress field review
1 test files, 36 assertions, 0 failures
```

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name 'PdfAcroForm*Test.php' | sort)
Focused test run: 84 selected test files (root lock skipped)
84 test files, 5365 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-pdfdocencoding-fields-currentbase.php
```

The smoke exits `0` and reports `field_count=2`, `field_names=["workflow\u2022title","workflow\u2020status"]`, `pdfdocencoding_fields_decoded=true`, `visible_text_excludes_form_values=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pdf_actions=false`.

Syntax and whitespace:

```text
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
php -l lanes/markerpdf/tests/PdfAcroFormFieldsPdfDocEncodingBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-acroform-pdfdocencoding-fields-currentbase.php
git diff --check -- lanes/markerpdf
```

All syntax checks reported no syntax errors, and `git diff --check -- lanes/markerpdf` produced no output.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `3246 -> 3247`.
- `wordpressScenarios`: `2652 -> 2653`.
- New focused file: `PdfAcroFormFieldsPdfDocEncodingBoundaryCurrentBaseTest.php` adds 1 PASS case and 36 assertions.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, dictionary/array tokenizer, field hierarchy builder, scalar string decoder, PDFDocEncoding byte map already used by neighboring markerPDF extractors, page text extractor, and WordPress smoke path. Full upstream live OCR/model benchmark parity remains intentionally out of scope under the current no-GPU markerPDF directive.

## Non-Overlap

This does not repeat accepted AcroForm escaped `/Fields` names, token-aware arrays, indirect `/Fields` and `/Kids`, generation-exact references, duplicate keys, direct dictionaries, object-stream field recovery, page-widget repair, XFA/signature/action review, submit/reset review, malformed hex scalar consumption, UTF-16 XFA packet decoding, or attachment/page-label/named-destination PDFDocEncoding slices. The bounded behavior is only PDFDocEncoding fallback for AcroForm field text strings before WordPress review metadata.
