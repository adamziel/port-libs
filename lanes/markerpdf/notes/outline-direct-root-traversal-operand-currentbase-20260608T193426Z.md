# markerpdf outline direct-root traversal operand current-base slice

Date: 2026-06-08 UTC
Session: port-dev-markerpdf-outline-meta-20260608T193426Z
Base accepted HEAD: 88bef356b21ec3553df0dd68cc49fd772a2059fd

## Behavior

PDF outline root dictionaries use `/First`, `/Last`, and `/Count` as single-value traversal operands. Referenced outline roots already had object-body guards for malformed trailing operands, but direct catalog roots such as `/Outlines << ... >>` have no root object body. This slice adds the same traversal boundary for selected direct catalog `/Outlines` dictionaries before native TOC/navigation promotion.

The new fixture covers:

- direct root `/First 6 0 R 9 0 R` suppresses outline item traversal;
- direct root `/Last 7 0 R 9 0 R` suppresses TOC, navigation, structure-context, and remote-action rows;
- document outline root review remains payload-free and visible page text remains importable;
- malformed outline titles/actions stay out of document metadata, lightweight `pdf_toc`, navigation review, remote action review, and visible WordPress paragraphs.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataDirectRootTraversalOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
2 failures: direct-root /First and /Last malformed operands still produced TOC rows.
1 test files, 21 assertions, 2 failures
```

After fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataDirectRootTraversalOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects direct outline root First references with trailing operands before TOC promotion
PASS rejects direct outline root Last references with trailing operands before navigation and remote action review
1 test files, 68 assertions, 0 failures
```

Adjacent boundary family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataDirectRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataDirectRootMetadataStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataDirectRootTraversalOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataCatalogOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataUnselectedCatalogOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataRootTraversalOperandBoundaryCurrentBaseTest.php
6 test files, 264 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-direct-root-traversal-operand-currentbase.php
exits 0; emits markerpdf-outline-direct-root-traversal-operand-currentbase with malformed_direct_root_traversal_rejected=true, executes_python_or_models=false, executes_external_pdf_tools=false.
```

Syntax:

```text
php -l lanes/markerpdf/src/PdfOutlineExtractor.php
php -l lanes/markerpdf/tests/PdfOutlineMetadataDirectRootTraversalOperandBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-outline-direct-root-traversal-operand-currentbase.php
```

## Non-overlap

This does not repeat the accepted direct-root parent boundary, direct-root metadata-stream review, catalog `/Outlines` duplicate operand boundary, referenced outline root traversal operand boundary, `/Last` sibling boundary, generation-exact outline references, or xref `/Prev` outline owner slices. It only adds the missing direct catalog outline-root traversal operand guard in `PdfOutlineExtractor`.

## Dependency closure

No new support component is needed. The slice reuses the native PDF tokenizer, outline parser, document metadata extractor, text extractor, and WordPress smoke harness. It does not execute Python, OCR/model code, multiprocessing, pypdfium, or external PDF tools.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior: outline/action metadata boundaries, metadata stream filtering, font/CMap text extraction, xref repair, annotations/forms/security preflight, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
