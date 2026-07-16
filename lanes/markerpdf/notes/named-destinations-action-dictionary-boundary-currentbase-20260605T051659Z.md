# markerPDF Named Destinations Action Dictionary Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T051659Z`
Session: `port-dev-markerpdf-named-destinations-20260605T051659Z`
Base accepted HEAD: `89fe927c0e557441b63b35cc5e2a446a60b5ddf2`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries PDF navigation metadata through the PDF parser boundary before OCR/model handoff. Under the current no-GPU markerPDF scope, this slice maps a native PDF parser boundary for catalog `/Names /Dests`: destination dictionaries may be plain `<< /D [...] >>`, and action-shaped dictionaries are only usable as named destinations when `/S` resolves to `/GoTo`.

URI, Launch, JavaScript, and malformed action dictionaries are review/action surfaces, not standalone named-destination rows, and must not leak action payloads or stale coordinates into WordPress destination metadata. No OCR, Surya, Texify, Torch, Python model worker, pypdfium/PDFium, browser, or external PDF tool execution was used.

## Behavior

- `PdfNamedDestinationExtractor` now checks dictionary values with `/S` before accepting their `/D` destination array.
- Plain destination dictionaries with `/D` and no `/S` remain valid.
- `/S /GoTo` dictionaries remain valid and resolve their `/D` arrays.
- `/S /URI`, `/S /Launch`, `/S /JavaScript`, and malformed `/S (GoTo)` dictionaries are rejected from standalone named-destination metadata.
- Rejected action payloads, filenames, URIs, script text, and stale coordinates stay out of visible WordPress text and destination review rows.

## Red-First Evidence

Before the source change, a direct probe imported non-GoTo action dictionaries because any dictionary with `/D` was accepted:

```text
php -r 'require "tools/bootstrap.php"; use PortLibs\MarkerPDF\PdfNamedDestinationExtractor; ...'
array (
  0 => 'GoTo Dictionary',
  1 => 'URI Masquerade',
  2 => 'Launch Masquerade',
)
```

## Verification

```text
php -l lanes/markerpdf/src/PdfNamedDestinationExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfNamedDestinationExtractor.php
```

```text
php -l lanes/markerpdf/tests/PdfNamedDestinationActionDictionaryBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfNamedDestinationActionDictionaryBoundaryCurrentBaseTest.php
```

```text
php -l lanes/markerpdf/examples/wordpress-pdf-named-destination-action-dictionary-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-named-destination-action-dictionary-boundary-currentbase.php
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationActionDictionaryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects non-GoTo action dictionaries before WordPress named-destination metadata
PASS keeps action dictionary payloads out of visible WordPress text and destination review rows
1 test files, 34 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php lanes/markerpdf/tests/PdfNamedDestinationGenerationBodyCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationIndirectArraysCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationKidGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationLimitsFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationNameKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationPageOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationXrefOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationActionDictionaryBoundaryCurrentBaseTest.php
Focused test run: 11 selected test files (root lock skipped)
11 test files, 254 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-action-dictionary-boundary-currentbase.php
Emits destination_count=3, destination_names=[GoTo Action Dest, Plain Dest Dict, LegacyOk], non_goto_action_dictionaries_rejected=true, visible_text_excludes_action_payloads=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

```text
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'
lanes/markerpdf/lane-status.json OK
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json OK
```

```text
git diff --check -- lanes/markerpdf
No output; exit 0.
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1455 -> 1457`.
- `wordpressScenarios`: `1374 -> 1375`.
- `pdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- `mappedPdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- New focused file: `PdfNamedDestinationActionDictionaryBoundaryCurrentBaseTest.php` adds 2 PASS cases and 34 assertions.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, tokenizer, generation-exact resolver, name-tree walker, page-tree indexer, text extractor, and WordPress smoke renderer. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed for this bounded no-GPU PHP slice.

## Non-Overlap

This does not repeat accepted named-destination `/Limits` pruning, malformed leaf `/Limits` fallback, indirect `/Kids`/`/Names`/`/Limits` arrays, PDFDocEncoding string keys, indirect view operands, PDF name-key rejection, page-operand validation, generation-exact destination dictionaries/page refs, object-stream recovery, trailer-root selection, xref-selected duplicate body selection, outline destination action context, PageLabels, xref repair, metadata, attachment, font, image/filter, or Type3 behavior. The bounded behavior is only fail-closed rejection of non-GoTo action dictionaries inside standalone catalog named-destination extraction.

## Next Task

Continue with non-overlapping native searchable-PDF behavior under the no-GPU scope: metadata, annotations, forms, xref repair, page geometry, image/filter review, font/CMap widths, supplied table/equation boundaries, or remaining runtime review behavior.
