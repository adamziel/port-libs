# Independent Audit - 2026-05-24T06:52Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every root `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
current `lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, recent Git history, and the required PHP
harness process gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T06:46Z through 2026-05-24T06:52Z
HEAD movement during audit: 53a08b3d -> 11b576e2d6ee -> 9dfbecf7a6e2
recent commits: 9dfbecf7 Record integration hold status; 11b576e2 Record integration hold status; 53a08b3d Refresh independent audit status
branch divergence: main...origin/main [ahead 743, behind 68]
tracked dirty rows: 315 -> 317
default status rows including untracked: 14761 -> 14766 -> 14832
git diff --shortstat: 315 files changed, 187800 insertions(+), 28977 deletions(-) -> 317 files changed, 188342 insertions(+), 29116 deletions(-)
manifest/status JSON validation: jq reads succeeded for all 12 root lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
dependency backlog: 23 items; 13 candidate, 10 deferred; no active support-library port
dashboard snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
root run by this audit: not started
```

Required root-run gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at the start of this audit: no rows
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T06:48:23Z: no rows
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T06:51Z:
2623184 php tools/run-tests.php lanes/quadrable/tests

ps -o pid,user,ppid,etime,stat,command -p 2623184:
2623184 claude 2559365 00:08 Rs php tools/run-tests.php lanes/quadrable/tests

final pgrep -af '^php tools/run-tests\.php( |$)' before commit:
2656122 php tools/run-tests.php
2659334 php tools/run-tests.php lanes/quadrable/tests
2659766 php tools/run-tests.php lanes/readability/tests
2659897 php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
2659917 php tools/run-tests.php lanes/syncthing/tests/ConfigDevicesTest.php ...
2660187 php tools/run-tests.php lanes/syncthing/tests/PullFinisherTest.php ...

ps -o pid,user,ppid,etime,stat,command -p 2656122,2659334,2659766,2659897,2659917,2660187:
2656122 claude 2655897 00:12 R+ php tools/run-tests.php
2659334 claude 2659073 00:08 R+ php tools/run-tests.php lanes/quadrable/tests
2659766 claude 2659588 00:07 R+ php tools/run-tests.php lanes/readability/tests
2659897 claude 2659701 00:07 R+ php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
2659917 claude 2659763 00:07 R+ php tools/run-tests.php lanes/syncthing/tests/ConfigDevicesTest.php ...
2660187 claude 2660042 00:06 R+ php tools/run-tests.php lanes/syncthing/tests/PullFinisherTest.php ...
```

I did not start `php tools/run-tests.php`. The exact process gate was clear in
early samples, but the checkout was not stable enough: `HEAD` moved twice
during the audit and untracked-inclusive status rows changed after the latest
integration holds. A later gate matched a focused Quadrable PHP harness, and
the final gate matched active no-argument root PID `2656122` owned by `claude`
plus focused Quadrable/Readability/Syncthing shards. A root run from this source
state would be another moving-tree diagnostic, not acceptance evidence.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:37`, `progress.md:39`,
     `audits/integration-status.md:16` through
     `audits/integration-status.md:35`, recent Git history.
   - Requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable slices, verification before integration, and
     honest repo-wide checks.
   - Evidence: `HEAD` advanced from the audit commit `53a08b3d` through
     `11b576e2d6ee` to `9dfbecf7a6e2` while this audit was running. The latest
     integration holds sampled `HEAD` `2f96174ce2a2` and then `11b576e2d6ee`
     with moving status rows; this audit saw `14761 -> 14766 -> 14832`
     default status rows and shortstat move to `317 files changed, 188342
     insertions(+), 29116 deletions(-)`. The branch is now
     `main...origin/main [ahead 743, behind 68]`.

