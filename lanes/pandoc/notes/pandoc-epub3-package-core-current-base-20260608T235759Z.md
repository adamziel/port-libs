# EPUB3 Package Current-Base Encrypted Exposure Policy

Slice: `pandoc-epub3-package-core-current-base-20260608T235759Z`  
Base accepted HEAD: `98e36d1bfbcd2aff359b39b4120999431e5e0fde`

## Behavior

`EpubReader` now builds a package-level `encryption.exposure` summary from
`META-INF/encryption.xml`. Encrypted resources are classified by OPF package
role (`stylesheet`, `audio`, `image`, `font`, and related package roles), all
encrypted byte exposure stays blocked, IDPF-obfuscated fonts are separated from
other encrypted resources, and the role/review/byte-exposure policy is
propagated to manifest item encryption metadata and the WordPress import
report.

This keeps encrypted EPUB assets explicit for import review without exposing
protected package bytes as attachment candidates.

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` notes existed.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed with `1 test files, 3169 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  failed with `1 test files, 3170 assertions, 1 failures` because
  `encryption.exposure` metadata was absent.
- Final: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed with `1 test files, 3198 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  passed with `epub3 package handoff self-test ok`.

Focused delta: one new mapped PHP PASS case and 29 focused assertions. Lane
status moves `phpPass` from 1992 to 1993. The manifest mapped denominator moves
from 2410 to 2411, with `mappedEpub3PackageCoreCases` from 6 to 7 and
`epub3PackageCoreAssertions` from 112 to 141.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `EpubReader`
package parsing, `ZipPackage` fixtures, OPF manifest metadata, and bounded OCF
`encryption.xml` parsing. No Pandoc, Cabal solver/build/test command, Haskell
runner, zip/unzip, EPUBCheck, browser renderer, online service, live provider
test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat prior EPUB3 package work for OCF container/rootfile
selection, OPF metadata/manifest/spine parsing, nav/NCX table-of-contents and
page-list/navList handoff, XHTML resource scans, CSS resource reference scans,
custom media bindings, remote-resource reconciliation, sidecar metadata,
media-overlay timing/sequence provenance, or the earlier obfuscated-font
preflight. It only adds the package-role exposure summary and byte/attachment
review policy for encrypted resources.

## Next

A next EPUB3 package slice can cover OPF collection/landmark preview metadata
or CSS cascade/import review policy, while avoiding the accepted encryption
exposure policy and existing OCF/nav/NCX/XHTML/SMIL package surfaces.
