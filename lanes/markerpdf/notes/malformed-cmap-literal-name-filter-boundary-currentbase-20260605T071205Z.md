# Malformed CMap Literal Name Filter Boundary Current Base

Slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T071205Z`
Base accepted HEAD: `7015b6c3156d56de0f0eae60550c6756f26d7797`

## Source Truth

Upstream markerPDF at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` relies on searchable-PDF text extraction through native/PDFium-style font CMap decoding before OCR/model fallbacks. The no-GPU markerPDF scope for this lane keeps the work inside native PDF parsing: stream filters, CMap parsing, font mappings, and review metadata.

## Behavior

`PdfTextExtractor::cMapName()` now scans CMap program tokens instead of using a regex over decoded bytes. It skips line comments, literal strings, hex strings, dictionaries, and arrays before accepting a top-level `/CMapName /Name def` declaration. This prevents a filtered base CMap whose decoded bytes begin with a literal string such as `(/CMapName /FakeBase-H def)` from registering `FakeBase-H` as the base CMap name and breaking `/UseCMap /RealBase-H` inheritance.

The focused fixture has a derived ToUnicode CMap using `/UseCMap /RealBase-H` and a FlateDecode base CMap whose literal-string decoy appears before the real `/CMapName /RealBase-H def`. After the fix, searchable text extraction imports `Literal Name Safe Import`, review metadata reports the derived CMap as `DerivedLiteralName-H`, the filtered base as `RealBase-H`, and no fake CMap name leaks into WordPress-visible text.

## Red/Green Evidence

Red before source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
```

Result: `1 test files, 757 assertions, 1 failures`. The new test expected `['Literal Name Safe Import']` but extraction returned `[]` because the literal-string `FakeBase-H` decoy was registered as the named base CMap.

Green after source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
```

Result: `1 test files, 788 assertions, 0 failures`.

Focused delta: `+1` TestRunner case and `+32` assertions in `PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php`; `phpPass` moved from `1561` to `1562`.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
```

Result: exit `0`; emitted `literal_name_usecmap_inherited=true`, `literal_name_decoy_excluded=true`, `literal_name_base_cmap_name=WPRealBase-H`, `literal_name_filter_operand_policy=filters_resolved`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat prior malformed CMap filter operand, direct/indirect null filter, DecodeParms, unsupported filter, identity/private Crypt filter, stale reference, nested dictionary/array, post-`endcmap`, or second complete program boundary work. The new boundary is specifically a decoded literal-string `/CMapName` decoy inside a filtered base CMap used by `/UseCMap` inheritance.

## Dependency Closure

No new support component is needed. The patch reuses the existing native Flate stream decoder, CMap parser, named CMap registry, PDF token helpers, and CMap stream review metadata. OCR/model execution, Surya/Texify/Torch, GPU benchmarks, and live service workers remain intentionally out of scope for this no-GPU markerPDF lane.
