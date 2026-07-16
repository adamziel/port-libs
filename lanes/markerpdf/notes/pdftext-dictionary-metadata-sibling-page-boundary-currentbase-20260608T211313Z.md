# markerPDF pdftext dictionary metadata sibling page boundary current-base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260608T211313Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260608T211313Z`
Base accepted HEAD: `860604a0752757d495f65dc774700e48fce8b337`

## Source Truth

Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. `marker/pdf/extract_text.py::get_text_blocks()` consumes the ordered page dictionaries returned by `pdftext.extraction.dictionary_output(...)` before Marker layout/order/table/model handoffs. Native PHP adapters may persist that output as source-page keyed maps plus cache metadata, but adapter metadata must not become selected PDF text pages.

## Behavior

Before this slice, a `dictionary_output` map with a nonnumeric `metadata` sibling whose value was accidentally page-shaped caused the normalizer to abandon numeric page-map ordering and fall back to positional array order. That could import stale adapter metadata text as a WordPress paragraph before the current numeric page dictionary.

`PdfTextDocumentExtractor` now ignores known adapter metadata/payload sibling keys while sorting source-page keyed pdftext maps. Numeric page keys remain strict, duplicate/negative/overflow page-key guards remain unchanged, and unknown page-shaped nonnumeric keys still use the existing fallback path.

## Verification

Red-first before implementation:

```bash
php -r 'require "tools/bootstrap.php"; $page=function($p,$t){return ["page"=>$p,"bbox"=>[0,0,612,792],"width"=>612,"height"=>792,"rotation"=>0,"blocks"=>[["bbox"=>[72,96,370,110],"lines"=>[["bbox"=>[72,96,370,110],"spans"=>[["text"=>$t,"bbox"=>[72,96,370,110],"font"=>["name"=>"Helvetica","flags"=>0,"weight"=>400,"size"=>11]]]]]]]];}; $doc=(new PortLibs\MarkerPDF\PdfTextDocumentExtractor())->getTextBlocks(["dictionary_output"=>["metadata"=>$page(1,"STALE"),"7"=>$page(7,"CURRENT")]], maxPages:1); echo $doc["pages"][0]["pnum"]."\n"; echo $doc["pages"][0]["blocks"][0]["lines"][0]["spans"][0]["text"]."\n";'
```

Printed `1` and `STALE`, proving the page-shaped metadata sibling was selected.

Focused test after implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreMetadataSiblingPageBoundaryCurrentBaseTest.php
```

Passed: 1 test file, 17 assertions, 0 failures.

Adjacent dictionary family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCore*CurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrder*CurrentBaseTest.php
```

Passed: 39 test files, 2381 assertions, 0 failures.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-metadata-sibling-page-boundary-currentbase.php
```

Passed with `selected_page_range=[1]`, `source_pages=2`, selected safe link Markdown imported, and metadata/raw_payload sibling text excluded.

Syntax/diff checks:

```bash
php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php
php -l lanes/markerpdf/tests/PdfTextDictionaryCoreMetadataSiblingPageBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-metadata-sibling-page-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

Passed.

## Non-Overlap

This does not repeat existing pdftext dictionary singleton envelope unwrapping, nested raw JSON pages handling, layout/order page-key validation, duplicate normalized artifact-map key rejection, JSON table/layout sidecar selection, xref/object-stream parsing, OCR, model execution, or table recognition. The new boundary is specifically page-shaped adapter metadata/raw_payload siblings inside pdftext dictionary page maps.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary normalizer, block converter, supplied-document converter, Markdown finalizer, and WordPress smoke path. Full upstream parity for live `pdftext`, pypdfium/PDFium rendering, Surya/Torch OCR/layout/table models, Texify, Streamlit/FastAPI workers, and exact model benchmark parity remains intentionally out of scope under the current no-GPU markerPDF direction.
