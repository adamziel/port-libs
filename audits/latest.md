# Independent Audit - 2026-05-24T17:41Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, all 12
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12 sampled
`lanes/*/lane-status.json`, `dependency-backlog.json`,
`porting-summary.json`, `audits/integration-status.md`, and recent Git
history through `b4220188 Record integration root hold status`. I did not edit
lane implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 17:28-17:41
observed HEAD movement during this audit: c3da13ff -> ce8f34918d64 -> 28e785481486 -> b4220188539a
recent history: b4220188 Record integration root hold status; c5ed7ccd Record integration hold status; b171aca3 Refresh independent audit status
branch: main...origin/main [ahead 958, behind 68]
tracked dirty files: 329 -> 331
default status rows including untracked: 19502 -> 19558
git diff --shortstat HEAD samples: 329 files changed, 263993 insertions(+), 32105 deletions(-) -> 329 files changed, 264075 insertions(+), 32107 deletions(-) -> 331 files changed, 264555 insertions(+), 32220 deletions(-) -> 331 files changed, 264769 insertions(+), 32114 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred), updated 2026-05-24 12:29:10 UTC
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started
```

Required exact pre-root process gate:

```text
17:28Z pgrep -af '^php tools/run-tests\.php$': no rows
17:29Z pgrep -af '^php tools/run-tests\.php$': no rows
17:30Z pgrep -af '^php tools/run-tests\.php$': no rows
17:34Z pgrep -af '^php tools/run-tests\.php$': no rows
17:36Z pgrep -af '^php tools/run-tests\.php$': 361102 php tools/run-tests.php
17:36Z ps -o pid,user,ppid,stat,etimes,command -p 361102: PID 361102, USER claude, PPID 361056, STAT R+, ELAPSED 27s, COMMAND php tools/run-tests.php
17:38Z ps -o pid,user,ppid,stat,etimes,command -p 361102: PID 361102, USER claude, PPID 361056, STAT R+, ELAPSED 98s, COMMAND php tools/run-tests.php
17:41Z pgrep -af '^php tools/run-tests\.php$': 393065 php tools/run-tests.php
17:41Z ps -o pid,user,ppid,stat,etimes,command -p 393065: PID 393065, USER claude, PPID 393013, STAT R+, ELAPSED 53s, COMMAND php tools/run-tests.php
```

I did not start `php tools/run-tests.php`. The exact duplicate-root gate was
empty during the audit decision samples, and the checkout failed the stability
gate: `HEAD` advanced during this run, dirty counts changed on repeated polls,
manifest values changed while being inspected, and no owner-free lane batch
was accepted. Before finishing, no-argument root harnesses appeared as PID
`361102` and then PID `393065`, both owned by `claude`; I did not start a
duplicate. The immediately preceding integration hold also recorded a
transient exact root process (`219862 php tools/run-tests.php`), so recent
root-runner churn reinforces the hold.

Latest sampled manifest/status counts versus the published dashboard:

```text
lane          latest sampled manifest/status                                  dashboard
difftastic    manifest 1030/1213; status 3556 assertions                      3245 pass, 851/1077
dolt          manifest 613/613, phpBehaviorTests 442; status 442 PASS         425 pass, 613/613
esbuild       manifest 462/2567; status 462 tests / 4339 assertions           429 pass, 429/2567
gitoxide      manifest 2877/2877; status 7517 assertions                      7152 pass, 2877/2877
libsqlite     manifest 370/1589; status 370 cases / 5910 assertions           348 pass, 349/1589
LightningCSS  manifest 2893/3548; status 4215 assertions                      4065 pass, 2765/3548
markerPDF     manifest 373/422, phpBehaviorTests 511; status 511 tests        484 pass, 347/396
pandoc        manifest 2182/2276; lane status still says 2162 / 386 tests     362 pass, 1891/2276
quadrable     manifest 55/55; status 5406 assertions                          232 pass, 55/55
rclone        manifest 967/1601, phpBehaviorTests 967; status 10013 assertions 906 pass, 906/1601
readability   manifest 1984/1984, phpBehaviorTests 282; status 3753 assertions 3545 pass, 1984/1984
syncthing     manifest 658/658; status 8634 assertions                        7902 pass, 658/658
```

## Findings

1. **Critical - the repo is still a moving dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:49-51`, `audits/integration-status.md:3-62`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:52` require small reviewable committed slices, verified
     handoffs, and visible stable progress for every lane.
   - Evidence: `HEAD` moved from `c3da13ff` to `ce8f34918d64` during this
     audit, then to `28e785481486` and `b4220188539a` before final commit
     prep. Dirty state kept moving: tracked shortstat changed from
     `329 files changed, 263993 insertions(+), 32105 deletions(-)` to
     `331 files changed, 264769 insertions(+), 32114 deletions(-)`, and
     default status rows moved `19502 -> 19558`. Lane status files continue
     to record unaccepted handoffs: Difftastic `latestCommit` is pending in a
     shared dirty worktree (`lanes/difftastic/lane-status.json:13`), Dolt is
     `not committed` (`lanes/dolt/lane-status.json:13`), esbuild is
     `uncommitted` (`lanes/esbuild/lane-status.json:13`), Gitoxide is
     `pending` (`lanes/gitoxide/lane-status.json:13`), libsqlite is
     `uncommitted` (`lanes/libsqlite/lane-status.json:13`), markerPDF is
     `uncommitted` (`lanes/markerpdf/lane-status.json:13`), and the same
     root/integrator ownership pattern appears across Pandoc, Quadrable,
     rclone, Readability, and Syncthing.

2. **Critical - `porting.html` and `porting-summary.json` are stale against every active lane.**
   - Paths: `porting.html:32-38`, `porting.html:56-67`,
     `porting-summary.json:2-8`, `porting-summary.json:16-212`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track denominator, mapped tests, PHP pass/fail, phase,
     audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes source `89260857cc71`, generated
     `2026-05-24 12:29:46 UTC`, while the sampled `HEAD` is
     `b4220188539a`. Current manifest/status samples differ from every
     dashboard row: Difftastic is `1030/1213` with `3556` assertions while the
     dashboard says `851/1077` and `3245`; esbuild is `462/2567` with `4339`
     assertions while the dashboard says `429/2567` and `429`; Gitoxide is
     `7517` assertions while the dashboard says `7152`; libsqlite is
     `370/1589` with `5910` assertions while the dashboard says `349/1589`
     and `348`; markerPDF is `373/422` with `511` behavior tests while the
     dashboard says `347/396` and `484`; Pandoc manifest is `2182/2276` while
     the dashboard says `1891/2276`; LightningCSS is `2893/3548` with `4215`
     assertions while the dashboard says `2765/3548` and `4065`; rclone is
     `967/1601` with `10013`
     assertions while the dashboard says `906/1601` and `906`; Readability is
     now `282` behavior tests while the dashboard says `3545` pass; Syncthing
     is `8634` assertions while the dashboard says `7902`.

3. **High - there is still no valid no-argument root acceptance run.**
   - Paths: `tools/run-tests.php`, `progress.md:51`,
     `audits/integration-status.md:45-52`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording; the current user instruction requires the
     exact duplicate-root guard before any no-argument root run.
   - Evidence: `pgrep -af '^php tools/run-tests\.php$'` returned no rows at
     17:28Z, 17:29Z, 17:30Z, and 17:34Z, so the audit did not start a root run
     because the tree was changing during the gate samples. A final pre-finish
     gate at 17:36Z then matched active PID `361102` owned by `claude`
     (`php tools/run-tests.php`, parent `361056`, state `R+`, elapsed `27s`;
     still active at 17:38Z with elapsed `98s`), and a later 17:41Z sample
     matched active PID `393065` owned by `claude` (`php tools/run-tests.php`,
     parent `393013`, state `R+`, elapsed `53s`), so no duplicate root was
     started. The prior
     integration hold recorded a transient exact root process
     `219862 php tools/run-tests.php` before acceptance
     (`audits/integration-status.md:45-52`). Current lane status files still
     say no-argument root verification is pending or was not assigned, for
     example Pandoc (`lanes/pandoc/lane-status.json:12`), rclone
     (`lanes/rclone/lane-status.json:12`), Readability
     (`lanes/readability/lane-status.json:12`), and Syncthing
     (`lanes/syncthing/lane-status.json:12`).

4. **High - manifest/status/progress ledgers remain internally inconsistent and are not acceptance-grade.**
   - Paths: `progress.md:51`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:28-29`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2578-2585`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:364-368`,
     `lanes/pandoc/lane-status.json:5-12`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14-15`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:1195`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:15-18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14-19`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14-16`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:44-45` require durable coordination by denominator, mapped
     tests, PHP pass/fail, current work, blocker, and commit.
   - Evidence: `progress.md:51` still records the previous audit counts
     (`Difftastic 1029`, `esbuild 460`, `libsqlite 369`, `markerPDF
     371/420`, `Pandoc 2148/2276`, `rclone 963`), while the current manifests
     sampled during this run report Difftastic `1030`, esbuild `462`,
     libsqlite `370`, markerPDF `373/422`, Pandoc `2182/2276`, rclone
     `967`, LightningCSS `2893`, and Readability `282` behavior tests. Dolt's latest evidence says
     `442 PASS cases` at
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:28`, but the warning prose still
     says `430 PASS cases` at `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2578`.
     Pandoc is especially inconsistent: the manifest now says `2182` mapped
     and a style/script slice (`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:364`
     and `:368`), while lane status still says `2162`, the SVG slice, and
     `386` tests (`lanes/pandoc/lane-status.json:5-12`). Difftastic likewise
     has top-level `mapped: 1030` but warning prose still says `1029`
     (`lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14-15` and `:1195`).

