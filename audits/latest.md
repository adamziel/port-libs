# Independent Audit - 2026-05-24T09:23Z

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
UTC samples: through 2026-05-24T09:23Z
HEAD moved during audit: dd9b522f23b8 -> 98b34f1b1240
recent history: 98b34f1b Record integration hold status; dd9b522f Refresh independent audit status; e08123da Record integration hold status; a4392cb2 Record dependency scout tracker updates
branch divergence: main...origin/main [ahead 796, behind 68] before the latest integration-hold commit sample
tracked dirty rows: 325 -> 324 -> 328
default status rows including untracked: 16317 -> 16319 -> 16332
git diff --shortstat: 325 files changed, 206062 insertions(+), 27858 deletions(-) -> 324 files changed, 206106 insertions(+), 27878 deletions(-) -> 328 files changed, 206870 insertions(+), 28000 deletions(-)
manifest/status JSON validation: jq empty passed for 12 root lane manifests, 12 lane-status files, porting-summary.json, and dependency-backlog.json at the final sample
dashboard worktree snapshot: porting.html and porting-summary.json still generated 2026-05-23 23:43:54 UTC from source 79768df0c427; both are dirty relative to HEAD
dependency backlog: dependency-backlog.json updated 2026-05-24 09:02:27 UTC with 31 rows; dashboard/summary still show 22
root run by this audit: not started
```

Required pre-root process-gate evidence:

```text
early sample pgrep -af '^php tools/run-tests\.php( |$)':
361519 php tools/run-tests.php lanes/readability/tests

later sample pgrep -af '^php tools/run-tests\.php( |$)':
366492 php tools/run-tests.php lanes/syncthing/tests

ps -o pid,ppid,user,stat,etime,args -p 366492:
366492 327710 claude Rs 01:19 php tools/run-tests.php lanes/syncthing/tests

final post-edit pgrep -af '^php tools/run-tests\.php( |$)':
no rows
```

I did not start `php tools/run-tests.php`. The exact pre-root gate matched
focused PHP harnesses during audit sampling; after it cleared, the checkout
still was not stable enough for a trustworthy audit-owned no-argument root
result.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md`, `audits/integration-status.md`,
     `lanes/*/lane-status.json`, recent Git history.
   - Goal requirement at risk: `goal.md` requires small, reviewable slices with
     passing tests; accepted lane output must be verified, committed, and
     integrated cleanly.
   - Evidence: `HEAD` moved during this audit from audit commit
     `dd9b522f23b8` to integration-hold commit `98b34f1b1240`. Default status
     rows moved from `16317` to `16319` to `16332`, tracked dirty rows moved
     from `325` to `324` to `328`, and shortstat moved from `325 files changed,
     206062 insertions(+), 27858 deletions(-)` through `324 files changed,
     206106 insertions(+), 27878 deletions(-)` to `328 files changed, 206870
     insertions(+), 28000 deletions(-)`. Lane status `latestCommit` fields
     still say `pending`, `uncommitted`, or shared dirty worktree across the
     active lanes.

2. **Critical - there is no acceptable repo-wide PHP result for this snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md` requires periodic repo-wide tests and
     static checks with failures recorded honestly.
   - Evidence: the required exact gate matched `361519 php tools/run-tests.php
     lanes/readability/tests` early and `366492 php tools/run-tests.php
     lanes/syncthing/tests` owned by `claude` later; a final post-edit gate was
     clear. I still did not start a no-argument root run because the dirty
     counts kept moving. Lane-local green checks and an integration-hold commit
     made while the tree was moving do not establish a serialized root result
     for `98b34f1b1240` plus this dirty worktree.

3. **Critical - `porting.html` and `porting-summary.json` remain stale dirty
   publication artifacts.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:75`, `porting-summary.json:3`,
     `porting-summary.json:4`, `porting-summary.json:218`,
     `dependency-backlog.json:3`.
   - Goal requirement at risk: `goal.md` requires the dashboard to show current
     per-lane denominator, mapped tests, PHP pass/fail, phase, audit, current
     work, blocker, and commit.
   - Evidence: the dashboard still claims average progress `97.7%`, generated
     `2026-05-23 23:43:54 UTC`, source snapshot `79768df0c427`, and `22`
     dependency rows. Current `HEAD` is `98b34f1b1240`, and
     `dependency-backlog.json` has `31` rows.

4. **High - dashboard, manifest, and lane-status counts still disagree across
   every active lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:11` through `porting-summary.json:212`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md` requires comparable upstream
     denominators, mapped upstream tests, PHP pass/fail counts, audit status,
     and latest commit per lane.
   - Evidence:

```text
lane          manifest total/mapped/php       lane-status php   dashboard total/mapped/php
difftastic    951 / 686 / n/a                 3019              735 / 374 / 374
dolt          prose total / 613 / 401         415               inventory / 613 / 356
esbuild       2567 / 396 / 396                396               2567 / 311 / 311
gitoxide      2877 / 2877 / n/a               6888              2877 / 2751 / 5634
libsqlite     1589 / 334 / n/a                334               1589 / 286 / 286
LightningCSS  3535 / 2694 / n/a               3880              3532 / 1732 / 2197
markerPDF     376 / 327 / 464                 464               330 / 280 / 416
pandoc        2276 / 1762 / n/a               341               2276 / 1061 / 278
quadrable     55 / 55 / n/a                   221               55 / 55 / 190
rclone        1601 / 861 / 861                861               1601 / 698 / 698
readability   1984 / 1984 / 243               243               1984 / 1984 / 204
syncthing     658 / 658 / n/a                 7229              658 / 658 / 4579
```

