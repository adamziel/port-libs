# Independent Audit - 2026-05-24T06:02Z

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
UTC samples: 2026-05-24T06:01:14Z and 2026-05-24T06:01:52Z
final pre-commit root-gate validation: 2026-05-24T06:03Z matched active no-argument root PID 1794173 owned by claude
HEAD observed: 9a216b17c59b both samples
recent commits: 9a216b17 Refresh independent audit status; 8dd38502 Record integration hold status; 6e60fb9a Record integration hold status
branch divergence: main...origin/main [ahead 727, behind 68]
tracked dirty rows: 312 -> 313
default status rows including untracked: 14015 -> 14017
git diff --shortstat: 312 files changed, 178559 insertions(+), 25638 deletions(-) -> 313 files changed, 178718 insertions(+), 25638 deletions(-)
manifest/status JSON validation: jq empty passed for all 12 root lane manifests, lane-status files, porting-summary.json, and dependency-backlog.json
tmux sessions observed: 29, including all primary port lanes plus auditor, integrator, dashboard updater, evaluator, watchdog, capacity executor/controller, and support-library scout/gate sessions
root run by this audit: not started
```

Required root-run gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T06:01:14Z:
<no rows>

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T06:01:52Z:
<no rows>

pgrep -af '^php tools/run-tests\.php( |$)' during final validation:
1794173 php tools/run-tests.php

ps -o pid,user,etime,args -p 1794173:
1794173 claude 01:17 php tools/run-tests.php
```

