# Independent Audit - 2026-05-24T17:08Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
`lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, and recent Git history through
`190f9e4e Clarify integration hold race`. I did not edit lane implementation
files, launch agents or tmux sessions, push, read secrets, inspect process
environments, credential stores, provider configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 17:06-17:08
observed HEAD: 190f9e4ec3cf
recent history: 190f9e4e Clarify integration hold race; a006ad72 Record integration hold status; 9f77fc48 Refresh independent audit status; 47dd2230 Record integration hold status
tracked dirty files: 332 -> 332
default status rows including untracked: 19562 -> 19562
git diff --shortstat: 332 files changed, 263749 insertions(+), 33704 deletions(-) -> 332 files changed, 263890 insertions(+), 33710 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred), updated 2026-05-24 12:29:10 UTC
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started
```

Required pre-root process gate:

```text
17:06Z pgrep -af '^php tools/run-tests\.php$': no rows
17:08Z pgrep -af '^php tools/run-tests\.php$': no rows
```

I did not start `php tools/run-tests.php`. The exact root gate was empty, but
the checkout failed the stability gate again: the tracked shortstat changed
while the audit was running, every priority lane still has dirty unaccepted
work, and no owner-free lane batch was accepted.

Latest sampled manifest/status counts versus the published dashboard:

```text
lane          latest sampled manifest/status                         dashboard
difftastic    manifest 1020/1213; status 3527 assertions             3245 pass, 851/1077
dolt          manifest 613/613, phpBehaviorTests 430; status prose 440 PASS but audit/blocker 438  425 pass, 613/613
esbuild       manifest 459/2567; status 459 PASS / 4334 assertions   429 pass, 429/2567
gitoxide      manifest 2877/2877; status 7447 assertions             7152 pass, 2877/2877
libsqlite     manifest 369/1589; status 369 focused cases            348 pass, 349/1589
LightningCSS  manifest 2875/3548; status 4195 assertions             4065 pass, 2765/3548
markerPDF     manifest 370/419; status 507 behavior tests            484 pass, 347/396
pandoc        manifest 2132/2276; status 384 behavior tests          362 pass, 1891/2276
quadrable     manifest 55/55; status 5383 assertions                 232 pass, 55/55
rclone        manifest 961/1601, phpBehaviorTests 958; status 961 behavior tests  906 pass, 906/1601
readability   manifest 1984/1984; status 279 behavior entries / 3732 assertions  3545 pass, 1984/1984
syncthing     manifest 658/658; status 8456 assertions               7902 pass, 658/658
```

## Findings

1. **Critical - the repo is still a moving dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:49-52`, `audits/integration-status.md:1-71`,
     `lanes/*/lane-status.json:12-13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:52` require small reviewable committed slices, verified
     handoffs, and visible stable progress for every lane.
   - Evidence: this audit saw 332 tracked dirty files and 19,562
     untracked-inclusive status rows. `git diff --shortstat` changed from
     `332 files changed, 263749 insertions(+), 33704 deletions(-)` to
     `332 files changed, 263890 insertions(+), 33710 deletions(-)` during
     the run. Recent history is still audit/integration-hold/status commits,
     not accepted lane batches. Every sampled lane status records `pending`,
     `uncommitted`, `not committed`, or lane-local handoff wording in
     `latestCommit`.

2. **Critical - `porting.html` and `porting-summary.json` are stale against every lane.**
   - Paths: `porting.html:32-38`, `porting.html:56-67`,
     `porting-summary.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track denominator, mapped tests, PHP pass/fail, phase,
     audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes source `89260857cc71` generated
     `2026-05-24 12:29:46 UTC`, while current `HEAD` is `190f9e4ec3cf` and
     every lane has newer counts. Examples: Difftastic is now `1020/1213`
     but the dashboard says `851/1077`; markerPDF is `370/419` with `507`
     behavior tests but the dashboard says `347/396` and `484`; Pandoc is
     `2132/2276` with `384` behavior tests but the dashboard says
     `1891/2276` and `362`; Syncthing reports `8456` assertions but the
     dashboard says `7902`.

3. **High - there is still no valid no-argument root acceptance run.**
   - Paths: `tools/run-tests.php`, `progress.md:51`,
     `audits/integration-status.md:35-47`, `lanes/*/lane-status.json:12-13`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording; the user also requires a duplicate-root
     harness guard before any no-argument run.
   - Evidence: `pgrep -af '^php tools/run-tests\.php$'` returned no rows at
     17:06Z and 17:08Z, but the audit did not start a root run because the
     tree changed during sampling and no owner-free acceptance snapshot
     exists. Lane statuses consistently leave root aggregate verification
     pending and report only focused lane-local runs.

4. **High - support-library coverage remains backlog-only, not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:3-22`,
     `dependency-backlog.json:81-95`, `dependency-backlog.json:129-188`,
     `dependency-backlog.json:214-285`, `dependency-backlog.json:322-425`,
     `dependency-backlog.json:629-645`, `porting.html:72-129`.
   - Goal requirement at risk: the latest support-library directives require
     bounded native PHP components with activation gates, dependency-specific
     upstream/spec denominators, mapped fixtures, PHP pass/fail ledgers,
     malformed/corrupt cases where relevant, bounded install attempts where
     relevant, and as much upstream/spec-suite evidence as can honestly run.
   - Evidence: the tracker has 37 rows and 0 active support ports. It does
     visibly cover each base tool's next rich-function dependency family, and
     Pandoc's required DOC, DOCX/OpenXML, PDF input/output handoff, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package
     containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax
     highlighting, and archive/compression categories are covered by gated
     rows. None has a support-library manifest, PHP pass/fail ledger,
     malformed/corrupt evidence, accepted activation record, or bounded
     install-attempt/ruled-out note. Lane-local WebDAV, ZIP/gzip/archive, PDF,
     table, URL, charset, or JSON helpers should not be counted as shared
     support progress until one row is activated from an accepted base-lane
     gate.