5. **High - support-library coverage is visible but still not first-class, manifest-backed work.**
   - Paths: `dependency-backlog.json:7-22`,
     `dependency-backlog.json:81-176`, `dependency-backlog.json:179-337`,
     `dependency-backlog.json:340-426`, `dependency-backlog.json:532-545`,
     `dependency-backlog.json:629-646`, `porting.html:72-129`.
   - Goal requirement at risk: the latest support-library directives require
     bounded native PHP components with activation gates,
     dependency-specific upstream/spec denominators, mapped fixtures, PHP
     pass/fail ledgers, malformed/corrupt cases where relevant, bounded
     install attempts or ruled-out notes where tooling is missing, and as
     much upstream/spec-suite evidence as can honestly run.
   - Evidence: the tracker has 37 rows and 0 active support ports. It does
     cover the required Pandoc categories as gated rows: DOC via
     `legacy-doc-cfb-core`, DOCX/OpenXML via `docx-openxml-core`, PDF
     input/output via `pdf-text-dictionary-core`,
     `pdf-page-render-plan-core`, and `pandoc-pdf-engine-handoff-core`, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package
     containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax
     highlighting, and archive/compression. None of those rows has a
     dependency-specific `UPSTREAM_TEST_MANIFEST.json`, PHP pass/fail ledger,
     malformed/corrupt-case evidence, accepted activation record, or bounded
     install-attempt/ruled-out note. Current WebDAV, PDF, ZIP/archive, JSON,
     charset, Unicode, QR, protobuf, table, checksum, and sequence-diff
     evidence remains lane-local until a frozen base-lane gate activates a
     row.

