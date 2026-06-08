# markerPDF Link Annotation Next Action Value Boundary

Slice: `markerpdf-annotations-links-boundary-current-base-20260608T204825Z`
Base: `760ca6aa9f81ad19edcddbf9a887d409a553e927`

## Source Truth

Upstream markerPDF delegates searchable PDF link extraction to the native PDF parser/text boundary rather than executing PDF actions. The corresponding no-GPU PHP boundary is PDF action dictionary review: annotation `/A` primary actions can promote safe URI links, while action-chain `/Next` entries are action dictionaries or arrays of action dictionaries. Scalar destination values and dictionaries without `/S` under `/Next` are malformed action-chain values and must not become local destination rows.

## Behavior

`PdfActionReviewExtractor` now disables destination fallback while recursively reviewing `/Next` action-chain values. A malformed scalar `/Next (named-target)` and a dictionary `/Next << /D (named-target) >>` are retained as `malformed-action-dictionary` review metadata. A valid `/Next << /S /GoTo /D (named-target) >>` still resolves as chained `local-destination` review metadata.

The WordPress path still promotes the safe primary URI for the annotation span, but malformed chained destinations do not become hidden same-document link targets, visible text, or Markdown hrefs.

## Evidence

Red-first:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationNextActionValueBoundaryCurrentBaseTest.php
```

Result: `1 test files, 3 assertions, 1 failures`; scalar `/Next` was classified as `local-destination`.

Focused green:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationNextActionValueBoundaryCurrentBaseTest.php
```

Result: `1 test files, 35 assertions, 0 failures`.

Adjacent regression:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionArrayBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationAdditionalActionOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPreviousUriBoundaryCurrentBaseTest.php
```

Result: `4 test files, 155 assertions, 0 failures`.

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-link-next-action-value-boundary-currentbase.php
```

Result: exits `0`; reports `scalar_next_local_destination_promoted=false`, `dictionary_without_s_next_local_destination_promoted=false`, `valid_goto_next_preserved=true`, and no Python/model/external PDF tool execution.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, action review extractor, link annotation extractor, text extractor, Markdown post-processor, and WordPress smoke path. It does not run GPU/model code, OCR, Python, PDF action execution, network services, or external PDF tools.

## Non-Overlap

This does not repeat accepted primary action scalar/array rejection, duplicate action key review, previous URI `/PA`, additional action tailed operands, remote GoToR, URI base resolution, QuadPoints, hidden flags, optional content, widget inherited actions, or page `/Annots` reference boundaries. The bounded behavior is specifically destination fallback suppression for malformed `/Next` action-chain values.
