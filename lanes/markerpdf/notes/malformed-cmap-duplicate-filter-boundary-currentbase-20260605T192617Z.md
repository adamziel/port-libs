# Malformed CMap Duplicate Filter Boundary Current Base

## Source Truth

- Upstream markerPDF obtains searchable PDF text through pdftext/PDFium font decoding before model/OCR paths. The native no-GPU port must therefore fail closed on malformed ToUnicode CMap stream filter metadata rather than applying a questionable CMap to WordPress-visible text.
- PDF stream `/Filter` is a single decoder name or an array of decoder names. Duplicate top-level `/Filter` declarations in the same CMap stream dictionary are ambiguous malformed metadata and should not be treated as a trusted decoder stack.

## Implementation

`PdfTextExtractor` now counts duplicate top-level `/Filter` declarations in CMap stream dictionaries and rejects CMap decoding when duplicates are present. The review row exposes `duplicate_filter_declaration_count` and reports `reject_duplicate_filter_declarations` for the filter operand, filter end-marker, and filter decode policies.

The guard is CMap-specific: ordinary page/content streams continue using the existing stream decoder behavior, while ToUnicode/Encoding CMap streams fail closed before applying replacement text from ambiguous duplicate filter declarations.

## Red Evidence

Inline reproduction on the current base before the source edit showed the duplicate-filter CMap being decoded through the first `/Filter /FlateDecode` and applying the leaking ToUnicode replacement:

```text
array (
  0 => 'Duplicate Filter CMap Leakuplicate Filter Safe Import',
)
array (
  'decoded_cmap_count' => 1,
  'invalid_filter_operand_count' => 0,
  'unsupported_filter_count' => 0,
  'entry_filters' =>
  array (
    0 => 'FlateDecode',
  ),
  'filter_policy' => 'filters_resolved',
)
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapDuplicateFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on duplicate CMap Filter declarations before current-base ToUnicode replacement

1 test files, 50 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapDuplicateFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectScalarFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapNullFilterLengthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUnknownFilterNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFallbackStreamBoundaryCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
37 PASS cases

7 test files, 1722 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-duplicate-filter-boundary-currentbase.php
```

The smoke emits `duplicate_filter_declarations_rejected=true`, `visible_text_uses_safe_fallback=true`, `visible_text_excludes_cmap_leak=true`, `filter_policy=reject_duplicate_filter_declarations`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted CMap malformed filter array operands, scalar extra filter operands, literal or indirect literal filter operands, unknown filter names, escaped filter names, null filter slots, all-null CMap stacks, DecodeParms failure boundaries, explicit ASCIIHex/ASCII85 EOD boundaries, post-`endcmap` program bounding, UseCMap inheritance guards, Type0 width/CID source mapping, object-stream/xref repair, image filters, inline images, encrypted permission preflight, or OCR/model behavior.

The bounded behavior is only duplicate top-level `/Filter` declarations on CMap stream dictionaries before ToUnicode replacement.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, top-level dictionary value scanner, CMap stream decoder, CMap review metadata, text fallback decoder, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium rendering, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF lane.
