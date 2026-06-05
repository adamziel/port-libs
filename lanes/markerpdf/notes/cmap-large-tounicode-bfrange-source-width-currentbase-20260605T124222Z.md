# markerpdf-cmap-large-tounicode-bfrange-source-width-current-base-20260605T124222Z

## Scope

Added native searchable-PDF CMap coverage for scalar ToUnicode `beginbfrange`
rows whose source range extends beyond the extractor's eager expansion cap.
The fixture keeps the accepted large CID encoding range source-width behavior
and adds a broad ToUnicode range:

- source range: `<0000> <1FFF>`
- ToUnicode target start: `<0041>`
- rendered source codes: `<1000>` through `<1007>`, the first entries past the
  existing 4096-entry eager map cap
- descendant font widths: CIDs `5096..5099` are wide and `5100..5103` are narrow

Before the implementation change, the focused test failed by decoding raw source
codes `U+1000..U+1007` instead of ToUnicode range targets `U+1041..U+1048`.

## Implementation

`PdfTextExtractor` now preserves scalar `beginbfrange` rows as lazy
`unicodeRanges` metadata while still eagerly expanding bounded rows for the
existing exact-map path. Text decoding, source-key tokenization, and mapped-key
checks now consult the lazy range formula only when an exact ToUnicode source
key is absent, preserving later-range override behavior.

This is distinct from the accepted
`cmap-large-cidrange-source-width-currentbase-20260605T121002Z` slice, which
added lazy CID range lookup for source-width metrics. This slice adds lazy
ToUnicode text lookup for scalar `beginbfrange` rows beyond the same eager cap.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapLargeToUnicodeBfrangeSourceWidthCurrentBaseTest.php
=> 1 test files, 1 assertions, 1 failures
Expected codepoints: U+1041..U+1048
Actual codepoints: U+1000..U+1007
```

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapLargeToUnicodeBfrangeSourceWidthCurrentBaseTest.php
=> 1 test files, 10 assertions, 0 failures
```

Adjacent CMap/source-width family after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapLargeToUnicodeBfrangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeCidRangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapBfrangeSurrogateWidthCurrentBaseTest.php
=> 4 test files, 285 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-large-tounicode-bfrange-source-width-currentbase.php
=> large_tounicode_bfrange_decoded=true
=> text_runs_preserved=true
=> large_cidrange_source_widths_applied=true
=> unmapped_source_fallback_excluded=true
=> executes_python_or_models=false
=> executes_external_pdf_tools=false
```

## Dependency Closure

No new support component is needed. This reuses the existing native
`pdf-text-dictionary-core` CMap/font-width parser path. GPU/OCR/model execution
and external PDF tools remain intentionally out of scope for this no-GPU
markerPDF lane.

## Next

Continue non-overlapping native markerPDF work around remaining searchable-PDF
CMap/font edge cases, stream filters, xref repair, annotations/forms, page
geometry, image/filter metadata, and supplied-boundary conversion handoffs.
