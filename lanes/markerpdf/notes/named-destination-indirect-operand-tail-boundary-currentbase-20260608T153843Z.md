# markerpdf named-destination indirect operand tail boundary

- Slice: `markerpdf-named-destinations-boundary-current-base-20260608T153843Z`
- Base accepted HEAD: `514053c7d86aad395662bad8b28dd55f8e398a73`
- Scope: native no-GPU markerPDF PDF parser/converter behavior under `lanes/markerpdf/**`.

## Behavior

Named destinations can store page-view and coordinate operands as indirect objects. This slice makes those operands fail closed unless the referenced object body is exactly one top-level PDF value. Hidden top-level tails such as `610 /PrivateCoordinateTail` or `/FitH /PrivateViewTail` no longer promote malformed catalog /Names /Dests rows, legacy /Dests rows, document-destination metadata, or outline navigation metadata.

The WordPress-facing smoke preserves valid name-tree and legacy destinations plus safe URI links, while rejecting the tailed indirect view/coordinate references and keeping hidden destination metadata out of visible text.

## Red-First Evidence

Before the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationIndirectOperandTailBoundaryCurrentBaseTest.php
1 test files, 33 assertions, 1 failures
```

The failing assertion showed `Tailed Coordinate Target` and `Tailed View Target` were imported by the named-destination/document-metadata path.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationIndirectOperandTailBoundaryCurrentBaseTest.php
1 test files, 55 assertions, 0 failures

php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/(PdfNamedDestination|PdfLinkAnnotationNameTreeLimitsBoundary|PdfOutlineNameTree|PdfOutlineActionNameTree|PdfOutlineNamedDestination|PdfOutlineDestinationAction|PdfMetadata.*NameTree|PdfParserNameTree).*Test\.php$' | sort)
82 test files, 3018 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-named-destination-indirect-operand-tail-boundary-currentbase.php
exit 0; tailed_coordinate_destination_rejected=true; tailed_view_destination_rejected=true; promoted_link_objects=[7,10,11]
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PDF token parser, object lookup, metadata extractor, outline extractor, named-destination extractor, and link review paths. It does not run Python, CUDA, OCR, model code, raster rendering, PDF action execution, decryption, network services, or external PDF tools.

## Non-Overlap

This does not repeat prior named-destination coverage for generation-exact references, /Limits traversal, sparse arrays, stream values, indirect name keys, or direct annotation action operands. The new boundary is specifically indirect Fit/view/coordinate operands inside named-destination arrays resolving to a single top-level PDF value.
