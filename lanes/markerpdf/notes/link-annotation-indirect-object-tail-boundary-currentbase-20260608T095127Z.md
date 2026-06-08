# Link Annotation Indirect Object Tail Boundary

- Slice: `markerpdf-annotations-links-boundary-current-base-20260608T095127Z`
- Base accepted HEAD: `f37923538221acd51c7fa0f16b86121e0ff32955`
- Scope: native no-GPU markerPDF PDF parser/action review behavior only.

## Source Truth

PDF annotation `/A` entries and `/Dest` entries are imported as review metadata first, then only safe primary URI/local-destination rows are promoted into WordPress spans. An indirect object used as a link action or destination must be a single object value for this native boundary. If the object body contains a valid dictionary or array followed by a second top-level operand before `endobj`, the object is treated as malformed for action/destination promotion. Stream dictionaries remain allowed to have a `stream` body tail.

This maps markerPDF's safe import boundary: PDF actions are not executed, and review-only action/destination payloads must not leak into visible Gutenberg text or Markdown links.

## Implementation

- `PdfActionReviewExtractor` now tags parsed indirect object values with an `objectTrailingOperandReview` marker when a non-stream object body has a second top-level operand after its first parsed value.
- Action dictionaries with that marker produce `malformed-action-dictionary` review rows and do not walk `/Next` chains.
- Local/remote destination resolution, FileSpec resolution, and destination map admission now reject tagged indirect values.
- `PdfLinkAnnotationExtractor` behavior changes through the shared action reviewer: tailed indirect `/A` and `/Dest` objects no longer produce promoted WordPress links.

## Evidence

Red-first:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectObjectTailBoundaryCurrentBaseTest.php
```

Before the parser patch: `1 test file / 4 assertions / 1 failure`; the tailed indirect `/A` object was reviewed as `review-uri`.

After the patch:

```text
1 test files, 28 assertions, 0 failures
```

Adjacent focused family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/Pdf(LinkAnnotation|AnnotationLink|ActionReview).*Test\.php$' | sort)
57 test files, 1974 assertions, 0 failures
```

Broader action-focused family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/Pdf.*Action.*Test\.php$' | sort)
68 test files, 3390 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-link-annotation-indirect-object-tail-currentbase.php --self-test
```

Result: exits 0 with `promoted_link_objects=[7]`, `tailed_action_object_rejected=true`, `tailed_destination_object_rejected=true`, `tailed_payload_in_review=false`, `executes_pdf_actions=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP PDF tokenizer/object parser and action reviewer. No Python, CUDA, OCR, model execution, PDFium, pypdfium2, browser, raster renderer, or external PDF tool is required.

## Non-Overlap

This does not repeat the prior CMap array end-operator stream-boundary slice, existing direct tailed `/A` and `/Dest` operand tests, primary `/A` array/scalar boundaries, duplicate action subtype/key handling, object-stream action selection, link geometry, optional-content, xref free-entry, or named-destination limit boundaries. The new behavior is limited to tailed indirect object bodies used as link action/destination values.
