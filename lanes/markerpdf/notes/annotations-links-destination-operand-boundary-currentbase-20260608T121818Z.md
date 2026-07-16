# markerPDF annotation link destination operand boundary current-base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260608T121818Z`
Session: `port-dev-markerpdf-annotations-links-20260608T121818Z`
Base accepted HEAD: `10bcbb2d7091a3fcd80f2751345f7a527f5879e7`

## Source truth

Upstream markerPDF promotes PDF link annotations only after searchable PDF text
has been extracted. In the native no-GPU PHP boundary, annotation actions and
destinations are review metadata first, and only safe URI or valid local
destination rows become WordPress span links. PDF explicit destination arrays
may use indirect numeric operands, but an indirect scalar object used as a page
or coordinate operand must be one top-level object value. A trailing operand
after the selected scalar is malformed and must not donate a clickable
destination.

## Behavior

`PdfActionReviewExtractor` now checks the raw object body for trailing operands
before accepting resolved local/remote destination operands. This covers scalar
indirect page indexes, view-mode operands, required coordinate operands, and
surplus coordinate operands without changing the general scalar parse shape.

The focused fixture covers:

- a same-page `/Dest [3 0 R /XYZ 20 0 R 21 0 R 22 0 R]` whose indirect
  `left`, `top`, and `null zoom` operands promote because they are exact and
  single-valued;
- an other-page `/Dest [4 0 R /FitH 23 0 R]` whose indirect top coordinate
  promotes;
- a malformed sibling `/Dest [4 0 R /FitH 24 0 R]` where object `24 0 obj`
  contains `640 /PrivateTail`, which remains annotation review metadata and is
  not promoted to WordPress spans;
- a safe URI sibling proving ordinary URI promotion is unchanged.

## Evidence

Red-first:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationDestinationOperandBoundaryCurrentBaseTest.php
```

Before the source fix: `1 test files, 3 assertions, 1 failures`; the tailed
scalar coordinate was incorrectly reviewed as `local-destination`.

Focused command after the fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationDestinationOperandBoundaryCurrentBaseTest.php
```

Result: `1 test files, 34 assertions, 0 failures`.

Adjacent focused regression:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationDestinationOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationSelfDestinationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectNumericBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationTailedOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectObjectTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationLinkDestinationGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationSurplusOperandBoundaryCurrentBaseTest.php
```

Result: `8 test files, 279 assertions, 0 failures`.

Broader shared action/link/named-destination family:

```bash
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/Pdf(LinkAnnotation|AnnotationLink|ActionReview|NamedDestination).*Test\.php$' | sort)
```

Result: `119 test files, 4021 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-link-destination-operand-boundary-currentbase.php
```

Result: exits 0 and emits `promoted_link_objects=[7,8,10]`,
`annotation_action_safety=[["local-destination"],["local-destination"],[],["review-uri"]]`,
`self_indirect_destination_promoted=true`,
`other_indirect_destination_promoted=true`,
`tailed_coordinate_promoted=false`,
`safe_uri_promoted=true`,
`annotation_payload_text_visible=false`,
`executes_pdf_actions=false`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted page `/Annots` ownership, escaped annotation
names, exact annotation object generation selection, annotation `/P` page
membership, indirect `/Rect` and `/QuadPoints` geometry operands, duplicate
action keys/subtypes, direct tailed `/A` and `/Dest` values, tailed indirect
action/destination array or dictionary objects, URI Base, URI control-byte
blocking, IsMap, remote GoToR, name-tree Limits, generation-qualified page
destinations, or named-destination surplus payload rejection. The bounded
behavior is scalar indirect destination operands with top-level tails.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF tokenizer,
object body table, action reviewer, named-destination map, link span promoter,
Markdown post-processor, and WordPress smoke path. Live OCR, Surya/Torch/Texify
models, pypdfium/PDFium rendering, external PDF tools, JavaScript/PDF action
execution, decryption, and exact upstream GPU/model benchmark parity remain
intentionally out of scope for the no-GPU markerPDF lane.
