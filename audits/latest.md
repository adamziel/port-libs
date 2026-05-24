# Independent Audit - 2026-05-24T17:55Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, all 12
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12 sampled
`lanes/*/lane-status.json`, `audits/integration-status.md`, and recent Git
history through `b8a59524 Update integration root hold status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 17:49-17:55
current HEAD at pre-commit check: b8a59524731e
observed HEAD movement during audit: 288b01b7aec7 -> d0cee5da9378 -> 1a6dc6f741a5 -> b8a59524731e
recent history: b8a59524 Update integration root hold status; 1a6dc6f7 Record integration hold status; d0cee5da Record integration hold status
branch sample: main...origin/main [ahead 962, behind 68]
default status rows including untracked: 20013 -> 20027
git diff --shortstat HEAD samples: 329 files changed, 265200 insertions(+), 31473 deletions(-) -> 329 files changed, 265315 insertions(+), 31407 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred), updated 2026-05-24 12:29:10 UTC
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started
```

Required exact pre-root process gate:

```text
first sample: pgrep -af '^php tools/run-tests\.php$' matched `431141 php tools/run-tests.php`
owner follow-up: `ps -o pid,user,lstart,etime,command -p 431141` returned only the header because the process exited before owner sampling
17:50-17:53Z follow-up samples: pgrep -af '^php tools/run-tests\.php$' returned no rows
17:55Z pre-commit sample: pgrep matched `477024 php tools/run-tests.php`; owner evidence `477024 claude 476915 R+ 00:47 php tools/run-tests.php`
17:56Z pre-commit sample: pgrep matched `521777 php tools/run-tests.php`; owner evidence `521777 claude 521652 R+ 00:13 php tools/run-tests.php`
```

I did not start `php tools/run-tests.php`. The first exact gate showed an
active no-argument root harness, so a duplicate run was blocked. Later gates
were briefly clear, but pre-commit samples again showed active no-argument
root harnesses owned by `claude`; no duplicate was launched. The
checkout also still failed the stability gate: `HEAD` had moved from the
previous audit, then advanced again through `d0cee5da9378`,
`1a6dc6f741a5`, and `b8a59524731e`; the dirty shortstat and
untracked-inclusive status counts changed during this pass, and the integration
hold still records a live multi-writer tree.

Latest sampled manifest/status counts versus the published dashboard:

```text
lane          latest sampled manifest/status                                  dashboard
difftastic    manifest 1032/1213; status 3580 assertions                      3245 pass, 851/1077
dolt          manifest 613/613; status 445 PASS cases                         425 pass, 613/613
esbuild       manifest 463/2567; status 463 tests                             429 pass, 429/2567
gitoxide      manifest 2877/2877; status 7529 assertions                      7152 pass, 2877/2877
libsqlite     manifest 371/1589; status 372 cases / 5942 assertions           348 pass, 349/1589
LightningCSS  manifest 2915/3548; status 4238 assertions                      4065 pass, 2765/3548
markerPDF     manifest 374/423; status 512 behavior tests                     484 pass, 347/396
pandoc        manifest 2199/2276; status 2216 checks / 389 behavior tests     362 pass, 1891/2276
quadrable     manifest 55/55; status 249 behavior tests                       232 pass, 55/55
rclone        manifest 968/1601; status 968 tests / 10050 assertions          906 pass, 906/1601
readability   manifest 1984/1984; status 283 behavior tests / 3755 assertions 3545 pass, 1984/1984
syncthing     manifest 658/658; status 8681 assertions                        7902 pass, 658/658
```

## Findings

1. **Critical - the repository is still a live dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:15`, `progress.md:51`,
     `audits/integration-status.md:3-30`,
     `audits/integration-status.md:38-62`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:52` require small reviewable committed slices, verified
     handoffs, and visible stable progress for every lane.
   - Evidence: current integration status says no lane/status claim was
     accepted, no dashboard artifacts were regenerated, and no support row was
     activated. During this audit, `HEAD` moved from `288b01b7aec7` through
     `d0cee5da9378` and `1a6dc6f741a5` to `b8a59524731e`,
     untracked-inclusive status rows moved
     `20013 -> 20027` and tracked shortstat moved from
     `329 files changed, 265200 insertions(+), 31473 deletions(-)` to
     `329 files changed, 265315 insertions(+), 31407 deletions(-)`.
     Integration also rejected esbuild as too accumulated and moving, skipped
     Readability as too broad, and recorded active/risky lanes still present.

