# markerPDF AcroForm Indirect Scalar Generation Boundary Current Base

Lane: `markerpdf`
Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T030541Z`
Base accepted HEAD: `7e8350b1ef3db6f47e1658b3972bdea83e44a6f0`

## Source Truth

Upstream markerPDF relies on parser/pdftext/PDFium boundaries before conversion; this no-GPU lane maps the native searchable-PDF parser/review behavior without running OCR, Surya, Texify, Torch, model workers, JavaScript, PDF actions, browser automation, or external PDF tools.

PDF indirect references are generation-qualified. AcroForm scalar operands such as `/T`, `/TU`, `/TM`, `/V`, `/DV`, and choice `/Opt` strings must resolve only when the referenced object generation matches the selected object body. A stale same-object-generation string must not rename fields, inject form values, add choice options, or enter visible WordPress text.

## Behavior

- `PdfAcroFormExtractor::pdfValueToString()` now rejects generation-mismatched indirect scalar references before resolving field names, alternate labels, mapping names, and current/default values.
- `PdfAcroFormExtractor::readScalarAt()` now validates indirect scalar reference generations and advances past rejected reference tokens so a mismatched `38 0 R` cannot be re-read as `8 0 R`.
- The focused fixture proves exact-generation scalar operands still populate `profile.title`, labels, mapping names, current/default values, and choice options, while mismatched stale scalar objects remain unresolved and absent from review metadata and page text.

## Red-First Evidence

Before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects generation mismatched indirect scalar operands in AcroForm fields
Expected: [["export":"post","label":"Post label"]]
Actual included stale option export/label strings from generation-mismatched refs.
1 test files, 259 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 285 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-scalar-generation-currentbase.php
```

The smoke exits 0 and reports `exact_indirect_scalars_resolved=true`, `generation_mismatched_option_excluded=true`, `generation_mismatched_value_unresolved=true`, `stale_scalar_operands_excluded=true`, `form_values_visible_in_text=false`, and all action/JavaScript/Python/model/external-tool execution flags false.

## Status Delta

- `phpPass` moves `1330 -> 1331`.
- `wordpressScenarios` moves `1281 -> 1282`.
- Manifest mapped behavior moves `718 -> 719`; `pdfAcroFormFieldsGenerationBoundaryCurrentBaseBehaviors` moves `2 -> 3`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted AcroForm page-owned widget discovery, direct Widget `/Fields` normalization, token-aware `/Fields` and `/Kids` parsing, indirect `/Fields`/`/Kids` arrays, indirect widget `/Rect`/`/F` operands, alternate `/TU`/`/TM` direct review, trailer `/Root` ownership, calculation/signature/XFA/action review, or xref generation repair. The bounded behavior is specifically generation-exact indirect scalar operands used inside AcroForm field review.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, generation map, dictionary/array tokenizer, AcroForm field hierarchy/value parser, text extractor, focused test harness, and WordPress smoke path. Full upstream markerPDF parity for live OCR/model execution, PDFium rasterization, table/equation models, Streamlit/FastAPI workers, benchmark downloads, decryption, signature validation, and external rendering tools remains intentionally out of scope under the current no-GPU markerPDF directive.
