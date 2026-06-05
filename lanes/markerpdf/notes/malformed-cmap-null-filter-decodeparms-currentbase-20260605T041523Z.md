# markerPDF malformed CMap null-filter DecodeParms boundary

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T041523Z`

## Source Truth

Upstream markerPDF at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text through `marker/pdf/extract_text.py` and pdftext/PDFium before Markdown/WordPress paragraphs are assembled. In the native no-GPU PHP lane, ToUnicode CMap streams must honor ordinary PDF stream filter semantics before CMap parsing: `null` filter-array entries are identity placeholders, so DecodeParms operands aligned to those null slots must not poison a later real decoder.

## Behavior

`PdfTextExtractor` now decodes CMap streams with the same slot-aware DecodeParms alignment used by page/Form/appearance content streams, while keeping extra trailing DecodeParms strict.

Focused fixture shape:

```text
/Filter [ null /FlateDecode ]
/DecodeParms [ 99 0 R << /Predictor 1 >> ]
```

The unresolved `99 0 R` belongs to the null identity filter slot and is ignored. The real `/FlateDecode` CMap stream still decodes with `<< /Predictor 1 >>`, maps `<0001>` to `Null Slot CMap Import`, and keeps the ignored operand visible in review metadata. The previously accepted `/Filter /FlateDecode /DecodeParms [ null << /Predictor /Twelve /Columns 1 >> ]` case remains fail-closed as an unmatched trailing malformed parameter.

## Evidence

Red-first focused run after adding the CMap null-slot case, before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL ignores malformed DecodeParms operands aligned to null CMap filters before ToUnicode decoding
Expected: array (
  0 => 'Null Slot CMap Import',
)
Actual: array (
)

1 test files, 515 assertions, 1 failures
```

Focused run after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS ignores malformed DecodeParms operands aligned to null CMap filters before ToUnicode decoding
...
1 test files, 568 assertions, 0 failures
```

Adjacent CMap/stream/text sweep:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 1383 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
```

The smoke emits `null_filter_decodeparms_slot_ignored=true`, `null_filter_decodeparms_decoded_cmap_count=1`, `null_filter_decodeparms_invalid_operand_count=0`, `null_filter_decodeparms_invalid_parameter_count=0`, `null_filter_decodeparms_ignored_reference=99`, and renders `Null Slot CMap Import` while excluding the raw DecodeParms and CMap-name metadata from visible paragraphs.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed CMap filter dictionary/literal operands, current-generation stale filter selection, selected indirect filter operands, malformed real-filter DecodeParms parameter rejection, trailing malformed DecodeParms rejection, inherited UseCMap DecodeParms review, post-endcmap parser bounding, unsupported image-filter rejection, or the content-stream null DecodeParms slot slice.

The bounded behavior here is specifically ToUnicode CMap stream decoding and CMap review accounting when an unresolved DecodeParms operand is aligned to an explicit `null` filter slot before a valid real decoder.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, stream dictionary reader, filter-stack resolver, slot-aware DecodeParms parser, Flate decoder, ToUnicode CMap parser, review metadata builder, and WordPress smoke renderer. Full upstream model/OCR parity remains intentionally out of scope under the no-GPU markerPDF direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed.
