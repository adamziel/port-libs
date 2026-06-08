# pandoc-epub3-package-core-current-base-20260608T225146Z

Accepted base: `c992bb947324f7207d596c6abc6496ba6a35dd32`

## Behavior

This slice adds bounded native EPUB3 SMIL media-overlay sequence provenance.

`EpubReader` now preserves SMIL `seq` grouping while keeping the existing
flattened `par` item list stable. Each overlay reports:

- `sequences`, `sequenceCount`, and `sequenceDiagnostics`;
- nested sequence index, id, depth, parent index, path, `epub:type`,
  `epub:textref`, resolved package resource provenance, and repeat/duration
  attributes;
- per-`par` sequence ancestry (`sequenceIndex`, `sequencePath`,
  `sequenceTypes`, and inherited sequence text target) for WordPress review.

Remote sequence `epub:textref` values remain unfetched and produce bounded
diagnostics.

## Evidence

- Rework notes: no current `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` existed before editing.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Passed with `1 test files, 3044 assertions, 0 failures`.
- Red-first focused test:
  - Failed with `1 test files, 3045 assertions, 1 failures` because
    `sequenceCount` was absent from the media-overlay report.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Passed with `1 test files, 3110 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  - Passed with `epub3 package handoff self-test ok`.

Focused assertion delta: `+66`.
Lane `phpPass` moves from `1941` to `1942`.
Manifest mapped denominator moves from `2362` to `2363`; EPUB3 package-core
cases move from `6` to `7`; EPUB3 package-core assertions move from `112` to
`178`.

## Dependency Closure

No new support component is needed. This reuses native `EpubReader` SMIL
parsing, OPF manifest resource provenance, `ZipPackage` byte access, focused
EPUB fixtures, and the existing WordPress EPUB3 package handoff example.

Pandoc, Cabal solver/build/test commands, Haskell runners, Word, LibreOffice,
zip/unzip, external archive/office tools, media players, browser renderers,
online services, live provider tests, and live-service provider tests were not
run.

## Non-Overlap

This does not repeat accepted EPUB container/rootfile, OPF metadata, fixed
layout/rendition, nav/NCX target and semantic-source, page-list, media
fragment/CFI, XHTML viewport/language/link/script/refresh/form/ping/switch/
trigger/semantic scans, CSS resource/font-face/image-set/conditional/page-rule,
fallback/bindings, OCF sidecars, media-overlay resource provenance,
duration/style/clip-timing, or encrypted-reference policy. The covered gap is
only SMIL sequence grouping and per-audio-item sequence ancestry for static
package review.

## Next

Choose a non-overlapping EPUB3 package edge such as CSS cascade/export policy,
encrypted-resource review policy, or nav/NCX rendering handoff. Keep the work
native PHP and bounded; do not shell out to Pandoc, Cabal/Haskell runners,
office/archive tools, media players, browser renderers, online services, or
live providers.
