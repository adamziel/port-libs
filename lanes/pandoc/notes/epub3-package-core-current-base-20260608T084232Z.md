# EPUB3 Package Switch Branch Handoff

Slice: `pandoc-epub3-package-core-current-base-20260608T084232Z`

Accepted base: `4b01f722ab0979bb02bbf54f86e6d0f4c3bbc7af`

## Source Truth

- EPUB XHTML content can carry legacy `epub:switch` branch markup with
  capability-gated `epub:case` branches and a fallback `epub:default` branch.
- This slice treats switch content as static review metadata only. It does not
  choose branches, execute media/script behavior, fetch resources, or render a
  reading-system view.

## Behavior

- `EpubReader` now preserves per-XHTML `epub:switch` reports with `id`,
  classes, raw attributes, case/default counts, required namespace/module gates,
  normalized branch text, and validity diagnostics.
- Missing `epub:default`, multiple defaults, and cases with neither
  `required-namespace` nor `required-modules` are surfaced as XHTML package
  diagnostics.
- Aggregate switch counts are exposed in `xhtmlResourceReport`, and WordPress
  raw HTML spine blocks receive `contentSwitches` alongside existing
  `contentTriggers`, `contentSemantics`, and content-resource flags.
- The existing WordPress EPUB package handoff smoke now checks that switch
  fallback text and SVG namespace requirements remain available for review.

## Evidence

Red-first focused run after adding the switch expectations:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
FAIL flags EPUB switch XHTML content for package review
1 test files, 2227 assertions, 1 failures
```

Final focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 2272 assertions, 0 failures
```

Other verification:

```text
php -l lanes/pandoc/src/EpubReader.php
php -l lanes/pandoc/tests/EpubReaderTest.php
php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php
php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'
git diff --check -- lanes/pandoc
```

Results:

- PHP lint passed for all changed PHP files.
- Example smoke passed with `epub3 package handoff self-test ok`.
- Lane JSON validation passed.
- `git diff --check -- lanes/pandoc` passed with no output.
- Focused delta: `+1` PHP PASS case and `+36` direct focused assertions in
  `EpubReaderTest.php`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `EpubReader`,
`ZipPackage` fixtures, DOM/libxml NONET XML parsing, existing XHTML package
resource scanning, `AstNode` metadata handoff, and `WordPressBlockWriter` raw
HTML output.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
JavaScript/media execution, zip/unzip, external converter, online service,
live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted EPUB OCF container/rootfile parsing, OPF
metadata/manifest/spine/nav/NCX/XHTML handoff, vendor metadata, guide/
collections, remote-resource reports, asset fallback chains, OCF sidecars,
media overlays, CFI preservation, semantic `epub:type` scanning, or the
previous `epub:trigger` slice. It owns only static `epub:switch` branch
metadata and diagnostics.

## Follow-Up

Keep XHTML-to-AST conversion, media extraction/export policy, remote-resource
policy, link rel semantics, encrypted/obfuscated font handling beyond
preflight, CSS cascade behavior, and active media/playback behavior as
separate bounded slices.
