# Independent Audit - 2026-05-24T05:29Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files,
current `lanes/*/lane-status.json`, `dependency-backlog.json`, recent Git
history, and root-runner process state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, shell-outs, whole applications,
external converter wrappers, and hidden process launchers are treated as
non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T05:26:32Z, 2026-05-24T05:28:01Z, 2026-05-24T05:29:06Z
HEAD observed moving: ce697863891d -> d3547d4de1ce
recent commits: d3547d4d Record integration hold status; ce697863 Refresh independent audit status; c4d9b99a Record integration hold status
branch divergence at final sample: main...origin/main [ahead 717, behind 68]
tracked dirty rows: 311 -> 312 -> 311
default status rows including untracked: 13552 -> 13555 -> 13554
git diff --shortstat: 311 files changed, 169781 insertions(+), 21950 deletions(-) -> 312 files changed, 170067 insertions(+), 21950 deletions(-) -> 311 files changed, 170105 insertions(+), 21950 deletions(-)
manifest/status JSON validation: jq empty passed for all lane manifests, lane-status files, porting-summary.json, and dependency-backlog.json
root run by this audit: not started
```

Required root-run gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T05:26:32Z:
<no rows>

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T05:28:01Z:
1204426 php tools/run-tests.php lanes/syncthing/tests

owner sample for PID 1204426:
1204426 claude 1047461 00:56 Rs php tools/run-tests.php lanes/syncthing/tests

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T05:29:06Z:
<no rows>
```

I did not start `php tools/run-tests.php`. The exact required process gate
briefly matched an active focused Syncthing harness, and even after it cleared
the checkout had already moved during review and remained a large dirty
aggregate.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md`, `audits/latest.md`, current Git status, and
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:52` require
     small reviewable slices, verified commits, and a visible stable baseline.
   - Evidence: `HEAD` advanced during this audit from `ce697863891d` to
     `d3547d4de1ce`; recent history is still audit/status-only; the branch is
     `ahead 717, behind 68`; tracked dirty rows moved `311 -> 312 -> 311`; and
     shortstat moved to `311 files changed, 170105 insertions(+), 21950
     deletions(-)`. That is active shared output, not an accepted lane handoff.

2. **Critical - there is no coherent root-harness result for the current
   snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md`, `audits/latest.md`, and
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:49` requires periodic repo-wide tests/static
     checks and honest failure recording.
   - Evidence: the required duplicate-run gate found active PID `1204426`
     owned by `claude` running `php tools/run-tests.php lanes/syncthing/tests`.
     A later gate cleared, but `HEAD`, status counts, and shortstat had already
     moved. Focused lane-green results in lane statuses cannot substitute for
     one serialized no-argument root run from a frozen source snapshot.

3. **Critical - `porting.html` and `porting-summary.json` are stale and still
   miss the dashboard contract.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:43` through `porting.html:52`, and `porting-summary.json`.
   - Requirement at risk: `goal.md:3` and `goal.md:45` require current per-lane
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: local dashboard artifacts still publish generated time
     `2026-05-23 23:43:54 UTC` and snapshot `main 79768df0c427`, while current
     `HEAD` is `d3547d4de1ce`. The HTML still collapses denominator/mapped/PHP
     evidence into broad `Benchmark` and `Mapped` cells, and commit cells still
     contain non-commit fragments such as `pending`, `uncommi`, `not com`, and
     `HEAD 8d`.

4. **High - manifest, lane-status, and dashboard counts disagree across active
   lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:25`, `goal.md:44`, and `goal.md:45` require
     real denominators and reliable coordination status.
   - Evidence: current manifests/statuses now report Difftastic `840` total /
     `490` mapped / `2770` PHP pass while the dashboard says `735 / 374 / 374`;
     Dolt `393` PHP pass while dashboard says `356`; esbuild `351` mapped/pass
     while dashboard says `311`; Gitoxide `2877` mapped and `6332` pass while
     dashboard says `2751 / 5634`; libsqlite `313` mapped/pass while dashboard
     says `286`; LightningCSS `2141` mapped and `2684` pass while dashboard says
     `1732 / 2197`; markerPDF `356 / 307 / 444` while dashboard says
     `330 / 280 / 416`; Pandoc `1485` mapped and `316` pass while dashboard says
     `1061 / 278`; Quadrable `206` pass while dashboard says `190`; rclone
     `813` mapped/pass while dashboard says `698`; Readability `233` pass while
     dashboard says `204`; and Syncthing `6356` pass while dashboard says
     `4579`.

5. **High - manifest/status schemas remain too free-form for acceptance.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:25`, `goal.md:38`, and `goal.md:45`.
   - Evidence: `benchmarkDenominator.total` is numeric in some manifests and a
     long narrative string in others; `phpPass` mixes tests, behavior checks,
     and assertions; and `latestCommit` fields contain prose like
     `pending in shared dirty worktree` or `uncommitted lane batch` instead of
     accepted commit identifiers.

6. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through `porting.html:67`, and
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:35` through `goal.md:40` and `goal.md:49`.
   - Evidence: the dashboard advertises `97.7%` average progress and `98-99%`
     for most lanes while lane blockers still record unrun full Cargo/Go/BATS,
     Haskell, release-extra, live provider/mount, benchmark/model, or root
     aggregate parity. Focused green slices are useful evidence, but they are
     not accepted full-port parity.

