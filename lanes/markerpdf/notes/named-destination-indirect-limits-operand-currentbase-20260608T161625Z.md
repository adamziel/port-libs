# markerPDF Named Destination Indirect Limits Operand Boundary

- Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260608T161625Z`
- Session: `port-dev-markerpdf-named-destinations-20260608T161625Z`
- Base accepted HEAD: `cdafd6d5225c48f3465bd46abb1eb68413725cd4`
- Scope: native no-GPU markerPDF PDF parser/converter behavior under `lanes/markerpdf/**`.

## Behavior

PDF destination name-tree `/Limits` entries are name strings. This slice covers the boundary where a `/Limits` string is supplied indirectly, but the referenced object hides extra top-level operands after the selected string, for example `(Clean Target) /PrivateLimitTail`.

The native destination walkers now reject that limit object as malformed before importing the name tree. Legacy catalog `/Dests` entries still import, safe URI links still promote, and malformed name-tree labels, outline titles, Fit operands, and tail tokens stay out of WordPress review metadata and visible text.

The same boundary is enforced in:

- `PdfNamedDestinationExtractor`
- `PdfMetadataExtractor`
- `PdfActionReviewExtractor`
- `PdfOutlineExtractor`

## Red-First Evidence

Before the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationIndirectLimitsOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects tailed indirect destination Limits operands before WordPress metadata
FAIL keeps tailed indirect destination Limits operands out of links and visible WordPress text

1 test files, 4 assertions, 2 failures
```

The failing assertions showed `Clean Target` and `Stale Tail Target` imported through metadata/action review despite the tailed indirect `/Limits` lower operand.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationIndirectLimitsOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects tailed indirect destination Limits operands before WordPress metadata
PASS keeps tailed indirect destination Limits operands out of links and visible WordPress text

1 test files, 48 assertions, 0 failures
```

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfNamedDestination*Test.php' -o -name 'PdfLinkAnnotationNameTreeLimitsBoundary*Test.php' -o -name 'PdfOutlineNameTree*Test.php' -o -name 'PdfOutlineActionNameTree*Test.php' -o -name 'PdfOutlineNamedDestination*Test.php' -o -name 'PdfOutlineDestinationAction*Test.php' -o -name 'PdfMetadata*NameTree*Test.php' -o -name 'PdfParserNameTree*Test.php' \) | sort)
Focused test run: 83 selected test files (root lock skipped)
83 test files, 3066 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-indirect-limits-operand-currentbase.php
exit 0; tailed_limits_rejected=true; destination_names=["LegacySafe"]; promoted_link_objects=[8,9]
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PDF token parser, object lookup, named-destination extractor, metadata extractor, action review walker, outline extractor, link annotation extractor, text extractor, and WordPress block smoke path. It does not run Python, CUDA, OCR, model code, raster rendering, PDF action execution, decryption, network services, or external PDF tools.

## Non-Overlap

This does not repeat prior named-destination coverage for direct `/Limits` byte comparisons, overlong `/Limits` arrays, reversed root limits, indirect array operands, tailed destination view/coordinate operands, sparse name arrays, stream carrier values, generation-exact objects, name-key typing, or outline/action context propagation. The bounded new behavior is specifically tailed indirect string objects used as name-tree `/Limits` operands.
