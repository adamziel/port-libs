# pandoc-epub3-package-core-current-base-20260609T011100Z

Slice: EPUB3 NCX `navList` role/type metadata handoff on accepted base `09109401d59cee7a589aaf8125432abbe4aef718`.

## Behavior

Legacy NCX `navList` groups are supplemental navigation, outside the primary `navMap`. Previous EPUB3 package slices preserved labels and target references, but not the supplemental list semantics reviewers need for list-of-figures, tables, bibliography, glossary, index, notes, and page-list handoffs.

This slice maps bounded NCX `navList` class aliases such as `loi`, `list-of-illustrations`, `lot`, `bibliography`, `glossary`, `index`, `notes`, and `page-list` into role/type fields. Custom classes remain in `unmappedRoleClasses`, conflicting or missing role hints are reported in `roleDiagnostics` and `navListRoleReport`, and those role diagnostics stay separate from existing target-reference diagnostics. NCX `navTarget` references now also carry `manifestId`, `mediaType`, `encrypted`, and `canExposeBytes` provenance through the supplemental navigation handoff.

## Focused Evidence

- No current `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` rework note existed for this lane.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` passed with `1 test files, 3243 assertions, 0 failures`.
- Red-first: the same command after adding the focused test failed with `1 test files, 3245 assertions, 1 failures` because `navLists[0].type` was absent.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` passed with `1 test files, 3286 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test` passed with `epub3 package handoff self-test ok`.
- PHP lint for changed PHP files, JSON validation for lane status/manifest, and `git diff --check -- lanes/pandoc` passed.
- Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ZipPackage`, `EpubReader`, `OpcPackagePath` reference resolution, DOM/libxml XML parsing, and the existing WordPress EPUB package handoff.

The pinned Pandoc upstream checkout was not available in the local upstream cache for this worker, so no upstream source files were read from that cache. No Pandoc, Cabal solver/build/test command, Haskell runner, zip/unzip, Word, LibreOffice, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted EPUB3 OCF container parsing, OPF metadata/spine parsing, EPUB3 nav XHTML parsing, NCX `navMap`, NCX head/docTitle/docAuthor metadata, NCX `pageList`/`pageTarget` provenance, previous NCX `navList` target aggregate counts, label provenance, nav/spine reconciliation, guide/collection handling, rendition metadata, fallback chains, bindings, media overlays, remote-resource reconciliation, encryption review, CFI reporting, or ZIP package primitives.

The bounded gap covered here is NCX `navList` role/type classification and manifest/media provenance for supplemental navigation review handoff.

## Follow-Up

A next non-overlapping EPUB3 slice could cover XHTML-to-AST conversion, CSS cascade/media export policy, encrypted-resource review policy, remote-resource policy, or EPUBCheck-style validation without executing external tools.
