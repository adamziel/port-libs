# Independent Audit - 2026-05-24T19:58Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and
recent Git history through `d8bada7c Record Readability handoff rejection`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 19:49-19:58
HEAD moved during this audit: 84a9d33d56d3 -> 353fefd8a39f -> d8bada7cffcb
recent history: d8bada7c Record Readability handoff rejection; 353fefd8 Refresh markerPDF integration status; 84a9d33d Refresh independent audit status; 4aff9012 Integrate markerPDF text positioning slice; 116ccf10 Refresh independent audit status; 57e13cae Record Readability handoff rejection
tracked status rows moved: 239 -> 235 -> 237 -> 240 -> 243
default status rows including untracked moved: 22318 -> 22342 -> 22355 -> 22371 -> 22415 -> 22509 -> 22555
dirty shortstat moved: 235 files changed, 210610 insertions(+), 26352 deletions(-) -> 235 files changed, 210661 insertions(+), 26352 deletions(-) -> 237 files changed, 210763 insertions(+), 26353 deletions(-) -> 240 files changed, 211464 insertions(+), 26537 deletions(-) -> 243 files changed, 211752 insertions(+), 26564 deletions(-)
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
dependency backlog: 37 rows, 0 active (blocked 1, candidate 25, deferred 11)
root run by this audit: not started; non-audit active root PID 1419767 observed at final gate
```

Required exact pre-root process gate:

```text
2026-05-24T19:50:57Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T19:51:36Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T19:53:12Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T19:56:19Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T19:57:58Z pgrep -af '^php tools/run-tests\.php$': 1419767 php tools/run-tests.php
2026-05-24T19:57:58Z owner evidence: 1419767 claude 1413469 Rs 00:13 php tools/run-tests.php
```

I did not start `php tools/run-tests.php`. The exact no-argument root gate was
clear in earlier samples, but the checkout was not stable enough. A non-audit
root harness then appeared at the final gate as PID `1419767`, owned by
`claude`, so no duplicate root run was allowed. The newly recorded markerPDF
root result is scoped to the held markerPDF batch only and must not be reused
as evidence for the moving dirty aggregate.

Latest sampled manifest/status counts. These are samples from a moving
worktree, not an acceptance ledger:

```text
lane          manifest mapped/total     status phpPass/phpFail
difftastic    1118/1258                 3714/0
dolt          613/613                   458/0
esbuild       480/2567                  480/0
gitoxide      1466/2877                 7570/0
libsqlite     215/1589                  215/0
LightningCSS  3003/3548                 4369/0
markerPDF     164/78                    269/0
pandoc        2276/2276                 399/0
quadrable     55/55                     259/0
rclone        468/1601                  468/0
Readability   1984/1984                 3887/0
syncthing     658/658                   9126/0
```

## Findings

1. **Critical - the repository is still a moving dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:49-53`, `audits/integration-status.md:1-63`,
     `lanes/*/lane-status.json`, `tools/run-tests.php`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35-40`,
     `goal.md:48`, and `goal.md:49` require small reviewable slices,
     honest blockers, verified cleanup, and repo-wide verification.
   - Evidence: `HEAD` moved during this audit from `84a9d33d56d3` through
     `353fefd8a39f` to `d8bada7cffcb`; default status rows kept moving
     `22318 -> 22342 -> 22355 -> 22371 -> 22415 -> 22509 -> 22555`; dirty
     shortstat moved to `243 files changed, 211752 insertions(+), 26564
     deletions(-)`. The
     latest history is still dominated by audit/status and selective
     integration commits, not clean lane-by-lane acceptance. Only the reduced
     markerPDF text-positioning batch is newly accepted; the other lanes remain
     dirty, uncommitted, or explicitly pending supervisor/integrator ownership.

2. **Critical - root-harness evidence is not valid for the current dirty snapshot.**
   - Paths: `tools/run-tests.php`, `audits/integration-status.md:1-63`,
     `lanes/*/lane-status.json`, `progress.md:49-53`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording.
   - Evidence: this audit initially observed an empty exact root gate and did
     not start a root run because the tree was moving; the final gate then
     matched active non-audit PID `1419767 php tools/run-tests.php`, owned by
     `claude`. `audits/integration-status.md` records an integration-owned
     markerPDF root pass (`341` files, `52446` assertions, `0` failures), but
     the same entry explicitly scopes it to the held markerPDF batch and says
     unrelated global dirty shortstat moved, so it is not evidence for any
     other lane claim or for the current aggregate. Focused lane shards remain
     useful local checks, but they do not replace a serialized no-argument root
     run from a frozen snapshot.

3. **Critical - `porting.html` / `porting-summary.json` are now a committed snapshot, but still materially stale relative to current lane metadata.**
   - Paths: `porting.html:32-67`, `porting-summary.json:1-213`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track denominator, mapped tests, PHP pass/fail, WordPress
     scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting-summary.json` says generated
     `2026-05-24 19:49:49 UTC` from source/dashboard commit `84a9d33d56d3`,
     while current `HEAD` is `d8bada7cffcb`. The dashboard row data lags the
     current dirty manifests/statuses: Difftastic shows `240/586` and `240`
     PHP pass units while current metadata is `1118/1258` and `3729`; rclone
     shows denominator `2553` and `458` mapped while current metadata is
     `1601` and `468`; Syncthing shows `324` PHP pass units while current
     status is `9125`; Pandoc shows `207` PHP pass units while current status
     is `399`. A committed snapshot is acceptable as a publication artifact
     only if the dirty handoffs are clearly non-progress; it is not a current
     coordination truth source.

4. **High - support-library coverage is visible, but still not first-class accepted work.**
   - Paths: `dependency-backlog.json:1-4`, `dependency-backlog.json:7-337`,
     `dependency-backlog.json:341-646`, `porting.html:72-129`,
     `progress.md:17-36`.
   - Goal requirement at risk: `goal.md:35-40` require real denominators,
     meaningful fixture parity, edge-case coverage, and hard-feature blockers.
     The latest support-library directive requires bounded native components,
     activation gates, dependency-specific upstream/spec denominators, mapped
     fixtures, PHP pass/fail evidence, malformed/corrupt cases where relevant,
     and bounded `sudo -n` install-attempt or ruled-out notes before missing
     packages become final blockers.
   - Evidence: the tracker covers the important rich-function libraries for
     all base tools. Pandoc's DOC, DOCX/OpenXML, PDF input/output handoff,
     EPUB, ODT/OpenDocument, templates, citations, math, tables, package
     containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax
     highlighting, and archive/compression needs are visible as gated rows.
     But all 37 support rows remain inactive (`candidate`, `deferred`, or one
     `blocked` QR row). There are still no accepted support manifests,
     dependency-specific PHP pass/fail ledgers, malformed/corrupt evidence
     records, accepted activation records, or bounded install-attempt notes.
     Lane-local helper work must remain lane-local until an accepted base lane
     opens or blocks on the exact bounded support component.

5. **High - Pandoc's manifest/status overstates readiness for the original document-conversion goal.**
   - Paths: `goal.md:12`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12-15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:419-423`,
     `lanes/pandoc/lane-status.json:4-14`, `dependency-backlog.json:97-115`,
     `dependency-backlog.json:214-269`, `dependency-backlog.json:322-387`.
   - Goal requirement at risk: `goal.md:12` requires a document conversion
     kernel with a shared AST plus readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented intermediate forms, and WordPress block output.
   - Evidence: the manifest reports `mapped: 2276` of `total: 2276`, but the
     status records only `399` focused PHP behavior tests and says the full
     Haskell runner is unexecuted. The current Pandoc status leaves live
     fetching/openURL, broader HTML parser parity, TeX RawInline math,
     PlainMath, general MathML conversion, malformed HTML parity, PDF/package
     parsing, citation/CSL, ZIP/XML/HTML, Unicode/charset, and syntax
     highlighting behind future bounded gates. Those are central conversion
     kernel requirements, not polish.

6. **High - markerPDF's denominator is still non-normalized even after the accepted reduced slice.**
   - Paths: `goal.md:9`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12-20`,
     `lanes/markerpdf/lane-status.json:4-14`,
     `dependency-backlog.json:273-337`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and
     `goal.md:35-40` say wrappers, shell-outs, whole applications, runtime
     launchers, and plan-only behavior must not count as native implementation
     progress.
   - Evidence: the accepted native `PdfTextExtractor` text-positioning slice
     is properly bounded, and its root result is useful for that slice. But the
     manifest still reports `mapped: 163` against `total: 78` repository paths,
     so mapped semantics and denominator units are mixed. The manifest/status
     continue to list Streamlit, FastAPI/Uvicorn, pypdfium/PDF rendering,
     Surya/OCR/Texify/Torch/Nougat, batch/chunk conversion scripts, and model
     pipeline planning. Those can be blockers or supplied-result boundaries;
     they cannot be counted as progress on external runtimes or inactive
     `pdf-text-dictionary-core`, `layout-ocr-result-core`, and
     `table-geometry-core` support rows.

7. **High - most near-complete lane percentages are unaccepted dirty handoffs.**
   - Paths: `lanes/difftastic/lane-status.json:4-14`,
     `lanes/dolt/lane-status.json:4-14`,
     `lanes/esbuild/lane-status.json:4-14`,
     `lanes/gitoxide/lane-status.json:4-14`,
     `lanes/libsqlite/lane-status.json:4-14`,
     `lanes/lightningcss/lane-status.json:4-14`,
     `lanes/pandoc/lane-status.json:4-14`,
     `lanes/quadrable/lane-status.json:4-14`,
     `lanes/rclone/lane-status.json:4-14`,
     `lanes/readability/lane-status.json:4-14`,
     `lanes/syncthing/lane-status.json:4-14`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35-40`, and
     `goal.md:48` require focused, reviewable slices with passing tests,
     commits, and cleanup before assigning new work.
   - Evidence: these statuses report `95-99%` progress or green focused tests,
     but their `latestCommit` fields are `pending`, `uncommitted`, or
     "not committed" because root aggregate verification and integrator
     acceptance remain outside the lane worker handoffs. Recent Readability and
     rclone integration records rejected/deferred handoffs because dirty scope
     did not match the advertised evidence. These lane rows should not be
     treated as accepted progress until the dirty files match one reduced slice
     and a supervisor/integrator accepts it.

8. **Medium - manifest/status/dashboard ledgers still mix incompatible count units.**
   - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, `porting.html:56-67`,
     `porting-summary.json:10-213`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:44-45` require durable coordination by upstream denominator,
     mapped tests, PHP pass/fail, current work, blocker, and commit.
   - Evidence: markerPDF uses repository paths as denominator but focused
     semantics as mapped units. Difftastic maps behavior artifacts while
     status reports assertion units. Dolt maps executable upstream files while
     status reports PASS cases. Pandoc maps static artifacts but reports
     focused behavior tests. Dashboard percentages derived from these mixed
     units are not comparable and should not drive priority decisions until
     each row has normalized denominator/mapped/PHP-unit definitions.

## Required Next Intervention

Freeze writers/runners/status publishers long enough for two stable polls of
`HEAD`, tracked/default status rows, shortstat, manifest/status mtimes, and the
exact root process gate. Do not broaden lane work while pending handoffs remain
accumulated. Use the markerPDF root result only for the accepted markerPDF
slice. Next, accept or reject one owner-free reduced lane batch whose dirty
files exactly match its evidence, normalize that lane's manifest/status count
units, regenerate dashboard artifacts from the accepted commit, then run one
serialized no-argument `php tools/run-tests.php` only if
`pgrep -af '^php tools/run-tests\.php$'` remains empty on the frozen snapshot.
Keep support-library rows inactive until a base lane is accepted-ready or
accepted-blocked on the exact bounded component with its own denominator,
mapped fixtures, malformed/corrupt cases, PHP pass/fail ledger, and bounded
install-attempt notes where missing packages matter.
