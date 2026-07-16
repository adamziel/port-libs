# markerPDF AcroForm Indirect Numeric Attributes Current Base

Lane: `markerpdf`
Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T033801Z`
Base accepted HEAD: `b3a326b364ff3b996dfb76ae85c81120026fd222`

## Source Truth

Upstream markerPDF depends on native PDF parser behavior before conversion and form review. Under the current no-GPU lane scope, this slice maps a bounded searchable-PDF parser boundary: AcroForm numeric field attributes are PDF objects like any other field operand and may be indirect references. Field flags, text MaxLen, and choice selected-index arrays must resolve only through the selected object generation before WordPress form-review metadata is produced.

## Behavior

- `PdfAcroFormExtractor` now resolves AcroForm `/Ff` and `/MaxLen` through the existing generation-checked numeric object resolver instead of regexing the reference token prefix.
- Choice field `/I` arrays now tokenize PDF values and resolve exact-generation indirect numeric operands, while ignoring stale-generation references such as `37 0 R` when only `37 1 obj` is selected.
- Password redaction, read-only/required/no-export flags, multi-select choice state, MaxLen review, and selected option review now use resolved numeric values.
- Field values remain review metadata and are not promoted into visible WordPress text.

## Red-First Evidence

Before implementation, the new focused case failed because `/Ff 30 1 R` was interpreted as integer `30`:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves generation exact indirect numeric AcroForm field attributes
Values are not identical
Expected: 8192
Actual: 30
1 test files, 289 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 320 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-indirect-numeric-currentbase.php
```

The smoke exits 0 and reports `password_flag_resolved=true`, `password_value_redacted=true`, `read_required_flags_resolved=true`, `maxlen_resolved=true`, `maxlen_exceeded_reviewed=true`, `choice_multiselect_flag_resolved=true`, `choice_indices_resolved=true`, `stale_choice_index_excluded=true`, `form_values_visible_in_text=false`, and all action/JavaScript/Python/model/external-tool execution flags false.

## Status Delta

- `phpPass` moves `1361 -> 1362`.
- `wordpressScenarios` moves `1305 -> 1306`.
- Adds 1 focused AcroForm PASS case; the focused AcroForm fields boundary file now passes with 320 assertions.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted AcroForm page-owned widget discovery, direct Widget `/Fields` normalization, token-aware `/Fields` and `/Kids` parsing, indirect `/Fields`/`/Kids` arrays, indirect widget `/Rect` and `/F` operands, alternate `/TU`/`/TM` review, generation-exact string/scalar operands, trailer `/Root` ownership, calculation/signature/XFA/action review, or xref generation repair. The bounded behavior is specifically generation-exact indirect numeric AcroForm field attributes used for flags, MaxLen, and choice selected indexes.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, generation map, dictionary/array tokenizer, numeric object resolver, AcroForm field hierarchy/value parser, text extractor, focused test harness, and WordPress smoke path. Full upstream markerPDF parity for live OCR/model execution, PDFium rasterization, table/equation models, Streamlit/FastAPI workers, benchmark downloads, decryption, signature validation, and external rendering tools remains intentionally out of scope under the current no-GPU markerPDF directive.
