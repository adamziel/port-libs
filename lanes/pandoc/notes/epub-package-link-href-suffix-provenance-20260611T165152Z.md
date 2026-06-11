# epub-package-link-href-suffix-provenance-20260611T165152Z

Slice: EPUB3 package link href suffix provenance.

This slice extends compact native `EpubPackage` ingestion so OCF metadata
links, OPF metadata links, and OPF collection links expose the same
`hrefHasQuery`, `hrefQuery`, `hrefHasFragment`, and `hrefFragment` provenance
already used by manifest and guide href review paths. Local ZIP part lookup
still strips query and fragment suffixes, so manifest linkage, byte length,
CRC, compression, and WordPress handoff summaries continue to resolve against
the real package part.

Verification on current `origin/main` `6995e705a`:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 test file, 1188 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 64116 assertions, 0 failures

No Pandoc, EPUBCheck, zip/unzip, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were run.

This does not repeat accepted manifest href suffix, guide href suffix,
remote-resource policy, collection inventory, accessibility metadata, ZIP
compression provenance, encryption, bindings, media overlays, nav/NCX, or
rootfile validation work. The new surface is only package-level link href
suffix provenance for existing EPUB link summaries.