5. **High - Pandoc status still overstates the original rich conversion-kernel goal.**
   - Paths: `goal.md:12`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14-16`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:351-355`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1320-1326`,
     `lanes/pandoc/lane-status.json:5`, `lanes/pandoc/lane-status.json:12`.
   - Goal requirement at risk: Pandoc must be a document conversion kernel
     with a shared AST plus readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented intermediate forms, and WordPress block output.
   - Evidence: the manifest reports `2132/2276` focused static checks and
     the lane status reports only 384 PHP behavior tests, with no full
     upstream Haskell runner parity. The latest slice is another bounded HTML
     reader case. The manifest explicitly says no upstream Pandoc execution,
     browser/network fetching, converter shell-outs, PDF processing,
     ZIP/package parsing, citation/CSL engine, TeX math/ref conversion, or
     broader XML/HTML/syntax-highlighting support is claimed. The WXR reader
     or writer requirement is still not visible as a real capability.

6. **High - markerPDF still mixes native PDF extraction with plan-only external/runtime evidence in the denominator.**
   - Paths: `goal.md:9`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:10-19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:899-906`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1123-1186`,
     `lanes/markerpdf/lane-status.json:12-13`,
     `dependency-backlog.json:272-336`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and the latest
     support-library directives say whole applications, converter wrappers,
     model/runtime stacks, and hidden shell-outs are non-progress unless they
     are explicit temporary oracle tooling.
   - Evidence: the manifest denominator/status string folds server/app plans,
     OCR install plans, model-runtime dependency graph planning,
     workflow/publish gates, Streamlit/FastAPI/Uvicorn boundaries, and shell
     lifecycle planning beside native PDF extraction behavior. The native
     xref/PDF slices may be useful, but richer pdftext/OCR/layout/table work
     should be credited only through accepted bounded support rows such as
     `pdf-text-dictionary-core`, `layout-ocr-result-core`, and
     `table-geometry-core`.

7. **Medium - several lane ledgers remain internally non-normalized even when focused tests are green.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12-30`,
     `lanes/dolt/lane-status.json:5`, `lanes/dolt/lane-status.json:10-13`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/lane-status.json:10-13`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:351-355`,
     `lanes/pandoc/lane-status.json:5-13`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:44-45` require durable coordination by denominator, mapped
     tests, PHP pass/fail, current work, blocker, and commit.
   - Evidence: Dolt's manifest still says `phpBehaviorTests: 430`; its
     latest suite-progress prose claims 440 PASS cases, while the audit and
     blocker fields still claim 438. Rclone's manifest still says
     `phpBehaviorTests: 958` while lane status reports 961 behavior tests.
     Pandoc exposes `2132` mapped static checks but only 384 behavior tests.
     These may not be PHP regressions, but the status/dashboard evidence is
     not comparable enough for acceptance.

8. **Medium - 98-99 percent progress claims still overstate accepted native parity.**
   - Paths: `porting.html:32`, `porting.html:56-67`,
     `porting-summary.json`, `lanes/*/lane-status.json:4-13`.
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
counts and stale metadata, especially Dolt `430` versus `438/440`, rclone
`958` versus `961`, Pandoc `2132` versus `384`, and every dashboard row still
tied to `89260857cc71`. After focused verification plus `git diff --check`,
regenerate dashboard artifacts from the accepted commit and run one serialized
no-argument root result only on that frozen snapshot.
