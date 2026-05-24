# Independent Audit - 2026-05-24T06:18Z

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
UTC samples: 2026-05-24T06:17:31Z through 2026-05-24T06:18:34Z
HEAD movement during audit: 69d63fad919e observed at start -> 255d649cd1c3
recent commits: 255d649c Record integration hold status; 69d63fad Refresh independent audit status; 71accc98 Record integration hold status
branch divergence: main...origin/main [ahead 733, behind 68]
tracked dirty rows: 314
default status rows including untracked: 14275 -> 14292
git diff --shortstat: 314 files changed, 181892 insertions(+), 27080 deletions(-) -> 314 files changed, 182155 insertions(+), 27145 deletions(-)
manifest JSON validation: jq empty passed for all 12 root lane manifests
dependency backlog: 23 items, grouped as candidate 13 / deferred 10 and critical 4 / high 8 / medium 11
root run by this audit: not started
```

Required root-run gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T06:17Z:
1977761 php tools/run-tests.php

ps -o pid,user,ppid,etime,stat,command -p 1977761:
1977761 claude 1977715 01:35 R+ php tools/run-tests.php

later pgrep at 2026-05-24T06:18Z:
2047592 php tools/run-tests.php lanes/syncthing/tests

ps -o pid,user,ppid,etime,stat,command -p 2047592:
2047592 claude 1927932 00:08 Rs php tools/run-tests.php lanes/syncthing/tests
```

I did not start `php tools/run-tests.php`. The exact process gate matched an
active no-argument root harness owned by `claude`, then later a focused
Syncthing harness. The checkout was also moving: `HEAD`, status rows,
shortstat, and lane-status artifacts changed during review.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:37`, `progress.md:39`, current Git status.
   - Requirement at risk: `goal.md:29`, `goal.md:36`, and `goal.md:48`
     require small reviewable slices, passing verification, and accepted
     commits.
   - Evidence: `HEAD` moved from `69d63fad919e` to `255d649cd1c3` during this
     audit. The dirty tree still spans 314 tracked files and over 14k
     untracked-inclusive status rows. Shortstat changed from `181892`
     insertions / `27080` deletions to `182155` insertions / `27145`
     deletions without an accepted freeze.

2. **Critical - there is no coherent root-harness result for the current
   source snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:39`,
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:49` requires repo-wide tests/static checks
     with honest failure recording.
   - Evidence: the required gate matched no-argument root PID `1977761`
     owned by `claude`, then later focused Syncthing PID `2047592` owned by
     `claude`. This audit started no duplicate. Any external root result from
     that interval is diagnostic only because the tree moved and lane-status
     files changed while it was running.

3. **Critical - `porting.html` and `porting-summary.json` are stale and fail
   the dashboard contract.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:75`, `porting-summary.json:8`,
     `porting-summary.json:218`.
   - Requirement at risk: `goal.md:3` and `goal.md:45` require current
     per-lane benchmark source, denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still advertises average progress `97.7%`,
     generated time `2026-05-23 23:43:54 UTC`, and source snapshot
     `79768df0c427`, while current `HEAD` is `255d649cd1c3`. The dashboard
     dependency table still publishes 22 items while
     `dependency-backlog.json` has 23.

4. **Critical - coordination files are being rewritten during audit sampling.**
   - Paths: `lanes/libsqlite/lane-status.json`, `progress.md:86`,
     `progress.md:92`.
   - Requirement at risk: `goal.md:44` and `goal.md:45` require current,
     browsable status for every lane.
   - Evidence: `lanes/libsqlite/lane-status.json` briefly appeared as deleted
     (`D`) and absent on disk during the audit, then reappeared as modified
     with mtime `2026-05-24 06:17:56 UTC`. That is not a stable dashboard
     source. The Active Lanes table also still names stale handoffs such as
     Gitoxide SSH config options, LightningCSS trig/math, markerPDF benchmark
     file inventory, and esbuild automatic JSX while lane statuses now describe
     later work.

5. **High - manifest denominator schema is still not mechanically reliable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2449`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`.
   - Requirement at risk: `goal.md:24`, `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real upstream denominators and comparable dashboard
     fields.
   - Evidence: `benchmarkDenominator.total` is still a prose string in five
     manifests. Dolt's total field now contains a FIND_IN_SET slice narrative
     rather than a denominator count, while `mapped` remains numeric. This
     prevents durable percentage/count generation.

6. **High - dashboard, manifest, and lane-status counts disagree across most
   lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:25`, `goal.md:44`, and `goal.md:45`
     require reliable upstream denominators, mapped tests, PHP pass/fail
     counts, blockers, and current status.
   - Evidence: current manifests/statuses versus the dashboard include
     Difftastic `875 total / 532 mapped` vs dashboard `735 / 374`, esbuild
     `359 mapped` vs `311`, libsqlite `318 mapped` vs `286`, LightningCSS
     `2336 mapped` vs `1732`, markerPDF `360 / 311 / 448 PHP behavior tests`
     vs `330 / 280 / 416`, Pandoc `1541 mapped` vs `1061`, rclone `823`
     behavior tests vs `698`, Readability `235` PHP tests vs `204`, and
     Syncthing `6511` lane assertions vs dashboard `4579 pass`.

