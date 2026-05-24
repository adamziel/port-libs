# Independent Audit - 2026-05-24T17:19Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, all 12
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12 sampled
`lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, and recent Git history through
`de081313 Clarify integration hold follow-up`. I did not edit lane implementation
files, launch agents or tmux sessions, push, read secrets, inspect process
environments, credential stores, provider configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 17:13-17:19
observed HEAD movement: 2992a55fe06b -> a32f6308935a -> 748e8ca928fd -> de081313ee82
recent history: de081313 Clarify integration hold follow-up; 748e8ca9 Record integration hold status; a32f6308 Record integration hold status; 2992a55f Refresh independent audit status
tracked dirty files: 330 -> 329 -> 331
default status rows including untracked: 19571 -> 19573 -> 19575 -> 19579
git diff --shortstat HEAD final sample: 331 files changed, 263230 insertions(+), 32241 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred), updated 2026-05-24 12:29:10 UTC
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started
```

Required pre-root process gate:

```text
17:19Z pgrep -af '^php tools/run-tests\.php$': no rows
```

I did not start `php tools/run-tests.php`. The exact no-argument root gate was
empty, but the checkout failed the stability gate: `HEAD`, tracked dirty count,
default status count, and shortstat changed while sampling, and no owner-free
lane batch was accepted.

Latest sampled manifest/status counts versus the published dashboard:

```text
lane          latest sampled manifest/status                         dashboard
difftastic    manifest 1028/1213; status 3550 assertions             3245 pass, 851/1077
dolt          manifest 613/613, phpBehaviorTests 430; status 440 PASS but audit/blocker 438  425 pass, 613/613
esbuild       manifest 460/2567; status 459 PASS / 4334 assertions   429 pass, 429/2567
gitoxide      manifest 2877/2877; status 7472 assertions             7152 pass, 2877/2877
libsqlite     manifest 369/1589; status 369 focused cases            348 pass, 349/1589
LightningCSS  manifest 2878/3548; status 4199 assertions             4065 pass, 2765/3548
markerPDF     manifest 371/420, phpBehaviorTests 509; status 509 behavior tests  484 pass, 347/396
pandoc        manifest 2148/2276; status 385 behavior tests          362 pass, 1891/2276
quadrable     manifest 55/55; status 246 behavior tests / 5383 assertions  232 pass, 55/55
rclone        manifest 963/1601, phpBehaviorTests 963; status 963 behavior tests / 9955 assertions  906 pass, 906/1601
readability   manifest 1984/1984, phpBehaviorTests 280; status 3739 assertions  3545 pass, 1984/1984
syncthing     manifest 658/658; status 8564 assertions               7902 pass, 658/658
```

## Findings

1. **Critical - the repo is still a moving dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:49-52`, `audits/integration-status.md`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:52` require small reviewable committed slices, verified
     handoffs, and visible stable progress for every lane.
   - Evidence: `HEAD` moved from `2992a55fe06b` through `a32f6308935a` and
     `748e8ca928fd` to `de081313ee82` during this audit. Tracked dirty files
     moved `330 -> 329 -> 331`, untracked-inclusive status rows moved
     `19571 -> 19573 -> 19575 -> 19579`, and the final
     `git diff --shortstat HEAD` sample was
     `331 files changed, 263230 insertions(+), 32241 deletions(-)`.
     Recent history remains audit/integration-hold/status commits rather
     than accepted lane implementation batches. Sampled lane statuses still
     use `pending`, `uncommitted`, `not committed`, or lane-local handoff
     wording in `latestCommit`.

