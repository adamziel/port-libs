# markerPDF AcroForm Reference Array Token Boundary

Lane: `markerpdf`
Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T002103Z`
Base accepted HEAD: `9b1ef263ff3924c6fe0e7eac819c5983af847fea`

## Source Truth

Upstream markerPDF relies on parser/pdftext layers before conversion. This native no-GPU slice maps the PDF parser boundary for AcroForm field discovery: `/Fields`, field `/Kids`, and page `/Annots` are arrays whose top-level `N G R` references are structural entries, while references embedded inside literal strings, hex strings, comments, nested arrays, or nested dictionaries are payload/decoy data and must not promote WordPress form metadata.

No OCR, Surya, Texify, Torch/model worker, pypdfium/PDFium rendering, JavaScript, PDF action, browser, live service, or external PDF tool was executed.

## Behavior

`PdfAcroFormExtractor::validObjectReferences()` now tokenizes array bodies before accepting generation-checked object references. It skips literal strings, hex strings, comments, nested arrays, nested dictionaries, and PDF names, preserving only top-level object references for:

- catalog AcroForm `/Fields`;
- field `/Kids`;
- page `/Annots` widgets used by the AcroForm field-repair path;
- page-tree `/Kids` inside the AcroForm extractor.

The focused fixture proves nested/comment/string references cannot create decoy fields such as `decoy.fields.literal`, `decoy.kids.nested_dict`, or `decoy.annots.comment`, while real top-level fields `article.array` and `article.keep` remain review metadata and field values stay out of visible WordPress text.

## Red-First Evidence

Before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses token aware AcroForm reference arrays before WordPress field review
Expected: [article.array, article.keep]
Actual: [article.array.decoy.kids.literal, article.array.decoy.kids.nested_array, article.array.decoy.kids.nested_dict, article.array.decoy.kids.comment, decoy.fields.literal, decoy.fields.nested_array, decoy.fields.nested_dict, decoy.fields.comment, article.keep, decoy.annots.literal, decoy.annots.nested_array, decoy.annots.nested_dict, decoy.annots.comment]
1 test files, 134 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 174 assertions, 0 failures
```

Adjacent AcroForm/security family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '(PdfAcroForm|PdfSecurityAcroForm).*Test\.php$' | sort)
Focused test run: 26 selected test files (root lock skipped)
26 test files, 2525 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-boundary-currentbase.php
```

Emits `field_count=4`, `field_names=["listed.email","omitted.category","inline.note","indirect.geometry"]`, `array_decoy_fields_excluded=true`, `array_decoy_sources=["annots_literal_nested_comment","fields_literal_nested_comment","kids_literal_nested_comment"]`, and all execution flags false.

## Status Delta

- `phpPass` moves `1176 -> 1177`.
- `wordpressScenarios` moves `1162 -> 1163`.
- Added 1 focused AcroForm PASS case and expanded 1 existing WordPress AcroForm fields smoke.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted page-owned widget field discovery, escaped AcroForm key parsing, generation-exact field references, indirect `/Fields` and `/Kids` arrays, indirect widget `/Rect` and `/F` operands, AcroForm action review, XFA/signature review, or page annotation link/markup token boundaries. The bounded behavior is only top-level reference tokenization inside AcroForm field/widget/page-reference arrays.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, generation guard, dictionary/array tokenizer, field hierarchy builder, page-widget repair path, text extractor, and existing WordPress smoke harness. Full upstream markerPDF parity for live OCR, PDFium rendering, Surya/Texify/Torch models, Streamlit/FastAPI workers, and exact model benchmarks remains intentionally out of scope under the no-GPU markerPDF directive.