7. **High - near-complete progress percentages still overstate accepted
   upstream parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/*/lane-status.json`, runner-status fields in
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:35` through `goal.md:40` require real
     denominator parity, edge cases, error behavior, and honest blockers.
   - Evidence: most lanes still show `98-99%` in the dashboard/status layer
     while full Cargo, Go, BATS, Haskell, release-extra, live-provider,
     model/runtime, and accepted no-argument root parity remain pending or
     explicitly unexecuted.

8. **High - markerPDF still mixes native progress with external/runtime
   orchestration plans.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:467`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:971`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:981`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:987`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:992`,
     `lanes/markerpdf/lane-status.json:12`.
   - Requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
     wrappers, bridge calls, or shell-outs to upstream binaries as native
     implementation progress.
   - Evidence: markerPDF has real native PDF filter work, but the manifest
     still foregrounds Pandoc/XeLaTeX helper plans, shell lifecycle planning,
     Streamlit/FastAPI/Uvicorn/runtime planning, OCR/Tesseract/Ghostscript
     install planning, Poetry/package metadata, and model-runtime graphs.
     These must remain preflight/oracle evidence, not port-progress credit.

9. **High - essential optional-library coverage remains backlog-only, not
   accepted support-library ports.**
   - Paths: `dependency-backlog.json:5`, `dependency-backlog.json:7`,
     `dependency-backlog.json:422`, `porting.html:75`,
     `porting-summary.json:218`.
   - Requirement at risk: this audit run requires support libraries to have a
     bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, and
     malformed/corrupt cases where relevant.
   - Evidence: there are 23 candidate/deferred backlog items, but no
     support-library `UPSTREAM_TEST_MANIFEST.json` files outside `lanes/*`.
     ZIP, OpenXML, legacy DOC/CFB, ODT, EPUB, doctemplates, PDF text/OCR/layout,
     XML/HTML5/WebDAV, Unicode, charset, source maps, protobuf, hashes,
     SQL/storage codecs, archive streams, glob/pathspec, and provider metadata
     remain backlog rows rather than accepted support ports.

10. **High - dependency expansion is happening lane-locally instead of through
    bounded shared gates.**
    - Paths: `lanes/rclone/lane-status.json:5`,
      `lanes/rclone/lane-status.json:9`,
      `lanes/rclone/src/VfsZipArchive.php`,
      `lanes/rclone/src/VfsServeZipResponse.php`,
      `lanes/rclone/src/VfsWebDavProppatchXml.php`,
      `lanes/rclone/src/VfsWebDavLockSystem.php`,
      `lanes/rclone/src/VfsWebDavServeMiddleware.php`,
      `lanes/rclone/src/OneDrivePermissionPlanner.php`,
      `lanes/rclone/src/MetadataMapper.php`,
      `dependency-backlog.json:7`, `dependency-backlog.json:25`,
      `dependency-backlog.json:402`.
    - Requirement at risk: this audit run requires dependency expansion to be
      bounded, gated, tested, and shared where appropriate.
    - Evidence: rclone carries lane-local ZIP, WebDAV XML/lock/middleware,
      gzip, auth-proxy, directory-template, OneDrive, and metadata helpers.
      These may be valid rclone slices, but they should not count as shared
      support-library progress without dependency-specific denominators,
      malformed/corrupt cases, PHP evidence, and activation gates.

11. **Medium - test-time shell-outs must remain oracle tooling, not native
    progress.**
    - Paths: `lanes/gitoxide/tests/FetchResponseTest.php:15`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:11`,
      `lanes/gitoxide/tests/GitUrlTest.php:70`,
      `tools/generate-dashboard.php:197`.
    - Requirement at risk: `goal.md:1`, `goal.md:30`, and `goal.md:39`
      require native ports and reproducible generated artifacts, with bridge
      code counted only as temporary oracle tooling.
    - Evidence: no lane implementation subprocess shell-out was accepted in
      this audit, but Gitoxide tests invoke `/usr/bin/git` through `proc_open`
      and the dashboard generator uses `shell_exec` for Git metadata. These
      are acceptable only as fixture/oracle and coordination tooling.

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
