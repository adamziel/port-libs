# Independent Audit - 2026-05-24T16:20Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
`lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, and recent Git history through
`47dd2230 Record integration hold status`. I did not edit lane implementation
files, launch agents or tmux sessions, push, read secrets, inspect process
environments, credential stores, provider configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 16:18-16:20
observed HEAD: 083c02180002 -> 47dd223097a4
recent history: 47dd2230 Record integration hold status; 083c0218 Refresh independent audit status; 7ac76223 Record integration hold status; f1f42d3b Record integration hold status
tracked dirty files: 329 -> 329
default status rows including untracked: 19460 -> 19462
git diff --shortstat: 329 files changed, 262738 insertions(+), 33601 deletions(-) -> 329 files changed, 262923 insertions(+), 33614 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred), updated 2026-05-24 12:29:10 UTC
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started
```

Required pre-root process gate:

```text
16:18Z pgrep -af '^php tools/run-tests\.php$': no rows
16:20Z pgrep -af '^php tools/run-tests\.php$': no rows
```

I did not start `php tools/run-tests.php`. The exact root gate was empty in
my samples, but the checkout was not stable: `HEAD`, status rows, shortstat,
and lane manifest/status counts moved while this audit was reading them. A
root result from this state would not be a frozen acceptance checkpoint.

Latest sampled manifest/status counts versus the published dashboard:

```text
lane          latest sampled manifest/status                         dashboard
difftastic    manifest 1020/1213; status text still says 1010; 3507 pass  3245 pass, 851/1077
dolt          manifest 613/613, phpBehaviorTests 430; status 438 pass     425 pass, 613/613
esbuild       manifest 457/2567, status 457 pass                         429 pass, 429/2567
gitoxide      manifest 2877/2877, status 7407 pass                       7152 pass, 2877/2877
libsqlite     manifest 368/1589, status 368 pass                         348 pass, 349/1589
LightningCSS  manifest 2875/3548, status 4195 pass                       4065 pass, 2765/3548
markerPDF     manifest 370/419, status 507 pass                          484 pass, 347/396
pandoc        manifest 2132/2276, status 383 pass                         362 pass, 1891/2276
quadrable     manifest 55/55, status 246 pass                            232 pass, 55/55
rclone        manifest 961/1601, status 958 pass                         906 pass, 906/1601
readability   manifest 1984/1984, 279 behavior entries; status 3732 assertions  3545 pass, 1984/1984
syncthing     manifest 658/658, status 8456 pass                         7902 pass, 658/658
```

## Findings

1. **Critical - the repo is still a moving dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:49-51`, `audits/integration-status.md:3-8`,
     `audits/integration-status.md:17-36`,
     `audits/integration-status.md:57-79`,
     `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`,
     and the other `lanes/*/lane-status.json:13` latest-commit fields.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:52` require small reviewable committed slices, verified
     handoffs, and visible stable progress for every lane.
   - Evidence: during this audit `HEAD` moved from `083c02180002` to
     `47dd223097a4`, default status rows moved `19460 -> 19462`, and
     shortstat moved from
     `329 files changed, 262738 insertions(+), 33601 deletions(-)` to
     `329 files changed, 262923 insertions(+), 33614 deletions(-)`.
     `audits/integration-status.md:17-24` records the same moving-tree
     pattern immediately before this audit. Every priority lane remains dirty
     or unaccepted, and lane status files still use `pending`, `uncommitted`,
     `not committed`, or lane-local handoff text rather than accepted commits.

2. **Critical - `porting.html` and `porting-summary.json` are stale against every lane.**
   - Paths: `porting.html:32-38`, `porting.html:56-67`,
     `porting-summary.json:2-8`, `porting-summary.json:11-203`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track denominator, mapped tests, PHP pass/fail, phase,
     audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes source `89260857cc71` generated
     `2026-05-24 12:29:46 UTC`, while current `HEAD` is `47dd223097a4`.
     Every lane has newer counts. Examples: Difftastic is sampled at
     `1020/1213` with `3507` pass but dashboard says `851/1077` and `3245`;
     markerPDF is `370/419` with `507` pass but dashboard says `347/396` and
     `484`; Pandoc is `2132/2276` with status `383` pass but dashboard says
     `1891/2276` and `362`; Syncthing is `8456` pass but dashboard says
     `7902`.

3. **High - there is still no valid no-argument root acceptance run.**
   - Paths: `tools/run-tests.php`, `progress.md:51`,
     `audits/integration-status.md:45-48`,
     `lanes/*/lane-status.json:12-13`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording; the user also forbids duplicate
     no-argument root harnesses.
   - Evidence: the exact gate returned no rows at 16:18Z and 16:20Z, but I
     did not start a run because the tree failed the stability gate. Current
     lane statuses consistently leave root aggregate verification pending with
     lane-local focused runs only. `audits/integration-status.md:45-48`
     independently records that the integration worker also skipped the root
     harness because there was no frozen acceptance snapshot.

4. **High - support-library coverage is visible but still backlog-only, not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:3-4`,
     `dependency-backlog.json:7-22`,
     `dependency-backlog.json:25-42`,
     `dependency-backlog.json:81-95`,
     `dependency-backlog.json:129-176`,
     `dependency-backlog.json:179-230`,
     `dependency-backlog.json:233-337`,
     `dependency-backlog.json:340-410`,
     `dependency-backlog.json:413-426`,
     `dependency-backlog.json:629-646`,
     `porting.html:72-78`.
   - Goal requirement at risk: the latest support-library directives require
     bounded native PHP components with activation gates, dependency-specific
     upstream/spec denominators, mapped fixtures, PHP ledgers, malformed or
     corrupt cases where relevant, bounded install attempts where relevant,
     and as much upstream/spec-suite evidence as can honestly run.
   - Evidence: the tracker has 37 rows and 0 active support ports. The rows
     do cover every base tool's next rich-function dependency family, and
     Pandoc's required DOC, DOCX/OpenXML, PDF input/output handoff, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package
     containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax
     highlighting, and archive/compression categories are visible as gated
     rows. None has a support-library manifest, PHP pass/fail ledger,
     malformed/corrupt evidence, accepted activation record, or bounded
     install-attempt/ruled-out note. Lane-local WebDAV, ZIP/gzip/archive, PDF,
     table, URL, charset, or JSON helpers should not be counted as shared
     support-library progress until accepted through those rows.

