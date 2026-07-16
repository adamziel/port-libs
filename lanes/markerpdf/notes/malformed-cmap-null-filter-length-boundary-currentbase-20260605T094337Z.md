# markerPDF malformed CMap null-filter Length boundary

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T094337Z`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, with pdftext/PDFium handling low-level CMap stream decoding before OCR/layout/model stages.
- PDF stream filter arrays can include `null` identity entries. DecodeParms entries aligned to those null filters do not configure an active decoder, so native stale-Length terminator recovery must not reject an otherwise valid filtered stream only because the null-filter DecodeParms slot is malformed or unresolved.

## Behavior

This slice adds a native CMap stale-Length boundary for a Type0 `/ToUnicode` stream:

```text
/Filter [ null /FlateDecode ]
/DecodeParms [ 99 0 R << /Predictor 1 >> ]
/Length <offset of a fake raw endstream marker inside stored Flate bytes>
```

The stored Flate payload decodes to a valid CMap that maps the page CID to:

```text
Recovered Null Length CMap Import
```

The compressed payload also contains a raw `endstream` marker and a fake nested stream object after `endcmap`. The parser now retries terminator-recovery decode checks with null-filter DecodeParms slots ignored after the strict decode fails. That lets the real filtered terminator win while keeping malformed DecodeParms on active filters fail-closed.

WordPress-visible paragraph:

```text
Recovered Null Length CMap Import
```

The fake nested stream owner, fake object number, raw `endstream`, unresolved `99 0 R`, and post-`endcmap` decoded bytes remain excluded from extracted text.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapNullFilterLengthBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS recovers stale CMap stream Length when malformed DecodeParms is aligned to a null filter slot

1 test files, 64 assertions, 0 failures
```

Adjacent CMap/filter boundary run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php
4 test files, 1194 assertions, 0 failures
```

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfParserMalformedCMapNullFilterLengthBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfParserMalformedCMapNullFilterLengthBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-null-filter-length-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-null-filter-length-currentbase.php
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-null-filter-length-currentbase.php
```

The smoke metadata emits `decodeparms_null_slot_ignored=true`, `decoded_cmap_count=1`, `decoded_with_current_operands=true`, `post_endcmap_bytes_excluded=true`, `parser_bounded_cmap_bytes_excluded=true`, `fake_stream_owner_excluded_from_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Status delta: `phpPass` moves `1685 -> 1686`, and `wordpressScenarios` moves `1546 -> 1547`.

Status/patch hygiene:

```text
jq empty lanes/markerpdf/lane-status.json
passed

git diff --check -- lanes/markerpdf
passed
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed CMap filter operand classification, active-filter DecodeParms fail-closed handling, all-null filter arrays, indirect null filter identity slots, compact DecodeParms alignment after null placeholders, stream filter stack stale-Length recovery, CMap owner stream length review, object-stream filter ownership, xref-stream DecodeParms recovery, inline image boundaries, image filter exclusion, Type3 font width behavior, or OCR/model handoff work.

The bounded behavior is specifically stale `/Length` recovery for a filtered ToUnicode CMap stream where a malformed or unresolved DecodeParms operand is aligned to a `null` filter slot and the first raw `endstream` marker appears inside valid compressed bytes.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream dictionary reader, filter resolver, DecodeParms parser, Flate decoder, CMap parser, text extractor, CMap stream filter-length owner review, and WordPress smoke renderer. Full upstream model/OCR parity remains intentionally out of scope under the no-GPU markerPDF direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
