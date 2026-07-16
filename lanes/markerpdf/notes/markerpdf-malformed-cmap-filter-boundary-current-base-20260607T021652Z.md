# markerpdf-malformed-cmap-filter-boundary-current-base-20260607T021652Z

Base accepted HEAD: `067c20d4516457e7c630a9a0a09157a4c0c95111`.

## Scope

Native no-GPU markerPDF searchable-PDF parser work. This slice covers selected
indirect ToUnicode CMap `/Filter` helper objects whose helper body is an invalid
scalar value such as `true` or `1.5`.

Direct scalar `/Filter true` and `/Filter 1.5` values already failed closed and
exposed typed review values. The missing boundary was the indirect form:

```text
6 0 obj
<< /Type /CMap /Filter 7 0 R /Length ... >>
stream
...
endstream
endobj
7 0 obj
true
endobj
```

The current patch keeps the fail-closed behavior and exposes typed `value`
metadata for single-token indirect scalar helper bodies (`null`, boolean, or
number). Malformed helper bodies with extra tokens still use the existing extra
operand review path and are not treated as single typed values.

## Source Truth

The lane manifest pins upstream `sddai/markerPDF` at
`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Upstream searchable-PDF text flows
through pdftext/PDF parser font and CMap decoding before marker assembles page
text. Under the current no-GPU scope, the PHP port owns this CMap filter
boundary: malformed stream filter operands must not decode ToUnicode CMap bytes
or replace WordPress-visible page text.

## Evidence

Red-first focused run after adding the new test and before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectScalarFilterValueBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed and types selected indirect boolean CMap Filter helper values before Length
Values are not identical
Expected: true
Actual: NULL
FAIL fails closed and types selected indirect real-number CMap Filter helper values before Length
Values are not identical
Expected: 1.5
Actual: NULL
1 test files, 108 assertions, 2 failures
```

Focused run after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectScalarFilterValueBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed and types selected indirect boolean CMap Filter helper values before Length
PASS fails closed and types selected indirect real-number CMap Filter helper values before Length
1 test files, 118 assertions, 0 failures
```

Adjacent CMap filter family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectScalarFilterValueBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapScalarFilterValueBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectScalarFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectArrayTailFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 1895 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-indirect-scalar-filter-value-currentbase.php
```

The smoke reports boolean and number cases with `safe_text_preserved=true`,
`leaking_cmap_text_excluded=true`, `typed_value_matches=true`,
`decoded_cmap_count=0`, `filter_operand_policy=reject_malformed_filter_operands`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted direct scalar CMap filter values, scalar/null
filter tails, direct/indirect extra filter names, indirect array tails,
DecodeParms boundaries, unsupported/escaped/duplicate filters, nested array or
dictionary operands, CMap EOD handling, Type3 fallback exclusion, xref repair,
image filter metadata, annotations/forms/security review, OCR/model execution,
or external PDF tools.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object
scanner, xref-selected indirect operand review, stream filter resolver, CMap
fail-closed decoding boundary, Identity-H fallback, and WordPress smoke path.
GPU/model/OCR, PDFium, Python, and external PDF tools remain intentionally out
of scope.

## Next

Continue non-overlapping native markerPDF parser/converter work around fonts,
CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry,
image/filter metadata, and supplied-boundary table/equation handoffs.
