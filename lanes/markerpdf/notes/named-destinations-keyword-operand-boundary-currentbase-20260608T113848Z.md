# Named Destination Keyword Operand Boundary

Slice: `markerpdf-named-destinations-boundary-current-base-20260608T113848Z`

Base accepted HEAD: `295de7be43d755bd0e5a2a0b4b78f621b5c55f17`

## Source Truth

- Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` relies on PDF parser/PDFium-style destination and TOC boundaries for searchable-PDF import.
- PDF name-tree keys are PDF strings. Bare keyword tokens are not valid string keys or named-destination aliases.
- Native PHP markerPDF must fail closed before WordPress metadata, outline review, or annotation link promotion when a `/Names /Dests` key or legacy `/Dests` alias is a bare keyword operand.

## Implementation

`PdfNamedDestinationExtractor` now keeps unknown PDF keyword operands typed as `__pdf_keyword` values instead of plain PHP strings. That prevents malformed bare tokens such as `BareKeywordTarget` from being treated as PDF string keys or aliases while preserving `true`, `false`, and `null` keyword decoding.

The focused fixture preserves valid string-key name-tree destinations and legacy `/Dests` rows, while rejecting:

- bare keyword name-tree keys;
- string-key destinations whose value is a bare keyword alias;
- legacy `/Dests` entries whose value is a bare keyword alias;
- outline and link annotation targets that refer only to the malformed keyword rows.

## Red-First Evidence

Before the implementation change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationKeywordOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects bare keyword operands before WordPress named-destination metadata
Expected: Valid String Target, Review Summary, LegacyOk
Actual: Valid String Target, BareKeywordTarget, Alias From Keyword, Review Summary, LegacyOk, LegacyKeyword
1 test files, 36 assertions, 1 failures
```

After the implementation change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationKeywordOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects bare keyword operands before WordPress named-destination metadata
PASS keeps bare keyword destination operands out of annotation promotion and visible WordPress text
1 test files, 57 assertions, 0 failures
```

Focused family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestination*CurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php
Focused test run: 61 selected test files (root lock skipped)
61 test files, 2042 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-keyword-operand-currentbase.php
exit 0
```

## Non-Overlap

This does not repeat accepted named-destination behavior for byte-string limits, UTF-8/UTF-16 decoding, direct PDF-name key rejection, sparse pair resynchronization, duplicate keys, kid ordering, scalar or direct kid rejection, object streams, xref repair, action dictionary aliases, view-coordinate validation, outline page context, or Image XObject CTM placement. The bounded behavior is only the parser operand boundary where unknown keywords must not masquerade as PDF strings in native named-destination extraction.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF token parser, named-destination extractor, metadata extractor, outline extractor, action/link review, text extractor, and WordPress smoke path. Live OCR, Surya/Texify/Torch, pypdfium/PDFium, PIL, Streamlit/FastAPI model workers, raster rendering, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF lane.
