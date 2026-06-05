# Malformed CMap Reference-Extra Filter Boundary

Slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T200639Z`

Accepted base: `b04f57c7230c881432b7183ac804ada5839368dd`

## Behavior

Native searchable-PDF text extraction now rejects malformed ToUnicode CMap stream dictionaries where `/Filter` is a direct indirect reference followed by an unkeyed extra decoder name, for example:

`/Filter 7 0 R /ASCIIHexDecode /Length ...`

Before this slice, `7 0 R` could resolve to `/FlateDecode` and the parser ignored the extra unkeyed decoder token, allowing the decoded CMap to rewrite visible WordPress import text. The parser now applies the same extra-operand fail-closed boundary to reference-valued filters that it already applied to direct name filters.

The review path still records the current xref-selected filter helper, the extra decoder name, and `reject_malformed_filter_operands`. Duplicate-filter diagnostics were kept review-only: the first filter operand remains visible in metadata, while duplicate declarations still cannot decode CMap streams.

## Evidence

Red-first focused run:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapReferenceExtraFilterBoundaryCurrentBaseTest.php`

Result: `1 test files / 1 assertions / 1 failures`; decoded text leaked as `Reference Extra CMap Leakeference Extra Safe Import`.

After fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapReferenceExtraFilterBoundaryCurrentBaseTest.php`

Result: `1 test files / 65 assertions / 0 failures`.

Family gate:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParser*CMap*Filter*CurrentBaseTest.php lanes/markerpdf/tests/PdfCMap*CurrentBaseTest.php lanes/markerpdf/tests/PdfFont*CMap*CurrentBaseTest.php`

Result: `25 test files / 2262 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-reference-extra-filter-currentbase.php`

Result: emitted `safe_import_text_preserved=true`, `cmap_payload_excluded=true`, `extra_decoder_rejected=true`, `decoded_cmap_count=0`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP PDF dictionary operand scanner, xref-selected object resolution, stream filter stack decoder, and CMap review/extraction paths. No GPU/model execution, Python bridge, PDF renderer, or external PDF tool is required.

## Non-Overlap

This slice does not repeat the existing dictionary/literal/duplicate/unknown direct-name CMap filter boundaries. It specifically covers the previously missing direct reference plus extra decoder-token boundary.
