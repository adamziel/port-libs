# Malformed CMap Filter Boundary Current Base

Slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260608T143709Z`

Accepted base: `4f21f5a494acd2cdaafcccc96a3334aa48f5dae4`

## Source Truth

Upstream markerPDF reaches searchable PDF text through pdftext/PDFium CMap decoding before OCR/model fallbacks. Under the current no-GPU lane scope, this patch ports a native parser boundary for filtered ToUnicode CMap streams whose balanced PostScript procedure bodies contain decoy `/Name usecmap` tokens.

Relevant PDF/CMap behavior for this slice: `usecmap` is an inheritance operator only at token level in the active CMap program. Bytes inside balanced procedure bodies such as `{ /Name usecmap } bind def` are procedure data and must not select a named base CMap for native fallback parsing.

## Implementation

`PdfTextExtractor::cMapUseCMapNames()` now skips balanced procedure bodies while scanning decoded filtered CMap streams for named `usecmap` inheritance. The scanner already skipped comments, literal strings, hex strings, dictionaries, and arrays; this patch adds the same procedure boundary to the `usecmap` scanner.

The focused fixture uses a FlateDecode ToUnicode CMap with a procedure body containing a decoy `/ProcedureUseCMapDecoy-H usecmap`. Before the source change, native extraction inherited that base CMap and emitted `Procedure UseCMap Leakrocedure UseCMap Safe Import`. After the change, WordPress-visible text is `Procedure UseCMap Safe Import`, while the procedure decoy base mapping remains unreferenced review-only CMap metadata.

## Evidence

Red-first focused check after adding the new test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapProcedureUseCMapFilterBoundaryCurrentBaseTest.php`

Result before implementation:

```text
FAIL ignores usecmap tokens inside filtered CMap procedure bodies before current-base text extraction
Expected: array (
  0 => 'Procedure UseCMap Safe Import',
)
Actual: array (
  0 => 'Procedure UseCMap Leakrocedure UseCMap Safe Import',
)
1 test files, 1 assertions, 1 failures
```

Focused check after implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapProcedureUseCMapFilterBoundaryCurrentBaseTest.php`

Result: `1 test files, 37 assertions, 0 failures`.

Adjacent CMap filter/usecmap family check:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapProcedureUseCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapProcedureEndOperatorFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapNamedUseCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUseCMapPostNameBoundaryCurrentBaseTest.php`

Result: `5 test files, 1754 assertions, 0 failures`.

Broader source-width/usecmap check:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapProcedureEndOperatorFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapNamedUseCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUseCMapPostNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php`

Result: `4 test files, 552 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-procedure-usecmap-currentbase.php --self-test`

Result: exits `0`, emits `OK markerpdf-malformed-cmap-procedure-usecmap-currentbase`, and records `safe_text_imported=true`, `procedure_usecmap_decoy_excluded=true`, `decoded_cmap_count=2`, `use_cmap_stream_count=0`, `derived_filter_operand_policy=filters_resolved`, `derived_filter_decode_policy=filter_decoders_resolved`, `base_reference_usages=[]`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CMap filter operand fail-closed behavior, escaped stream dictionary keys, CMap `endcmap` procedure-boundary parsing, CMap literal-string operator boundaries, CMap array/dictionary row boundaries, named UseCMap valid inheritance, post-`endcmap` name registration, inline-image tokenizer boundaries, OCR/model execution, or external raster/PDF tools.

The bounded behavior is only named `usecmap` discovery while scanning decoded filtered CMap programs when a `usecmap` token appears inside a balanced PostScript procedure body before native WordPress text fallback.

## Dependency Closure

No new support component is needed. The implementation reuses the existing native PHP PDF parser, CMap stream decoder, stream filter operand review helpers, and procedure skipper already used by CMap operator-boundary scanning.

Next useful markerPDF work: continue non-overlapping native searchable-PDF parser behavior around CMap owner/recovery, font widths, xref repair, stream filters, metadata, annotations, forms, page geometry, and image/filter metadata without GPU/model execution.
