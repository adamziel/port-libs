# Pandoc PDF Engine Handoff - StructTreeRoot IDTree

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260608T203257Z`
Base accepted HEAD: `bb37a42dff2002404bb134df44da31542c787c36`
Date: 2026-06-08 UTC

## Behavior

This slice adds bounded native PHP handoff support for tagged-PDF
`/StructTreeRoot /IDTree` name-tree metadata in `PdfEngineHandoff`.

The fake runner now extracts:

- decoded IDTree names from literal and UTF-16 hex PDF strings;
- `/Limits` string metadata on IDTree nodes;
- target value kind and target object reference;
- structure references reachable from target values;
- missing reference diagnostics for unresolved IDTree targets;
- sequence-level `finalPdfStructureIdTree` handoff data.

The lane-local WordPress PDF handoff example now exposes `pdfStructureIdTree`
and `finalPdfStructureIdTree` in the review packet and self-tests the new
diagnostics.

## Verification

Baseline before the new assertion:

```text
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
1 test files, 956 assertions, 0 failures
```

Red-first after adding the focused IDTree test, before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
1 test files, 958 assertions, 1 failures
Failure: undefined pdfStructureIdTree result key.
```

Final focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
1 test files, 965 assertions, 0 failures
```

Final additional checks:

```text
php -l lanes/pandoc/src/PdfEngineHandoff.php
No syntax errors detected in lanes/pandoc/src/PdfEngineHandoff.php
php -l lanes/pandoc/tests/PdfEngineHandoffTest.php
No syntax errors detected in lanes/pandoc/tests/PdfEngineHandoffTest.php
php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-pdf-engine-handoff.php
php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test
pdf engine handoff self-test ok
git diff --check -- lanes/pandoc
passed with no whitespace errors
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted PDF engine clusters for engine argv,
template/resource planning, log/sidecar diagnostics, produced-output metrics,
multipass state, bibliography sidecars, xref/object streams, page metadata,
outlines, document-info/XMP/PDF-A/PDF-UA, output intents, URI base, catalog
requirements, legal attestation, DSS, signatures, AcroForm, active actions,
optional content, annotations, embedded files, marked-content properties,
parent-tree mappings, structure element accessibility metadata, structure
attributes, ClassMap extraction, or structure ClassMap usage.

## Dependency Closure

No new support component is needed. The implementation reuses the existing
bounded PDF byte parser, dictionary/reference resolution helpers, PDF structure
tree inspection path, focused PHP test harness, and lane-local WordPress PDF
handoff example.

No Pandoc, Cabal/Haskell runner, TeX/PDF engine, Typst, browser renderer, roff
renderer, external PDF validator, online service, live provider test, or
live-service provider test was executed.

## Follow-Up

Next PDF-engine work should stay non-overlapping, such as RoleMap/ClassMap
interaction details, structure destination references, or incremental signature
revision provenance.
