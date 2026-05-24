# Independent Audit - 2026-05-24T16:15Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
`lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, and recent Git history through
`7ac76223 Record integration hold status`. I did not edit lane implementation
files, launch agents or tmux sessions, push, read secrets, inspect process
environments, credential stores, provider configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 16:02-16:15
observed HEAD: 6680a1c98ea9 -> 17be9aa89c52 -> 4b9edad3fd1b -> f1f42d3b0b60 -> 7ac762234b68
recent history: 7ac76223 Record integration hold status; f1f42d3b Record integration hold status; 4b9edad3 Record integration hold status; 17be9aa8 Record integration hold status
tracked dirty files: 330 -> 329 -> 331
default status rows including untracked: 19435 -> 19437 -> 19445 -> 19453 -> 19456
git diff --shortstat: 330 files changed, 259519 insertions(+), 32427 deletions(-) -> 329 files changed, 259855 insertions(+), 32425 deletions(-) -> 331 files changed, 260839 insertions(+), 33056 deletions(-) -> 331 files changed, 261480 insertions(+), 33061 deletions(-) -> 331 files changed, 261507 insertions(+), 33058 deletions(-) -> 331 files changed, 262374 insertions(+), 33749 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred), updated 2026-05-24 12:29:10 UTC
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started
```

Required pre-root process gate:

```text
16:02Z pgrep -af '^php tools/run-tests\.php$': no rows
16:05Z pgrep -af '^php tools/run-tests\.php$': no rows
16:06Z pgrep -af '^php tools/run-tests\.php$': no rows
post-edit gate: matched transient no-argument root PID 1142019 (`php tools/run-tests.php`)
owner sample for PID 1142019: `ps -o pid,user,ppid,etimes,stat,args -p 1142019` returned only the header because the process had already exited
16:15Z validation gate: active no-argument root PID 1224822 (`php tools/run-tests.php`)
owner sample for PID 1224822: user `claude`, PPID `1212874`, elapsed `28s`, state `Rs`, command `php tools/run-tests.php`
pre-commit pgrep -af '^php tools/run-tests\.php$': no rows
```

I did not start `php tools/run-tests.php`. The exact root gate was initially
empty, but the checkout and lane metadata moved during the audit, a transient
no-argument root PID appeared during validation, and a separate active
no-argument root PID was present at the final gate. I did not start a
duplicate. A root result from this source would not be an audit-owned frozen
acceptance checkpoint.

Current manifest/status sample versus the published dashboard:

```text
lane          current manifest/status                 dashboard
difftastic    manifest 1010/1213, status 3507 pass    3245 pass, 851/1077
dolt          manifest 613/613, status 436 pass       425 pass, 613/613
esbuild       manifest 456/2567, status 456 pass      429 pass, 429/2567
gitoxide      manifest 2877/2877, status 7407 pass    7152 pass, 2877/2877
libsqlite     manifest 368/1589, status 368 pass      348 pass, 349/1589
LightningCSS  manifest 2874/3548, status 4193 pass    4065 pass, 2765/3548
markerPDF     manifest 369/418, status 506 pass       484 pass, 347/396
pandoc        manifest 2121/2276, status 383 pass, stale summary text from prior slice  362 pass, 1891/2276
quadrable     manifest 55/55, status 245 pass         232 pass, 55/55
rclone        manifest 958/1601, status 958 pass      906 pass, 906/1601
readability   manifest 1984/1984, status 3722 assertions / 278 behavior entries  3545 pass, 1984/1984
syncthing     manifest 658/658, status 8427 pass      7902 pass, 658/658
```

## Findings