5. **High - Pandoc's public status still overstates the original rich-kernel goal.**
   - Paths: `goal.md:12`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12-16`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:348-352`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1314-1320`,
     `lanes/pandoc/lane-status.json:5-13`.
   - Goal requirement at risk: Pandoc must be a document conversion kernel
     with a shared AST plus readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented intermediate forms, and WordPress block output.
   - Evidence: the manifest now reports `2132/2276` focused static checks,
     while lane status still reports only `383` PHP behavior tests and no full
     Haskell runner parity. The current latest slice is another bounded HTML
     reader case, and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1316-1317`
     explicitly says no PDF processing, ZIP/package parsing, citation/CSL
     engine, TeX math/ref conversion, or broader XML/HTML/syntax-highlighting
     support is claimed. The WXR requirement remains not visible as a real
     reader/writer capability.

6. **High - markerPDF still mixes native PDF extraction with plan-only external/runtime evidence in the denominator.**
   - Paths: `goal.md:9`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:10-19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1120-1200`,
     `lanes/markerpdf/lane-status.json:5-13`,
     `dependency-backlog.json:272-337`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and the latest
     support-library directives say whole applications, converter wrappers,
     model/runtime stacks, and hidden shell-outs are non-progress unless they
     are explicit temporary oracle tooling.
   - Evidence: the manifest denominator/status string folds `*-plan`,
     Streamlit/FastAPI server/app/upload routes, install plans, model runtime
     graph planning, workflow/publish gates, and shell lifecycle planning
     beside native PDF extraction behavior. The native xref/PDF slices may be
     useful, but richer pdftext/OCR/layout/table work should be credited only
     through accepted bounded support rows such as `pdf-text-dictionary-core`,
     `layout-ocr-result-core`, and `table-geometry-core`.

7. **Medium - multiple lane ledgers remain internally non-normalized even when focused tests are green.**
   - Paths: `lanes/difftastic/lane-status.json:5-13`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12-16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12-18`,
     `lanes/dolt/lane-status.json:5-13`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:12-18`,
     `lanes/rclone/lane-status.json:5-13`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:348-352`,
     `lanes/pandoc/lane-status.json:5-13`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:44-45` require durable coordination by denominator, mapped
     tests, PHP pass/fail, current work, blocker, and commit.
   - Evidence: Difftastic's manifest says `1020/1213`, while status prose
     still says `1010` focused mappings. Dolt status reports `438` pass, but
     manifest `nativeImplementation.phpBehaviorTests` remains `430`. Rclone
     manifest moved to `961/1601`, while status still reports `958` behavior
     tests. Pandoc manifest reports `2132` mapped checks while lane status
     reports only `383` pass. These are not necessarily PHP regressions, but
     they make status/dashboard evidence non-comparable.

8. **Medium - 98-99 percent progress claims still overstate accepted native parity.**
   - Paths: `porting.html:32`, `porting.html:56-67`,
     `porting-summary.json:8`, `lanes/*/lane-status.json:4-13`.
   - Goal requirement at risk: `goal.md:35-40` requires meaningful fixture
     parity, edge-case coverage, upstream tests as source of truth, and
     explicit blockers for hard features.
   - Evidence: the dashboard reports `98.3%` average and most lanes at
     `98-99%`, but the dashboard is stale, current lane work is unaccepted,
     root aggregate verification is pending/non-attributable, several
     upstream runners are static-only or bounded, and no support-library row
     is active.

## Next Intervention

Freeze or wait out lane workers, focused/root runners, dashboard/status
publishers, support-library scouts, capacity jobs, evaluator/auditor loops,
and integration-hold writers long enough to get two stable polls of `HEAD`,
tracked/default status counts, shortstat, exact root gate
`pgrep -af '^php tools/run-tests\.php$'`, dashboard/dependency counts, lane
status timestamps, and relevant log mtimes. Then accept or reject exactly one
owner-free lane batch at a time. First normalize manifest/status/dashboard
counts and stale next-task metadata, especially Difftastic `1020` versus
status `1010`, Dolt `430` versus `438`, Pandoc `2132` versus `383`, Rclone
`961` versus `958`, and every dashboard row still tied to `89260857cc71`.
After focused verification plus `git diff --check`, regenerate dashboard
artifacts from the accepted commit and run one serialized no-argument root
result only on that frozen snapshot.
