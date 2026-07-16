# EPUB3 Package Vocabulary Prefix Handoff

Slice: `pandoc-epub3-package-core-current-base-20260606T045147Z`

Accepted base: `bd267d6c7c3b75fd2d89153f838d469484d0ec30`

## Source Truth

- W3C EPUB 3.3 package metadata uses OPF package `prefix` declarations to bind
  compact vocabulary prefixes used by OPF `meta property` terms.
- The local Pandoc upstream checkout is absent from `.upstream-cache`, matching
  the existing lane runner blocker, so this slice uses the accepted static
  manifest/spec-backed EPUB3 package row and native PHP fixtures only.

## Behavior

- `EpubReader` now passes OPF package prefix bindings into metadata parsing.
- Common EPUB reserved prefixes such as `dcterms`, `media`, `rendition`,
  `a11y`, `schema`, and `xsd` are available as bounded defaults, while explicit
  package prefix declarations override those defaults.
- OPF `meta property` entries get `propertyVocabulary` provenance with raw
  term, prefix, local name, binding IRI, resolved IRI, and diagnostics.
- Package, manifest-resource, spine, and DC metadata refinements preserve that
  same vocabulary provenance while keeping the existing raw keys such as
  `schema:name` and `schema:position`.
- `metadata.vocabulary` summarizes resolved and unresolved prefixed metadata
  properties by prefix, including unknown-prefix diagnostics for review queues.
- The WordPress EPUB package example self-test now verifies vocabulary IRIs in
  metadata properties and package/resource/spine refinements.

## Verification Evidence

Baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1319 assertions, 0 failures
```

Red-first:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
FAIL resolves OPF metadata property vocabulary terms from package prefixes
1 test files, 1320 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1333 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
epub3 package handoff self-test ok
```

Focused delta: `+1` PHP PASS case, `+14` net focused assertions.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ZipPackage`
fixtures, DOM parsing, `EpubReader` OPF metadata/refinement handoff, and the
existing WordPress EPUB package example. No Pandoc, Cabal solver/build/test
command, Haskell runner, Word, LibreOffice, zip/unzip, ZipArchive, browser
renderer, JavaScript/media execution, online sanitizer, online service, or live
provider test was executed.

## Non-Overlap

This does not repeat accepted EPUB OCF container/rootfile parsing, OPF
manifest/spine/nav/NCX/XHTML handoff, guide/collection links, remote-resource
reports, media overlays, OCF sidecars, CFI preservation, `epub:trigger`
content reporting, title-refinement summaries, or raw prefix-declaration
preservation. It owns only OPF metadata property vocabulary resolution from
package prefix bindings.

## Follow-Up

Keep fuller controlled-vocabulary validation, XHTML-to-AST conversion,
CSS cascade/media export policy, active media playback, and full Haskell/Pandoc
runner comparison as separate bounded slices.
