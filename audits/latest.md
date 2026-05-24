# Independent Audit - 2026-05-24T08:54Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
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
UTC samples: 2026-05-24T08:48Z through 2026-05-24T08:54Z
HEAD observed after audit started: b661c3565aa8
recent history: b661c356 Record integration hold status; d12fa1dc Record support-library tracker expansion; 3a6e7bbc Record integration hold status
branch divergence: main...origin/main [ahead 787, behind 68]
tracked dirty rows: 324 -> 326
default status rows including untracked: 16215 -> 16231
git diff --shortstat: 324 files changed, 203286 insertions(+), 27903 deletions(-) -> 326 files changed, 204022 insertions(+), 27979 deletions(-)
manifest/status JSON validation: jq empty passed for all 12 root lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
dashboard snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
dependency backlog: dependency-backlog.json updated 2026-05-24 08:41:08 UTC with 29 rows; dashboard/summary still show 22
root run by this audit: not started
```

Required pre-root process-gate evidence:

```text
2026-05-24T08:48:40Z pgrep -af '^php tools/run-tests\.php( |$)':
21759 php tools/run-tests.php lanes/syncthing/tests

2026-05-24T08:49Z ps -o pid,ppid,user,stat,etime,args -p 21759:
21759 4125289 claude Rs 01:02 php tools/run-tests.php lanes/syncthing/tests

2026-05-24T08:52:43Z pgrep -af '^php tools/run-tests\.php( |$)':
no rows

2026-05-24T08:53:39Z pgrep -af '^php tools/run-tests\.php( |$)':
88041 php tools/run-tests.php lanes/esbuild/tests

2026-05-24T08:53Z ps -o pid,ppid,user,stat,etime,args -p 88041:
no process row; the focused Esbuild harness exited before owner sampling

2026-05-24T08:53:48Z pgrep -af '^php tools/run-tests\.php( |$)':
no rows

2026-05-24T08:54:40Z pgrep -af '^php tools/run-tests\.php( |$)':
104128 php tools/run-tests.php

2026-05-24T08:54Z ps -o pid,ppid,user,stat,etime,args -p 104128:
104128 103944 claude R+ 00:09 php tools/run-tests.php
```

I did not start `php tools/run-tests.php`. The exact process gate was initially
nonempty with a focused Syncthing harness, later cleared, briefly matched a
transient focused Esbuild harness, cleared again, and finally matched active
no-argument root PID `104128` owned by `claude`. The checkout failed the
stability gate because `HEAD`, dirty counts, and shortstat moved during the
audit, and the final root gate was active.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:39`, `lanes/*/lane-status.json:13`, recent Git
     history.
   - Goal requirement at risk: `goal.md` requires small reviewable slices with
     passing tests, and finished lane output must be verified, committed, and
     integrated cleanly.
   - Evidence: `HEAD` moved from the previous audit commit `8422acd9` through
     integration-hold commit `3a6e7bbc`, support-library tracker commit
     `d12fa1dc`, and integration-hold commit `b661c356` while this audit was
     running. The tree moved from `324` to `326` tracked dirty rows, from
     `16215` to `16231` default status rows including untracked files, and from
     `324 files changed, 203286 insertions(+), 27903 deletions(-)` to `326
     files changed, 204022 insertions(+), 27979 deletions(-)`. Every lane still
     reports a pending, uncommitted, or shared-dirty `latestCommit`.

