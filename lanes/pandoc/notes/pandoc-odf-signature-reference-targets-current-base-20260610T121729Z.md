# pandoc-odf-signature-reference-targets-current-base-20260610T121729Z

## Summary

Implemented one bounded ODF/ODT package ingestion slice in native PHP:

- `OdfReader` now annotates XML Digital Signature sidecar references with package target provenance.
- Each local signed part records whether the target is manifest-declared, present in the ZIP package, encrypted, byte-exposable, its manifest media type, declared size, stored byte length, and stored CRC.
- Package-level `signatureMetadata` now aggregates missing, unmanifested, and encrypted signed-part inventories for reviewer handoff.

This is package provenance only. The reader does not validate signatures, verify digests, load certificates, or perform cryptographic trust decisions.

## Source Truth

The slice follows the existing ODF/ODT package-reader contract for `META-INF/*signatures.xml`: native import reports should make package trust and review relationships visible without invoking external tooling or exposing encrypted bytes.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, office suite, TeX/PDF engine, browser renderer, zip/unzip command, external validator, online service, live provider test, or live-service provider test was executed.

## Verification

Focused checks:

```text
php -l lanes/pandoc/src/OdfReader.php
No syntax errors detected in lanes/pandoc/src/OdfReader.php

php -l lanes/pandoc/tests/OdfReaderTest.php
No syntax errors detected in lanes/pandoc/tests/OdfReaderTest.php

php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 3533 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests
44 test files, 59923 assertions, 0 failures
```

## Counters

- `lane-status.json` `phpPass`: 2957 -> 2958
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: 3129 -> 3130
- `mappedOdfSignatureSidecarCases`: 1 -> 2
- `odfSignatureSidecarAssertions`: 30 -> 72

## Dependency Closure

No new support component is needed. This reuses `OdfReader`, `ZipPackage`, `ZipPackageEntry`, the existing safe XML loader, and the existing generated ODT package fixtures.

## Non-Overlap And Follow-Up

This slice does not repeat accepted ODF signature sidecar discovery/parsing, manifest declared-size mismatch, encrypted media, RDF sidecar, style, field, or content-declaration work. Good follow-ups are encrypted signature sidecar review summaries and additional OpenDocument trust/provenance metadata that can stay native PHP and external-tool free.
