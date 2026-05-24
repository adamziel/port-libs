# Independent Audit - 2026-05-24T06:10Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every root `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
current `lanes/*/lane-status.json`, `dependency-backlog.json`, recent Git
history, and process state for the required root-run gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T06:07Z through 2026-05-24T06:10:46Z
HEAD observed: 3ece69cee88a throughout samples
recent commits: 3ece69ce Record integration hold status; 7e33f787 Refresh independent audit status; 6fee1a74 Record integration hold status
branch divergence: main...origin/main [ahead 730, behind 68]
tracked dirty rows: 312 -> 315
default status rows including untracked: 14144 -> 14154
git diff --shortstat: 312 files changed, 179160 insertions(+), 25655 deletions(-) -> 315 files changed, 179466 insertions(+), 25655 deletions(-)
manifest/status JSON validation: jq empty passed for all 12 root lane manifests, lane-status files, porting-summary.json, and dependency-backlog.json
dependency backlog: 23 items, grouped as candidate 13 / deferred 10 and critical 4 / high 8 / medium 11
root run by this audit: not started
```

Required root-run gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' during the first process sample:
1847943 php tools/run-tests.php lanes/syncthing/tests

ps -o pid,user,etime,command -p 1847943:
<process exited before owner sample; ps printed only the header>

pgrep -af '^php tools/run-tests\.php( |$)' during second validation:
<no rows>

pgrep -af '^php tools/run-tests\.php( |$)' during final pre-commit validation:
1936402 php tools/run-tests.php lanes/quadrable/tests

ps -o pid,user,etime,command -p 1936402:
1936402 claude 00:07 php tools/run-tests.php lanes/quadrable/tests
```

I did not start `php tools/run-tests.php`. The exact process gate briefly
matched a focused Syncthing PHP harness, cleared, then matched a focused
Quadrable PHP harness during final pre-commit validation. The checkout was not
stable enough for an audit-owned no-argument root run: tracked status, total
status, and shortstat all changed while `HEAD` stayed fixed, and the worktree
contains a broad multi-lane aggregate. A root harness over this moving tree
would be diagnostic only, not proof for a frozen source snapshot.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:37`, `progress.md:39`, current Git status.
   - Requirement at risk: `goal.md:29`, `goal.md:36`, and `goal.md:48`
     require small reviewable slices, passing verification, and accepted
     commits.
   - Evidence: `HEAD` stayed at `3ece69cee88a`, but tracked dirty rows moved
     `312 -> 315`, total status rows moved `14144 -> 14154`, and shortstat
     moved from `312 files changed, 179160 insertions(+), 25655 deletions(-)`
     to `315 files changed, 179466 insertions(+), 25655 deletions(-)`. The
     dirty scope spans every priority lane plus status/dashboard/support files.

2. **Critical - there is no coherent root-harness result for the current
   source snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:39`,
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:49` requires repo-wide tests/static checks
     with honest failure recording.
   - Evidence: the required process gate first matched focused PID `1847943`
     (`php tools/run-tests.php lanes/syncthing/tests`), which exited before
     owner sampling; a later validation had no PHP harness rows; final
     pre-commit validation matched focused Quadrable PID `1936402` owned by
     `claude`. No no-argument root run was started because the tree changed
     between samples. Any root result now would mix source from different
     moving intervals.

3. **Critical - `porting.html` and `porting-summary.json` are stale and fail
   the dashboard contract.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:75`, `porting-summary.json:1`.
   - Requirement at risk: `goal.md:3` and `goal.md:45` require current
     per-lane benchmark source, denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: the local dashboard still advertises average progress `97.7%`,
     generated time `2026-05-23 23:43:54 UTC`, and source snapshot
     `79768df0c427`, while current `HEAD` is `3ece69cee88a`. The auxiliary
     table still publishes `22` dependency items even though
     `dependency-backlog.json` contains `23`.

4. **High - manifest denominator schema is not mechanically reliable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:24`, `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real upstream denominators and comparable dashboard
     fields.
   - Evidence: `benchmarkDenominator.total` is a prose string in Difftastic,
     Dolt, esbuild, Pandoc, and Quadrable, while other lanes use numbers.
     Dolt's `total` field currently contains the latest FIND_IN_SET slice
     narrative rather than a denominator count, while `mapped` remains `613`.
     This prevents durable percentage/count generation.