2. **Critical - `porting.html` is stale against every current lane.**
   - Paths: `porting.html:32-38`, `porting.html:56-67`,
     `porting-summary.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track denominator, mapped tests, PHP pass/fail, phase,
     audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes source `89260857cc71` generated
     `2026-05-24 12:29:46 UTC`, while current sampled `HEAD` is
     `de081313ee82`. Every row is stale: Difftastic is now `1028/1213` but
     the dashboard says `851/1077`; markerPDF is `371/420` with lane status
     `509` behavior tests but the dashboard says `347/396` and `484`;
     Pandoc is `2148/2276` with `385` behavior tests but the dashboard says
     `1891/2276` and `362`; rclone is `963/1601` but the dashboard says
     `906/1601`; Syncthing status reports `8564` assertions but the
     dashboard says `7902`.

3. **High - there is still no valid no-argument root acceptance run.**
   - Paths: `tools/run-tests.php`, `progress.md:51`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording; the current user instruction requires the
     exact duplicate-root guard before any no-argument run.
   - Evidence: `pgrep -af '^php tools/run-tests\.php$'` returned no rows at
     17:19Z, but the audit did not start a root run because the tree moved
     while sampled. Lane statuses continue to say root aggregate verification
     is pending and cite only focused lane-local checks.

4. **High - support-library coverage is visible but still not first-class, manifest-backed work.**
   - Paths: `dependency-backlog.json:3-22`,
     `dependency-backlog.json:81-95`, `dependency-backlog.json:129-188`,
     `dependency-backlog.json:214-285`, `dependency-backlog.json:322-425`,
     `dependency-backlog.json:629-645`, `porting.html:72-129`.
   - Goal requirement at risk: the latest support-library directives require
     bounded native PHP components with activation gates,
     dependency-specific upstream/spec denominators, mapped fixtures, PHP
     pass/fail ledgers, malformed/corrupt cases where relevant, bounded
     install attempts or ruled-out notes where tooling is missing, and as
     much upstream/spec-suite evidence as can honestly run.
   - Evidence: the tracker has 37 rows and 0 active support ports. It does
     cover all required Pandoc categories as gated rows: DOC via
     `legacy-doc-cfb-core`, DOCX/OpenXML via `docx-openxml-core`,
     PDF input/output via `pdf-text-dictionary-core`,
     `pdf-page-render-plan-core`, and `pandoc-pdf-engine-handoff-core`,
     EPUB, ODT/OpenDocument, templates, citations, math, tables, package
     containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax
     highlighting, and archive/compression. None has a support-library
     manifest, PHP ledger, malformed/corrupt evidence, accepted activation
     record, or bounded install-attempt/ruled-out note. Current WebDAV,
     ZIP/archive, PDF, table, URL, charset, and JSON helpers remain
     lane-local evidence and should not be counted as shared support progress
     until an accepted base-lane gate activates a row.

5. **High - Pandoc status still overstates the original rich conversion-kernel goal.**
   - Paths: `goal.md:12`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12-16`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:355-357`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1330-1334`,
     `lanes/pandoc/lane-status.json`.
   - Goal requirement at risk: Pandoc must be a document conversion kernel
     with a shared AST plus readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented intermediate forms, and WordPress block output.
   - Evidence: the manifest reports `2148/2276` mapped static/focused checks
     and the lane status reports only `385` PHP behavior tests, with no full
     upstream Haskell runner parity. The latest slice is an HTML reader
     span-like inline case. The manifest explicitly says no upstream Pandoc
     execution, browser/network fetching, converter shell-outs, PDF
     processing, ZIP/package parsing, citation/CSL engine, TeX math/ref
     conversion, or broader XML/HTML/syntax-highlighting support is claimed.
     The WXR reader/writer requirement remains not visible as a real
     capability.

6. **High - markerPDF still mixes native PDF behavior with plan-only external/runtime evidence, and its count prose is internally stale.**
   - Paths: `goal.md:9`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:10-19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:587`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:898-907`,
     `lanes/markerpdf/lane-status.json:5-13`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and the latest
     support-library directives say whole applications, converter wrappers,
     model/runtime stacks, and hidden shell-outs are non-progress unless they
     are explicit temporary oracle tooling.
   - Evidence: the manifest denominator/status string still folds server/app
     plans, OCR install plans, model-runtime dependency graph planning,
     workflow/publish gates, Streamlit/FastAPI/Uvicorn boundaries, and shell
     lifecycle planning beside native PDF extraction behavior. The manifest
     top-level count says `420` total and `371` mapped, while its
     `latestRunAddendum` still says `419` and `370` and `warning` says `370`.
     The native xref/PDF slices are useful, but richer pdftext/OCR,
     layout, and table progress should be credited only through accepted
     bounded rows such as `pdf-text-dictionary-core`,
     `layout-ocr-result-core`, and `table-geometry-core`.

7. **Medium - lane ledgers remain non-normalized even when focused tests are green.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14-29`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2585`,
     `lanes/dolt/lane-status.json:5-12`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14-16`,
     `lanes/rclone/lane-status.json:5-13`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/lightningcss/lane-status.json:6-13`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:355-357`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:44-45` require durable coordination by denominator, mapped
     tests, PHP pass/fail, current work, blocker, and commit.
   - Evidence: Dolt's manifest still says `phpBehaviorTests: 430`; status
     prose says `440` PASS, while audit/blocker text still says `438`.
     markerPDF has `420/371` at top level but stale `419/370` addendum/prose.
     LightningCSS
     moved to `2878/3548` and `4199` assertions while the dashboard still says
     `2765/3548` and `4065`. Pandoc exposes `2148` mapped checks but only
     `385` behavior tests. These may be status-writer lag, not PHP
     regressions, but the evidence is not comparable enough for acceptance.

8. **Medium - 98-99 percent progress claims still overstate accepted native parity.**
   - Paths: `porting.html:32`, `porting.html:56-67`,
     `porting-summary.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:35-40` requires meaningful fixture
     parity, edge-case coverage, upstream tests as source of truth, and
     explicit blockers for hard features.
   - Evidence: the dashboard reports `98.3%` average and most lanes at
     `98-99%`, but the dashboard is stale, current lane work is unaccepted,
     root aggregate verification is pending, several upstream runners are
     static-only or bounded, and no support-library row is active.

## Next Intervention

Freeze or wait out lane workers, focused/root runners, dashboard/status
publishers, support-library scouts, capacity jobs, evaluator/auditor loops,
and integration-hold writers long enough to get two stable polls of `HEAD`,
tracked/default status counts, shortstat, exact root gate
`pgrep -af '^php tools/run-tests\.php$'`, dashboard/dependency counts, lane
status timestamps, and relevant log mtimes. Then accept or reject exactly one
owner-free lane batch at a time. First normalize manifest/status/dashboard
counts and stale metadata, especially Dolt `430` versus `438/440`, markerPDF
`420/371` versus `419/370`, Pandoc `2148` versus `385`, and every
dashboard row still tied to `89260857cc71`. After focused verification plus
`git diff --check`, regenerate dashboard artifacts from the accepted commit
and run one serialized no-argument root result only on that frozen snapshot.