7. **High - markerPDF still over-credits external/runtime orchestration as
   mapped native progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:967`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:970`,
     `lanes/markerpdf/lane-status.json:5`, and
     `lanes/markerpdf/src/ChunkConversionPlanner.php:142`.
   - Requirement at risk: `goal.md:1`, `goal.md:30`, `goal.md:35`, and the
     support-library granularity requirement in this audit prompt.
   - Evidence: markerPDF now has valuable native PDF stream-filter work, but
     the denominator/status also count marker server/app/runtime planning,
     Poetry/package metadata, model runtime graphs, OCR install plans,
     Texify/Nougat boundaries, and `shell_execution => eval` chunk-convert
     lifecycle metadata. Those are preflight/oracle boundaries unless separated
     from native port progress.

8. **High - essential optional-library coverage remains backlog-only.**
   - Paths: `dependency-backlog.json:4` through `dependency-backlog.json:23`,
     `progress.md:17` through `progress.md:24`, and `porting.html:75` through
     `porting.html:78`.
   - Requirement at risk: `goal.md:24` through `goal.md:31`, `goal.md:35`, and
     this audit prompt's support-library requirement for bounded native
     components, activation gates, dependency-specific denominators, mapped
     fixtures, PHP pass/fail evidence, and malformed/corrupt cases.
   - Evidence: `dependency-backlog.json` has 23 rows (`candidate: 13`,
     `deferred: 10`), while `porting.html` still says 22 rows (`candidate: 12`).
     No support-library manifest/status files record dependency-specific PHP
     pass/fail evidence for the rich-function gaps now blocking real parity:
     Pandoc package/OpenXML/ODT/doctemplates/citation/math, markerPDF PDF
     text/OCR/layout/table/Unicode, rclone archive/XML/provider metadata, and
     shared charset/hash/glob/compression surfaces.

9. **High - dependency expansion is happening lane-locally instead of through
   bounded shared gates.**
   - Paths: `lanes/rclone/src/VfsZipArchive.php:8`,
     `lanes/rclone/src/VfsWebDavProppatchXml.php:8`,
     `lanes/rclone/src/VfsWebDavPropfindResponse.php:8`,
     `lanes/rclone/src/VfsWebDavServeMiddleware.php:10`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:996`, and
     `dependency-backlog.json`.
   - Requirement at risk: `goal.md:24` through `goal.md:31` and this audit
     prompt's dependency-expansion requirement.
   - Evidence: rclone carries lane-local ZIP and WebDAV XML/response/middleware
     components, while markerPDF carries benchmark archive/supplied-document
     archive evidence. These may be justified lane slices, but they should not
     count as shared support-library progress until split or gated with their
     own dependency-specific denominator, mapped fixtures, PHP pass/fail
     evidence, and malformed/corrupt cases.

10. **Medium - `progress.md` Active Lanes still lags current handoffs.**
    - Paths: `progress.md:84` through `progress.md:97` and
      `lanes/*/lane-status.json`.
    - Requirement at risk: `goal.md:44`.
    - Evidence: the Active Lanes table still lists older handoffs such as
      Gitoxide SSH config-options, LightningCSS trig/math, markerPDF benchmark
      file-inventory planning, Readability negative header cleanup, Syncthing
      system-log route, Difftastic Ada/Apex, rclone VFS Statfs, and esbuild
      automatic JSX fallback. Current lane statuses describe later sparse-index,
      relative-color, PDF LZWDecode, Dropbox trailing taxonomy cleanup,
      disk-change events, PHP/Hack constructor promotion, WebDAV middleware, and
      decorator/static-private-field work.

11. **Medium - blocker fields lead with local-green wording while acceptance
    blockers remain open.**
    - Paths: `lanes/*/lane-status.json` and `progress.md`.
    - Requirement at risk: `goal.md:31`, `goal.md:40`, `goal.md:48`, and
      `goal.md:49`.
    - Evidence: many blockers start with "no local blocker" or focused green
      evidence, then later mention that root aggregate verification or full
      upstream parity is pending. For integration triage, blocker fields should
      lead with the highest unresolved acceptance gate: frozen snapshot, schema
      normalization, focused verification, no-argument root result, dashboard
      regeneration, and accepted commit.

## Next Intervention

Keep the hard writer/runner/status freeze. The next acceptable move is still:
two stable polls of `HEAD`, tracked status count, untracked-inclusive status
count, shortstat, exact PHP runner state, Dolt runner state, capacity queue
state, and relevant log mtimes; accept one lane-scoped batch only; normalize
schema/count fields for that batch; run focused verification plus
`git diff --check`; run exactly one serialized no-argument `php
tools/run-tests.php` from that frozen snapshot if the exact process gate is
empty; regenerate `porting.html`/`porting-summary.json` from the accepted
commit; then commit or reject. Do not count support-library work until it has a
bounded component, activation gate, dependency-specific denominator, mapped
fixtures, PHP pass/fail evidence, and malformed/corrupt cases where relevant.
