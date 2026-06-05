# AcroForm Calculation-Order Generation Boundary

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T005321Z`

Base: `81a90be3f304481aac5b20cd8094fb5c9eed9d2b`

## Source Truth

- PDF AcroForm `/CO` calculation-order arrays are review metadata in the native no-GPU markerPDF scope.
- Object references inside `/CO` must respect the same current-generation boundary already used by `/Fields`, `/Kids`, and page `/Annots`.
- Missing `/CO` targets are preserved as unresolved review rows, but stale-generation references to objects whose current selected body has a different generation are excluded before they attach to current WordPress field metadata.

## Patch

- `PdfAcroFormExtractor` now uses a token-aware calculation-order reference scanner that:
  - skips references in literal strings, nested arrays, nested dictionaries, names, and comments;
  - keeps missing objects as unresolved review targets;
  - rejects references whose generation does not match the selected object generation.
- Calculation-order widget parent resolution now uses the generation-aware object-reference helper.
- Added a WordPress smoke proving `/CO [8 0 R 10 0 R 99 0 R 12 1 R]` keeps unresolved `99`, keeps exact widget `12 1 R`, and rejects stale refs `8 0 R` and `10 0 R` when objects `8` and `10` are selected at generation `1`.

## Verification

- `php -l lanes/markerpdf/src/PdfAcroFormExtractor.php`
  - `No syntax errors detected`
- `php -l lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceCalcOrderReviewCurrentBaseTest.php`
  - `No syntax errors detected`
- `php -l lanes/markerpdf/examples/wordpress-pdf-acroform-calculation-order-generation-boundary-currentbase.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceCalcOrderReviewCurrentBaseTest.php`
  - `1 test files, 105 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationParentTreeWidgetCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityAcroFormDssActionAttachmentBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityAcroFormPermissionActionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPermissionByteRangeFieldMdpCurrentBaseTest.php`
  - `30 test files, 2763 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-calculation-order-generation-boundary-currentbase.php`
  - Emits `stale_generation_refs_excluded=true`, `unresolved_review_object_preserved=true`, `exact_widget_order_preserved=true`, and `literal_nested_comment_decoys_excluded=true`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PDF tokenizer, generation table, AcroForm extractor, and focused PHP test harness. It does not run Python, OCR, model workers, JavaScript actions, signing/validation, pypdfium, or external PDF tools.

## Non-Overlap

This does not repeat the accepted link annotation QuadPoints boundary, page widget discovery, AcroForm field generation filtering for `/Fields` and page `/Annots`, widget appearance calculation-order review, XFA/signature action review, or security permission slices. The new behavior is specifically `/CO` calculation-order generation filtering while preserving unresolved review targets.
