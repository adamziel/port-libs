# Independent Audit - 2026-05-24T01:37Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, and recent Git history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, plan-only wrappers,
and shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every lane-status file,
`porting-summary.json`, and `dependency-backlog.json`.

## Current Snapshot

```text
HEAD: fb4c11c0892d
HEAD movement during audit: 21621c4cd5c3 -> 0512013da2bd -> fb4c11c0892d via integration-hold status commits
latest visible commits: fb4c11c0 Record integration hold status; 0512013d Record integration hold status; 21621c4c Refresh independent audit status
recent history: latest sampled commits are audit/status/integration-hold commits, not accepted lane feature commits
branch sample: main...origin/main [ahead 637, behind 68]
tracked dirty rows: 296
total status rows including untracked: 11103
git diff --shortstat: 296 files changed, 140677 insertions(+), 16826 deletions(-)
tmux sessions: 174
root run by this audit: not started; latest integration hold saw an active no-argument root PID, and final audit-owned guard matched an active focused PHP harness
```

## Findings

1. **Critical - the current worktree is still not an acceptable aggregate
   verification or lane-acceptance target.**
   - Paths: `progress.md:39`, `progress.md:52` through `progress.md:71`,
     `audits/integration-status.md:3` through
     `audits/integration-status.md:72`, `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`,
     `goal.md:48`, and `goal.md:49` require small committed slices,
     meaningful verification, cleanup, and honest repo-wide test/static-check
     records.
   - Evidence: `HEAD` moved again while this audit was reading the tree,
     from `21621c4cd5c3` through `0512013da2bd` to `fb4c11c0892d`. The latest
     integration-hold entry explicitly says no lane output was integrated, no
     dashboard/progress artifacts were regenerated or accepted, and a sampled
     no-argument root PID was already active during that pass. Current status
     still shows 296 tracked dirty rows, 11,103 total rows including untracked
     files, a 296-file diff, and 174 tmux sessions. A root run here would
     measure a moving worker queue, not one accepted snapshot.

2. **Critical - `porting.html` and `porting-summary.json` are stale and
   contradict current manifests/status files.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`,
     `porting.html:75` through `porting.html:77`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `porting-summary.json:215` through `porting-summary.json:227`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     denominator, mapped-test, PHP pass/fail, WordPress scenario, phase, audit,
     current-work, blocker, and commit data in the dashboard.
   - Evidence: the dashboard still advertises generated time
     `2026-05-23 23:43:54 UTC` and snapshot `main 79768df0c427`, while
     current `HEAD` is `fb4c11c0892d`. Current manifests have moved beyond the
     dashboard: Difftastic is now 398 mapped over a 774-artifact inventory, not
     374/735; esbuild is 324 mapped, not 311; Gitoxide is 2810/2877, not
     2751/2877; markerPDF is 290/340, not 280/330; Pandoc is 1162/2276, not
     1061/2276; rclone is 733/1601, not 698/1601. The dashboard dependency
     table says 22 items and 12 candidates, while `dependency-backlog.json`
     has 23 items and 13 candidates.

3. **High - `progress.md` active-lane handoff labels are stale relative to
   current lane-status files.**
   - Paths: `progress.md:58` through `progress.md:69`,
     `lanes/gitoxide/lane-status.json:11`,
     `lanes/markerpdf/lane-status.json:11`,
     `lanes/syncthing/lane-status.json:11`,
     `lanes/rclone/lane-status.json:11`,
     `lanes/esbuild/lane-status.json:11`.
   - Goal requirement at risk: `goal.md:44` requires `progress.md` to show
     active lanes, current owner/session, next task, open blockers, and current
     status.
   - Evidence: `progress.md` still lists Gitoxide "SSH config-options",
     markerPDF "benchmark file-inventory", Syncthing "system log", rclone
     "VFS Statfs/usage", and esbuild "automatic JSX key/spread" handoffs.
     Current lane-status files instead describe Gitoxide gix-ignore,
     markerPDF Markdown transition pnum metadata, Syncthing noauth health,
     rclone VFS CreateZip, and esbuild named decorated class-expression work.
     The human coordination file is not a reliable current-work source.

4. **High - every primary lane still reports a pending or uncommitted
   handoff, not an accepted committed slice.**
   - Paths: `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:48` require small,
     reviewable committed slices after tests and cleanup.
   - Evidence: current lane-status handoffs explicitly defer commit selection
     to the supervisor/integrator or root verification. The dirty tree includes
     source, test, fixture, example, note, manifest, and status edits across all
     lanes. Focused green lane results are useful evidence, but they are not
     accepted progress until integrated from a frozen snapshot.

5. **High - markerPDF is still over-crediting plan-only external/runtime
   orchestration as native mapped progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:424` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:425`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:903` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:925`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:9`,
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:9`,
     `goal.md:24` through `goal.md:31`, `goal.md:35`, and `goal.md:40`
     require a native PDF-to-structured-content port, no wrapper/shell-out
     progress credit, precise blockers, and explicit marking of hard unported
     features.
   - Evidence: the 290/340 mapped count still includes Pandoc/XeLaTeX helper
     planning, benchmark/archive workflow planning, `chunk_convert.py`
     shell-command/subprocess planning, `chunk_convert.sh` lifecycle planning,
     `run_marker_app.py` Streamlit planning, `marker_server.py` FastAPI/Uvicorn
     planning, Poetry/package/runtime dependency planning, OCR/Tesseract/
     Ghostscript installer plans, and model-stack preflight. Those are useful
     preflight/oracle metadata, but they are not native PDF extraction or
     conversion progress.

6. **High - essential optional-library coverage is backlog-only, and a ZIP
   support slice is already being duplicated inside a lane instead of being
   governed as a shared support library.**
   - Paths: `progress.md:17` through `progress.md:24`,
     `dependency-backlog.json:5` through `dependency-backlog.json:23`,
     `dependency-backlog.json:383` through `dependency-backlog.json:396`,
     `porting.html:71` through `porting.html:114`,
     `lanes/rclone/src/VfsZipArchive.php:8`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1238`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1244`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:12`,
     `goal.md:24` through `goal.md:31`, `goal.md:35`, and `goal.md:40`
     require rich native behavior, bounded dependency ports, upstream/spec
     denominators, mapped fixtures, malformed/corrupt cases where relevant, and
     no shell-out progress credit.
   - Evidence: `dependency-backlog.json` has 23 gated items and zero active
     implementations. There are no support-library manifests with activation
     owners, dependency-specific denominators, fixture maps, PHP pass/fail
     evidence, malformed/corrupt coverage, or root verification. Rich gaps
     remain for ZIP/package containers, XML/HTML5, DOCX/OpenXML, legacy CFB
     `.doc`, EPUB/ODT, doctemplates, CSL/citations, math/TeX, PDF text/render/
     OCR/layout/table geometry, source maps, protobuf/BEP wire behavior,
     Unicode/charset repair, checksums, archive streams, glob/pathspec, SQL/
     storage codecs, and provider metadata normalization. Rclone's new native
     `VfsZipArchive` may be valid lane-local evidence, but it should not count
     as the shared `shared-zip-package-core` dependency unless it receives the
     same support-library manifest, gate, denominator, corrupt-case, and root
     evidence required of lanes.

