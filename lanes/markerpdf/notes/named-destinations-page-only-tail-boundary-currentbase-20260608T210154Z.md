# Named Destination Page-Only Tail Boundary

- Slice: `markerpdf-named-destinations-boundary-current-base-20260608T210154Z`
- Accepted base: `0091df3813ad73254e2c1f230ab975292c14a7c0`
- Scope: native no-GPU PDF destination name-tree parsing for WordPress review/import paths.

## Behavior

PDF name-tree `/Names` leaves can use page-only destination values as a single value. This slice rejects page-only values that are immediately followed by unbracketed destination-view operands such as `/FitH 610` or `/FitV 120`, because those operands indicate a malformed explicit destination that was not wrapped in a destination array.

The guard is applied before the entries are promoted through:

- `PdfNamedDestinationExtractor` destination inventory rows.
- `PdfMetadataExtractor` `document_destinations` review metadata.
- `PdfOutlineExtractor` TOC/navigation destination maps.
- `PdfActionReviewExtractor` destination review maps used by link extraction.

Valid page-only named destinations remain covered by the existing page-only boundary test. Valid array destinations and legacy `/Dests` rows remain accepted.

## Evidence

Red-first focused run before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationPageOnlyTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects page-only name-tree values with unbracketed view operands before WordPress metadata
Actual names included Page Tail Target and Numeric Tail Target.
FAIL keeps page-only name-tree view tails out of links and visible WordPress text
Actual promoted link annotation objects included 8 and 9.
1 test files, 3 assertions, 2 failures
```

Focused green run after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationPageOnlyTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects page-only name-tree values with unbracketed view operands before WordPress metadata
PASS keeps page-only name-tree view tails out of links and visible WordPress text
1 test files, 41 assertions, 0 failures
```

Adjacent named-destination boundary run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationPageOnlyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationIndirectOperandTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationActionSTypeOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationPageOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationViewModeBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 204 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-page-only-tail-currentbase.php
exits 0; destination_names=[Valid Target, Alias Target, LegacyOk], promoted_link_objects=[7,10,11], page_only_tail_destination_rejected=true, numeric_page_tail_destination_rejected=true.
```

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, name-tree collection, page-index resolution, metadata, outline, and link-review extractors. No Python, OCR/model execution, multiprocessing, PDF action execution, pypdfium, PDF rendering engine, or external PDF tool is invoked.

## Non-Overlap

This does not repeat the accepted page-only destination slice, which verifies valid page refs/page indexes normalize to `/Fit`. It also does not repeat the accepted indirect operand-tail slice, which rejects tailed operands inside destination arrays. This slice covers the separate name-tree leaf boundary where an otherwise valid page-only value is followed by unbracketed view operands in the surrounding `/Names` array.
