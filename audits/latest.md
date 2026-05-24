# Independent Audit - 2026-05-24T09:01Z

Scope reviewed: `goal.md`, `progress.md`, current worktree `porting.html`,
`porting-summary.json`, every root `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
current `lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git
history. I did not edit lane implementation files, launch agents or tmux
sessions, push, read secrets, inspect process environments, credential stores,
provider configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: through 2026-05-24T09:01Z
HEAD moved during audit: cb766b73 -> d2c120dd
recent history: d2c120dd Record integration hold status; cb766b73 Refresh independent audit status; b661c356 Record integration hold status; d12fa1dc Record support-library tracker expansion
branch divergence: main...origin/main [ahead 789, behind 68]
tracked dirty rows: 324
default status rows including untracked: 16287 -> 16299
git diff --shortstat: 324 files changed, 204076 insertions(+), 27842 deletions(-) -> 324 files changed, 204396 insertions(+), 27812 deletions(-)
manifest/status JSON validation: jq empty passed for all 12 root lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
dashboard worktree snapshot: porting.html and porting-summary.json still generated 2026-05-23 23:43:54 UTC from source 79768df0c427; both are dirty relative to HEAD
dependency backlog: dependency-backlog.json updated 2026-05-24 08:41:08 UTC with 29 rows; dashboard/summary still show 22
root run by this audit: not started
```

Required pre-root process-gate evidence:

```text
early sample pgrep -af '^php tools/run-tests\.php( |$)':
no rows

later sample pgrep -af '^php tools/run-tests\.php( |$)':
164213 php tools/run-tests.php lanes/syncthing/tests

ps -o pid,ppid,user,stat,etime,args -p 164213:
164213 92396 claude Rs 01:07 php tools/run-tests.php lanes/syncthing/tests

final sample pgrep -af '^php tools/run-tests\.php( |$)':
no rows
```

I did not start `php tools/run-tests.php`. The exact process gate was
temporarily nonempty with focused Syncthing PID `164213`, and the checkout was
not stable enough for a trustworthy no-argument root result even after that
process cleared: `HEAD`, untracked rows, and shortstat all moved during this
audit.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:39`, `lanes/*/lane-status.json:13`, recent Git
     history.
   - Goal requirement at risk: `goal.md` requires small, reviewable slices with
     passing tests; accepted lane output must be verified, committed, and
     integrated cleanly.
   - Evidence: `HEAD` moved from audit commit `cb766b73` to integration-hold
     commit `d2c120dd` during this run. Default status rows moved from `16287`
     to `16299`, and shortstat moved from `204076 insertions(+), 27842
     deletions(-)` to `204396 insertions(+), 27812 deletions(-)`. Every lane
     still reports `pending`, `uncommitted`, or shared-dirty `latestCommit`
     state.

