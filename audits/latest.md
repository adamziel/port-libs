# Independent Audit - 2026-05-24T17:46Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, all 12
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12 sampled
`lanes/*/lane-status.json`, `dependency-backlog.json`,
`porting-summary.json`, `audits/integration-status.md`, and recent Git history
through `ffb9dfef Record integration hold status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 17:41-17:46
observed HEAD movement during this audit: fe07a7f0e9f5 -> ffb9dfef5f0a
recent history: ffb9dfef Record integration hold status; fe07a7f0 Refresh independent audit status; b4220188 Record integration root hold status
tracked dirty files: 330 -> 329
default status rows including untracked: 19727 -> 19740 -> 19766
git diff --shortstat HEAD samples: 330 files changed, 264687 insertions(+), 31382 deletions(-) -> 329 files changed, 264587 insertions(+), 31382 deletions(-) -> 329 files changed, 264647 insertions(+), 31382 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred), updated 2026-05-24 12:29:10 UTC
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started
```

Required exact pre-root process gate:

```text
17:41Z pgrep -af '^php tools/run-tests\.php$': no rows
17:45Z pgrep -af '^php tools/run-tests\.php$': no rows
17:46Z pgrep -af '^php tools/run-tests\.php$': no rows
```

I did not start `php tools/run-tests.php`. The exact duplicate-root gate was
clear in my samples, but the checkout failed the stability gate: `HEAD`,
shortstat, and status-row counts changed while the audit was running, all lane
status files still describe pending/uncommitted lane batches, and the
integration worker recorded active lane children for every primary lane. The
latest integration status also records dirty-root churn, including a failed
dirty root at `17:42:23Z` before a later rclone handoff added the referenced
example as an untracked file.

Latest sampled manifest/status counts versus the published dashboard:

```text
lane          latest sampled manifest/status                                  dashboard
difftastic    manifest 1032/1213; status 3580 assertions                      3245 pass, 851/1077
dolt          manifest 613/613, phpBehaviorTests 442; status 444 PASS         425 pass, 613/613
esbuild       manifest 463/2567; status 463 tests / 4340 assertions           429 pass, 429/2567
gitoxide      manifest 2877/2877; status 7525 assertions                      7152 pass, 2877/2877
libsqlite     manifest 371/1589; status 371 cases / 5927 assertions           348 pass, 349/1589
LightningCSS  manifest 2893/3548; status 4215 assertions                      4065 pass, 2765/3548
markerPDF     manifest 374/422, phpBehaviorTests 511; status prose still 373  484 pass, 347/396
pandoc        manifest 2199/2276; status 388 tests / 3983 assertions          362 pass, 1891/2276
quadrable     manifest 55/55; status 248 tests / 5425 assertions              232 pass, 55/55
rclone        manifest 968/1601, phpBehaviorTests 968; status 968 tests       906 pass, 906/1601
readability   manifest 1984/1984, phpBehaviorTests 283; status 3755 assertions 3545 pass, 1984/1984
syncthing     manifest 658/658; status 8681 assertions                        7902 pass, 658/658
```

## Findings

