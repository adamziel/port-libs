# Outline Action Dictionary Boundary

Slice: `markerpdf-outline-metadata-boundary-current-base-20260605T224109Z`
Base accepted HEAD: `49046e6b737373fb58c4274ee3175e5ae5f87499`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable PDF text and PDF TOC rows to the PDF parser boundary before OCR/model execution. Native PHP markerPDF must therefore treat valid outline item dictionaries as navigation metadata, while action dictionaries identified by `/S` remain non-executing action metadata only when reached through `/A`, `/OpenAction`, `/AA`, or named-destination action maps.

## Behavior

This slice rejects action-shaped dictionaries that are linked directly into an outline sibling chain. A malformed object with top-level `/S /JavaScript`, `/Title`, `/Parent`, `/Prev`, `/Next`, and `/Dest` no longer becomes a bookmark item, and its following tail row is not trusted through the spoof backlink.

The same boundary is applied to:

- `PdfMetadataExtractor::document_outline` item traversal;
- `PdfOutlineExtractor` TOC, destination-view, navigation, action-review, structure-context, and remote GoTo traversals;
- `PdfTextExtractor::extractOutlineMetadata()` lightweight upstream-style `pdf_toc`.

Nested action dictionaries under valid outline `/A` entries are preserved; the full outline family rerun verified existing destination/action review behavior.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataActionDictionaryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects action-shaped dictionaries inside document outline metadata chains
Expected: 1
Actual: 3
FAIL applies action dictionary boundaries to TOC navigation and lightweight metadata
Expected titles: Action Dictionary Boundary Chapter
Actual titles: Action Dictionary Boundary Chapter, Stale Action Dictionary Spoof Outline, Untrusted Tail After Action Dictionary

1 test files, 9 assertions, 2 failures
```

## Verification

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataActionDictionaryBoundaryCurrentBaseTest.php
=> 1 test files, 41 assertions, 0 failures
```

Regression check for the new boundary and previously sensitive nested-action fixtures:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataActionDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineTitleEncodingBoundaryCurrentBaseTest.php
=> 3 test files, 92 assertions, 0 failures
```

Adjacent outline/metadata family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfOutline.*Test\.php$|PdfMetadata.*Outline.*Test\.php$' | sort)
=> 63 test files, 3165 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-action-dictionary-boundary-currentbase.php
=> action_dictionary_spoof_excluded=true; tail_after_action_dictionary_excluded=true; stale_actions_excluded=true; visible_text_excludes_outline_metadata=true; executes_python_or_models=false; executes_external_pdf_tools=false
```

Syntax and whitespace:

```text
php -l lanes/markerpdf/src/PdfOutlineExtractor.php
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfOutlineMetadataActionDictionaryBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-outline-action-dictionary-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

=> PHP lint reported no syntax errors for all changed PHP files; `git diff --check -- lanes/markerpdf` produced no whitespace errors.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted `/Type /Annot` or typed non-outline object guards, titleless bridge boundaries, `/A` action-chain review, named-destination action maps, root `/Count`, item `/Count`, `/Prev`, `/Parent`, `/Last`, generation-exact outline rows, trailer-root ownership, title encoding, structure-element `/SE`, or PageLabels/page-transition enrichment. The bounded behavior is only top-level `/S` action-shaped dictionaries linked as outline sibling rows.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, dictionary token readers, outline extractors, metadata extractor, lightweight pdftext-style metadata path, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch, pypdfium/PDFium, PIL, Streamlit/FastAPI model workers, JavaScript/action execution, and external PDF tools remain intentionally out of scope for the markerPDF no-GPU lane.
