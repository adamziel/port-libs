# pandoc-odf-signature-sidecar-provenance-current-base-20260610T113332Z

## Summary

Implemented one bounded ODF/ODT package ingestion slice in native PHP:

- `OdfReader` now discovers XML Digital Signature sidecars under `META-INF/*signatures.xml` from both `META-INF/manifest.xml` entries and actual package entries.
- Package-level `signatureMetadata` is attached to document attrs, the reader result, and `importReport`.
- Signature sidecars remain out of media byte handoff.
- The metadata records part existence, manifest media type, byte lengths, CRC provenance, parseability, signature counts, reference counts, signed package parts, digest method/value lengths, transforms, and malformed/encrypted/missing diagnostics.

This is package provenance only. The reader does not validate signatures, verify digests, load certificates, or perform cryptographic trust decisions.

## Source Truth

The slice follows the ODF/ODT package-reader contract: auxiliary package sidecars that affect document trust and review workflow must stay visible to import queues without exposing unsafe bytes or invoking external tools. XMLDSig sidecars are parsed only far enough to preserve bounded `SignedInfo` reference metadata and reviewer diagnostics.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, office suite, TeX/PDF engine, browser renderer, zip/unzip command, external validator, online service, live provider test, or live-service provider test was executed.

## Verification

Focused post-rebase check:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 3481 assertions, 0 failures
```

Full post-rebase lane gate:

```text
php tools/run-tests.php lanes/pandoc/tests
44 test files, 59698 assertions, 0 failures
```

Syntax checks:

```text
php -l lanes/pandoc/src/OdfReader.php
php -l lanes/pandoc/tests/OdfReaderTest.php
```

Both syntax checks passed.

## Counters

- `lane-status.json` `suiteProgress`: 856 -> 857
- `lane-status.json` `phpPass`: 2954 -> 2955
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: 3125 -> 3126
- `mappedOdfSignatureSidecarCases`: 0 -> 1
- `odfSignatureSidecarAssertions`: 0 -> 30

## Dependency Closure

No new support component is needed. This reuses `OdfReader`, `ZipPackage`, `ZipPackageEntry`, the existing safe XML loader, and the existing generated ODT package fixtures.

## Non-Overlap And Follow-Up

This slice does not repeat accepted ODF field, style, RDF sidecar, media manifest, encryption, duplicate-style, content-declaration, or table-change work. Good follow-ups are ODF package signature relationship edge cases, encrypted sidecar review summaries, or additional OpenDocument trust/provenance metadata that can stay native PHP and external-tool free.
