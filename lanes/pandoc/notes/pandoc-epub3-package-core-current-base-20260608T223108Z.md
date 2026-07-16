# pandoc-epub3-package-core-current-base-20260608T223108Z

Accepted base: `a93e698ac06f7885c2a47509237e09731628d097`

## Behavior

This slice adds bounded native EPUB3 SMIL media-overlay resource provenance.

`EpubReader` now resolves SMIL `epub:textref`, `<text src>`, and `<audio src>` through the OPF manifest map so media-overlay handoff records preserve:

- package part, manifest id, media type, byte length, CRC32, and SHA-256 for exposable local text/audio resources;
- encryption and `canExposeBytes` policy for encrypted overlay references;
- encrypted media-overlay reference diagnostics while keeping encrypted bytes hash-free;
- the same textref provenance on manifest, spine, import-report, and WordPress raw HTML handoff metadata.

## Evidence

- Rework notes: no current non-stale `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` existed for this slice.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Passed with `1 test files, 3011 assertions, 0 failures`.
- Red-first focused test:
  - Failed with `1 test files, 3013 assertions, 1 failures` because `textRefPart` and related SMIL resource provenance fields were absent.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - Passed with `1 test files, 3044 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  - Passed with `epub3 package handoff self-test ok`.
- PHP lint passed for changed PHP files.

Focused assertion delta: `+33`.
Lane `phpPass` moves from `1928` to `1929`.
Manifest mapped denominator moves from `2350` to `2351`; EPUB3 package-core cases move from `6` to `7`; EPUB3 package-core assertions move from `112` to `145`.

## Dependency Closure

No new support component is needed. This reuses native `EpubReader` SMIL parsing, OPF manifest and encryption metadata, `ZipPackage` byte access, focused EPUB fixtures, and the existing WordPress EPUB3 package handoff example.

Pandoc, Cabal solver/build/test commands, Haskell runners, Word, LibreOffice, zip/unzip, external archive/office tools, browser renderers, online services, live provider tests, and live-service provider tests were not run.

## Non-Overlap

This does not repeat accepted EPUB container/rootfile, OPF metadata, fixed-layout/rendition, nav/NCX target and semantic-source, page-list, media-fragment/CFI, XHTML viewport/language/link/script/refresh/form/ping/switch/trigger/semantic scans, CSS resource/font-face/image-set/conditional/page-rule, fallback/bindings, OCF sidecars, or existing media-overlay duration/style/clip-timing tests. The covered gap is specifically SMIL media-overlay text/audio resource provenance and encrypted-reference policy.

## Next

Choose a non-overlapping EPUB3 package gap such as media-overlay sequence provenance, CSS cascade/export policy, nav/NCX rendering handoff, or encrypted-resource review policy. Keep the work native PHP and bounded; do not shell out to Pandoc, Cabal/Haskell runners, office/archive tools, browser renderers, online services, or live providers.
