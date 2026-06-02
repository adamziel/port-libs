# markerPDF AcroForm Action Boundary Current Base

Slice: `acroform-action-boundary-currentbase`
Session: `port-dev-markerpdf-form37pdf-20260602T1843Z`
Base accepted HEAD: `4bfec4c2ed04ec45b69266408311f6827e291bfb`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF conversion through page text blocks from `pdftext.dictionary_output()` and PDFium text pages in `marker/pdf/extract_text.py`; it does not execute AcroForm actions during document import.
- Relevant PDF action behavior for this PHP boundary: AcroForm field/widget `/A`, `/AA`, and `/Next` entries are action dictionaries. Launch actions may carry platform dictionaries such as `/Win`; GoToE actions carry embedded-document navigation operands `/F`, `/D`, `/T`, and `/NewWindow`. These are review metadata, not imported text or executable behavior.

## Implementation

- `PdfAcroFormExtractor` now resolves Launch platform dictionaries (`/Win`, `/Unix`, `/Mac`, `/DOS`) and records target, platform, operation, parameters, default directory, and platform dictionary object when present.
- AcroForm `/S /GoToE` action rows now report `embedded-document-review` safety, file target, destination preview, embedded target dictionary details, nested target dictionaries, and `NewWindow`, all with action execution disabled.
- Added `PdfAcroFormActionBoundaryCurrentBaseTest.php` for the bounded platform Launch + chained GoToE field action and widget GoToE action boundary.
- Added `wordpress-pdf-acroform-action-boundary-currentbase.php` to prove WordPress-facing review metadata is emitted while launch paths, embedded FileSpec names, and embedded payload bytes stay out of visible text.
- Manifest/status movement: PHP behavior tests `648 -> 649`; mapped semantics `474 -> 475 / 78`.

## Verification

Red-first before the source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormActionBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps platform launch and embedded goto AcroForm actions review only at current base
Expected safety labels: launch-action-review, embedded-document-review
Actual safety labels: launch-action-review, unsupported-action-review
1 test files, 4 assertions, 1 failures
```

Passing focused checks after the source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormActionBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps platform launch and embedded goto AcroForm actions review only at current base
1 test files, 37 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 1219 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-action-boundary-currentbase.php
passed; emitted Launch target review-helper.exe, Win platform, GoToE targets embedded-review.pdf and parent-package.pdf, and all execution flags false.
```

Additional required checks:

```text
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
php -l lanes/markerpdf/tests/PdfAcroFormActionBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-acroform-action-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

All passed.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/array tokenizer, FileSpec string resolver, AcroForm action walker, visible text extractor, and existing non-executing action review model. Full upstream markerPDF parity remains gated by pdftext, pypdfium2/PDFium, Surya/OCR, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers; none were run for this bounded PHP slice.

## Non-Overlap

This does not repeat accepted AcroForm SubmitForm/ResetForm field-value review, non-JavaScript URI/Launch/ImportData/Hide/Named/GoTo/GoToR action parsing, nested `/Next` action walking, signature/XFA widget action summaries, rich-media GoToE attachment actions, catalog/page OpenAction review, or security Launch/URI certificate permission review. The new behavior is specifically AcroForm Launch platform dictionary operands plus AcroForm GoToE embedded-document target dictionaries.
