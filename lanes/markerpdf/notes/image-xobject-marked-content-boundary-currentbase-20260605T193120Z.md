# markerPDF Image XObject marked-content boundary current base

## Source truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps rendered image handling in `marker/pdf/images.py::render_image_rgb`, separate from searchable text extraction and later OCR/model paths:

https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py

For the native no-GPU PHP lane, Image XObject invocations are media review boundaries. Marked-content tags, MCIDs, `/Alt`, and `/ActualText` around an image invocation should be preserved as review metadata for the image row, while image-only replacement strings and raster payload bytes must not become visible WordPress paragraphs. Text-marked content that actually wraps text-showing operators remains paragraph replacement text.

## Behavior

`PdfTextExtractor` now:

- tracks `BMC`/`BDC`/`EMC` state while collecting Image XObject invocation details;
- resolves direct marked-content dictionaries and `/Resources /Properties` entries for tag, MCID, `/Alt`, and `/ActualText`;
- stores invocation marked-content stacks on Image XObject review rows as `invocation_marked_content` with `marked_content_review_only=true`;
- preserves non-Form XObject `Do` operators through page/form text-stream expansion so text extraction can identify image-only marked-content boundaries;
- suppresses marked-content replacement fallback only when the marked content enclosed a known image XObject `Do`, preserving existing text-wrapped `/ActualText` and `/Alt` replacements.

The new focused fixture covers two non-artifact `Figure` wrappers:

- direct `/Figure << /MCID 7 /Alt (...) /ActualText (...) >> BDC ... /Tagged#20Image Do ... EMC`;
- resource-backed `/Figure /Image#20Props BDC ... /Property#20Image Do ... EMC` where `/Resources /Properties /Image#20Props` carries `/MCID 8` and `/Alt`.

Both image entries are invoked and carry review-only marked-content stacks; visible text remains only `Before tagged images` and `After tagged images`.

## Evidence

Red-first before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
FAIL records non-artifact marked-content metadata at image XObject invocation boundaries
Expected: true
Actual: NULL
1 test files, 988 assertions, 1 failures
```

Focused passing run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
1 test files, 1008 assertions, 0 failures
```

Adjacent marked-content run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageAssociatedFilesMarkedContentAltCurrentBaseTest.php lanes/markerpdf/tests/PdfPageArtifactMarkedContentClipCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentOperandBoundaryCurrentBaseTest.php
4 test files, 61 assertions, 0 failures
```

Broad coupled observation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 626 assertions, 2 failures
```

The broad file is not counted as a passing verification for this slice. Its remaining failures are ToUnicode `usecmap` expectations outside this image-boundary change (`inherits ToUnicode usecmap mappings...` and `guards cyclic ToUnicode usecmap...`); the marked-content cases in that file pass after this patch.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-marked-content-currentbase.php
```

The smoke emits `tagged_image_mcid=7`, `property_image_mcid=8`, `tagged_alt_reviewed=true`, `tagged_actual_text_reviewed=true`, `property_alt_reviewed=true`, `visible_text_excludes_marked_image_replacement=true`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted image payload exclusion, optional-content image visibility, Form XObject image recursion, ExtGState transparency, clipping/page geometry, SMask/Mask/Decode/filter metadata, Do operand malformed handling, Type3 CharProc marked-content behavior, inline image tokenization, OCR/model execution, or upstream raster benchmark parity. The bounded behavior is specifically non-artifact marked-content metadata at real Image XObject invocation boundaries and the visible-text boundary for image-only `/Alt`/`/ActualText`.

## Dependency closure

No new support component is needed. This reuses the native PDF object parser, resource dictionary lookup, marked-content property parsing, content-stream tokenizer, XObject invocation review, and WordPress smoke path. Full upstream markerPDF parity remains gated by no-GPU scope limits: live OCR, Surya/Texify/Torch/model execution, Streamlit/FastAPI workers, pypdfium/PIL raster parity, and exact upstream model benchmarks were not run.