1. **Critical - the repository is still a moving dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:15`, `progress.md:49-51`,
     `audits/integration-status.md:5-40`, `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:52` require small reviewable committed slices, verified
     handoffs, and visible stable progress for every lane.
   - Evidence: `HEAD` moved from `fe07a7f0e9f5` to `ffb9dfef5f0a` during
     this audit. Dirty state also moved: tracked shortstat changed from
     `330 files changed, 264687 insertions(+), 31382 deletions(-)` to
     `329 files changed, 264647 insertions(+), 31382 deletions(-)`, and
     untracked-inclusive status rows moved `19727 -> 19740 -> 19766`.
     `audits/integration-status.md:37-40` records active Codex children for
     all primary lanes. Current lane status files continue to say
     `pending`, `uncommitted`, or `not committed` for Difftastic, Dolt,
     esbuild, Gitoxide, libsqlite, LightningCSS, markerPDF, Pandoc,
     Quadrable, rclone, Readability, and Syncthing.

2. **Critical - no trustworthy no-argument root acceptance result exists for the dirty tree.**
   - Paths: `tools/run-tests.php`, `audits/integration-status.md:62-82`,
     `lanes/rclone/tests/VfsWebDavReadResponseTest.php:613`,
     `lanes/rclone/examples/wordpress-webdav-servecontent-headers-preflight.php`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording; the current user instruction requires the
     exact duplicate-root guard before any no-argument root run.
   - Evidence: my exact root gates at 17:41Z, 17:45Z, and 17:46Z returned no
     rows, but I did not start a root run because the checkout changed during
     the audit. The integration worker recorded a dirty root pass at 17:38Z
     that was invalidated by later dirty-tree changes, then a dirty root
     failure at 17:42:23Z: `383` files, `62949` assertions, `1` failure,
     caused by `VfsWebDavReadResponseTest.php` requiring missing
     `wordpress-webdav-servecontent-headers-preflight.php`
     (`audits/integration-status.md:62-82`). That example exists now only as
     an untracked file, so the rclone fix is still not an accepted committed
     root baseline.

3. **Critical - `porting.html` and `porting-summary.json` are stale against every active lane.**
   - Paths: `porting.html:32-38`, `porting.html:56-67`,
     `porting-summary.json:3-8`, `porting-summary.json:16-212`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track denominator, mapped tests, PHP pass/fail, phase,
     audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes source `89260857cc71`, generated
     `2026-05-24 12:29:46 UTC`, while current `HEAD` is `ffb9dfef5f0a`.
     Every row is stale: Difftastic is now `1032/1213` with `3580`
     assertions while the dashboard says `851/1077` and `3245`; esbuild is
     `463/2567` while the dashboard says `429/2567`; markerPDF is `374/422`
     while the dashboard says `347/396`; Pandoc is `2199/2276` while the
     dashboard says `1891/2276`; rclone is `968/1601` while the dashboard
     says `906/1601`; Syncthing is `8681` assertions while the dashboard says
     `7902`.

4. **High - manifest/status ledgers still disagree inside the lane evidence itself.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:1197`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:1207`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:910`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1366`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1515`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:44-45` require durable coordination by denominator, mapped
     tests, PHP pass/fail, current work, blocker, and commit.
   - Evidence: Difftastic's top-level manifest says `mapped: 1032`, one
     warning agrees, but another stale warning still says `1030`. markerPDF
     top-level manifest says `374` mapped, while manifest warning and lane
     status prose still say `373`. rclone top-level manifest and native
     implementation now say `968`, but the manifest warning still says `965`
     focused tests with `9983` assertions. These inconsistencies are
     dashboard inputs, not harmless commentary, because the goal requires the
     dashboard and progress ledger to be generated from trustworthy lane
     state.

5. **High - support-library coverage remains backlog-only, not first-class support-lane work.**
   - Paths: `dependency-backlog.json:4`, `dependency-backlog.json:7-21`,
     `dependency-backlog.json:81-93`, `dependency-backlog.json:129-175`,
     `dependency-backlog.json:179-228`, `dependency-backlog.json:233-267`,
     `dependency-backlog.json:274-336`, `dependency-backlog.json:340-409`,
     `dependency-backlog.json:413-425`, `dependency-backlog.json:629-645`,
     `porting.html:72-129`.
   - Goal requirement at risk: the latest support-library directives require
     bounded native PHP components with activation gates,
     dependency-specific upstream/spec denominators, mapped fixtures, PHP
     pass/fail ledgers, malformed/corrupt cases where relevant, bounded
     install attempts or ruled-out notes where tooling is missing, and as
     much upstream/spec-suite evidence as can honestly run.
   - Evidence: the tracker has the required Pandoc rows visible: DOC via
     `legacy-doc-cfb-core`, DOCX/OpenXML, PDF input/output handoff, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package
     containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax
     highlighting, and archive/compression. It also has rows for the other
     high-value base-tool support needs. But there are still `0` active
     support ports, and there are no support-library
     `UPSTREAM_TEST_MANIFEST.json` files, dependency-specific PHP pass/fail
     ledgers, malformed/corrupt evidence records, accepted activation records,
     or bounded install-attempt/ruled-out notes. Current WebDAV, PDF, ZIP,
     JSON, charset, Unicode, QR, protobuf, table, checksum, and diff evidence
     remains lane-local until a frozen base-lane gate activates a bounded row.

6. **High - Pandoc remains far short of the original rich conversion-kernel goal despite 99% status.**
   - Paths: `goal.md:12`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:368-372`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1357-1360`,
     `lanes/pandoc/lane-status.json:5`, `lanes/pandoc/lane-status.json:11-14`,
     `dependency-backlog.json:81-93`, `dependency-backlog.json:129-175`,
     `dependency-backlog.json:179-228`, `dependency-backlog.json:233-267`,
     `dependency-backlog.json:391-425`.
   - Goal requirement at risk: Pandoc must be a document conversion kernel
     with a shared AST plus readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented intermediate forms, and WordPress block output.
   - Evidence: the current Pandoc manifest is honest that full upstream runner
     parity is not executed and that no live fetch, browser, converter,
     PDF/package parser, PlainMath/MathML conversion, or XML/HTML support
     expansion is claimed. The latest accepted-looking slice is a narrow HTML
     reader textarea raw-block branch. DOCX, ODT, EPUB, PDF, citations, math,
     table-rich package behavior, JSON/YAML metadata, templates, syntax
     highlighting, archive/compression, and XML/HTML support are still gated
     backlog rows rather than accepted conversion-kernel parity. WXR is still
     not visible as an accepted Pandoc reader/writer denominator.

7. **High - markerPDF still mixes native PDF progress with plan-only runtime/application evidence.**
   - Paths: `goal.md:9`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:10-19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:595`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:910`,
     `lanes/markerpdf/lane-status.json:12`,
     `dependency-backlog.json:274-336`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and the support
     library directives say whole applications, converter wrappers, model
     stacks, and hidden shell-outs are non-progress unless they are explicit
     temporary oracle tooling.
   - Evidence: the native PDF xref/text-stream slices are useful, but the
     denominator/status still folds OCR install plans, package graphs,
     model-runtime dependency graphs, benchmark/archive plans, Streamlit,
     FastAPI/Uvicorn, multiprocessing, chunk-convert shell lifecycle, and
     publish/CLA workflow plans beside native PDF extraction behavior. The
     lane itself says full upstream benchmarks/app parity and the heavy
     Python/PDF/model stack are not executed. Richer searchable PDF,
     OCR/layout, and table progress should be credited only through accepted
     bounded rows such as `pdf-text-dictionary-core`,
     `layout-ocr-result-core`, and `table-geometry-core`.

8. **Medium - the 98-99 percent progress claims remain misleading.**
   - Paths: `porting.html:32`, `porting.html:56-67`,
     `porting-summary.json:8`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:35-40` requires meaningful fixture
     parity, edge-case coverage, upstream tests as source of truth, and
     explicit blockers for hard features.
   - Evidence: most lanes still report `98-99%`, and the dashboard average is
     `98.3%`, but the dashboard is stale, current lane work is unaccepted,
     root aggregate verification is pending or failed on a dirty snapshot,
     multiple upstream runners remain static-only or bounded, and no
     support-library row is active. The percentages are not a reliable signal
     for accepted native parity.

## Next Intervention

Freeze or wait out lane workers, focused/root runners, dashboard/status
publishers, support-library scouts, capacity jobs, evaluator/auditor loops,
and integrator loops. Then accept or reject one owner-free lane batch from a
stable two-poll snapshot. The current best target is the rclone root blocker if
the dirty-root failure persists: stage or reject the untracked
`lanes/rclone/examples/wordpress-webdav-servecontent-headers-preflight.php`
handoff together with its matching test/status/manifest changes, run focused
rclone verification, then run exactly one serialized no-argument
`php tools/run-tests.php` only after `pgrep -af '^php tools/run-tests\.php$'`
is clear and the checkout is frozen. Regenerate `porting.html` and
`porting-summary.json` only from the accepted commit, and keep support-library
rows inactive until a base-lane slice is accepted or accepted-blocked on a
specific bounded component.
