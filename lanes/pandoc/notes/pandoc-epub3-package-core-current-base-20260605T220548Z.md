# EPUB3 OCF Metadata Sidecar Handoff

Slice: `pandoc-epub3-package-core-current-base-20260605T220548Z`

Accepted base: `0767b5e8420e6daf3987ebf609a811fb16a2a427`

## Source Truth

- W3C EPUB 3.3 OCF reserves optional `META-INF/metadata.xml` for container-level metadata and recommends the `http://www.idpf.org/2013/metadata` metadata root while allowing backward-compatible alternatives: https://www.w3.org/TR/epub-33/
- EPUB Reading Systems 3.3 treats unrecognized `metadata.xml` roots as ignorable rather than fatal, so this slice reports review diagnostics without blocking package handoff: https://www.w3.org/TR/epub-rs-33/

## Behavior

- `EpubReader` now reports optional OCF `META-INF/metadata.xml` alongside existing encryption, rights, and signatures sidecars.
- Metadata reports preserve byte length, CRC, SHA-256, root name/namespace, `xml:lang`, recommended-root status, child record text, attributes, namespace qualification, media type, and IDs.
- Child `href`, `src`, and `URI` references are resolved through the existing OCF sidecar reference path, preserving local, remote, missing, and fragment-only targets without fetching.
- Namespace-unqualified metadata children are kept visible with review diagnostics instead of causing an import failure.
- WordPress EPUB review packets now expose container metadata sidecars via `importReport['ocf']['metadata']` and document `ocf` attributes.

## Verification Evidence

Red-first:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
FAIL reports OCF metadata sidecar records for container-level review
1 test files, 1053 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1092 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
epub3 package handoff self-test ok
```

Focused delta: `+1` PHP PASS case and `+40` focused assertions.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ZipPackage`, DOM parsing, `EpubReader` OCF sidecar reporting, and the existing WordPress EPUB package handoff example. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, ZipArchive, browser renderer, online sanitizer, online service, or live provider test was executed.

## Follow-Up

Keep OCF `manifest.xml` reporting, richer container metadata vocabularies, XHTML-to-AST conversion, media extraction/export policy, CSS cascade behavior, and active media playback behavior as separate bounded slices.
