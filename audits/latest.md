# Independent Audit - 2026-05-24T17:24Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, all 12
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12 sampled
`lanes/*/lane-status.json`, `dependency-backlog.json`,
`porting-summary.json`, and recent Git history through
`c3da13ff Record integration hold status`. I did not edit lane implementation
files, launch agents or tmux sessions, push, read secrets, inspect process
environments, credential stores, provider configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 17:22-17:24
observed HEAD movement during this audit: 50b501f5 -> c3da13ff8300
recent history: c3da13ff Record integration hold status; 50b501f5 Refresh independent audit status; de081313 Clarify integration hold follow-up
tracked dirty files: 329
default status rows including untracked: 19495 -> 19496
git diff --shortstat HEAD samples: 329 files changed, 263418 insertions(+), 32134 deletions(-) -> 329 files changed, 263605 insertions(+), 32134 deletions(-) -> 329 files changed, 263630 insertions(+), 32134 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred), updated 2026-05-24 12:29:10 UTC
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started
```

Required pre-root process gate:

```text
17:22Z pgrep -af '^php tools/run-tests\.php$': no rows
17:23Z pgrep -af '^php tools/run-tests\.php$': no rows
```

I did not start `php tools/run-tests.php`. The exact no-argument root gate was
empty, but the checkout failed the stability gate: `HEAD` advanced during this
run, untracked-inclusive status changed, shortstat changed on every sample, and
no owner-free lane batch was accepted.

Latest sampled manifest/status counts versus the published dashboard:

```text
lane          latest sampled manifest/status                                  dashboard
difftastic    manifest 1029/1213; status 3556 assertions                      3245 pass, 851/1077
dolt          manifest 613/613, phpBehaviorTests 442; status 442 PASS         425 pass, 613/613
esbuild       manifest 460/2567; status 460 behavior tests                    429 pass, 429/2567
gitoxide      manifest 2877/2877; status 7474 assertions                      7152 pass, 2877/2877
libsqlite     manifest 369/1589; status 369 focused cases / 5884 assertions   348 pass, 349/1589
LightningCSS  manifest 2888/3548, warning/status 2878; status 4199 assertions 4065 pass, 2765/3548
markerPDF     manifest 371/420, phpBehaviorTests 509; status 509 tests        484 pass, 347/396
pandoc        manifest 2148/2276; status 2162 checks / 386 behavior tests     362 pass, 1891/2276
quadrable     manifest 55/55; status 246 behavior tests / 5389 assertions     232 pass, 55/55
rclone        manifest 963/1601, phpBehaviorTests 963; status 965 tests       906 pass, 906/1601
readability   manifest phpBehaviorTests 281; status 280 entries / 3739 assertions 3545 pass, 1984/1984
syncthing     manifest 658/658; status 8564 assertions                        7902 pass, 658/658
```

## Findings

1. **Critical - the repo is still a moving dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:49-52`, `audits/integration-status.md`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:52` require small reviewable committed slices, verified
     handoffs, and visible stable progress for every lane.
   - Evidence: `HEAD` moved from `50b501f5` to `c3da13ff8300` during this
     run. The dirty tree is still broad: `329` tracked dirty files, default
     status rows moved `19495 -> 19496`, and shortstat moved from
     `329 files changed, 263418 insertions(+), 32134 deletions(-)` to
     `329 files changed, 263630 insertions(+), 32134 deletions(-)`. Current
     lane statuses still record `pending`, `uncommitted`, `not committed`, or
     lane-local handoff wording in `latestCommit`.

2. **Critical - `porting.html` and `porting-summary.json` are stale against every active lane.**
   - Paths: `porting.html:32-38`, `porting.html:56-67`,
     `porting-summary.json:3-8`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track denominator, mapped tests, PHP pass/fail, phase,
     audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes source `89260857cc71` generated
     `2026-05-24 12:29:46 UTC`, while current sampled `HEAD` is
     `c3da13ff8300`. Every row is stale: Difftastic is now `1029/1213` but
     the dashboard says `851/1077`; Dolt focused PHP is now `442` PASS but
     the dashboard says `425`; markerPDF is `371/420` with `509` behavior
     tests but the dashboard says `347/396` and `484`; Pandoc lane status now
     says `2162` mapped checks and `386` behavior tests while the dashboard
     says `1891/2276` and `362`; rclone status is now `965` behavior tests
     while the dashboard says `906`; Syncthing status reports `8564`
     assertions while the dashboard says `7902`.

3. **High - there is still no valid no-argument root acceptance run.**
   - Paths: `tools/run-tests.php`, `progress.md:51`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording; the current user instruction requires the
     exact duplicate-root guard before any no-argument run.
   - Evidence: `pgrep -af '^php tools/run-tests\.php$'` returned no rows in
     the 17:22Z and 17:23Z samples, but the audit did not start a root run
     because the tree moved while sampled. Lane statuses still say root
     aggregate verification is pending and cite only focused lane-local
     checks.

4. **High - manifest/status ledgers remain internally inconsistent and are not acceptance-grade.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2578-2585`,
     `lanes/dolt/lane-status.json:5-13`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:359-361`,
     `lanes/pandoc/lane-status.json:5-13`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14-16`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1358-1504`,
     `lanes/rclone/lane-status.json:5-13`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14-15`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:3569`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:1066-1078`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:44-45` require durable coordination by denominator, mapped
     tests, PHP pass/fail, current work, blocker, and commit.
   - Evidence: Dolt top-level `phpBehaviorTests` is `442`, but manifest
     warning prose still says `430 PASS cases` and `2,913 assertions`; Pandoc
     manifest `mapped` remains `2148` while lane status says `2162` checks and
     `386` behavior tests; rclone manifest says `963/1601` and
     `phpBehaviorTests: 963`, warning prose still says `956` tests /
     `9,813` assertions, while lane status says `965` tests / `9,983`
     assertions; LightningCSS top-level `mapped` is `2888`, but warning/status
     prose says `2,878`; Readability manifest records `phpBehaviorTests: 281`
     while current status prose says `280` loaded behavior entries. These are
     likely writer lag or partial metadata updates, but they are not
     comparable enough for acceptance.

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
     `legacy-doc-cfb-core`, DOCX/OpenXML via `docx-openxml-core`,
     PDF input/output via `pdf-text-dictionary-core`,
     `pdf-page-render-plan-core`, and `pandoc-pdf-engine-handoff-core`,
     EPUB, ODT/OpenDocument, templates, citations, math, tables, package
     containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax
     highlighting, and archive/compression. None of those rows has a
     support-library manifest, PHP ledger, malformed/corrupt evidence,
     accepted activation record, or bounded install-attempt/ruled-out note.
     Current WebDAV, ZIP/archive, PDF, table, URL, charset, JSON, QR,
     protobuf, and sequence-diff evidence remains lane-local until an accepted
     base-lane gate activates a row.

6. **High - Pandoc status still overstates the original rich conversion-kernel goal.**
   - Paths: `goal.md:12`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:359-361`,
     `lanes/pandoc/lane-status.json:5-13`.
   - Goal requirement at risk: Pandoc must be a document conversion kernel
     with a shared AST plus readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented intermediate forms, and WordPress block output.
   - Evidence: the strongest Pandoc evidence is still static/focused: full
     Haskell runner parity is unexecuted, the lane has only `386` behavior
     tests, and the manifest explicitly says no live fetch, browser,
     converter, PDF/package parser, or support-library expansion is claimed.
     The current slice is HTML/SVG raw inline handling. DOCX/ODT/EPUB fixture
     inventories are recorded, but the required WXR reader/writer and real
     PDF/package/citation/math/template/metadata support remain behind
     inactive support rows or future slices.

