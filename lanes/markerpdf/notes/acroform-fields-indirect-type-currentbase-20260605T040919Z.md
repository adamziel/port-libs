# AcroForm Fields Indirect Type Boundary Current Base

## Source Truth

- Upstream markerPDF treats AcroForm fields as native PDF form metadata, separate from visible page text.
- PDF field dictionaries define `/FT` as a name object. This slice covers direct names plus generation-exact indirect name objects used by malformed or incrementally updated PDFs.
- Current no-GPU markerPDF scope applies: no OCR, Surya, Texify, Torch, Streamlit/FastAPI model workers, or external PDF tools were run.

## Behavior

- `PdfAcroFormExtractor` now resolves `/FT` through a generation-checked indirect object when the referenced object contains a PDF name.
- Escaped field-type names such as `/T#78` and `/C#68` decode to `Tx` and `Ch`.
- A stale-generation `/FT 32 0 R` is rejected when only object `32 1` is selected, leaving that field as `unknown` instead of importing an incorrect text field type.
- Form field values remain AcroForm review metadata and are not exposed as visible page text.

## Evidence

- `php -l lanes/markerpdf/src/PdfAcroFormExtractor.php`
- `php -l lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-acroform-fields-indirect-type-currentbase.php`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php` passed with `1 test files, 354 assertions, 0 failures`.
- `php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(AcroForm|SecurityAcroForm).*Test\.php$' | sort)` passed with `27 test files, 2774 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-indirect-type-currentbase.php` exits `0` and emits `stale_generation_field_type_rejected=true`.
- `git diff --check -- lanes/markerpdf`

## Status Delta

- Focused markerPDF PHP pass count: `1392 -> 1393`.
- WordPress scenario count: `1328 -> 1329`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This patch does not touch accepted AcroForm page-widget discovery, direct widget fields, token-aware arrays, inherited scalar values, numeric indirect attributes, widget appearance/action/XFA/signature review, encryption/security preflight, xref recovery, stream filters, image handling, CMaps, outlines, annotations, or metadata clusters.

## Dependency Closure

No new support component is needed. The implementation reuses the existing native PHP object table, object generation selection, PDF-name decoding, field hierarchy, value-state, and text extractor boundaries.