2. **Critical - there is no acceptable repo-wide PHP result for this snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:39`,
     `lanes/*/lane-status.json:12`.
   - Goal requirement at risk: `goal.md` requires periodic repo-wide tests and
     static checks with failures recorded honestly.
   - Evidence: no audit-owned no-argument root harness was started. The required
     exact gate temporarily matched focused Syncthing PID `164213`
     (`php tools/run-tests.php lanes/syncthing/tests`), and the final clear gate
     occurred only after the checkout had already moved. Lane-local green
     results cannot substitute for one serialized root result from a frozen
     snapshot.

3. **Critical - `porting.html` and `porting-summary.json` remain stale and
   dirty publication artifacts.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:75`, `porting-summary.json:3`,
     `porting-summary.json:215`, `dependency-backlog.json:3`.
   - Goal requirement at risk: `goal.md` requires the dashboard to show current
     per-lane denominator, mapped tests, PHP pass/fail, phase, audit, current
     work, blocker, and commit.
   - Evidence: the worktree dashboard still claims average progress `97.7%`,
     generated `2026-05-23 23:43:54 UTC`, source snapshot `79768df0c427`, and
     `22` dependency rows. Current `HEAD` is `d2c120dd`, and
     `dependency-backlog.json` has `29` rows (`19 candidate`, `10 deferred`).
     The dashboard files are dirty relative to HEAD; HEAD itself contains an
     even older `2026-05-22` dashboard.

4. **High - dashboard, manifest, and lane-status counts still disagree across
   every active lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:12` through `porting-summary.json:205`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md` requires comparable upstream
     denominators, mapped upstream tests, PHP pass/fail counts, audit status,
     and latest commit per lane.
   - Evidence:

```text
lane          manifest total/mapped/php       lane-status php   dashboard total/mapped/php
difftastic    946 / 681 / n/a                 3007              735 / 374 / 374
dolt          prose total / 613 / 400         414               inventory / 613 / 356
esbuild       2567 / 393 / 389                393               2567 / 311 / 311
gitoxide      2877 / 2877 / n/a               6858              2877 / 2751 / 5634
libsqlite     1589 / 333 / n/a                333               1589 / 286 / 286
LightningCSS  3535 / 2693 / n/a               3876              3532 / 1732 / 2197
markerPDF     375 / 326 / 461                 462               330 / 280 / 416
pandoc        2276 / 1742 / n/a               338               2276 / 1061 / 278
quadrable     55 / 55 / n/a                   221               55 / 55 / 190
rclone        1601 / 857 / 857                857               1601 / 698 / 698
readability   1984 / 1984 / 240               241               1984 / 1984 / 204
syncthing     658 / 658 / n/a                 7174              658 / 658 / 4579
```

5. **High - Dolt still has a non-machine-checkable denominator.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2489`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2495`,
     `lanes/dolt/lane-status.json:6`, `lanes/dolt/lane-status.json:13`.
   - Goal requirement at risk: `goal.md` requires a real upstream benchmark
     denominator and durable coordination fields.
   - Evidence: Dolt's later `benchmarkDenominator.total` is a long prose
     evidence paragraph, not a numeric denominator. Dolt also records manifest
     `phpBehaviorTests = 400`, lane status `phpPass = 414`, and dashboard
     `356 pass`.

6. **High - near-complete progress percentages overstate accepted upstream and
   root parity.**
   - Paths: `lanes/difftastic/lane-status.json:4`,
     `lanes/gitoxide/lane-status.json:4`,
     `lanes/pandoc/lane-status.json:4`,
     `lanes/rclone/lane-status.json:4`,
     `lanes/syncthing/lane-status.json:4`, `porting.html:32`.
   - Goal requirement at risk: `goal.md` says passing PHP tests are not enough,
     upstream tests are the source of truth where possible, and hard features
     must be marked as blockers or future slices.
   - Evidence: lanes claim `98` or `99` percent while full Difftastic Cargo,
     Gitoxide Cargo workspace, Pandoc Haskell, Syncthing `go test ./...`, and
     full rclone provider/mount parity remain unexecuted or explicitly outside
     current evidence. Root aggregate verification is pending for all of those
     handoffs.

7. **High - essential optional-library coverage remains backlog-only.**
   - Paths: `dependency-backlog.json:18`, `dependency-backlog.json:45`,
     `dependency-backlog.json:318`, `dependency-backlog.json:392`,
     `dependency-backlog.json:410`, `dependency-backlog.json:470`,
     `porting.html:75`.
   - Goal requirement at risk: support libraries need the same granularity as
     lanes: a bounded native PHP component, activation gate,
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much of
     the upstream/spec suite as can actually run.
   - Evidence: `dependency-backlog.json` now has `29` rows, including rich
     gaps for ZIP/package, XML/HTML5 DOM, PDF text dictionary, WebDAV, JSON/JSON5,
     tree-sitter grammar subset, protobuf wire format, and SQL expression
     semantics. All rows remain `candidate` or `deferred`; none is an active
     support-library manifest with PHP pass/fail evidence. The dashboard still
     publishes only `22` rows.

8. **High - rclone's WebDAV/provider/compression expansion is too broad to
   count as shared dependency progress.**
   - Paths: `lanes/rclone/lane-status.json:9`,
     `lanes/rclone/lane-status.json:10`, `lanes/rclone/lane-status.json:13`,
     `dependency-backlog.json:45`.
   - Goal requirement at risk: dependency expansion must be bounded, gated,
     tested, shared where appropriate, and backed by dependency-specific
     denominators.
   - Evidence: rclone now carries a broad WebDAV surface: PROPFIND/PROPPATCH,
     LOCK/If, COPY/MOVE, gzip, serve middleware, auth-proxy, directory
     templates, URL decoding, held locks, and partial failure behavior. It is
     useful lane-local evidence, but it is not accepted shared WebDAV/XML/archive
     progress until it has its own bounded support-library denominator and
     tests.

9. **High - markerPDF still mixes native PDF evidence with external/runtime
   application boundaries.**
   - Paths: `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md` forbids counting wrappers, bridge
     calls, shell-outs, external converter/runtime execution, and whole
     applications as native port progress.
   - Evidence: markerPDF has real native PDF text/filter/font slices, but status
     still carries marker_app, marker_server, convert.py, chunk_convert,
     pdftext execution, Streamlit, FastAPI/Uvicorn, Poetry, Torch/Surya/Texify,
     Nougat, OCRMyPDF/Tesseract, Ghostscript, Pandoc/XeLaTeX, and GitHub
     Actions/publishing boundaries. These must remain preflight or supplied
     oracle metadata unless bounded native PHP components own the behavior.

10. **Medium - manifests retain stale internal PHP evidence against their
    current lane-status files.**
    - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2495`,
      `lanes/dolt/lane-status.json:6`,
      `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`,
      `lanes/esbuild/lane-status.json:6`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
      `lanes/markerpdf/lane-status.json:6`,
      `lanes/readability/UPSTREAM_TEST_MANIFEST.json`,
      `lanes/readability/lane-status.json:6`.
    - Goal requirement at risk: `goal.md` requires durable coordination files
      with honest mapped tests and PHP pass/fail evidence.
    - Evidence: Dolt manifest PHP count is `400` while lane status is `414`;
      esbuild manifest is `389` while lane status is `393`; markerPDF manifest
      is `461` while lane status is `462`; Readability manifest is `240` while
      lane status is `241`.

11. **Medium - Gitoxide shell-outs remain acceptable only as explicit oracle
    tooling.**
    - Paths: `lanes/gitoxide/lane-status.json:12`,
      `lanes/gitoxide/tests/FetchResponseTest.php`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php`,
      `lanes/gitoxide/tests/GitUrlTest.php`.
    - Goal requirement at risk: `goal.md` says generated fixtures, bridge calls,
      and shell-outs to upstream binaries must not count as native
      implementation progress.
    - Evidence: Gitoxide still records caller-supplied shell-backed filter,
      askpass, transport, and Git diagnostic boundaries. Those can remain
      labeled oracle tooling, but they must not inflate native parity or accepted
      implementation progress.

## Next Best Intervention

Freeze active writers, dashboard/status publishers, focused lane harnesses, and
root loops; wait for two stable `HEAD`, dirty-count, and process-gate polls;
accept or reject one lane batch at a time; normalize manifest/status numeric
fields and commit fields; run focused verification plus `git diff --check`;
regenerate `progress.md`, `porting.html`, and `porting-summary.json` from the
accepted commit; then run one serialized no-argument `php tools/run-tests.php`
only if the exact process gate remains empty and the tree stays stable.
