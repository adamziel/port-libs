# Isolated Gitoxide Signature-Consuming Slice

- Worktree: `/tmp/port-libs-gitoxide-signature-consuming-20260524T220041Z`
- Base commit used for rebuild: `cd6a6b6997a20283b1797b2c4bcb157fa76ccf1a`
- Patch path: `.tmux-team/tmp/isolate-gitoxide-signature-consuming-20260524T220041Z.patch`
- Clean apply check: passed against a second detached worktree at `/tmp/port-libs-gitoxide-signature-consuming-check-20260524T220041Z` after `HEAD` moved to `d1a7a79b`.
- Ready marker: `.tmux-team/tmp/handoff-candidates/port-isolate-gitoxide-signature-consuming.ready`

## Touched Files

- `lanes/gitoxide/src/CommitSignature.php`
- `lanes/gitoxide/tests/CommitTest.php`
- `lanes/gitoxide/fixtures/wordpress-commit-signature-consuming.php`
- `lanes/gitoxide/examples/wordpress-commit-signature-consuming.php`
- `lanes/gitoxide/notes/upstream-inventory.md`
- `lanes/gitoxide/notes/wordpress-scenarios.md`

No manifest/status JSON files were edited for this isolated patch.

## Excluded Dirty-Main Changes

The dirty main checkout was read only as reference. This patch excludes the accumulated Gitoxide discovery, mailmap, protocol, fetch, push, pack, index, config, attributes, URL/refspec, SHA-256, SSH/daemon, credential, and unrelated example/test surfaces visible in the shared checkout. It also excludes all non-Gitoxide lane changes and all support-library row activation.

## Verification

- `php -l lanes/gitoxide/src/CommitSignature.php`: exit 0.
- `php -l lanes/gitoxide/tests/CommitTest.php`: exit 0.
- `php -l lanes/gitoxide/fixtures/wordpress-commit-signature-consuming.php`: exit 0.
- `php -l lanes/gitoxide/examples/wordpress-commit-signature-consuming.php`: exit 0.
- `php lanes/gitoxide/examples/wordpress-commit-signature-consuming.php`: exit 0.
- `php tools/run-tests.php lanes/gitoxide/tests/CommitTest.php`: exit 0; 1 test file, 204 assertions, 0 failures.
- `php tools/run-tests.php lanes/gitoxide/tests`: exit 0; 32 test files, 2659 assertions, 0 failures.
- `CARGO_HOME=/home/claude/port-libs/.upstream-cache/port-rust-local-capacity-20260523T0140Z/gitoxide-cargo-home CARGO_NET_OFFLINE=true CARGO_TARGET_DIR=/home/claude/port-libs/.upstream-cache/port-rust-local-capacity-20260523T0140Z/gitoxide-target timeout 240 cargo test -p gix-actor signature -- --nocapture`: exit 0; 16 signature-focused tests passed, 0 failed, 2 filtered out.
- `jq empty lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json lanes/gitoxide/lane-status.json`: exit 0.
- `git diff --check -- lanes/gitoxide`: exit 0.
- `git apply --check .tmux-team/tmp/isolate-gitoxide-signature-consuming-20260524T220041Z.patch` in the second clean worktree: exit 0.

## Decision

The patch is clean and verified. The ready marker was created. Integrator should accept this isolated signature-consuming split rather than the rejected mixed Gitoxide handoff.