5. **High - dashboard, manifest, and lane-status counts disagree across most
   lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:25`, `goal.md:44`, and `goal.md:45`
     require reliable upstream denominators, mapped tests, PHP pass/fail
     counts, blockers, and current status.
   - Evidence: current manifests/statuses versus the dashboard include
     Difftastic `869 total / 522 mapped` vs dashboard `735 / 374`,
     LightningCSS `3532 / 2336` vs `3532 / 1732`, markerPDF `359 / 310 / 447
     PHP behavior tests` vs `330 / 280 / 416`, Pandoc `1525 mapped` vs
     `1061`, rclone `821` PHP behavior tests vs `698`, Readability `234`
     behavior tests vs `204`, and Syncthing lane status `6415` assertions vs
     dashboard `4579 pass`.

6. **High - near-complete progress percentages still overstate accepted
   upstream parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/*/lane-status.json`, and runner-status fields in
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:35` through `goal.md:40` require real
     denominator parity, edge cases, error behavior, and honest blockers.
   - Evidence: most lanes still show `98-99%` in the dashboard/status layer,
     while full Cargo, Go, BATS, Haskell, release-extra, live-provider,
     model/runtime, and no-argument root aggregate parity are pending or
     explicitly unexecuted. Focused green lane checks are useful evidence, but
     they are not accepted full-port parity.

7. **High - markerPDF still mixes native progress with external/runtime
   orchestration plans.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:968`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:977`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:980`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1024`,
     `lanes/markerpdf/lane-status.json`.
   - Requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
     wrappers, bridge calls, or shell-outs to upstream binaries as native
     implementation progress.
   - Evidence: markerPDF has real native PDF stream-filter work, but the
     manifest/status still count or foreground Poetry/package metadata, model
     runtime graphs, benchmark memory/Nougat planning, OCR/Tesseract install
     planning, `chunk_convert.py`/`chunk_convert.sh` lifecycle planning,
     Streamlit, FastAPI/Uvicorn, Pandoc/XeLaTeX, OCRMyPDF, Ghostscript, and
     model-worker boundaries. These must remain preflight/oracle evidence
     unless separated from native port progress.

8. **High - essential optional-library coverage remains backlog-only, not
   accepted support-library ports.**
   - Paths: `dependency-backlog.json:5`, `dependency-backlog.json:111`,
     `porting.html:75`, `porting.html:77`, and the absence of support-library
     `UPSTREAM_TEST_MANIFEST.json` files outside `lanes/*`.
   - Requirement at risk: this audit run requires support libraries to have a
     bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, and
     malformed/corrupt cases where relevant; `goal.md:35` requires meaningful
     fixture and edge-case coverage.
   - Evidence: `dependency-backlog.json` has 23 gated items, including
     `pandoc-doctemplates-core`, but the dashboard still shows 22. ZIP,
     OpenXML, legacy DOC/CFB, ODT, EPUB, doctemplates, PDF text/OCR/layout,
     XML/HTML5/WebDAV, Unicode, charset, source maps, protobuf, hashes,
     SQL/storage codecs, archive streams, glob/pathspec, and provider metadata
     remain candidate/deferred rows without dependency-level manifests or PHP
     pass/fail evidence.

9. **High - dependency expansion is happening lane-locally instead of through
   bounded shared gates.**
   - Paths: `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/lane-status.json:9`,
     `lanes/rclone/src/VfsZipArchive.php`,
     `lanes/rclone/src/VfsWebDavPropfindResponse.php`,
     `lanes/rclone/src/VfsWebDavProppatchXml.php`,
     `lanes/rclone/src/VfsWebDavReadResponse.php`,
     `lanes/rclone/src/OneDrivePermissionPlanner.php`,
     `dependency-backlog.json:25`, `dependency-backlog.json:384`,
     `dependency-backlog.json:423`.
   - Requirement at risk: this audit run requires dependency expansion to be
     bounded, gated, tested, and shared where appropriate.
   - Evidence: rclone now carries lane-local ZIP writing, WebDAV XML/mutation
     parsing, gzip response behavior, URL-decoded WebDAV paths, provider
     metadata normalization, and OneDrive permission planning. These may be
     valid rclone slices, but they should not count as shared support-library
     progress without dependency-specific denominators, malformed/corrupt
     cases, PHP evidence, and activation gates usable by other lanes.

10. **Medium - test-time shell-outs must remain oracle tooling, not native
    progress.**
    - Paths: `lanes/gitoxide/tests/FetchResponseTest.php:15`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:11`,
      `lanes/gitoxide/tests/GitUrlTest.php:70`,
      `tools/generate-dashboard.php:197`.
    - Requirement at risk: `goal.md:1`, `goal.md:30`, and `goal.md:39`
      require native ports and reproducible generated artifacts, with bridge
      code counted only as temporary oracle tooling.
    - Evidence: the shell-out scan found no lane implementation `proc_open`
      or `shell_exec` use, but Gitoxide tests invoke `/usr/bin/git` through
      `proc_open` to read upstream fixtures, and the dashboard generator uses
      `shell_exec` for Git metadata. These are acceptable only as fixture/oracle
      and coordination tooling; they must not be cited as native implementation
      coverage.

11. **Medium - `progress.md` Active Lanes remain stale relative to current
    handoffs.**
    - Paths: `progress.md:85` through `progress.md:104`,
      `lanes/*/lane-status.json`.
    - Requirement at risk: `goal.md:44` requires current owner/session, next
      task per lane, current work, blocker, and percentage estimates.
    - Evidence: the Active Lanes table still lists older handoffs such as
      Gitoxide SSH config options, LightningCSS trig/math, markerPDF benchmark
      file inventory, Readability negative header cleanup, Syncthing system
      log, Difftastic Ada/Apex, rclone VFS Statfs, Dolt query-diff expression,
      and esbuild automatic JSX. Current status files describe later gix-index
      EOIE, relative color, PDF FlateDecode predictors, table-btree delete
      redistribution, NYT story-header retention, Native DOCX diagrams,
      multi-character Quadrable separators, receive-only scan cleanup,
      PHP/Hack class constants, WebDAV URL-decoded paths, FIND_IN_SET, and
      large numeric lexer work.

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