7. **High - markerPDF still mixes native PDF behavior with plan-only external/runtime evidence.**
   - Paths: `goal.md:9`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14-19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:899-908`,
     `lanes/markerpdf/lane-status.json:5-13`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and the latest
     support-library directives say whole applications, converter wrappers,
     model/runtime stacks, and hidden shell-outs are non-progress unless they
     are explicit temporary oracle tooling.
   - Evidence: the native PDF xref/text slices are useful and focused tests
     are green, but the denominator/status string still folds server/app
     plans, OCR install plans, model-runtime dependency graph planning,
     workflow/publish gates, Streamlit/FastAPI/Uvicorn boundaries, and shell
     lifecycle planning beside native PDF extraction behavior. The lane itself
     lists full benchmark/app parity, `pdftext`, `pypdfium2`, OCR/model stacks,
     Streamlit, FastAPI/Uvicorn, multiprocessing, and packaging as not
     executed. Richer searchable PDF, OCR/layout, and table progress should be
     credited only through accepted bounded rows such as
     `pdf-text-dictionary-core`, `layout-ocr-result-core`, and
     `table-geometry-core`.

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
counts and stale metadata, especially Pandoc `2148` manifest mapped versus
`2162` status checks, rclone `963/956/965`, Dolt `442` versus stale warning
`430`, LightningCSS `2888` versus `2878`, and every dashboard row still tied
to `89260857cc71`. After focused verification plus `git diff --check`,
regenerate dashboard artifacts from the accepted commit and run one serialized
no-argument root result only on that frozen snapshot.