1. **Critical - the repo is still a moving dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:50`, `audits/integration-status.md:5-8`,
     `audits/integration-status.md:17-36`,
     `audits/integration-status.md:49-65`,
     `lanes/difftastic/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`,
     and the other `lanes/*/lane-status.json:13` latest-commit fields.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:52` require small reviewable committed slices, verified
     handoffs, and visible stable progress for every lane.
   - Evidence: during this audit `HEAD` moved from `6680a1c98ea9` through
     `17be9aa89c52`, `4b9edad3fd1b`, and `f1f42d3b0b60` to `7ac762234b68`,
     tracked dirty files moved `330 -> 329 -> 331`, default status rows moved `19435 -> 19437 -> 19445 -> 19456`,
     and shortstat moved from
     `330 files changed, 259519 insertions(+), 32427 deletions(-)` through
     `329 files changed, 259855 insertions(+), 32425 deletions(-)` to
     `331 files changed, 262374 insertions(+), 33749 deletions(-)`.
     Multiple lane manifests/status files changed while or after they were
     being read, and all 12 lane statuses still report `pending`,
     `uncommitted`, or `not committed` latest-commit values rather than
     accepted lane commits.

2. **Critical - `porting.html` and `porting-summary.json` are stale against every lane.**
   - Paths: `porting.html:32-38`, `porting.html:56-67`,
     `porting-summary.json:2-8`, `porting-summary.json:11-203`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track denominator, mapped tests, PHP pass/fail, phase,
     audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes source `89260857cc71` generated
     `2026-05-24 12:29:46 UTC`, while current `HEAD` is `7ac762234b68`.
     Every lane has newer counts. Examples: Difftastic is now `1010/1213`
     mapped with `3507` pass but dashboard says `851/1077` and `3245`;
     libsqlite is now `368/1589` with `368` pass but dashboard says `349/1589`
     and `348`; markerPDF is now `369/418` with `506` pass but dashboard says
     `347/396` and `484`; Syncthing is now `8427` pass but dashboard says
     `7902`.

3. **High - there is still no valid no-argument root acceptance run.**
   - Paths: `tools/run-tests.php`, `progress.md:50`,
     `audits/integration-status.md:31-36`,
     `lanes/*/lane-status.json:12-13`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording; the user also forbids duplicate
     no-argument root harnesses.
   - Evidence: the exact gate returned no rows at 16:02Z, 16:05Z, and
     16:06Z, then a post-edit gate matched transient no-argument root PID
     `1142019` (`php tools/run-tests.php`). The immediate owner sample
     returned only the `ps` header because the process had already exited.
     A validation gate at 16:15Z matched active no-argument root PID
     `1224822`, owned by `claude` with PPID `1212874`, elapsed `28s`, state
     `Rs`, and command `php tools/run-tests.php`; the pre-commit gate later
     returned no rows. I did not start a duplicate.
     Current lane statuses consistently leave root aggregate verification
     pending with lane-local focused runs only.

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
   - Evidence: the tracker has 37 rows and 0 active support ports. Pandoc's
     required DOC, DOCX/OpenXML, PDF input/output handoff, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package
     containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax
     highlighting, and archive/compression categories are present as gated
     rows, but none has a support-library manifest, PHP pass/fail ledger,
     malformed/corrupt evidence, accepted activation record, or bounded
     install-attempt/ruled-out note. Lane-local WebDAV, ZIP/gzip/archive, PDF,
     and table helpers should not be counted as shared support-library
     progress until accepted through those rows.

5. **High - Pandoc's public status still overstates the original rich-kernel goal.**
   - Paths: `goal.md:12`, `lanes/pandoc/lane-status.json:5`,
     `lanes/pandoc/lane-status.json:11-14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:348-352`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1308-1315`,
     `lanes/pandoc/tests/MarkdownReaderTest.php:2157`.
   - Goal requirement at risk: Pandoc must be a document conversion kernel
     with a shared AST plus readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented intermediate forms, and WordPress block output.
   - Evidence: the manifest now reports `2,121` focused checks and a latest
     HTML `pSmall` slice, while lane status has moved to `383` pass but still
     relies on bounded lane-local slices rather than full runner parity. Full Haskell runner
     parity remains unexecuted. `rg` finds WXR in the Pandoc lane only as a
     test string (`MarkdownReaderTest.php`), not as a visible WXR
     reader/writer capability. PDF, package, citation, math, template,
     syntax-highlighting, JSON/YAML metadata, Unicode/charset, and archive
     support remain gated and inactive.

6. **High - markerPDF still mixes native PDF extraction with plan-only external/runtime evidence in the denominator.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:10-19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1144-1189`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:9-14`,
     `dependency-backlog.json:272-337`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and the latest
     support-library directives say whole applications, converter wrappers,
     model/runtime stacks, and hidden shell-outs are non-progress unless they
     are explicit temporary oracle tooling.
   - Evidence: the manifest denominator/status string folds `*-plan`,
     Streamlit/FastAPI server/app/upload routes, install plans, model runtime
     graph planning, workflow/publish gates, and shell lifecycle planning
     beside native PDF extraction behavior. The native startxref/PDF slices may
     be useful, but richer pdftext/OCR/layout/table work should be credited
     only through accepted bounded support rows such as
     `pdf-text-dictionary-core`, `layout-ocr-result-core`, and
     `table-geometry-core`.

7. **Medium - multiple lane ledgers remain internally non-normalized even when focused tests are green.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12-18`,
     `lanes/dolt/lane-status.json:5-13`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:348-352`,
     `lanes/pandoc/lane-status.json:5-13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14-19`,
     `lanes/markerpdf/lane-status.json:5-13`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:44-45` require durable coordination by denominator, mapped
     tests, PHP pass/fail, current work, blocker, and commit.
   - Evidence: Dolt status reports `436` pass but the manifest
     `nativeImplementation.phpBehaviorTests` remains `430`. Pandoc manifest
     reports `2,121` mapped checks while lane status reports only `383` pass
     and no accepted full-runner denominator. markerPDF normalized to `369/418` and `506` pass during this
     audit while the dashboard remains at `347/396` and `484`. These are not
     PHP regressions, but they make status/dashboard evidence non-comparable.

8. **Medium - 98-99 percent progress claims still overstate accepted native parity.**
   - Paths: `porting.html:32`, `porting.html:56-67`,
     `porting-summary.json:8`, `lanes/*/lane-status.json:12-13`.
   - Goal requirement at risk: `goal.md:35-40` requires meaningful fixture
     parity, edge-case coverage, upstream tests as source of truth, and
     explicit blockers for hard features.
   - Evidence: the dashboard reports `98.3%` average and most lanes at
     `98-99%`, but the published dashboard is stale, current lane work is
     unaccepted, root aggregate verification is pending/non-attributable, many
     upstream runners are static-only or bounded, and no support-library row is
     active.

## Next Intervention

Freeze or wait out lane workers, focused/root runners, dashboard/status
publishers, support-library scouts, capacity jobs, evaluator/auditor loops,
and integration-hold writers long enough to get two stable polls of `HEAD`,
tracked/default status counts, shortstat, exact root gate
`pgrep -af '^php tools/run-tests\.php$'`, dashboard/dependency counts, lane
status timestamps, and relevant log mtimes. Then accept or reject exactly one
owner-free lane batch at a time. First normalize manifest/status/dashboard
counts and stale next-task metadata, especially Difftastic/Readability latest
movement, Pandoc status `383` versus manifest `2121`, Dolt `430` versus
`436`, libsqlite `368/1589` versus dashboard `349/1589`, markerPDF `369/418`
versus dashboard `347/396`, Syncthing `8427` versus dashboard `7902`, and
rclone `958` versus dashboard `906`. After
focused verification plus `git diff --check`, regenerate dashboard artifacts
from the accepted commit and run one serialized no-argument root result only
on that frozen snapshot.
