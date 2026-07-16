# markerPDF Named Destination PDFDocEncoding Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260604T234139Z`
Session: `port-dev-markerpdf-named-destinations-20260604T234139Z`
Base accepted HEAD: `6b7d2b31a4199423d46a2747e99c0aca0dfb5f71`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries PDF destination/navigation metadata through the native PDF parsing boundary before OCR/model handoff.
- PDF text strings without a UTF-16 BOM use PDFDocEncoding. Destination name-tree keys may be literal or hex strings, so WordPress named-destination metadata should decode high-byte PDFDocEncoding labels instead of emitting raw invalid bytes.
- This stays in the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, Python model worker, browser, or external PDF tool execution.

## Implementation

- `PdfNamedDestinationExtractor` now decodes non-BOM PDF text strings with the same PDFDocEncoding override table used by document metadata extraction.
- Literal and hex-string `/Names /Dests` keys such as byte `0x80`, `0x8d`, `0x8e`, and `0xa0` now become readable UTF-8 destination names before export.
- Existing named-destination boundaries remain intact: `/Limits` pruning, generation-exact indirect references, malformed destination rejection, duplicate names-tree precedence, direct destination dictionaries, and legacy `/Dests` fallback.
- The WordPress named-destination smoke now proves the decoded name reaches block metadata while out-of-limits and generation-mismatched rows remain excluded.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL decodes PDFDocEncoding name-tree keys before WordPress named destination metadata
Expected: wp\u{2022} review, Deck \u{201c}draft\u{201d}, Budget \u{20ac}
Actual: raw high-byte labels rendered as replacement characters
1 test files, 41 assertions, 1 failures
```

## Verification

```text
php -l lanes/markerpdf/src/PdfNamedDestinationExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfNamedDestinationExtractor.php
```

```text
php -l lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php
```

```text
php -l lanes/markerpdf/examples/wordpress-pdf-named-destinations-import.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-named-destinations-import.php
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
7 PASS cases
1 test files, 46 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destinations-import.php
Emits destination_count=5, pdfdocencoded_destination_name_decoded=true, out_of_limits_destination_filtered=true, generation_mismatch_destinations_filtered=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

```text
git diff --check -- lanes/markerpdf
passed
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1118 -> 1119`.
- WordPress scenarios: `1113 -> 1114`.
- `pdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- `mappedPdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- Focused named-destination assertions: `40 -> 46`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, text-string parser, name-tree traversal, destination normalizer, and the existing PDFDocEncoding mapping already used by metadata extraction. Full upstream model parity remains intentionally out of scope under the no-GPU markerPDF directive.

## Non-Overlap

This does not repeat accepted named-destination `/Limits` pruning, generation-exact indirect references, Fit/XYZ operand normalization, indirect destination dictionaries, outline destination action/page-review enrichment, xref repair, metadata xref/current-trailer handling, font-width grouping, or runtime conversion preflight work. The bounded new behavior is specifically PDFDocEncoding decoding for standalone catalog named-destination name-tree text-string keys before WordPress metadata export.
