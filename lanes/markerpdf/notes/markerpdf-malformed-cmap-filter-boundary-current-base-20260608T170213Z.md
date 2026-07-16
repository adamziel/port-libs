## Malformed CMap DecodeParms Generation Boundary

Slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260608T170213Z`

Base accepted HEAD: `bd26eb8502d3ef58d14ef2894a441f7f7e2fc910`

## Source Truth

Upstream markerPDF routes searchable-PDF text through parser-backed PDF object
and stream decoding before any OCR/model fallback. Under the current no-GPU
markerPDF scope, the PHP port owns native PDF xref/object selection, stream
filtering, DecodeParms validation, and ToUnicode CMap parsing without running
PDFium, OCR, Surya/Torch, Texify, or external PDF tools.

PDF indirect references include an object generation. When the current xref row
selects generation `8 1`, a stream dictionary operand `/DecodeParms 8 0 R` is
an unselected stale helper and must not configure a current filtered CMap
stream.

## Behavior

`PdfTextExtractor` now resolves DecodeParms helper references through the
current-owner resolver used by stream-filter review. That keeps filtered
ToUnicode CMap decoding aligned with xref-selected object ownership:

- `/Filter /FlateDecode /DecodeParms 8 0 R`
- current xref selects object `8 1`
- generation `8 0` contains stale permissive `<< /Predictor 1 >>`
- generation `8 1` contains the current malformed DecodeParms owner

Before the fix, a local red-first probe decoded the stale generation-zero
DecodeParms, applied the CMap, and returned visible text
`DecodeParms Gen CMap LeakecodeParms Gen Safe Import` with
`decoded_cmap_count=1`.

After the fix, extraction preserves fallback searchable text
`DecodeParms Gen Safe Import`, reports `decoded_cmap_count=0`,
`decodeparms_resolution_failed=true`,
`decodeparms_operand_policy=reject_unresolved_decodeparms_operands`, and the
DecodeParms operand remains review-visible with
`owner_policy=xref_entry_points_elsewhere` and `selected_generation=1`.

## Files

- `lanes/markerpdf/src/PdfTextExtractor.php`
- `lanes/markerpdf/tests/PdfParserMalformedCMapDecodeParmsGenerationBoundaryCurrentBaseTest.php`
- `lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-decodeparms-generation-boundary-currentbase.php`
- `lanes/markerpdf/lane-status.json`

## Verification

Focused new test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapDecodeParmsGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects stale-generation CMap DecodeParms helpers before ToUnicode decoding

1 test files, 61 assertions, 0 failures
```

Adjacent CMap/DecodeParms/stream-filter owner regression set:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapDecodeParmsGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapDuplicateDecodeParmsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectHelperDecodeParmsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapPostDecodeParmsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterDecodeParmsNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php
Focused test run: 10 selected test files (root lock skipped)
...
10 test files, 2339 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-decodeparms-generation-boundary-currentbase.php
```

The smoke exits `0`, emits one paragraph with
`WP DecodeParms Gen Safe Import`, and reports
`payload_excluded=true`, `decoded_cmap_count=0`,
`decodeparms_owner_policy=xref_entry_points_elsewhere`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted malformed CMap work for scalar/array/literal/
dictionary `/Filter` operands, duplicate `/Filter` or `/DecodeParms`, stale or
free `/Filter` owners, escaped/unsupported/Crypt filters, null-filter
DecodeParms alignment, all-null CMap filter stacks, DecodeParms parameter
validation when the referenced generation is current, explicit filter EOD
enforcement, post-`endcmap` payload exclusion, literal/array/procedure operator
decoys, bfchar/bfrange row-shape boundaries, UseCMap inheritance, CMap
source-width fallback, CIDFont widths, xref repair, image/filter metadata,
annotations/forms/security, OCR/model handoffs, or supplied-boundary table/
equation work.

The bounded new behavior is only stale-generation DecodeParms helper rejection
before filtered ToUnicode CMap stream decoding.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object
scanner, xref owner selection, stream filter resolver, DecodeParms validator,
Flate decoder, ToUnicode CMap parser, and WordPress smoke renderer. Full
upstream model parity remains dependency-gated by `pdftext`,
`pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime
paths, benchmark/model downloads, and external OCR/rendering helpers; none were
executed.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser
behavior around fonts, CMaps, stream filters, xref repair, metadata, outlines,
annotations, forms, security preflight, page geometry, image/filter metadata,
and supplied-boundary table/equation handoffs.
