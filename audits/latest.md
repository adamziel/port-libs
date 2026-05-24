# Independent Audit - 2026-05-24T05:34Z

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
UTC samples: 2026-05-24T05:33:07Z, 2026-05-24T05:33:27Z, 2026-05-24T05:38:01Z
HEAD observed during audit: d5c5983c52b0 -> 9f3a844c -> 5f78cd93c188
recent commits: 5f78cd93 Record integration hold status; 9f3a844c Record integration hold status; d5c5983c Refresh independent audit status
branch divergence: main...origin/main [ahead 720, behind 68]
tracked dirty rows during audit: 311 -> 312 -> 314
default status rows including untracked during audit: 13556 -> 13557 -> 13626
git diff --shortstat during audit: 311 files changed, 170286 insertions(+), 21931 deletions(-) -> 312 files changed, 170363 insertions(+), 21931 deletions(-) -> 314 files changed, 171911 insertions(+), 22463 deletions(-)
manifest/status JSON validation: jq empty passed for all lane manifests, lane-status files, porting-summary.json, and dependency-backlog.json
root run by this audit: not started
```

Required root-run gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' before root-run decision:
<no rows>

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T05:33:07Z:
<no rows>

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T05:33:27Z:
<no rows>

pgrep -af '^php tools/run-tests\.php( |$)' at final validation:
1422933 php tools/run-tests.php
1425295 php tools/run-tests.php lanes/syncthing/tests
1426821 php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php lanes/syncthing/tests/PullJobQueueTest.php lanes/syncthing/tests/PullScannerTest.php lanes/syncthing/tests/PullTemporaryFileTest.php lanes/syncthing/tests/ReceiveEncryptedBepConnectionTest.php lanes/syncthing/tests/ReceiveEncryptedBepModelTest.php lanes/syncthing/tests/ReceiveEncryptedTest.php lanes/syncthing/tests/RemoteDownloadProgressTrackerTest.php lanes/syncthing/tests/RequestExchangeTest.php lanes/syncthing/tests/RequestServerTest.php lanes/syncthing/tests/SentDownloadStateTest.php lanes/syncthing/tests/ServiceDeviceIdTest.php lanes/syncthing/tests/ServiceLanguageTest.php lanes/syncthing/tests/ServiceMapTest.php lanes/syncthing/tests/ServiceRandomStringTest.php

owner sample:
1422933 claude 1422854 00:59 R+ php tools/run-tests.php
1425295 claude 1335595 00:55 Rs php tools/run-tests.php lanes/syncthing/tests
1426821 claude 1426650 00:54 R+ php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php lanes/syncthing/tests/PullJobQueueTest.php lanes/syncthing/tests/PullScannerTest.php lanes/syncthing/tests/PullTemporaryFileTest.php lanes/syncthing/tests/ReceiveEncryptedBepConnectionTest.php lanes/syncthing/tests/ReceiveEncryptedBepModelTest.php lanes/syncthing/tests/ReceiveEncryptedTest.php lanes/syncthing/tests/RemoteDownloadProgressTrackerTest.php lanes/syncthing/tests/RequestExchangeTest.php lanes/syncthing/tests/RequestServerTest.php lanes/syncthing/tests/SentDownloadStateTest.php lanes/syncthing/tests/ServiceDeviceIdTest.php lanes/syncthing/tests/ServiceLanguageTest.php lanes/syncthing/tests/ServiceMapTest.php lanes/syncthing/tests/ServiceRandomStringTest.php
```

I did not start `php tools/run-tests.php`. The exact process gate was clear
during the stability poll, but the checkout was not stable enough:
tracked/default dirty counts and shortstat changed inside 20 seconds and
`HEAD` advanced during the audit. Final validation then found an active
no-argument root harness plus focused Syncthing harnesses, so a duplicate root
run was also blocked.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md`, `audits/latest.md`, current Git status, and
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:52` require
     small reviewable slices, verified commits, and a stable visible baseline.
   - Evidence: `HEAD` advanced during this audit from `d5c5983c52b0` through
     `9f3a844c` to `5f78cd93c188`; recent history is still dominated by
     audit/status refresh commits; the branch is `ahead 720, behind 68`;
     tracked dirty rows moved `311 -> 312 -> 314`; default status rows moved
     `13556 -> 13557 -> 13626`; and shortstat moved to `314 files changed,
     171911 insertions(+), 22463 deletions(-)`. That is active shared output,
     not an accepted lane handoff.

