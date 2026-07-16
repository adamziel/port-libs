# markerPDF Image XObject Resource Entry Tail Boundary

Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260606T165437Z`

## Source Truth

Upstream `sddai/markerPDF` at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable text extraction separate from image rendering. Under the current no-GPU markerPDF scope, this PHP lane owns the native PDF parser boundary before an Image XObject would be handed to `marker.pdf.images.render_image`.

PDF resource dictionaries are key/value dictionaries. A resource entry such as `/Im1 5 0 R` has one indirect-reference value; an extra top-level operand before the next `/Name` key makes that entry malformed. The native parser already rejected malformed indirect XObject dictionary object tails. This slice adds the equivalent direct `/XObject` resource-entry tail boundary.

## Behavior

`PdfTextExtractor::topLevelResourceReferenceEntries()` now has an XObject-only strict mode. `xObjectResourceObjectNumbers()` and `xObjectResourceReferences()` use that mode so a direct resource dictionary like:

```text
/XObject << /Bad 5 0 R 99 0 R /Good 6 0 R >>
```

skips `/Bad` before image placement review while preserving `/Good`. Whitespace and PDF comments after a valid resource value remain allowed.

The image payload for the malformed resource entry is excluded from review rows and visible WordPress text. Valid sibling image resources still record invocation matrices, bboxes, decoded hashes, and review-only metadata.

## Red First

Before the source edit, a current-base probe accepted the malformed direct resource entry:

```text
{"count":2,"invoked":2,"names":[["Bad Tail",true,5],["Good Image",true,6]]}
```

After the source edit, the same probe returned only the valid sibling:

```text
{"count":1,"invoked":1,"names":[["Good Image",true,6]]}
```

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectResourceEntryTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects malformed direct XObject resource entry tails before image placement review
1 test files, 37 assertions, 0 failures
```

Focused Image XObject family:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfImageXObject*CurrentBaseTest.php' -o -name 'PdfPageResourceImageXObjectInheritanceCurrentBaseTest.php' \) | sort)
14 test files, 1683 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-resource-entry-tail-currentbase.php
```

The smoke exits 0 and emits `malformed_resource_entry_excluded=true`, `valid_sibling_image_painted=true`, `comment_tail_image_painted=true`, `bad_payload_hash_excluded_from_review=true`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, direct/indirect resource wrappers, duplicate resource-name rejection, indirect XObject dictionary object-tail rejection, Form XObject traversal, page resource inheritance, optional content, compatibility/text-object `Do` rejection, malformed `Do`/`cm` operands, masks/SMask/alternates/metadata/OPI, color-space Decode and preview-only filters, page clipping, q/Q current-path handling, ExtGState transparency, artifact/marked-content metadata, pattern image paints, Type3 CharProc images, or encrypted fail-closed image review.

The bounded behavior is only direct `/XObject` resource entries whose indirect-reference value is followed by a non-name top-level tail operand before the next resource key.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, content tokenizer, exact-generation object lookup, stream decoders, Image XObject review rows, and WordPress smoke renderer. Full pixel/raster parity remains gated on PDFium/pypdfium/PIL or a future native raster backend. Live OCR, Surya/Texify/Torch model execution, Streamlit/FastAPI workers, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
