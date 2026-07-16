# markerPDF Image XObject explicit subtype boundary current base

Slice: `markerpdf-image-xobject-boundary-current-base-20260607T014334Z`

## Source truth

Pinned upstream markerPDF keeps searchable-PDF text extraction and image rendering as separate PDF parser/rendering paths. `marker/pdf/extract_text.py` routes text through `pdftext.extraction.dictionary_output(...)`, while `marker/pdf/images.py` renders page/bbox images through PDFium and converts them to RGB before later image handoff. Under the current no-GPU PHP lane scope, this patch maps the parser-side XObject subtype boundary before any future raster backend.

PDF XObject dictionaries are subtype-specific. A stream explicitly declared as `/Subtype /Form` or `/Subtype /PS` is not an Image XObject just because it also carries image-like `/Width`, `/Height`, `/ColorSpace`, and `/BitsPerComponent` keys. Those keys can appear in malformed or producer-specific Form/PostScript dictionaries, but the explicit resolved subtype remains authoritative.

## Change

`PdfTextExtractor::isImageStreamDictionary()` now:

- accepts explicit resolved `/Subtype /Image` as before;
- rejects explicit resolved non-Image subtypes before the image-key fallback;
- preserves the existing fallback only when no resolvable subtype name is present.

This keeps a dimensioned Form XObject traversable as a Form so nested Image XObjects are reviewed, while a dimensioned PostScript XObject decoy stays outside Image XObject review.

## Evidence

Pre-fix probe on the accepted base returned a single image review row for the form resource itself:

```text
image_xobject_count=1, resource_name=Form1, subtype=Form, resource_path=["Form1"]
```

Focused verification after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectExplicitSubtypeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps explicit non-Image XObject subtypes out of image-key fallback review
1 test files, 41 assertions, 0 failures
```

Adjacent Form traversal checks:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectFormSubtypeNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectFormAliasSuppressionCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
PASS suppresses page-scope image aliases when the same stream is painted through a Form XObject alias
PASS resolves escaped Form XObject Subtype names before nested Image XObject review
2 test files, 59 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-explicit-subtype-currentbase.php
```

The smoke emits `dimensioned_form_not_counted_as_image=true`, `postscript_decoy_not_counted_as_image=true`, `nested_form_image_path=["Dimensioned Form","Nested Image"]`, `nested_image_sha256_matches=true`, `payload_in_visible_text=false`, and both execution flags false.

PHP lint:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfImageXObjectExplicitSubtypeBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-explicit-subtype-currentbase.php
```

All reported no syntax errors.

## Non-overlap

This does not repeat accepted Image XObject payload exclusion, exact object generation, auxiliary stream generation, Form escaped subtype-name decoding, Form alias suppression, resource-entry tail rejection, duplicate resource names, optional-content visibility, artifact suppression, clipping, page geometry, top-level dimensions, masks/SMask/alternates/metadata/OPI, ColorSpace/Decode/filter review, Type3 CharProc image traversal, pattern image traversal, inline-image tokenizer behavior, or live raster execution. The bounded behavior is only explicit resolved non-Image XObject subtypes before the image-key fallback.

## Dependency closure

No new support component is needed. The slice reuses the native PHP PDF dictionary parser, PDF-name/object resolver, XObject resource walker, Form XObject recursion, stream decoder, Image XObject review rows, focused PHP tests, and WordPress smoke path. Full rendered-image parity remains gated on PDFium/pypdfium/PIL or a future native raster backend; live OCR, Surya/Texify/Torch, model workers, external PDF tools, and exact upstream benchmark parity were not run.
