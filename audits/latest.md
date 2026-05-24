# Independent Audit - 2026-05-24T06:28Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every root `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
current lane status files, `dependency-backlog.json`, recent Git history, and
the required PHP root-run process gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T06:22:58Z through 2026-05-24T06:27:46Z
HEAD movement during audit: 62df9aa6e101 -> 8cd70f0cd145
recent commits: 8cd70f0c Record integration hold status; 62df9aa6 Refresh independent audit status; 69167de1 Record integration hold status
branch divergence: main...origin/main [ahead 736, behind 68]
tracked dirty rows: 314 -> 316
default status rows including untracked: 14414 -> 14425
git diff --shortstat: 314 files changed, 182621 insertions(+), 27146 deletions(-) -> 316 files changed, 184898 insertions(+), 28064 deletions(-)
manifest JSON validation: jq empty passed for all 12 root lane manifests
status JSON validation: jq empty passed for all 12 lane-status files, porting-summary.json, and dependency-backlog.json
dependency backlog: 23 items, grouped as candidate 13 / deferred 10 and critical 4 / high 8 / medium 11; no active dependency port
dashboard snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
root run by this audit: not started
```

Required root-run gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T06:22Z:
2121305 php tools/run-tests.php lanes/readability/tests

owner sample for 2121305:
process had already exited before ps could sample it

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T06:24Z:
2125947 php tools/run-tests.php
2127503 php tools/run-tests.php lanes/syncthing/tests/PullFinisherTest.php ... lanes/syncthing/tests/ServiceMapTest.php

ps -o pid,user,ppid,etime,stat,command -p 2125947,2127503:
2125947 claude 2125861 00:36 R+ php tools/run-tests.php
2127503 claude 2127345 00:31 R+ php tools/run-tests.php lanes/syncthing/tests/PullFinisherTest.php ... lanes/syncthing/tests/ServiceMapTest.php

later pgrep at 2026-05-24T06:24:45Z:
2135298 php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php

owner sample for 2135298:
process had already exited before ps could sample it

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T06:27:46Z:
2159612 php tools/run-tests.php lanes/syncthing/tests
2159668 php tools/run-tests.php

ps -o pid,user,ppid,etime,stat,command -p 2159612,2159668:
2159612 claude 2131507 00:05 Rs php tools/run-tests.php lanes/syncthing/tests
2159668 claude 2159626 00:04 R+ php tools/run-tests.php
```

I did not start `php tools/run-tests.php`. The required exact process gate
matched an active no-argument root harness owned by `claude`, then later a
focused Syncthing shard and a transient focused Pandoc shard; final sampling
again matched an active no-argument root harness owned by `claude`. The
checkout was also moving: `HEAD`, tracked rows, untracked-inclusive status rows,
and shortstat changed during the audit window.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:37`, `progress.md:39`, current Git status.
   - Requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable slices, verification before integration, and
     honest repo-wide checks.
   - Evidence: `HEAD` moved from `62df9aa6e101` to `8cd70f0cd145` during this
     audit. The dirty tree still spans 316 tracked files and more than
     14,400 status rows including untracked files. During this audit, shortstat
     moved from `314 files changed, 182621 insertions(+), 27146 deletions(-)`
     to `316 files changed, 184898 insertions(+), 28064 deletions(-)`. That is
     implementation/status churn, not an accepted frozen snapshot.

2. **Critical - no coherent root-harness result can be accepted for the current
   source snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:39`,
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:49` requires periodic repo-wide tests and
     static checks with failures recorded honestly.
   - Evidence: the required gate matched no-argument root PID `2125947` owned
     by `claude` plus a focused Syncthing shard, then later no-argument root
     PID `2159668` owned by `claude` plus another focused Syncthing shard. This
     audit correctly started no duplicate. Any root result from those periods
     is diagnostic only because the dirty snapshot and status files continued
     to change while the harness was running.

3. **Critical - `porting.html` and `porting-summary.json` fail the current
   dashboard contract.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:56`,
     `porting.html:67`, `porting.html:93`, `porting-summary.json:1`.
   - Requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with per-lane benchmark source, upstream denominator, mapped
     tests, PHP pass/fail, WordPress scenarios, phase, audit, current work,
     blocker, and commit.
   - Evidence: the dashboard still advertises average progress `97.7%`,
     generated time `2026-05-23 23:43:54 UTC`, and source snapshot
     `79768df0c427`, while current `HEAD` is `8cd70f0cd145`. The dashboard
     dependency table publishes 22 items while `dependency-backlog.json` has
     23 items.