2. **Critical - no coherent no-argument root-harness result can be accepted for
   this snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:39`,
     `audits/integration-status.md:30` through
     `audits/integration-status.md:35`.
   - Requirement at risk: `goal.md:49` requires periodic repo-wide tests and
     static checks with failures recorded honestly.
   - Evidence: the preceding integration hold recorded active no-argument root
     PID `2488219` plus focused lane runners. Later samples were briefly clear,
     but only after `HEAD` and status rows had moved; later process gates
     matched focused Quadrable PID `2623184` and final active no-argument root
     PID `2656122`, both owned by `claude`, plus focused lane shards. That does
     not make the dirty aggregate stable enough for an acceptance root run.

3. **Critical - `porting.html` and `porting-summary.json` fail the current
   dashboard contract.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:56` through `porting.html:67`, `porting.html:75`,
     `porting-summary.json:2` through `porting-summary.json:8`.
   - Requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with per-lane denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still advertises average progress `97.7%`,
     generated time `2026-05-23 23:43:54 UTC`, and source snapshot
     `79768df0c427`, while current `HEAD` is `9dfbecf7a6e2`. The dashboard
     dependency section still shows 22 items, but `dependency-backlog.json`
     has 23.

4. **High - dashboard, manifest, and lane-status counts disagree across every
   active lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:10` through `porting-summary.json:120`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:25`, `goal.md:44`, and `goal.md:45`
     require reliable upstream denominators, mapped test counts, PHP
     pass/fail counts, blockers, and current status.
   - Evidence: current lane statuses versus dashboard include Difftastic
     `875 total / 550 mapped / 2861 pass` vs dashboard `735 / 374 / 374`;
     Dolt `397 pass` vs `356`; esbuild `372 mapped/pass` vs `311`;
     Gitoxide `6435 pass` vs `5634`; libsqlite `322 mapped / 321 pass` vs
     `286 / 286`; LightningCSS `2461 mapped / 3456 pass` vs `1732 / 2197`;
     markerPDF `363 total / 314 mapped / 451 pass` vs `330 / 280 / 416`;
     Pandoc `1584 mapped / 325 pass` vs `1061 / 278`; Quadrable `212 pass`
     vs `190`; rclone `829` vs `698`; Readability `236` vs `204`; and
     Syncthing `6700` vs `4579`.

5. **High - manifest denominator schema remains non-normalized and one
   manifest now overwrites the denominator with latest-slice prose.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2455` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2456`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`.
   - Requirement at risk: `goal.md:24`, `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real upstream denominators and comparable dashboard
     fields.
   - Evidence: four manifests still use prose strings for
     `benchmarkDenominator.total`. Dolt is worse than a prose count: line
     2456 stores the latest FIND_IN_SET slice narrative in `total`, while the
     manifest warning on line 2455 still says 396 PHP cases and the current
     lane status says 397. The canonical denominator field is therefore not
     machine-safe.

6. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:33` through `goal.md:40` require real
     denominator parity, fixture parity, edge/error behavior, and honest
     blockers.
   - Evidence: most lanes report `98-99%`, but lane statuses still say root
     aggregate verification is pending for the supervisor/integrator, full
     Cargo/Go/BATS/Haskell/upstream parity is unexecuted or bounded, and many
     batches are `pending`, `not committed`, or `uncommitted` in the shared
     dirty worktree.

7. **High - essential optional-library coverage remains backlog-only, not
   accepted support-library ports.**
   - Paths: `dependency-backlog.json:3` through
     `dependency-backlog.json:4`, `dependency-backlog.json:7` through
     `dependency-backlog.json:23`, `dependency-backlog.json:25` through
     `dependency-backlog.json:43`, `dependency-backlog.json:111` through
     `dependency-backlog.json:124`, `porting.html:75`.
   - Requirement at risk: this audit run requires support libraries to have a
     bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, and
     malformed/corrupt cases where relevant.
   - Evidence: the backlog has 23 candidate/deferred items and no active
     support-library port. Rich-function gaps remain for ZIP/package, XML/HTML5,
     DOCX/OpenXML, legacy DOC/CFB, EPUB, ODT, doctemplates, citations/CSL,
     math/TeX, PDF text, PDF render planning, OCR/layout, table geometry,
     Unicode, charset, source maps, protobuf, checksum/hash, storage codecs,
     archive streams, glob/pathspec, and provider metadata.

8. **High - rclone dependency expansion is lane-local and should not be
   credited as shared optional-library progress.**
   - Paths: `lanes/rclone/lane-status.json:5`,
     `lanes/rclone/lane-status.json:9` through
     `lanes/rclone/lane-status.json:12`, `dependency-backlog.json:25` through
     `dependency-backlog.json:40`.
   - Requirement at risk: this audit run requires dependency expansion to be
     bounded, gated, tested, and shared where appropriate.
   - Evidence: rclone carries lane-local WebDAV XML/PROPFIND/PROPPATCH/LOCK,
     gzip, auth-proxy, custom directory-template, URL decoding, provider
     metadata, VFS, response, and reader helpers. These can be valid rclone
     slices, but they cannot count as support-library progress without a
     dependency-specific denominator, malformed/corrupt cases, activation gate,
     and reusable ownership.

9. **High - markerPDF still mixes native progress with external/runtime
   orchestration plans.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:10`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:473`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:502` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:504`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:616` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:617`.
   - Requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
     wrappers, bridge calls, or shell-outs to upstream binaries as native
     implementation progress.
   - Evidence: markerPDF has real native PDF stream/filter/text work, but the
     manifest still foregrounds pdftext/pypdfium/Surya model stacks,
     Tesseract/Ghostscript/Pandoc/XeLaTeX/Poetry/Streamlit/FastAPI/Uvicorn
     plans, chunk-convert shell lifecycle plans, server startup/lifespan model
     slots, and helper-script plans. Those are only preflight/oracle evidence
     unless bounded native components own the behavior directly.

10. **Medium - Readability's full-fixture claim still hides known parity gaps.**
    - Paths: `lanes/readability/lane-status.json:5`,
      `lanes/readability/lane-status.json:10`,
      `lanes/readability/lane-status.json:12`, `porting.html:66`.
    - Requirement at risk: `goal.md:35` and `goal.md:37` require meaningful
      fixture parity and upstream tests as source of truth.
    - Evidence: current lane status records three remaining copied-fixture
      normalized text mismatches: `firefox-nightly-blog`, `wikipedia-2`, and
      `wikipedia-3`. The dashboard still presents `1984 / 1984` mapped and a
      `99.0%` row without surfacing that parity gap.

11. **Medium - test-time shell-outs must remain oracle tooling, not native
    progress.**
    - Paths: `lanes/gitoxide/tests/FetchResponseTest.php:16`,
      `lanes/gitoxide/tests/FetchResponseTest.php:18`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:11`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:13`,
      `lanes/gitoxide/tests/GitUrlTest.php:69`,
      `lanes/gitoxide/tests/GitUrlTest.php:70`,
      `lanes/gitoxide/tests/GitUrlTest.php:102`,
      `lanes/gitoxide/tests/GitUrlTest.php:104`,
      `tools/generate-dashboard.php:197`.
    - Requirement at risk: `goal.md:1`, `goal.md:30`, and `goal.md:39`
      require native ports and reproducible generated artifacts, with bridge
      code counted only as temporary oracle tooling.
    - Evidence: Gitoxide tests invoke `/usr/bin/git` through process helpers,
      and the dashboard generator uses Git shell metadata. That can be
      acceptable as explicit fixture/oracle or coordination tooling only; it
      must not become implementation progress.

## Next Intervention

Keep the hard writer/runner/status freeze as the next gate. Stop or wait out
active writers/status publishers and PHP shards; take two stable polls of
`HEAD`, tracked status count, total status count, shortstat, exact PHP runner
state, capacity/disk state, Dolt runner state, dashboard publication state,
and relevant log mtimes; accept or reject one lane-scoped batch; normalize
schema/count fields for that batch; run focused verification plus
`git diff --check`; run exactly one serialized no-argument
`php tools/run-tests.php` from that same frozen snapshot if the exact process
gate remains empty; regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from the accepted commit; then commit
or reject.
