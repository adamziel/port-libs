# markerpdf-malformed-cmap-filter-boundary-current-base-20260606T141406Z

## Scope

- Lane: `markerpdf`
- Accepted base: `78406f3d5dccc3d1a3f450862a98b46c50437d15`
- Cluster: native searchable-PDF ToUnicode CMap filter operand boundary.
- Non-overlap: this is additive to the earlier scalar `null /Filter` extra-operand slice. It targets xref-selected indirect `/Filter` helper objects whose body contains a valid helper value, a keyed `/DecodeParms` pair, and then an unkeyed decoder-looking name. It does not repeat direct scalar, direct array, reference-extra, scalar-null, post-Length, post-DecodeParms direct dictionary, duplicate-filter, unsupported-filter, CMap EOD, Type3, OCR, Surya/Texify/Torch, or model-worker surfaces.

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through pdftext/PDFium font decoding before OCR/model handoff. In the native no-GPU port, ToUnicode CMap stream filter operands are a parser boundary: malformed or ambiguous filter stacks must fail closed so CMap payload bytes cannot remap safe WordPress text.

PDF stream filter helper objects can be indirect. A selected helper body such as `/FlateDecode /DecodeParms << /Predictor 1 >> /ASCIIHexDecode` contains a valid filter value, then a keyed dictionary operand, then a second unkeyed decoder name. The stream still must be rejected, but review metadata should identify `/ASCIIHexDecode` as the trailing decoder rather than treating the keyed `/DecodeParms` name as the malformed filter.

## Implementation

`PdfTextExtractor::indirectFilterHelperExtraOperand()` now delegates to the existing `postDirectFilterExtraDecoderOperand()` scanner after the first helper value. This shares the direct dictionary behavior: skip ordinary key/value pairs, then report any trailing unkeyed decoder-looking name or malformed operand.

The decoded CMap remains unavailable for these malformed helpers, so text extraction falls back to the source bytes without applying the poisoned ToUnicode map.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectHelperDecodeParmsFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL reports trailing decoder names after keyed DecodeParms in selected indirect scalar CMap Filter helpers
Values are not identical
Expected: '/ASCIIHexDecode'
Actual: '/DecodeParms'
FAIL reports trailing decoder names after keyed DecodeParms in selected indirect array CMap Filter helpers
Values are not identical
Expected: '/ASCIIHexDecode'
Actual: '/DecodeParms'

1 test files, 124 assertions, 2 failures
```

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectHelperDecodeParmsFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS reports trailing decoder names after keyed DecodeParms in selected indirect scalar CMap Filter helpers
PASS reports trailing decoder names after keyed DecodeParms in selected indirect array CMap Filter helpers

1 test files, 132 assertions, 0 failures
```

Adjacent malformed CMap filter family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectHelperDecodeParmsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectScalarFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectArrayTailFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapReferenceExtraFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapPostDecodeParmsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapScalarNullFilterExtraBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
...
7 test files, 2041 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-indirect-helper-decodeparms-filter-currentbase.php
```

Result: emitted one Gutenberg paragraph for `Indirect Helper Keyed Safe Import` and review metadata with `safe_text_preserved=true`, `payload_excluded=true`, `decoded_cmap_count=0`, `filter_operand_policy=reject_malformed_filter_operands`, `extra_filter_name=ASCIIHexDecode`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF tokenizer, xref-selected object resolver, stream-filter operand reviewer, CMap decoder boundary, and WordPress smoke harness. GPU/model OCR, external PDF tools, pypdfium/PDFium, PIL, Surya/Torch, Texify, tabled-pdf model paths, Streamlit/FastAPI workers, and live benchmark/model parity remain intentionally out of scope under the markerPDF no-GPU directive.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs. A useful follow-up is CMap helper-object owner boundaries or stream DecodeParms array alignment that can fail closed without leaking review bytes into visible WordPress text.
