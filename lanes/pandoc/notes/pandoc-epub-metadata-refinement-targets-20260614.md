# pandoc-epub-metadata-refinement-targets-20260614

Slice: `plib-s4ngz`, EPUB3 package ingestion.

Base: `origin/main` at `1018e5f40c` after ZIP inventory manifest accounting.

`EpubPackage` now reports OPF metadata `<meta refines>` target accounting for
compact package review. Local refinement subjects are matched against package,
Dublin Core metadata, OPF metadata meta, manifest item, spine itemref, metadata
link, collection, collection link, and collection metadata ids.

Unresolved local refinement subjects are surfaced in
`validation.metadata.refinementTargetDiagnostics`,
`metadata.refinementTargets`, and WordPress import metadata handoff. Duplicate
identifier semantics remain owned by the existing identifier diagnostics, so
duplicate ids are counted in the target inventory without adding a second
validation failure.

Accounting:
- `phpPass`: 3509 -> 3510
- `phpFail`: 0
- `mappedEpubMetadataRefinementTargetCases`: 1
- `epubMetadataRefinementTargetAssertions`: 30

Verification:
- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 file, 2382 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 files, 82744 assertions, 0 failures

No Pandoc binary, EPUBCheck, zip/unzip, ZipArchive, Cabal/Haskell runner,
browser renderer, external validator, online service, live provider test, or
live-service provider test was invoked.
