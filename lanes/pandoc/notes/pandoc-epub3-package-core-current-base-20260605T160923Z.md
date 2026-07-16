## EPUB3 Package Contributor Roles

Slice: `pandoc-epub3-package-core-current-base-20260605T160923Z`
Base accepted HEAD: `48a89663839470a7f859e92d82aaf22dbf92f634`

### Source Truth

This slice stays inside native PHP EPUB3 package handoff behavior. It maps OPF
`dc:creator` / `dc:contributor` agents plus `meta refines` role, file-as,
display-seq, and alternate-script metadata into a stable handoff shape for
Pandoc-like metadata and WordPress import previews.

The hydrated Pandoc upstream checkout is not present in this worktree/cache, so
no Haskell runner, Cabal solver, Pandoc binary, zip/unzip utility, EPUB checker,
or online service was used.

### Implementation Delta

- Added normalized EPUB agent detail summaries in `EpubReader` for creators and
  contributors.
- Added contributor top-level lists, role grouping, untyped contributor
  grouping, file-as/display-seq, alternate-script, and refinement handoff data.
- Extended the EPUB3 package example smoke to expose contributor names and
  contributor role groupings.

### Focused Evidence

- Baseline before the new case:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  -> `1 test files, 970 assertions, 0 failures`
- Red-first after adding the contributor-role fixture:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  -> `1 test files, 971 assertions, 1 failures`
  Missing key: `metadata["contributors"]`
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  -> `1 test files, 994 assertions, 0 failures`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  -> `epub3 package handoff self-test ok`
- JSON metadata validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  -> `pandoc json ok`

### Status Delta

- Focused EPUB reader test grew from `970` to `994` assertions.
- `lane-status.json` `phpPass` moves from `991` to `992` for the new focused
  test case.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator moves from `1446` to `1447`.
- EPUB3 package core cases move from `4` to `5`.
- EPUB3 package core assertions move from `62` to `86`.

### Non-overlap

This does not repeat accepted EPUB work for OCF container/rootfiles, raw OPF
metadata capture, generic refinement grouping, metadata links, manifest/spine,
nav/NCX, guide/collections, renditions, fallbacks/bindings, remote resources,
encryption, OCF rights/signatures, media overlays, CFI fragments, container
links, XHTML-to-AST, CSS, or media export.

### Dependency Closure

No new support component is required. The slice reuses existing bounded native
PHP package pieces: `EpubReader`, `ZipPackage`, `OpcPackagePath`,
`PandocAstNode`, and `WordPressBlockWriter`. The remaining upstream-runner
dependency gap is unchanged: hydrate the Pandoc checkout and add a bounded
Tasty/fixture runner before upstream executable parity can be counted.

### Next Task

Next EPUB3 work should stay non-overlapping: XHTML nav/body AST fidelity, CSS
handoff, media export, rendition selection, or optional role code-list labeling
for MARC relator values.

Root harness: not run - isolated micro-slice.
