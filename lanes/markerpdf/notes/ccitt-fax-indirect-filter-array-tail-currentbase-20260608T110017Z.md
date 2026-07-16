# markerPDF CCITT Fax indirect filter-array tail boundary

Slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260608T110017Z`
Session: `port-dev-markerpdf-ccitt-fax-filter-20260608T110017Z`
Accepted base: `624743f10725e73aaece85dd9da3beede0b6bcda`

## Source Truth

Upstream markerPDF keeps searchable PDF text extraction separate from image rendering. In this no-GPU PHP port, CCITT Fax image bytes remain review-only metadata unless a native raster backend is explicitly in scope. PDF filter operands that resolve through indirect objects still must be parsed as PDF tokens, not as arbitrary strings; an indirect array slot such as:

```pdf
/Filter [20 0 R]
20 0 obj
/CCF /DCTDecode
endobj
```

contains one usable name token followed by a trailing top-level operand. The safe boundary is to preserve the first parsed filter name for review and DecodeParms slot alignment while marking the operand malformed before any image preview/raster path can run.

## Red First

A renderer probe before this patch used `PdfImageRenderer::imageColorSpaceSoftMaskPlan()` with `/Filter [20 0 R]` and object `20 => '/CCF /DCTDecode'`. It returned:

```php
[
    'image_filters' => ['CCF /DCTDecode'],
    'native_raster_decode' => true,
]
```

There was no preview-only CCITT filter and no `ccitt_fax_filter_boundary`, so the renderer path lost the CCITT alias and did not fail closed on the trailing operand. The XObject review path already preserved `['MalformedFilterOperand', 'CCF']`, so the fix was scoped to the renderer filter-array parser.

## Implementation

`PdfImageRenderer::imageFilterValuesFromValue()` now parses indirect array-slot values that start with `/` by using the first PDF name token. If non-whitespace bytes remain after that token, it inserts `MalformedFilterOperand` before appending the parsed filter name.

That keeps `/CCF` aligned with the same DecodeParms slot, records a malformed-filter policy, disables native raster decode, and preserves the CCITT review-only metadata.

## Verification

Focused new test:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxIndirectFilterArrayTailCurrentBaseTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS marks indirect CCITT Fax filter array entries with trailing operands fail closed before renderer preview
PASS keeps indirect CCITT Fax filter array tail metadata aligned for XObject WordPress import

1 test files, 27 assertions, 0 failures
```

Focused adjacent image-filter family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxIndirectFilterArrayTailCurrentBaseTest.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCcittFaxDecodeParmsTrailingOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
```

Result:

```text
5 test files, 2457 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-indirect-filter-array-tail-currentbase.php
```

Result: exits 0 and emits `renderer_indirect_filter_array_tail_rejected=true`, `xobject_indirect_filter_array_tail_rejected=true`, `payload_excluded_from_paragraphs=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Syntax checks:

```bash
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfCcittFaxIndirectFilterArrayTailCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-indirect-filter-array-tail-currentbase.php
```

Result: no syntax errors detected in each changed PHP file.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct scalar filter extra operands, duplicate filter declarations, malformed direct filter-array slots, chained indirect filter names, PDF comments in filter arrays, post-CCITT filters, prefix filters, DecodeParms trailing operands, null filter alignment, row/EOL/RTC/EOFB boundaries, invalid dimensions, ImageMask polarity, DCT/JBIG2/JPX review-only paths, OCR, raster rendering, or model execution.

The bounded behavior is specifically an indirect filter-array slot where the resolved object starts with a valid CCITT Fax name token and then contains a trailing top-level operand.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object resolver, image renderer filter parser, image XObject review metadata planner, DecodeParms alignment logic, and WordPress smoke renderer. Full CCITT raster decode remains intentionally out of scope under the current no-GPU markerPDF direction unless a future native raster backend is activated with fixtures.