I did not start `php tools/run-tests.php`. The exact process gate was clear, but
the checkout was not stable enough for an audit-owned acceptance run: tracked
status, total status, and shortstat all changed between samples, the worktree
contains a 313-file tracked aggregate plus thousands of untracked artifacts, and
active lane/status/capacity sessions were visible. A later final validation also
matched active no-argument root PID `1794173` owned by `claude`, so starting a
duplicate would have violated the root-run gate. A root harness over this moving
aggregate would be another diagnostic run, not proof for a frozen source
snapshot.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:39`, `lanes/*/lane-status.json:13`, current Git
     status.
   - Requirement at risk: `goal.md:29`, `goal.md:36`, and `goal.md:48` require
     small reviewable slices, passing verification, and accepted commits.
   - Evidence: the audit samples held `HEAD` at `9a216b17c59b`, but tracked
     dirty rows moved `312 -> 313`, total status rows moved `14015 -> 14017`,
     and shortstat moved to `313 files changed, 178718 insertions(+), 25638
     deletions(-)`. Current lane statuses still mark nearly every lane
     `pending`, `not committed`, or `uncommitted ... because root verification
     was not assigned`.

2. **Critical - there is no coherent root-harness result for the current
   source snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:39`, `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:49` requires periodic repo-wide tests/static
     checks with honest failure recording.
   - Evidence: the required exact gate returned no rows twice, but the source
     snapshot changed between samples and active lane/status loops were present;
     final validation then matched active no-argument root PID `1794173` owned
     by `claude`. Current lane statuses consistently leave no-argument root
     verification to the supervisor/integrator. Running another instance now
     would not prove one stable tree.

3. **Critical - the local dashboard is stale and violates the dashboard
   contract.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting-summary.json:1`, `porting-summary.json:2`,
     `porting-summary.json:8`.
   - Requirement at risk: `goal.md:3` and `goal.md:45` require current per-lane
     benchmark source, denominator, mapped tests, PHP pass/fail, audit, work,
     blocker, and commit in `porting.html`.
   - Evidence: the dashboard still publishes generated time `2026-05-23
     23:43:54 UTC`, source snapshot `79768df0c427`, and average progress
     `97.7%`, while current `HEAD` is `9a216b17c59b` and manifests/statuses
     have moved substantially.

4. **High - dashboard, manifest, and lane-status counts disagree across most
   lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:10` through `porting-summary.json:214`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, and `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:25`, `goal.md:44`, and `goal.md:45` require
     reliable upstream denominators, mapped tests, PHP pass/fail counts, and
     current status.
   - Evidence: current manifests/statuses versus dashboard include Difftastic
     `862 total / 515 mapped / 2803 pass` vs dashboard `735 / 374 / 374`;
     esbuild `357 pass` vs `311`; Gitoxide `2877 mapped / 6377 pass` vs
     `2751 / 5634`; libsqlite `315 pass` vs `286`; LightningCSS `2268 mapped /
     2909 pass` vs `1732 / 2197`; markerPDF `359 total / 310 mapped / 447
     pass` vs `330 / 280 / 416`; Pandoc `1512 mapped / 320 pass` vs `1061 /
     278`; Quadrable `209 pass` vs `190`; rclone `821 pass` vs `698`;
     Readability `234 pass` vs `204`; and Syncthing `6415 pass` vs `4579`.

5. **High - manifest/status schemas still block mechanical acceptance.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2446`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`, and
     `lanes/*/lane-status.json:13`.
   - Requirement at risk: `goal.md:24`, `goal.md:25`, `goal.md:31`, and
     `goal.md:44` require comparable source/version/test metadata, blockers,
     and latest commit per lane.
   - Evidence: `benchmarkDenominator.total` is numeric in some manifests and a
     long prose string in others; `mapped` mixes behavior units, files,
     assertions, and static inventory units; `latestCommit` fields contain
     prose such as `pending in shared dirty worktree`, `not committed`, and
     `uncommitted lane batch`.

6. **High - near-complete percentages still overstate accepted upstream
   parity.**
   - Paths: `lanes/*/lane-status.json:4`, `porting.html:56` through
     `porting.html:67`, and manifest warning fields such as
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:903`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:313`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:129`, and
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1233`.
   - Requirement at risk: `goal.md:35` through `goal.md:40` require real
     denominator parity, edge cases, error behavior, and honest blockers.
   - Evidence: most lanes report `98-99%`, while full Cargo, Go, BATS, Haskell,
     release-extra, live-provider, model/runtime, and root aggregate parity are
     pending or explicitly unexecuted. Focused green lane checks are useful, but
     they are not accepted full-port parity.

7. **High - markerPDF still mixes native progress with external/runtime
   orchestration plans.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:749`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:750`, and
     `lanes/markerpdf/lane-status.json:12`.
   - Requirement at risk: `goal.md:1` and `goal.md:30` forbid counting wrappers
     or shell-outs as native implementation progress.
   - Evidence: markerPDF has real native PDF stream-filter work, but the
     manifest/status still counts Poetry/package metadata, model runtime graphs,
     benchmark memory/Nougat planning, OCR/Tesseract install planning,
     `chunk_convert.py`/`chunk_convert.sh` lifecycle planning, Streamlit,
     FastAPI/Uvicorn, Pandoc/XeLaTeX, OCRMyPDF, Ghostscript, and model-worker
     boundaries. These should remain preflight/oracle evidence unless separated
     from native port progress.

8. **High - essential optional-library coverage remains backlog-only, not
   manifest-backed support ports.**
   - Paths: `dependency-backlog.json:3`, `dependency-backlog.json:5`,
     `dependency-backlog.json:111`, `porting.html:75`,
     `porting.html:77`, and the absence of support-library
     `UPSTREAM_TEST_MANIFEST.json` files outside `lanes/*`.
   - Requirement at risk: this audit run requires support libraries to have a
     bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, and
     malformed/corrupt cases where relevant; `goal.md:35` requires meaningful
     fixture and edge-case coverage.
   - Evidence: `dependency-backlog.json` now has 23 items, including
     `pandoc-doctemplates-core`; `porting.html` and `porting-summary.json` still
     publish 22 items. ZIP/OpenXML/ODT/DOC, doctemplates, PDF text/OCR/layout,
     XML/HTML5/WebDAV, Unicode, charset, source maps, protobuf, hashes,
     SQL/storage codecs, archive streams, glob/pathspec, and provider metadata
     remain candidates/deferred rows without dependency-level manifests or PHP
     pass/fail evidence.

9. **High - dependency expansion is happening lane-locally instead of through
   bounded shared gates.**
   - Paths: `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:56`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1250`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1298`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1319`,
     `lanes/rclone/src/VfsVirtualTree.php:481`, and
     `lanes/rclone/tests/VfsWebDavMutationResponseTest.php:333`.
   - Requirement at risk: this audit run requires dependency expansion to be
     bounded, gated, tested, and shared where appropriate.
   - Evidence: rclone now carries lane-local ZIP writing, WebDAV XML/mutation
     behavior, URL-decoded WebDAV paths, provider metadata normalization, and
     OneDrive permission planning. These may be valid rclone slices, but they
     should not count as shared support-library progress without
     dependency-specific denominators, malformed/corrupt cases, PHP evidence,
     and activation gates usable by other lanes.

10. **Medium - `progress.md` Active Lanes are stale relative to current
    handoffs.**
    - Paths: `progress.md:90` through `progress.md:101` and
      `lanes/*/lane-status.json:11`.
    - Requirement at risk: `goal.md:44` requires current owner/session, next
      task per lane, current work, blocker, and percentage estimates.
    - Evidence: the table still lists older handoffs such as Gitoxide SSH
      config options, LightningCSS trig/math, markerPDF benchmark file
      inventory, Readability negative header cleanup, Syncthing system log,
      Difftastic Ada/Apex, rclone VFS Statfs, Dolt query-diff expression, and
      esbuild automatic JSX. Current status files describe later gix-index
      EOIE, non-SRGB relative color, PDF FlateDecode predictors, table-btree
      delete redistribution, ARS compact headers, Native DOCX diagrams,
      transactional command-stdin, receive-only scan cleanup, PHP/Hack
      namespace/use/const declarations, WebDAV URL-decoded paths, CONCAT_WS,
      and question-dot numeric lookahead work.

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