2. **Critical - there is no coherent root-harness result for the current
   snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md`, `audits/latest.md`, and
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:49` requires periodic repo-wide tests/static
     checks and honest failure recording.
   - Evidence: the required exact duplicate-run gate returned no rows during
     the stability poll, but the source snapshot changed. Final validation then
     found active no-argument root PID `1422933` plus focused Syncthing PIDs
     `1425295` and `1426821`, all owned by `claude`. Focused lane-green claims,
     active in-flight root runs, and prior green/red root results cannot
     substitute for one completed serialized no-argument root result from a
     frozen source snapshot.

3. **Critical - `porting.html` and `porting-summary.json` remain stale and do
   not satisfy the dashboard contract.**
   - Paths: `porting.html:32` through `porting.html:35`,
     `porting.html:56` through `porting.html:67`, and
     `porting-summary.json`.
   - Requirement at risk: `goal.md:3` and `goal.md:45` require current
     per-lane benchmark source, upstream denominator, mapped tests, PHP
     pass/fail, WordPress scenarios, phase, audit, current work, blocker, and
     commit.
   - Evidence: dashboard artifacts still publish generated time
     `2026-05-23 23:43:54 UTC` and snapshot `main 79768df0c427`, while current
     `HEAD` advanced to `5f78cd93c188`. The HTML still collapses upstream
     denominator, mapped tests, and PHP pass/fail into broad cells, and commit
     cells still contain non-commit fragments such as `pending`, `uncommi`,
     `not com`, and `HEAD 8d`.

4. **High - manifest, lane-status, and dashboard counts disagree across active
   lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:25`, `goal.md:44`, and `goal.md:45` require
     real denominators and reliable coordination status.
   - Evidence: current manifests/statuses now report Difftastic `840` total /
     `490` mapped / `2770` PHP pass while the dashboard says `735 / 374 /
     374`; Dolt manifest/status PHP pass disagree at `392` vs `393` while the
     dashboard says `356`; esbuild `351` mapped/pass while dashboard says
     `311`; Gitoxide `2877` mapped and `6342` status pass while dashboard says
     `2751 / 5634`; libsqlite `313` mapped/pass while dashboard says `286`;
     LightningCSS `2141` mapped and `2684` pass while dashboard says `1732 /
     2197`; markerPDF `356 / 307 / 444` while dashboard says `330 / 280 /
     416`; Pandoc `1485` mapped and `317` status pass while dashboard says
     `1061 / 278`; Quadrable `206` status pass while dashboard says `190`;
     rclone `813` mapped/pass while dashboard says `698`; Readability `233`
     pass while dashboard says `204`; and Syncthing `6382` pass while
     dashboard says `4579`.

5. **High - manifest/status schemas remain too free-form for acceptance.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:25`, `goal.md:38`, and `goal.md:45`.
   - Evidence: `benchmarkDenominator.total` is numeric in some manifests and a
     long narrative string in others; `phpPass` mixes behavior tests, direct
     checks, and assertions; `mapped` sometimes counts files, sometimes cases,
     and sometimes supplied-boundary semantics; and `latestCommit` fields
     contain prose such as `pending in shared dirty worktree`, `not committed`,
     and `uncommitted lane batch` instead of accepted commit identifiers.

6. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through `porting.html:67`,
     and `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:35` through `goal.md:40` and `goal.md:49`.
   - Evidence: the dashboard advertises `97.7%` average progress and `98-99%`
     for most lanes while lane blockers still record unrun full Cargo/Go/BATS,
     Haskell, release-extra, live provider/mount, benchmark/model, full
     upstream, or root aggregate parity. Focused green slices are useful
     evidence, but they are not accepted full-port parity.

