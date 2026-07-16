# AcroForm Fields Duplicate Subtype Boundary Current Base

- Slice: `markerpdf-acroform-fields-boundary-current-base-20260608T150306Z`
- Base: `daf1297ec7ca92d379d36088cbd404afd750eb24`
- Scope: native no-GPU markerPDF AcroForm parser behavior only; no OCR, PDF rendering, Python models, action execution, or external PDF tools.

## Behavior

PDF dictionary keys can repeat, and the markerPDF native AcroForm parser already treats the last top-level key as authoritative for field attributes, page annotation arrays, and repair boundaries. This slice applies that same boundary to widget annotation `/Subtype` classification before field-tree traversal and page-widget repair.

The new focused PDF fixture covers both directions:

- `/Subtype /Text /Subtype /Widget` is accepted as a Widget annotation when it is the effective last top-level subtype, preserving a listed field widget and a page-owned inline field repair.
- `/Subtype /Widget /Subtype /Text` is rejected as stale non-widget annotation metadata, so decoy field names, values, and annotation contents do not surface in AcroForm review or visible WordPress text.

## Evidence

- Red-first focused check before source fix:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDuplicateSubtypeBoundaryCurrentBaseTest.php`
  failed with 1 assertion / 1 failure because the stale first-Widget/last-Text annotation was imported and the last-Widget annotation was missed.
- After source fix:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDuplicateSubtypeBoundaryCurrentBaseTest.php`
  passed with 1 test file / 42 assertions / 0 failures.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-duplicate-subtype-boundary-currentbase.php`
  exits 0 and emits review-only metadata for two retained fields while excluding stale duplicate-subtype decoys.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PDF object parser, generation-aware object resolution, AcroForm field repair path, page annotation map, and text extractor. GPU/model/OCR/rendering parity remains intentionally out of scope for markerPDF workers under the current supervisor override.

## Non-Overlap

This avoids accepted AcroForm work for non-widget subtype rejection, indirect subtype object generation, duplicate `/Fields` and `/Annots` keys, duplicate field attributes, direct widget canonical matching, parent ownership, page-owned widget repair, `/MaxLen`, XFA/action/appearance review, encrypted preflight, annotations, outlines, xref repair, filters, fonts, page geometry, tables, and metadata.