2. **Critical - there is no acceptable repo-wide PHP result for this snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:39`,
     `lanes/*/lane-status.json:12`.
   - Goal requirement at risk: `goal.md` requires periodic repo-wide tests and
     static checks with failures recorded honestly.
   - Evidence: the required exact gate initially matched focused Syncthing PID
     `21759` owned by `claude` (`php tools/run-tests.php lanes/syncthing/tests`),
     later briefly matched focused Esbuild PID `88041`, which exited before
     owner sampling, and finally matched active no-argument root PID `104128`
     owned by `claude` (`php tools/run-tests.php`). No duplicate root run was
     started by this audit.

3. **Critical - `porting.html` and `porting-summary.json` remain stale
   publication artifacts.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:38`, `porting.html:75`, `porting-summary.json:2`,
     `porting-summary.json:3`, `porting-summary.json:8`,
     `porting-summary.json:265`, `dependency-backlog.json:3`.
   - Goal requirement at risk: `goal.md` requires the dashboard to show current
     per-lane denominator, mapped tests, PHP pass/fail, phase, audit, current
     work, blocker, and commit.
   - Evidence: the dashboard still claims average progress `97.7%`, generated
     `2026-05-23 23:43:54 UTC`, and source snapshot `79768df0c427`; current
     `HEAD` is `b661c356`. The dashboard shows `22` optional dependency rows,
     while `dependency-backlog.json` now has `29` rows (`19 candidate`, `10
     deferred`).

4. **High - dashboard, manifest, and lane-status counts disagree across every
   active lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:11` through `porting-summary.json:249`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md` requires comparable upstream
     denominators, mapped upstream tests, PHP pass/fail counts, audit status,
     and latest commit per lane.
   - Evidence:

```text
lane          manifest total/mapped/php       lane-status php   dashboard total/mapped/php
difftastic    933 / 668 / n/a                 2995              735 / 374 / 374
dolt          prose total / 613 / 400         413               inventory / 613 / 356
esbuild       2567 / 391 / 389                391               2567 / 311 / 311
gitoxide      2877 / 2877 / n/a               6847              2877 / 2751 / 5634
libsqlite     1589 / 333 / n/a                333               1589 / 286 / 286
LightningCSS  3535 / 2692 / n/a               3876              3532 / 1732 / 2197
markerPDF     373 / 324 / 461                 461               330 / 280 / 416
pandoc        2276 / 1734 / n/a               337               2276 / 1061 / 278
quadrable     55 / 55 / n/a                   220               55 / 55 / 190
rclone        1601 / 855 / 855                855               1601 / 698 / 698
readability   1984 / 1984 / 240               241               1984 / 1984 / 204
syncthing     658 / 658 / n/a                 7144              658 / 658 / 4579
```

5. **High - manifest/status schemas remain non-normalized, with Dolt still
   carrying a prose denominator.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2486`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2492`,
     `lanes/dolt/lane-status.json:6`, `lanes/dolt/lane-status.json:13`.
   - Goal requirement at risk: `goal.md` requires a real upstream benchmark
     denominator and durable coordination fields.
   - Evidence: Dolt's canonical `benchmarkDenominator.total` is a long prose
     evidence paragraph, not a numeric denominator. The same lane records
     manifest `phpBehaviorTests = 400`, lane status `phpPass = 413`, and stale
     dashboard `356 pass`.

6. **High - near-complete progress percentages overstate accepted upstream and
   root parity.**
   - Paths: `porting.html:32`, `lanes/difftastic/lane-status.json:4`,
     `lanes/gitoxide/lane-status.json:4`, `lanes/pandoc/lane-status.json:4`,
     `lanes/syncthing/lane-status.json:4`, `lanes/rclone/lane-status.json:4`.
   - Goal requirement at risk: `goal.md` says passing PHP tests are not enough,
     upstream tests are the source of truth where possible, and hard features
     must be marked as blockers or future slices.
   - Evidence: many lanes show `98` or `99` percent while Difftastic full Cargo
     parity, Gitoxide full Cargo workspace parity, Pandoc full Haskell runner
     parity, Syncthing full `go test ./...`, and full rclone provider/mount
     parity remain unexecuted or explicitly outside current evidence.

7. **High - essential optional-library coverage is expanding as backlog text,
   not as bounded support-library ports.**
   - Paths: `dependency-backlog.json:5`, `dependency-backlog.json:45`,
     `dependency-backlog.json:127`, `dependency-backlog.json:185`,
     `dependency-backlog.json:470`, `porting.html:75`,
     `porting-summary.json:265`.
   - Goal requirement at risk: support libraries must have the same granularity
     as lanes: bounded native PHP component, activation gate,
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much of
     the upstream/spec suite as can actually run.
   - Evidence: `dependency-backlog.json` now lists `29` rows, including newer
     `webdav-protocol-core`, `pandoc-pdf-engine-handoff-core`,
     `json-json5-document-core`, `tree-sitter-grammar-subset`,
     `protobuf-wire-core`, and `sql-expression-semantics-core`. All remain
     `candidate` or `deferred`; none is an active manifest-backed support
     library with PHP pass/fail evidence. The dashboard still publishes only
     the older `22` rows.

8. **High - rclone's WebDAV/provider/compression work remains lane-local and
   too broad for shared dependency progress.**
   - Paths: `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1333` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1356`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1361`,
     `lanes/rclone/lane-status.json:12`,
     `dependency-backlog.json:45`.
   - Goal requirement at risk: optional dependency expansion must be bounded,
     gated, tested, shared where appropriate, and backed by
     dependency-specific denominators.
   - Evidence: rclone now carries a large WebDAV surface, including
     PROPFIND/PROPPATCH, LOCK/If, COPY/MOVE, gzip, serve middleware,
     auth-proxy, directory templates, URL decoding, held locks, and partial
     failure behavior. That may be useful rclone lane evidence, but it is not
     accepted shared WebDAV/XML/archive/provider dependency progress until it
     has its own bounded support-library denominator and tests.

9. **High - markerPDF still mixes native PDF evidence with external/runtime
   application boundaries.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:438`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:493`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:783`,
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md` forbids counting wrappers, bridge
     calls, shell-outs, external converter/runtime execution, and whole
     applications as native port progress.
   - Evidence: markerPDF has real native PDF text/filter/font work, but the
     status/manifest still carry marker_app, marker_server, convert.py,
     chunk_convert, pdftext dictionary execution, Streamlit, FastAPI/Uvicorn,
     Poetry, model downloads, Torch/Surya/Texify/Nougat, OCRMyPDF/Tesseract,
     Ghostscript, Pandoc/XeLaTeX, and GitHub Actions/publishing boundaries.
     These must remain preflight or supplied-oracle metadata unless bounded
     native PHP components own the behavior.

10. **Medium - manifests contain stale internal evidence strings, not just
    stale dashboard rows.**
    - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
      `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
      `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:977`,
      `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:16`,
      `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:345`,
      `lanes/readability/UPSTREAM_TEST_MANIFEST.json:939`,
      `lanes/readability/lane-status.json:6`,
      `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1754`,
      `lanes/syncthing/lane-status.json:6`.
    - Goal requirement at risk: `goal.md` requires durable coordination files
      with honest mapped tests and PHP pass/fail evidence.
    - Evidence: Difftastic top-level values say `933 / 668`, while its warning
      still says `925 / 656`; esbuild top-level mapped is `391`, while the
      warning says PHP maps `389`; Readability manifest says `phpBehaviorTests
      = 240`, while lane status says `phpPass = 241`; Syncthing manifest text
      says lane PHP has `7174` assertions, while lane status says `7144`.

11. **Medium - Gitoxide shell-outs remain acceptable only as explicit oracle
    tooling.**
    - Paths: `lanes/gitoxide/tests/FetchResponseTest.php:18`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:13`,
      `lanes/gitoxide/tests/GitUrlTest.php:70`,
      `lanes/gitoxide/tests/GitUrlTest.php:104`,
      `tools/generate-dashboard.php:197`.
    - Goal requirement at risk: `goal.md` says generated fixtures, bridge
      calls, and shell-outs to upstream binaries must not count as native
      implementation progress.
    - Evidence: Gitoxide tests still use `proc_open` helpers for `git`
      diagnostics or fixtures. That can remain valid oracle evidence, but it
      must stay labeled and must not inflate native parity or accepted
      implementation progress.

## Next Best Intervention

Freeze active writers, dashboard/status publishers, focused lane harnesses, and
root loops; wait for two stable `HEAD` and dirty-count polls; accept or reject
one lane batch at a time; normalize manifest/status numeric fields and commit
fields; run focused verification plus `git diff --check`; regenerate
`progress.md`, `porting.html`, and `porting-summary.json` from the accepted
commit; then run one serialized no-argument `php tools/run-tests.php` only if
the exact process gate remains empty and the tree stays stable.
