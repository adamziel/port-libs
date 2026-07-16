# markerPDF Named Destinations Name-Key Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T040818Z`
Session: `port-dev-markerpdf-named-destinations-20260605T040818Z`
Base accepted HEAD: `7347ccbb2a8d618c6ba51825bd5a595b1aac8ded`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries PDF navigation metadata through the PDF parsing boundary before OCR/model handoff. Under the current no-GPU markerPDF scope, this slice maps the native PDF parser boundary for catalog `/Names /Dests`: name-tree keys are PDF text strings, while legacy catalog `/Dests` dictionary entries are PDF name keys.

No OCR, Surya, Texify, Torch, Python model worker, pypdfium/PDFium, browser, or external PDF tool execution was used.

## Behavior

- `PdfNamedDestinationExtractor` now accepts catalog destination name-tree `/Names` keys only when they resolve to text strings.
- Direct malformed PDF name keys such as `/NameObjectStale` are rejected from `/Names /Dests` rows.
- Indirect keys that resolve to PDF name objects are also rejected.
- Legacy catalog `/Dests << /LegacyNameKey [...] >>` remains valid because that older PDF surface is a dictionary keyed by names.
- The rejected destination key labels, stale coordinates, and destination metadata stay out of visible WordPress text and review metadata.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationNameKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects PDF-name keys in destination name trees while preserving legacy Dests name keys
Expected: Current String Key, Review Summary, LegacyNameKey
Actual: Current String Key, NameObjectStale, IndirectNameObjectStale, Review Summary, LegacyNameKey
FAIL keeps malformed name-tree name-object rows out of WordPress visible text and metadata
1 test files, 4 assertions, 2 failures
```

## Verification

```text
php -l lanes/markerpdf/src/PdfNamedDestinationExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfNamedDestinationExtractor.php
```

```text
php -l lanes/markerpdf/tests/PdfNamedDestinationNameKeyBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfNamedDestinationNameKeyBoundaryCurrentBaseTest.php
```

```text
php -l lanes/markerpdf/examples/wordpress-pdf-named-destination-name-key-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-named-destination-name-key-boundary-currentbase.php
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationNameKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects PDF-name keys in destination name trees while preserving legacy Dests name keys
PASS keeps malformed name-tree name-object rows out of WordPress visible text and metadata
1 test files, 16 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php lanes/markerpdf/tests/PdfNamedDestinationGenerationBodyCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationIndirectArraysCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationKidGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationLimitsFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationXrefOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationNameKeyBoundaryCurrentBaseTest.php
Focused test run: 9 selected test files (root lock skipped)
25 PASS cases
9 test files, 188 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-name-key-boundary-currentbase.php
Emits destination_count=3, destination_names=[Current String Key, Review Summary, LegacyNameKey], name_tree_name_object_keys_rejected=true, legacy_dests_dictionary_name_key_preserved=true, visible_text_excludes_destination_metadata=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1391 -> 1393`.
- `wordpressScenarios`: `1327 -> 1328`.
- `pdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- `mappedPdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- New focused file: `PdfNamedDestinationNameKeyBoundaryCurrentBaseTest.php` adds 2 PASS cases and 16 assertions.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, tokenizer, generation-exact resolver, name-tree walker, page-tree indexer, text extractor, and WordPress smoke renderer. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed for this bounded no-GPU PHP slice.

## Non-Overlap

This does not repeat accepted named-destination `/Limits` pruning, malformed leaf `/Limits` fallback, indirect `/Kids`/`/Names`/`/Limits` arrays, PDFDocEncoding string keys, indirect view operands, generation-exact destination dictionaries/page refs, object-stream recovery, trailer-root selection, xref-selected duplicate body selection, outline destination action context, PageLabels, xref repair, metadata, attachment, font, image/filter, or Type3 behavior. The bounded behavior is only rejecting PDF name objects used as catalog destination name-tree keys while preserving legacy `/Dests` dictionary name-key rows.

## Next Task

Continue with non-overlapping native searchable-PDF behavior under the no-GPU scope: metadata, annotations, forms, xref repair, page geometry, image/filter review, font/CMap widths, supplied table/equation boundaries, or remaining runtime review behavior.