2. **Critical - no trustworthy no-argument root acceptance result exists for the dirty tree.**
   - Paths: `tools/run-tests.php`, `audits/integration-status.md:22-35`,
     `audits/integration-status.md:148-171`,
     `lanes/rclone/tests/VfsWebDavReadResponseTest.php`,
     `lanes/rclone/examples/wordpress-webdav-servecontent-headers-preflight.php`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording; the current user instruction requires the
     exact duplicate-root guard before any no-argument root run.
   - Evidence: this audit's first exact gate matched active no-argument root
     PID `431141 php tools/run-tests.php`; owner evidence could not be captured
     because the process exited before `ps` sampling. Later exact gates were
     clear, but pre-commit samples matched active no-argument root PID
     `477024`, owned by `claude`, PPID `476915`, state `R+`, elapsed `00:47`,
     command `php tools/run-tests.php`, then PID `521777`, owned by `claude`,
     PPID `521652`, state `R+`, elapsed `00:13`, command
     `php tools/run-tests.php`. I did not start a root run. Integration records
     the active dirty root later completed at `17:57:11Z` with `383` files,
     `63166` assertions, and `0` failures, but it explicitly remains
     non-acceptance evidence because the worktree had already moved and no lane
     batch was accepted before it ran. Earlier integration records one dirty
     root pass invalidated by later dirty shortstat movement, then a dirty root
     failure at `17:42:23Z`: `383` files, `62949` assertions, `1` failure,
     caused by rclone requiring missing
     `wordpress-webdav-servecontent-headers-preflight.php`.

3. **Critical - `porting.html` and `porting-summary.json` are stale against every active lane.**
   - Paths: `porting.html:32-38`, `porting.html:56-67`,
     `porting-summary.json:2-8`, `porting-summary.json:11-90`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track denominator, mapped tests, PHP pass/fail, phase,
     audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes source `89260857cc71`, generated
     `2026-05-24 12:29:46 UTC`, while current `HEAD` is `288b01b7aec7`.
     Every row is stale: Difftastic is now `1032/1213` while the dashboard says
     `851/1077`; libsqlite is `371/1589` while the dashboard says `349/1589`;
     LightningCSS is `2915/3548` while the dashboard says `2765/3548`;
     markerPDF is `374/423` while the dashboard says `347/396`; Pandoc status
     now says `2216` checks and `389` behavior tests while the dashboard says
     `1891/2276` and `362`; rclone is `968/1601` while the dashboard says
     `906/1601`; Syncthing is `8681` assertions while the dashboard says
     `7902`.

4. **High - lane ledgers still disagree internally, so manifest/status data cannot be trusted as generated dashboard input.**
   - Paths: `lanes/dolt/lane-status.json:5-13`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:28`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2583-2590`,
     `lanes/libsqlite/lane-status.json:6-13`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:16-18`,
     `lanes/pandoc/lane-status.json:5-12`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:368-372`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1357`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:44-45` require durable coordination by denominator, mapped
     tests, PHP pass/fail, current work, blocker, and commit.
   - Evidence: Dolt lane status and current manifest evidence report `445`
     PASS cases, but `nativeImplementation.phpBehaviorTests` remains `442`.
     libsqlite status reports `372` focused cases and `5942` assertions, while
     the manifest top-level denominator still says `mapped: 371` and its latest
     evidence still describes the older comma-grouped numeric slice. Pandoc
     status reports a newer doc-noteref/endnotes slice with `2216` mapped
     checks and `389` behavior tests, but the manifest still reports
     `mapped: 2199` and its latest-slice fields still describe the prior
     textarea raw-block slice. These are not harmless notes: they are the
     fields the dashboard is supposed to summarize.

