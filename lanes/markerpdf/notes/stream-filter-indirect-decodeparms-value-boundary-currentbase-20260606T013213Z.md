# markerPDF stream filter indirect DecodeParms value boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260606T012747Z`

## Source Truth

- Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level PDF stream decoding to pdftext/PDFium before OCR/layout/model stages.
- PDF stream `/DecodeParms` operands are single values: a dictionary, null, an array aligned to `/Filter`, or an indirect object resolving to one of those values. A helper object that starts with a valid dictionary but has a trailing top-level operand is malformed and must not be silently accepted before native stream decoding.

## Behavior

This slice makes indirect DecodeParms helper resolution consume exactly one PDF value before applying stream filters:

- `/DecodeParms 10 0 R` where object `10 0` is `<< /Predictor 1 >> << /Predictor 12 /Columns 64 >>` now fails closed before Flate text extraction.
- `/DecodeParms [ 11 0 R ]` where object `11 0` is `<< /Predictor 1 >> null` now fails closed before Flate text extraction.
- A valid indirect helper `14 0 obj << /Predictor 12 /Columns N >> endobj` still decodes a Flate predictor stream natively.

## Evidence

Red-first probe on accepted base `e4fc45845f8fab8d74e7fa5d1f40c3f833e8ee9c` before the source edit extracted:

```text
Indirect Trailing DecodeParms Leak
Visible After Indirect Trailing DecodeParms
```

After the source edit, the same probe extracted only:

```text
Visible After Indirect Trailing DecodeParms
```

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
PASS rejects indirect DecodeParms helpers with trailing top-level operands before page text import
...
1 test files, 303 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-indirect-decodeparms-value-currentbase.php
```

The smoke emits `indirect_scalar_decodeparms_rejected=true`, `indirect_array_decodeparms_rejected=true`, `valid_indirect_decodeparms_preserved=true`, `visible_fallback_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat the accepted ASCII85/Flate missing-Length boundary, stale declared-Length recovery, RunLength/LZW EOD boundaries, null-filter DecodeParms alignment, extra DecodeParms array-slot rejection, nested DecodeParms array rejection, Crypt identity handling, duplicate stream keys, or the prior primary CCITT Fax native-prefix image-review boundary.

The new behavior is specifically the single-value owner boundary for indirect `/DecodeParms` helper objects before stream-filter decoding.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object scanner, stream dictionary reader, indirect operand resolver, DecodeParms parser, Flate decoder, PNG predictor decoder, content-token parser, and WordPress smoke path. Full upstream model/OCR parity remains intentionally out of scope under the no-GPU direction.