7. **High - markerPDF still over-credits external/runtime orchestration as
   mapped native progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:459`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:984` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:993`, and
     `lanes/markerpdf/src/ChunkConversionPlanner.php:142`.
   - Requirement at risk: `goal.md:1`, `goal.md:30`, `goal.md:35`, and this
     audit prompt's support-library granularity requirement.
   - Evidence: markerPDF now has valuable native PDF stream-filter work, but
     the denominator/status also count marker server/app/runtime planning,
     Poetry/package metadata, model runtime graphs, OCR install plans,
     Texify/Nougat boundaries, benchmark archive planning, and
     `shell_execution => eval` chunk-convert lifecycle metadata. Those are
     preflight/oracle boundaries unless separated from native port progress.

8. **High - essential optional-library coverage remains backlog-only.**
   - Paths: `dependency-backlog.json`, `progress.md:19` through
     `progress.md:24`, `porting.html:75` through `porting.html:77`, and the
     absence of any support-library `UPSTREAM_TEST_MANIFEST.json` outside
     `lanes/*`.
   - Requirement at risk: `goal.md:24` through `goal.md:31`, `goal.md:35`, and
     this audit prompt's requirement for bounded native support components,
     activation gates, dependency-specific denominators, mapped fixtures, PHP
     pass/fail evidence, and malformed/corrupt cases.
   - Evidence: `dependency-backlog.json` has 23 rows (`candidate: 13`,
     `deferred: 10`), while `porting.html` still says 22 rows (`candidate: 12`,
     `deferred: 10`). The only manifest files are the 12 lane manifests; no
     support-library manifest/status files record dependency-specific PHP
     pass/fail evidence for the rich-function gaps now blocking real parity:
     Pandoc ZIP/OpenXML/ODT/doctemplates/citation/math, markerPDF PDF
     text/OCR/layout/table/Unicode, rclone archive/XML/provider metadata, and
     shared charset/hash/glob/compression surfaces.

9. **High - dependency expansion is happening lane-locally instead of through
   bounded shared gates.**
   - Paths: `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:96` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:131`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1294` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1304`,
     `lanes/rclone/src/VfsServeZipResponse.php:10`,
     `lanes/rclone/src/VfsWebDavProppatchXml.php:10`,
     `lanes/rclone/src/VfsWebDavPropfindResponse.php:10`, and
     `dependency-backlog.json`.
   - Requirement at risk: `goal.md:24` through `goal.md:31` and this audit
     prompt's dependency-expansion requirement.
   - Evidence: rclone carries lane-local ZIP, WebDAV XML, WebDAV lock, WebDAV
     compression, and response/middleware components, while markerPDF carries
     benchmark archive and supplied-document archive evidence. These may be
     justified lane slices, but they should not count as shared support-library
     progress until split or gated with a dependency-specific denominator,
     mapped fixtures, PHP pass/fail evidence, and malformed/corrupt cases.

10. **Medium - `progress.md` Active Lanes still lags current handoffs.**
    - Paths: `progress.md:81` through `progress.md:97` and
      `lanes/*/lane-status.json`.
    - Requirement at risk: `goal.md:44`.
    - Evidence: the Active Lanes table still lists older handoffs such as
      Gitoxide SSH config-options, LightningCSS trig/math, markerPDF benchmark
      file-inventory planning, Readability negative header cleanup, Syncthing
      system-log route, Difftastic Ada/Apex, rclone VFS Statfs, and esbuild
      automatic JSX fallback. Current lane statuses describe later split-index,
      relative-color, PDF stream-filter, Dropbox taxonomy cleanup,
      folder-scan, PHP/Hack callback, WebDAV, and TypeScript private-field work.

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
count, shortstat, and exact PHP runner state; accept one lane-scoped batch only;
normalize schema/count fields for that batch; run focused verification plus
`git diff --check`; run exactly one serialized no-argument `php
tools/run-tests.php` from that frozen snapshot if the exact process gate is
empty; regenerate `porting.html`/`porting-summary.json` from the accepted
commit; then commit or reject. Do not count support-library work until it has a
bounded component, activation gate, dependency-specific denominator, mapped
fixtures, PHP pass/fail evidence, and malformed/corrupt cases where relevant.