4. **High - manifest denominator schema is still not mechanically reliable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2453`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`.
   - Requirement at risk: `goal.md:24`, `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real upstream denominators and comparable dashboard
     fields.
   - Evidence: `benchmarkDenominator.total` is still a prose string in five
     manifests. Dolt's total field is not a denominator at all; it contains a
     FIND_IN_SET slice narrative. That blocks durable percentage/count
     generation and keeps status publishers dependent on ad hoc parsing.

5. **High - dashboard, manifest, and lane-status counts disagree across most
   lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:25`, `goal.md:44`, and `goal.md:45`
     require reliable upstream denominators, mapped test counts, PHP pass/fail
     counts, blockers, and current status.
   - Evidence: current manifests/statuses versus the dashboard include
     Difftastic `875 total / 532 mapped / 2830 PHP pass` vs dashboard
     `735 / 374 / 374`; esbuild `359 mapped / 364 pass` vs `311 / 311`;
     Gitoxide `2877 mapped / 6398 pass` vs `2751 / 5634`; libsqlite
     `318 mapped` vs `286`; LightningCSS `2377 mapped / 3372 pass` vs
     `1732 / 2197`; markerPDF source text says `361 counted / 312 mapped`
     while manifest scalars say `360 / 311` and dashboard says `330 / 280`;
     Pandoc `1541 mapped / 322 pass` vs `1061 / 278`; rclone `825 pass` vs
     `698`; Readability `235 pass` vs `204`; and Syncthing `6580 assertions`
     vs dashboard `4579 pass`.

6. **High - near-complete percentages still overstate accepted upstream
   parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/*/lane-status.json`, runner/status fields in
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:35` through `goal.md:40` require real
     denominator parity, fixture parity, edge/error behavior, and honest
     blockers.
   - Evidence: most lanes still display `98-99%` despite pending or explicitly
     unexecuted full Cargo/Go/BATS/Haskell/release-extra/live-provider/model
     parity and no accepted no-argument root result from a frozen snapshot.

7. **High - markerPDF still mixes native progress with external/runtime
   orchestration plans.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:10`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:469`,
     `lanes/markerpdf/lane-status.json:12`.
   - Requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
     wrappers, bridge calls, or shell-outs to upstream binaries as native
     implementation progress.
   - Evidence: markerPDF has real native PDF filter work, but the manifest
     still foregrounds Pandoc/XeLaTeX helper plans, shell lifecycle planning,
     Streamlit/FastAPI/Uvicorn runtime planning, OCR/Tesseract/Ghostscript
     install planning, Poetry/package metadata, and model-runtime graphs.
     These can be preflight/oracle evidence, not native port progress credit.

8. **High - essential optional-library coverage remains backlog-only, not
   accepted support-library ports.**
   - Paths: `dependency-backlog.json:7`, `dependency-backlog.json:112`,
     `dependency-backlog.json:422`, `porting.html:72`,
     `porting-summary.json:252`.
   - Requirement at risk: this audit run requires support libraries to have a
     bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, and
     malformed/corrupt cases where relevant.
   - Evidence: there are 23 candidate/deferred backlog items and zero active
     support-library ports. The only repo-root manifests are the 12 lane
     manifests under `lanes/*/UPSTREAM_TEST_MANIFEST.json`; ZIP, OpenXML,
     legacy DOC/CFB, ODT, EPUB, doctemplates, PDF text/OCR/layout, XML/HTML5,
     WebDAV, Unicode, charset, source maps, protobuf, hashes, SQL/storage
     codecs, archive streams, glob/pathspec, and provider metadata remain
     backlog rows.

9. **High - dependency expansion is happening lane-locally instead of through
   bounded shared gates.**
   - Paths: `lanes/rclone/lane-status.json:5`,
     `lanes/rclone/lane-status.json:8`, `lanes/rclone/lane-status.json:9`,
     `lanes/rclone/lane-status.json:12`, `dependency-backlog.json:7`,
     `dependency-backlog.json:35`, `dependency-backlog.json:422`.
   - Requirement at risk: this audit run requires dependency expansion to be
     bounded, gated, tested, and shared where appropriate.
   - Evidence: rclone now carries lane-local ZIP/file-response, WebDAV
     XML/property/PROPFIND/PROPPATCH/LOCK/middleware, gzip, auth-proxy,
     directory-template, OneDrive, and metadata helpers. These may be valid
     rclone slices, but they cannot count as shared support-library progress
     without dependency-specific denominators, malformed/corrupt cases,
     activation gates, and reusable ownership.

10. **Medium - test-time shell-outs must remain oracle tooling, not native
    progress.**
    - Paths: `lanes/gitoxide/tests/FetchResponseTest.php:16`,
      `lanes/gitoxide/tests/FetchResponseTest.php:18`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:11`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:13`,
      `lanes/gitoxide/tests/GitUrlTest.php:69`,
      `lanes/gitoxide/tests/GitUrlTest.php:70`,
      `tools/generate-dashboard.php:197`.
    - Requirement at risk: `goal.md:1`, `goal.md:30`, and `goal.md:39`
      require native ports and reproducible generated artifacts, with bridge
      code counted only as temporary oracle tooling.
    - Evidence: no lane implementation subprocess shell-out was accepted in
      this audit, but Gitoxide tests invoke `/usr/bin/git` through `proc_open`
      and the dashboard generator uses `shell_exec` for Git metadata. These are
      acceptable only as fixture/oracle and coordination tooling.

## Next Intervention

Keep the hard writer/runner/status freeze as the next gate. Stop active
writers/status publishers and focused/root runners; take two stable polls of
`HEAD`, tracked status count, total status count, shortstat, exact PHP runner
state, capacity/disk state, and relevant log mtimes; accept or reject one
lane-scoped batch; normalize schema/count fields for that batch; run focused
verification plus `git diff --check`; run exactly one serialized no-argument
`php tools/run-tests.php` from that same frozen snapshot if the exact process
gate remains empty; regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from the accepted commit; then commit
or reject.