7. **High - near-complete progress percentages overstate accepted native
   upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through
     `porting.html:67`, `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`
     through `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:841`,
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:40` require real upstream
     denominators, upstream tests as source of truth, explicit slices, and
     honest blockers.
   - Evidence: the dashboard advertises 97.7% average and 92-99% lane progress
     while major lanes still lack accepted root proof or full upstream parity.
     Difftastic has no full Cargo runner, Gitoxide has no full workspace Cargo
     runner, Pandoc has no full Haskell Tasty runner, Syncthing has no full
     `go test ./...`, markerPDF has no full benchmark/model/PDF runner,
     rclone excludes provider/mount/live-service parity, esbuild excludes
     release-extra `make test-all`, and libsqlite excludes full all/release
     SQLite permutations. Readability maps 1984/1984 upstream checks but only
     about 214-215 PHP behavior tests depending on the current handoff; that is
     useful coverage, not complete native parity.

8. **Medium - manifest/status schemas remain non-normalized and hard to
   compare across lanes.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:29`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require comparable denominator, mapped-test, and PHP
     pass/fail fields.
   - Evidence: `benchmarkDenominator.total` is numeric in some lanes and a
     narrative string in Difftastic, Pandoc, Quadrable, and Dolt. Dolt's
     `mapped` is 613 executable upstream files while native status describes
     370 behavior tests and 372 PASS cases. PHP pass values mix behavior-test
     counts, PASS cases, and assertion counts. The compact dashboard collapses
     these into strings, so readers cannot compute accepted parity or compare
     lanes safely.

9. **Medium - blocker fields still lead with local-green wording while real
   acceptance blockers remain unresolved.**
   - Paths: `lanes/dolt/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`, and equivalent blocker fields in
     the other lane statuses.
   - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
     blockers and explicit marking of hard or unported features.
   - Evidence: blockers start with "No current", "No focused", or "No
     lane-local" blocker, then later acknowledge pending root verification,
     uncommitted dirty batches, unexecuted full upstream runners, excluded live
     provider/service coverage, model-heavy markerPDF execution, or broad
     hydration/build limits. The unresolved acceptance blocker should be first.

## Test Gate

I did not run `php tools/run-tests.php`.

The required gate was checked before considering a root run. Earlier samples
matched focused Syncthing and Quadrable PHP runners, and the latest
integration hold recorded active no-argument root PID `2034399` before it
exited. The latest audit-owned handoff sample found an active no-argument root
harness plus focused harnesses:

```text
2026-05-24T01:41Z
pgrep -af '^php tools/run-tests\.php( |$)'
2102019 php tools/run-tests.php
2104709 php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
2105150 php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ...
2105787 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests

owner evidence:
2102019 claude 2101745 00:23 R+ php tools/run-tests.php
2105150 claude 2104780 00:20 R+ php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php ...
```

Because a no-argument root harness was active, this audit did not start a
duplicate. The tree also failed the stability gate: broad dirty lane edits,
active tmux sessions, stale
dashboard/progress artifacts, moving `HEAD`, and recent status/audit-only
history mean a no-argument root run would not produce accepted aggregate
evidence.

## Next Intervention

Freeze or wait out lane/reseed/runner/status writers, including focused PHP
harnesses, then confirm `HEAD`, tracked dirty count, total status count,
shortstat, and runner state are unchanged across two polls. Accept exactly one
lane-scoped batch. Rerun that lane's focused verification, run one serialized
`php tools/run-tests.php`, run `git diff --check`, normalize the denominator
and PHP pass/fail schema for the accepted data, regenerate dashboard artifacts
from that same snapshot only, and commit or reject that batch.
