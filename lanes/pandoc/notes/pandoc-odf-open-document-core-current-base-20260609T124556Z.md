# ODF/OpenDocument Manifest Validation Handoff

Slice: `pandoc-odf-open-document-core-current-base-20260609T124556Z`
Base accepted HEAD: `a38edfb50352ef212fcb62803d82a7ae9bd2908c`
Date: 2026-06-09 UTC

## Behavior

Native `OdfReader` package preflight now rejects structurally invalid ODT
manifests before any content conversion handoff:

- missing root `manifest:file-entry` for `/`
- root media type that is not `application/vnd.oasis.opendocument.text`
- manifest files that omit `content.xml`
- duplicate raw `manifest:full-path` entries
- duplicate decoded package parts such as `Pictures/hero.png` and
  `Pictures/hero%2Epng`

This stays inside the bounded ODF package contract. It does not invoke Pandoc,
Cabal, Haskell tests, Word, LibreOffice, zip/unzip, external converters,
online services, live provider tests, or live-service provider tests.

## Evidence

Focused ODF reader verification:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 3373 assertions, 0 failures
```

WordPress ODF handoff smoke:

```text
php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test
odf open document handoff self-test ok
```

Syntax, JSON, and whitespace checks:

```text
php -l lanes/pandoc/src/OdfReader.php
No syntax errors detected in lanes/pandoc/src/OdfReader.php

php -l lanes/pandoc/tests/OdfReaderTest.php
No syntax errors detected in lanes/pandoc/tests/OdfReaderTest.php

php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'
pandoc json ok

git diff --check -- lanes/pandoc
```

## Status Delta

- `phpPass`: `2778 -> 2779`
- `benchmarkDenominator.mapped`: `3015 -> 3016`
- `mappedOdfOpenDocumentCoreCases`: `13 -> 14`
- `odfOpenDocumentCoreAssertions`: `295 -> 300`
- Focused ODF test growth: `+1` PASS case and `+5` assertions.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`,
safe DOM XML loading, existing ODF manifest path normalization, `OdfReader`
package preflight, the focused ODF test suite, and the existing WordPress ODF
handoff example.

Full upstream Pandoc ODT runner parity remains a separate upstream-runner
dependency task requiring hydrated pinned upstream sources and Haskell test
executables.

## Non-Overlap

This does not repeat accepted ODF work for RDF sidecars, inline RDF metadata,
manifest media encryption, missing media reports, preferred view modes,
settings/meta XML, table formulas, sheet-name fields, page continuation,
dropdown fields, database ranges, data pilots, tracked changes, draw layers,
charts, embedded objects, table cell style provenance, or data-style grammar.
It only closes the package-level manifest validity gate for ODT handoff.

## Root Harness

Not run - isolated micro-slice.
