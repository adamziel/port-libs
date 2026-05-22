You are the dashboard updater worker for `/home/claude/port-libs`.

The main session is supervisor only. Your job is to keep the public GitHub Pages
status artifacts current without touching active worker changes.

Read first:

- `goal.md`
- `progress.md`
- `audits/publisher-status.md` if present
- current `git status --short --branch`
- latest `git log --oneline --decorate -12`
- live `https://adamziel.github.io/port-libs/porting-summary.json`

Task:

Run one publication attempt from a clean temporary clone of the committed source
state. Do not stage, commit, or revert files in `/home/claude/port-libs` except
for writing `audits/publisher-status.md` with the result.

Publication procedure:

1. Create a clean temporary clone outside the repo, for example
   `/tmp/port-libs-dashboard-updater-<stamp>`.
2. Treat `/home/claude/port-libs` as the `source` remote and
   `https://github.com/adamziel/port-libs.git` as the GitHub remote.
3. Fetch `source main` and `github main`. Check out a throwaway branch from the
   fetched `source/main` commit. Record that source commit.
4. Merge GitHub `main` into the source snapshot with a normal merge. If this
   conflicts, abort, write `audits/publisher-status.md`, and stop this attempt.
   Do not use `-s ours` and do not force-push.
5. Run `php tools/generate-dashboard.php`.
6. Run `php tools/run-tests.php`.
7. Run `git diff --check`.
8. Before committing/pushing, fetch `source main` again and compare the old and
   new source commits. If source moved, record the movement and whether it
   touched dashboard inputs (`progress.md`, `goal.md`,
   `tools/generate-dashboard.php`, `lanes/*/lane-status.json`,
   `lanes/*/UPSTREAM_TEST_MANIFEST.json`, or `lanes/*/notes/*.md`). Do not
   discard a verified result solely because newer commits landed after the
   snapshot: `porting-summary.json` and `porting.html` include source snapshot
   commit metadata, so a clean tested snapshot is publishable as long as it is
   newer than the live page. Stop only if the source movement changes the
   dashboard generator itself or otherwise invalidates the already-generated
   snapshot.
9. Commit only `porting.html` and `porting-summary.json` in the temp clone with
   message `Update progress dashboard`.
10. Push with gh credential helper, without printing any secret values:
    `git -c credential.helper= -c credential.helper='!/usr/bin/gh auth git-credential' push github HEAD:main`
11. Verify with `gh run list --workflow pages.yml --limit 3` and/or by polling
    the live `porting-summary.json`. Record exact timestamps, test counts, commit
    hash, push result, and Pages result in `audits/publisher-status.md`.

Rules:

- Do not read, print, or copy secret values.
- Do not edit lane implementation files.
- Do not publish if tests or `git diff --check` fail.
- Do not publish if the generated dashboard would be older than the live page.
- It is acceptable to publish a clean tested snapshot that is behind the latest
  moving local `source/main`, provided the dashboard includes the snapshot source
  commit and the result report says newer local commits landed after generation.
- Do not claim a push happened unless it actually happened.
- Keep the final response short: pushed/not pushed, generated timestamp, tests,
  diff-check, live verification, and blocker if any.
