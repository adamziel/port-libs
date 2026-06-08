# markerpdf-malformed-cmap-filter-boundary-current-base-20260608T132711Z

Accepted base: `76d39cc9c7483ed466e997afd7e62cf44440b906`

Scope: native no-GPU markerPDF searchable-PDF parsing. This slice does not run OCR, Surya/Texify/Torch, raster rendering, model workers, external PDF tools, or live-service tests.

## Behavior

Decoded filtered CMap streams now skip balanced PostScript procedure bodies while scanning top-level CMap directives:

- `/WMode N def`
- `/Name usecmap`
- `/CMapName /Name def` and `CMapName currentdict /Name defineresource`

This extends the current CMap token-boundary behavior already used by `endcmap` and mapping-operator scanners. Procedure-body decoys such as `{ /WMode 1 def } pop`, `{ /Base-H usecmap } pop`, or `{ /CMapName /Base-H def } pop` no longer alter writing mode, named CMap inheritance, or named CMap registration after a filtered CMap stream is decoded.

## Red-First Evidence

Before the source fix, the new focused test failed:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapProcedureDirectiveFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL ignores filtered CMap procedure-body WMode decoys while keeping top-level writing mode semantics
FAIL ignores filtered CMap procedure-body usecmap decoys before source mapping operators
FAIL ignores filtered CMap procedure-body CMapName decoys when building named CMap imports
1 test files, 3 assertions, 3 failures
```

The failures showed horizontal text being regrouped as vertical text and procedure-body `usecmap`/`CMapName` decoys leaking named-base text.

## Verification

Focused test after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapProcedureDirectiveFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS ignores filtered CMap procedure-body WMode decoys while keeping top-level writing mode semantics
PASS ignores filtered CMap procedure-body usecmap decoys before source mapping operators
PASS ignores filtered CMap procedure-body CMapName decoys when building named CMap imports
1 test files, 24 assertions, 0 failures
```

Adjacent CMap scanner/filter boundary regression:

```text
php tools/run-tests.php \
  lanes/markerpdf/tests/PdfParserMalformedCMapProcedureDirectiveFilterBoundaryCurrentBaseTest.php \
  lanes/markerpdf/tests/PdfParserMalformedCMapWModeFilterBoundaryCurrentBaseTest.php \
  lanes/markerpdf/tests/PdfParserMalformedCMapProcedureEndOperatorFilterBoundaryCurrentBaseTest.php \
  lanes/markerpdf/tests/PdfParserMalformedCMapArrayEndOperatorFilterBoundaryCurrentBaseTest.php \
  lanes/markerpdf/tests/PdfParserMalformedCMapUseCMapPostNameBoundaryCurrentBaseTest.php \
  lanes/markerpdf/tests/PdfParserMalformedCMapNamedUseCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
9 PASS
6 test files, 252 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-procedure-directive-currentbase.php --self-test
{"self_test_passed":true,...,"procedure_wmode_decoy_ignored":true,"executes_python_or_models":false,"executes_external_pdf_tools":false}
```

## Dependency Closure

No new support component is needed. The patch reuses the existing native CMap tokenizer and `cMapProcedureEndOffset()` balanced-procedure scanner.

## Next

Continue with non-overlapping native markerPDF behavior: CMap/filter edge cases not covered by scalar, array, dictionary, literal, post-`endcmap`, and procedure-boundary slices; or other searchable-PDF parser surfaces such as fonts, xref repair, annotations, forms, metadata, page geometry, and image/filter metadata.
