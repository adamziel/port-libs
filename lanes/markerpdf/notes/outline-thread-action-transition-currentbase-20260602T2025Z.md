# markerPDF Outline Thread Action Transition Current Base

Micro-slice: `outline-thread-action-transition-currentbase`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`; native PHP keeps PDF navigation metadata at the pdftext/PDFium extraction boundary and does not execute viewer actions, Python workers, models, or external PDF tools.
- PDF Thread actions are a standard PDF action type: `/S /Thread` follows an article thread, with `/F` for an external file, `/D` for a thread object/index/title, and `/B` for a selected bead object/index. Source references checked for this slice: iText `PdfAction.createThread(...)` documents ISO 32000-1 section 12.6.4.6 and Thread action `fileSpec`, destination thread, and bead operands at https://api.itextpdf.com/iText/java/7.2.3/com/itextpdf/kernel/pdf/action/PdfAction.html; the PDF 1.3 reference summary identifies Thread action `/D` and `/B` attributes.

## Behavior

`PdfOutlineExtractor` now treats outline `/A << /S /Thread ... >>` rows as non-executing navigation review metadata instead of generic unsupported actions. For current-document thread actions it resolves:

- `/D` by thread object reference, thread index, or thread title;
- `/B` by bead object reference or bead index, defaulting to the first bead;
- selected bead page, rectangle, page label, page `/Dur`, `/Trans`, page `/AA`, and catalog `/Threads` context.

Chained `/Next` URI or JavaScript rows inherit the selected Thread target page context, so WordPress review UIs can inspect the action stack without adding same-document TOC rows or exposing action operands as visible paragraphs.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineThreadActionTransitionCurrentBaseTest.php
FAIL reviews outline Thread actions with selected bead transition context
Expected safety: article-thread-review
Actual safety: unsupported-action-review
1 test files, 14 assertions, 1 failures
```

After source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineThreadActionTransitionCurrentBaseTest.php
1 test files, 53 assertions, 0 failures
```

Adjacent outline gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineThreadActionTransitionCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineRemoteThreadActionStackCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineLaunchThreadTransitionContextCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionTransitionCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationFitActionChainCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineArticleThreadBeadNavigationCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php
7 test files, 639 assertions, 0 failures
```

Full outline family:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -name 'PdfOutline*Test.php' | sort)
14 test files, 929 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-thread-action-transition-currentbase.php
passed; emitted Thread action review metadata with selected bead 22, page label Article 42, Push transition context, chained URI target context, and visible-text exclusion flags.
```

## Status Delta

- `phpPass`: `773 -> 775`
- mapped semantics: `549 -> 550 / 78`
- Added WordPress smoke: `wordpress-pdf-outline-thread-action-transition-currentbase.php`

## Non-Overlap

This does not repeat catalog `/Threads` bead navigation, named-destination action review, launch/remote action-stack target context, page PieceInfo transition/thread review, remote GoToE transition review, page transition metadata, or catalog OpenAction review. The bounded behavior is specifically PDF `/S /Thread` action dictionaries and selected `/B` bead targets in outline action stacks.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, outline action walker, catalog `/Threads` bead metadata, page-label resolver, page transition/action review parser, and visible text extraction boundary. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers.