6. **High - Pandoc status still overstates the original rich conversion-kernel goal.**
   - Paths: `goal.md:12`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:364-368`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1349-1352`,
     `lanes/pandoc/lane-status.json:5-14`,
     `dependency-backlog.json:81-94`, `dependency-backlog.json:129-176`,
     `dependency-backlog.json:214-269`, `dependency-backlog.json:391-426`.
   - Goal requirement at risk: Pandoc must be a document conversion kernel
     with a shared AST plus readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented intermediate forms, and WordPress block output.
   - Evidence: the strongest Pandoc evidence remains cloned static inventory
     plus focused PHP slices. The manifest explicitly says full upstream
     runner parity is not executed and no live fetch, browser, converter,
     PDF/package parser, PlainMath/MathML conversion, or XML/HTML support
     expansion is claimed (`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:366`).
     The current manifest points at a style/script HTML-reader slice
     (`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1349`), while status still
     advertises the older SVG slice and only `386` behavior tests
     (`lanes/pandoc/lane-status.json:5-12`). WXR is not visible as an
     accepted Pandoc reader/writer denominator, and DOCX/ODT/EPUB/PDF,
     templates, citations, math, tables, JSON/YAML metadata, and package
     container support remain inactive support-library gates rather than
     accepted conversion-kernel parity.

