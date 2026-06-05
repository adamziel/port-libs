# Outline Metadata Titleless Bridge Boundary

Slice: `markerpdf-outline-metadata-boundary-current-base-20260605T075432Z`
Base accepted HEAD: `1b72408ed94109ba862fc9360cd5e316e7f53484`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` receives PDF TOC rows through the PDFium/pdftext document-outline boundary and keeps those rows as review/navigation metadata, not visible page text. PDF outline item dictionaries require a `/Title`; native PHP traversal must therefore avoid using titleless sibling dictionaries as bridges into stale later titles or action payloads when the outline root does not declare a terminal `/Last`.

## Behavior

`PdfMetadataExtractor`, `PdfOutlineExtractor`, and the lightweight `PdfTextExtractor::extractOutlineMetadata()` TOC path now stop an unbounded outline sibling chain when the matched item has no `/Title`. When a parent/root does declare `/Last`, existing placeholder behavior is preserved: the titleless item is skipped, its children/actions are not imported, and traversal may continue to the declared terminal sibling.

The focused fixture keeps one current bookmark, then a same-parent titleless bridge with `/Next` and a JavaScript action, then a stale titled remote-action appendix. The catalog outline root intentionally omits `/Last`. Current metadata/navigation keeps only the current bookmark, excludes the titleless action and stale remote action, and keeps all outline/action operands out of visible WordPress text.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataTitlelessBridgeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL stops document outline metadata at titleless sibling bridge before stale rows
Expected: 1
Actual: 2
FAIL applies titleless sibling bridge boundary to TOC navigation and remote action review
Expected: ['Current Titleless Bridge Chapter']
Actual: ['Current Titleless Bridge Chapter', 'Stale Titleless Bridge Appendix']

1 test files, 9 assertions, 2 failures
```

## Verification

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataTitlelessBridgeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataTitleBoundaryCurrentBaseTest.php
=> 2 test files, 74 assertions, 0 failures
```

Full outline family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfOutline.*Test\.php$' | sort)
=> 44 test files, 2365 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-titleless-bridge-boundary-currentbase.php
=> stale_outline_excluded=true; stale_remote_action_excluded=true; titleless_action_excluded=true; visible_text_excludes_outline_metadata=true; executes_python_or_models=false; executes_external_pdf_tools=false
```

PHP lint:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/src/PdfOutlineExtractor.php
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfOutlineMetadataTitlelessBridgeBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-outline-titleless-bridge-boundary-currentbase.php
=> no syntax errors
```

Root harness: not run - isolated micro-slice.

## Status Delta

- Adds 2 focused markerPDF TestRunner PASS cases.
- `phpPass` moves `1595 -> 1597`.
- Adds 1 WordPress scenario; `wordpressScenarios` moves `1478 -> 1479`.

## Non-Overlap

This does not repeat accepted outline `/Prev`, `/Last`, missing-parent, generation-exact, xref-selected owner, EOF, named-destination, action-context, page-transition, root-type, title-encoding, color, or trailer-root boundaries. The bounded behavior is only the unbounded titleless sibling bridge that can otherwise lead document outline metadata, TOC rows, navigation review, and remote-action review into stale later outline items.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, outline sibling traversal, document metadata extractor, lightweight TOC extractor, navigation review extractor, and WordPress smoke path. Python, pdftext, pypdfium/PDFium, OCR, Surya/Texify/Torch, Streamlit/FastAPI model workers, and external PDF tools were not run and remain intentionally out of scope for this no-GPU markerPDF slice.
