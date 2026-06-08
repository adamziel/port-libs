# pandoc-epub3-package-core-current-base-20260608T104606Z

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-epub3-package-core-current-base-20260608T104606Z`
- Base accepted HEAD: `53829eeb84ed0d66a52425c8a2b7d09e8158ea35`
- Behavior: bounded native EPUB3 OPF metadata `<link>` vocabulary handoff for
  `rel` and `properties` tokens.

## Behavior

`EpubReader` now attaches vocabulary reports to resolved OPF metadata links:

- bare NMTOKEN terms such as `record`, `alternate`, and `schema-org`;
- package-prefix terms such as `schema:associatedMedia` and
  `review:packet`, resolved through the existing OPF `prefix` bindings and
  reserved package prefixes;
- absolute URL vocabulary terms with fragments;
- diagnostics for invalid slash-containing tokens, repeated tokens on the same
  link, and unknown package prefixes.

The aggregate `metadata.linkVocabulary` summary is exposed through the package
result, import report, and document metadata so WordPress review queues can
audit external metadata records, accessibility records, voicing links, and
source records without fetching remote resources or running EPUB validators.

## Evidence

Baseline focused test before this slice:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 2272 assertions, 0 failures
```

Red-first focused run after adding the focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
FAIL reports OPF metadata link vocabulary tokens for package review
1 test files, 2286 assertions, 1 failures
```

Final focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 2325 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
epub3 package handoff self-test ok
```

Additional checks:

```text
php -l lanes/pandoc/src/EpubReader.php
php -l lanes/pandoc/tests/EpubReaderTest.php
php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php
php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $file . ": ok\n"; }'
git diff --check -- lanes/pandoc
```

## Delta

- Focused PHP PASS cases: `+1`
- Focused assertions: `2272 -> 2325` (`+53`)
- `lane-status.json` `phpPass`: `1617 -> 1618`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2036 -> 2037`
- EPUB3 package core cases: `6 -> 7`
- EPUB3 package core assertions: `112 -> 165`

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`EpubReader` DOM/OPF metadata parser, package prefix vocabulary binding model,
package-reference resolver, focused `EpubReaderTest.php` coverage, and the
WordPress EPUB3 package handoff example.

Pandoc, Cabal solver/build/test commands, Haskell runners, Stack, `zip`,
`unzip`, ZipArchive, EPUBCheck, browser renderers, external validators, online
services, live provider tests, and live-service provider tests were not run.

## Non-Overlap

This does not repeat accepted EPUB OCF mimetype/container/rootfile validation,
OCF sidecars, OPF metadata/DC/meta extraction, metadata link target resolution
or subject attachment, vendor metadata, collection membership metadata, OPF
manifest/spine order, fixed-layout spine properties, nav/NCX/page-list parsing,
primary navigation target policy, guide/collections, alternate renditions,
fallback chains, bindings, remote-resource reconciliation, encryption/font
preflight, SMIL media overlays, EPUB CFI fragments, XHTML content resource
scanning, CSS resource scanning, cover/asset reports, or auxiliary navigation
summaries.

## Follow-Up

Keep nav target media-fragment policy, CSS cascade/resource export metadata,
EPUBCheck-style structural validation, encrypted resource decryption policy,
active media-overlay playback semantics, and full XHTML-to-AST conversion as
separate bounded EPUB slices.