5. **High - Dolt still has a non-machine-checkable denominator.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/lane-status.json`, `porting-summary.json`.
   - Goal requirement at risk: `goal.md` requires a real upstream benchmark
     denominator and durable coordination fields.
   - Evidence: Dolt's `benchmarkDenominator.total` is a long prose evidence
     paragraph beginning with the latest BIN/query-diff slice, not a numeric
     denominator. The same lane reports manifest `phpBehaviorTests = 401`,
     lane-status `phpPass = 415`, and dashboard `356 pass`.

6. **High - near-complete progress percentages overstate accepted upstream and
   root parity.**
   - Paths: `lanes/difftastic/lane-status.json:4`,
     `lanes/dolt/lane-status.json:4`, `lanes/gitoxide/lane-status.json:4`,
     `lanes/pandoc/lane-status.json:4`, `lanes/rclone/lane-status.json:4`,
     `lanes/syncthing/lane-status.json:4`, `porting.html:32`.
   - Goal requirement at risk: `goal.md` says passing PHP tests are not enough,
     upstream tests are the source of truth where possible, and hard features
     must be marked as blockers or future slices.
   - Evidence: lanes claim `98` or `99` percent while full Difftastic Cargo,
     Gitoxide Cargo workspace, Pandoc Haskell, Syncthing `go test ./...`, broad
     Dolt Go/BATS, and full rclone provider/mount parity remain unexecuted or
     explicitly outside current evidence. Root aggregate verification remains
     pending for the dirty handoffs.

7. **High - essential optional-library coverage remains backlog-only.**
   - Paths: `dependency-backlog.json:3`, `dependency-backlog.json:45`,
     `dependency-backlog.json:61`, `dependency-backlog.json:337`,
     `dependency-backlog.json:429`, `dependency-backlog.json:448`,
     `dependency-backlog.json:508`, `porting.html:75`,
     `porting-summary.json:218`.
   - Goal requirement at risk: support libraries need the same granularity as
     lanes: bounded native PHP component, activation gate,
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much of
     the upstream/spec suite as can actually run.
   - Evidence: `dependency-backlog.json` has `31` rows (`21 candidate`, `10
     deferred`) including WebDAV, URL percent-encoding, JSON/JSON5,
     sequence-diff/merge, protobuf wire, SQL expression semantics, ZIP/XML,
     DOCX/ODT/EPUB, and PDF/OCR/layout rows. None is an active support-library
     manifest with pass/fail evidence, and the dashboard still publishes `22`
     rows.

8. **High - rclone's WebDAV/provider/compression expansion is too broad to
   count as shared dependency progress.**
   - Paths: `lanes/rclone/lane-status.json:5`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `dependency-backlog.json:45`.
   - Goal requirement at risk: dependency expansion must be bounded, gated,
     tested, shared where appropriate, and backed by dependency-specific
     denominators.
   - Evidence: rclone carries lane-local WebDAV behavior across PROPFIND,
     PROPPATCH, LOCK/If, COPY/MOVE, gzip, middleware, auth-proxy, directory
     responses, URL decoding, held locks, and x/net runner evidence. That is not
     accepted shared WebDAV/XML/archive progress until a bounded support library
     has its own manifest, gate, spec/upstream denominator, malformed cases, and
     PHP pass/fail evidence.

9. **High - markerPDF still mixes native PDF evidence with external/runtime
   application boundaries.**
   - Paths: `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md` forbids counting wrappers, bridge
     calls, shell-outs, external converter/runtime execution, and whole
     applications as native port progress.
   - Evidence: markerPDF has useful native PDF text/filter/font slices, but
     status still carries marker_app, marker_server, convert.py, chunk_convert,
     pdftext execution, Streamlit, FastAPI/Uvicorn, Poetry, Torch/Surya/Texify,
     Nougat, OCRMyPDF/Tesseract, Ghostscript, Pandoc/XeLaTeX, and GitHub
     Actions/publishing boundaries. These must remain preflight or supplied
     oracle metadata unless bounded native PHP components own the behavior.

10. **Medium - Gitoxide shell-outs remain acceptable only as explicit oracle
    tooling.**
    - Paths: `lanes/gitoxide/lane-status.json:12`,
      `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
      `lanes/gitoxide/tests/FetchResponseTest.php`,
      `lanes/gitoxide/tests/GitUrlTest.php`.
    - Goal requirement at risk: `goal.md` says generated fixtures, bridge calls,
      and shell-outs to upstream binaries must not count as native
      implementation progress.
    - Evidence: Gitoxide still records shell-backed filter, askpass, transport,
      external merge driver, URL, and diagnostic boundaries. They can remain as
      labeled oracle/tooling surfaces, but must not inflate native parity or
      accepted implementation progress.

## Next Best Intervention

Freeze active writers, dashboard/status publishers, focused lane harnesses, and
root loops; wait for two stable `HEAD`, dirty-count, and process-gate polls;
accept or reject one lane batch at a time; normalize manifest/status numeric
fields and commit fields; run focused verification plus `git diff --check`;
regenerate `progress.md`, `porting.html`, and `porting-summary.json` from the
accepted commit; then run one serialized no-argument `php tools/run-tests.php`
only if the exact process gate remains empty and the tree stays stable.