5. **High - support-library coverage is still backlog-only, not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:3-4`,
     `dependency-backlog.json:7-22`, `dependency-backlog.json:25-42`,
     `dependency-backlog.json:81-94`, `dependency-backlog.json:129-176`,
     `dependency-backlog.json:214-286`, `dependency-backlog.json:306-337`,
     `dependency-backlog.json:340-426`, `dependency-backlog.json:481-566`,
     `porting.html:72-129`, `audits/integration-status.md:64-77`,
     `audits/integration-status.md:185-191`.
   - Goal requirement at risk: the latest support-library directives require
     bounded native PHP components with activation gates,
     dependency-specific upstream/spec denominators, mapped fixtures, PHP
     pass/fail ledgers, malformed/corrupt cases where relevant, bounded
     install attempts or ruled-out notes where tooling is missing, and as much
     upstream/spec-suite evidence as can honestly run.
   - Evidence: the tracker has visible gated rows for Pandoc DOC,
     DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument,
     templates, citations, math, tables, package containers, XML/HTML,
     Unicode/charset, JSON/YAML metadata, syntax highlighting, and
     archive/compression, plus support needs for the other base tools. But
     there are still `0` active support ports, tracked `git ls-files
     '*UPSTREAM_TEST_MANIFEST.json'` lists only the 12 lane manifests, and
     there are no support-library manifests, dependency-specific PHP ledgers,
     malformed/corrupt evidence records, accepted activation records, or
     bounded install-attempt/ruled-out notes. Broad rows such as Unicode,
     charset, checksum, archive/compression, and sequence diff should not be
     activated as multi-lane blobs; they need one concrete base-lane gate and a
     bounded denominator before any progress credit.

6. **High - Pandoc remains far short of the original rich conversion-kernel goal despite near-complete status language.**
   - Paths: `goal.md:12`, `lanes/pandoc/lane-status.json:5-12`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:368-372`,
     `dependency-backlog.json:81-94`, `dependency-backlog.json:129-176`,
     `dependency-backlog.json:214-286`, `dependency-backlog.json:413-426`.
   - Goal requirement at risk: Pandoc must be a document conversion kernel
     with a shared AST plus readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented intermediate forms, and WordPress block output.
   - Evidence: the current Pandoc status is honest that full upstream Haskell
     runner parity is unexecuted and that the latest slice does not invoke
     upstream Pandoc, browser tooling, converter shell-outs, PDF processing,
     ZIP/package parsers, citation/CSL engines, XML/HTML support-library
     expansion, PlainMath/MathML conversion, or broader syntax-highlighting
     support. DOCX, ODT, EPUB, PDF, citations, math, templates, rich table
     behavior, JSON/YAML metadata, archive/compression, XML/HTML, Unicode, and
     charset work remain gated backlog rows rather than accepted conversion
     kernel parity. WXR still is not visible as an accepted Pandoc
     reader/writer denominator.

7. **High - markerPDF still mixes native PDF progress with plan-only runtime/application evidence.**
   - Paths: `goal.md:9`, `lanes/markerpdf/lane-status.json:5-14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12-19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:907-920`,
     `dependency-backlog.json:272-337`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and the support
     library directives say whole applications, converter wrappers, model
     stacks, and hidden shell-outs are non-progress unless they are explicit
     temporary oracle tooling.
   - Evidence: the native hybrid xref stream slice is useful and explicitly
     avoids Python/pdftext/pypdfium/Poppler/Ghostscript, but the denominator
     still folds benchmark/archive plans, Streamlit, FastAPI/Uvicorn,
     multiprocessing/chunk shell lifecycle, model-runtime dependency graphs,
     OCR install plans, package/publish workflows, and other plan-only
     application/runtime evidence beside native PDF extraction behavior. Richer
     searchable PDF, OCR/layout, and table progress should be credited only
     through accepted bounded rows such as `pdf-text-dictionary-core`,
     `layout-ocr-result-core`, and `table-geometry-core`.

8. **Medium - the 98-99 percent progress claims remain misleading.**
   - Paths: `porting.html:32`, `porting.html:56-67`,
     `porting-summary.json:8`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:35-40` requires meaningful fixture
     parity, edge-case coverage, upstream tests as source of truth, and
     explicit blockers for hard features.
   - Evidence: most lanes still report `98-99%`, and the dashboard average is
     `98.3%`, but the dashboard is stale, current lane work is unaccepted,
     root aggregate verification is absent for the dirty tree, several
     manifests/status files disagree, multiple upstream runners remain
     static-only or bounded, and no support-library row is active. The
     percentages are not a reliable signal for accepted native parity.

## Next Intervention

Freeze or wait out lane workers, root/focused runners, status publishers,
dashboard publishers, evaluator/auditor loops, capacity jobs, and integrator
loops. Then accept or reject one owner-free lane batch from a stable two-poll
snapshot. The current best intervention is still an integration freeze plus one
small intake: first normalize the libsqlite/Pandoc/Dolt manifest-status count
drift, then pick a single owner-free lane batch with stopped logs, run focused
verification, run exactly one serialized no-argument `php tools/run-tests.php`
only after `pgrep -af '^php tools/run-tests\.php$'` is clear and the checkout
is frozen, run `git diff --check`, regenerate `porting.html` and
`porting-summary.json` from the accepted commit, and keep support-library rows
inactive until a base-lane slice is accepted or accepted-blocked on one bounded
component.
