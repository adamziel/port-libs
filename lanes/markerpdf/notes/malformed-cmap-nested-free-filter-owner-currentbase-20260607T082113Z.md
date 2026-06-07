# Malformed CMap Nested Free Filter Owner Boundary - 2026-06-07

Slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260607T082113Z`
Base: `88f1e22fbe8fb31ab1773f697eb872e68d918898`

## Behavior

This patch covers a native searchable-PDF CMap filter boundary where the
top-level `/Filter` operand is xref-selected, but that selected helper contains
a nested indirect reference to a free/stale object. Before the fix, a selected
helper such as `[ 7 0 R /FlateDecode ]` could fall through the generic stream
filter resolver, bind the nested free object through stale direct-object
fallback, decode the ToUnicode CMap, and leak CMap replacement text into the
WordPress import.

The CMap decode/review path now treats nested unresolved filter references as
unresolved CMap filter operands. The stream is left undecoded for text mapping,
visible text falls back to the page content bytes, and review metadata records
`nested_unresolved_filter_operand_count=1`,
`invalid_filter_operand_count=1`, and
`filter_operand_policy=reject_unresolved_filter_operands`.

## Evidence

Red-first probe on this base showed the fixture text as
`Nested Free CMap Leakested Free Safe Import` with `filters_resolved` and a
selected helper preview `[ 7 0 R /FlateDecode ]`.

After the fix:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapNestedFreeFilterOwnerBoundaryCurrentBaseTest.php`
  - `1 test files, 67 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFreeFilterOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapNestedIndirectDictionaryFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectArrayTailFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapNestedFreeFilterOwnerBoundaryCurrentBaseTest.php`
  - `4 test files, 256 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-nested-free-filter-owner-currentbase.php`
  - exits `0`, emits only the safe paragraph, and reports no Python/model/OCR
    or external PDF tool execution.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PDF
object owner/xref selection and stream filter review helpers; GPU/OCR/model
behavior remains intentionally out of scope for this markerPDF lane.

## Non-overlap

This does not repeat the existing direct free `/Filter 7 0 R` owner boundary or
the nested dictionary filter boundary. The new case is specifically a selected
indirect filter helper whose nested array member is free/unselected.