7. **High - markerPDF still mixes native PDF behavior with plan-only external/runtime evidence.**
   - Paths: `goal.md:9`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:900-905`,
     `lanes/markerpdf/lane-status.json:9-13`,
     `dependency-backlog.json:272-337`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and the latest
     support-library directives say whole applications, converter wrappers,
     model/runtime stacks, and hidden shell-outs are non-progress unless they
     are explicit temporary oracle tooling.
   - Evidence: the native PDF xref/text slices are useful and focused tests
     are green, but the denominator/status still folds server/app plans, OCR
     install plans, model-runtime dependency graphs, workflow/publish gates,
     Streamlit/FastAPI/Uvicorn boundaries, and shell lifecycle planning beside
     native PDF extraction behavior. The lane itself lists full benchmark/app
     parity, `pdftext`, `pypdfium2`, OCR/model stacks, Streamlit,
     FastAPI/Uvicorn, multiprocessing, package setup, and external OCR/PDF
     tooling as not executed (`lanes/markerpdf/lane-status.json:12`). Richer
     searchable PDF, OCR/layout, and table progress should be credited only
     through accepted bounded rows such as `pdf-text-dictionary-core`,
     `layout-ocr-result-core`, and `table-geometry-core`.

8. **Medium - 98-99 percent progress claims still overstate accepted native parity.**
   - Paths: `porting.html:32`, `porting.html:56-67`,
     `porting-summary.json:8`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:35-40` requires meaningful fixture
     parity, edge-case coverage, upstream tests as source of truth, and
     explicit blockers for hard features.
   - Evidence: the dashboard reports `98.3%` average and most lanes at
     `98-99%`, but the dashboard is stale, current lane work is unaccepted,
     root aggregate verification is pending, multiple upstream runners remain
     static-only or bounded, and no support-library row is active. The
     percentages are not a reliable signal for acceptance or remaining
     denominator risk.

## Next Intervention

Freeze or wait out lane workers, focused/root runners, dashboard/status
publishers, support-library scouts, capacity jobs, evaluator/auditor loops,
and integration-hold writers long enough to get two stable polls of `HEAD`,
tracked/default status counts, shortstat, exact root gate
`pgrep -af '^php tools/run-tests\.php$'`, dashboard/dependency counts, lane
status timestamps, and relevant log mtimes. Then accept or reject exactly one
owner-free lane batch at a time. First normalize manifest/status/dashboard
counts and stale metadata, especially Pandoc manifest `2182` versus status
`2162`, Difftastic `1030` versus stale `1029` warning prose, Dolt `442`
versus stale warning `430`, esbuild `462` versus dashboard `429`,
LightningCSS `2893` versus dashboard `2765`, libsqlite `370` versus stale
dashboard `349`, markerPDF `373/422` versus dashboard `347/396`, rclone
`967` versus dashboard `906`, Readability `282` behavior tests versus dashboard `3545`
pass, and every dashboard row still tied to `89260857cc71`. After
focused verification plus `git diff --check`, regenerate dashboard artifacts
from the accepted commit and run one serialized no-argument root result only
on that frozen snapshot.
