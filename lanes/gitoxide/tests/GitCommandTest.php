<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitCommand;

$argv = static fn (GitCommand $command): array => $command->render('sh')['argv'];
$env = static fn (GitCommand $command): array => $command->render('sh')['env'];
$cwd = static fn (GitCommand $command): mixed => $command->render('sh')['cwd'];

return [
    'upstream extract_interpreter' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'gitoxide-shebang-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary shebang fixture');
        }

        try {
            file_put_contents($path, "#!/b/exe\n");
            $t->same(
                ['interpreter' => '/b/exe', 'args' => []],
                GitCommand::extractInterpreter($path),
            );
        } finally {
            @unlink($path);
        }
    },
    'upstream shebang parse valid' => static function (TestRunner $t): void {
        $t->same(['interpreter' => '/bin/sh', 'args' => []], GitCommand::parseShebang('#!/bin/sh'));
        $t->same(['interpreter' => '/bin/sh', 'args' => []], GitCommand::parseShebang('#!/bin/sh   '));
        $t->same(['interpreter' => '/bin/sh', 'args' => []], GitCommand::parseShebang("#!/bin/sh\t\nother"));
        $t->same(['interpreter' => '\\bin\\sh', 'args' => []], GitCommand::parseShebang('#!\\bin\\sh'));
        $t->same(
            ['interpreter' => 'C:\\Program Files\\shell.exe', 'args' => []],
            GitCommand::parseShebang("#!C:\\Program Files\\shell.exe\r\nsome stuff"),
        );
        $t->same(
            ['interpreter' => '/bin/sh', 'args' => ['-i', '-o', '-u']],
            GitCommand::parseShebang("#!/bin/sh -i -o -u\nunrelated content"),
        );
        $t->same(
            ['interpreter' => '/bin/sh', 'args' => ['-o']],
            GitCommand::parseShebang("#!/bin/sh  -o\nunrelated content"),
        );
        $t->same(
            ['interpreter' => '/bin/exe', 'args' => ['anything', 'goes']],
            GitCommand::parseShebang("#!/bin/exe anything goes\nunrelated content"),
        );
        $t->same(
            ['interpreter' => '/bin/sh', 'args' => ["-x \xC3\x28\x41 -y"]],
            GitCommand::parseShebang("#!/bin/sh   -x \xC3\x28\x41 -y  "),
        );
        $t->same(
            ['interpreter' => "/bin/\xC3\x28\x41", 'args' => []],
            GitCommand::parseShebang("#!/bin/\xC3\x28\x41 "),
        );
    },
    'upstream shebang parse invalid' => static function (TestRunner $t): void {
        $t->same(null, GitCommand::parseShebang(''));
        $t->same(null, GitCommand::parseShebang('missing shebang'));
        $t->same(null, GitCommand::parseShebang('#!missing-slash'));
        $t->same(null, GitCommand::parseShebang('/bin/sh'));
    },
    'upstream context git_dir_sets_git_dir_env_and_cwd' => static function (TestRunner $t) use ($env, $cwd): void {
        $command = GitCommand::prepare('')->withContext(['git_dir' => '.']);

        $t->same(['GIT_DIR' => '.'], $env($command));
        $t->same(null, $cwd($command));
    },
    'upstream context worktree_dir_sets_env_only' => static function (TestRunner $t) use ($env, $cwd): void {
        $command = GitCommand::prepare('')->withContext(['worktree_dir' => '.']);

        $t->same(['GIT_WORK_TREE' => '.'], $env($command));
        $t->same(null, $cwd($command));
    },
    'upstream context no_replace_objects_sets_env_only' => static function (TestRunner $t) use ($env, $cwd): void {
        foreach ([false, true] as $value) {
            $command = GitCommand::prepare('')->withContext(['no_replace_objects' => $value]);

            $t->same(['GIT_NO_REPLACE_OBJECTS' => $value ? '1' : '0'], $env($command));
            $t->same(null, $cwd($command));
        }
    },
    'upstream context ref_namespace_sets_env_only' => static function (TestRunner $t) use ($env, $cwd): void {
        $command = GitCommand::prepare('')->withContext(['ref_namespace' => 'namespace']);

        $t->same(['GIT_NAMESPACE' => 'namespace'], $env($command));
        $t->same(null, $cwd($command));
    },
    'upstream context literal_pathspecs_sets_env_only' => static function (TestRunner $t) use ($env, $cwd): void {
        foreach ([false, true] as $value) {
            $command = GitCommand::prepare('')->withContext(['literal_pathspecs' => $value]);

            $t->same(['GIT_LITERAL_PATHSPECS' => $value ? '1' : '0'], $env($command));
            $t->same(null, $cwd($command));
        }
    },
    'upstream context glob_pathspecs_sets_env_only' => static function (TestRunner $t) use ($env, $cwd): void {
        foreach ([false, true] as $value) {
            $expected = $value ? ['GIT_GLOB_PATHSPECS' => '1'] : ['GIT_NOGLOB_PATHSPECS' => '1'];
            $command = GitCommand::prepare('')->withContext(['glob_pathspecs' => $value]);

            $t->same($expected, $env($command));
            $t->same(null, $cwd($command));
        }
    },
    'upstream context icase_pathspecs_sets_env_only' => static function (TestRunner $t) use ($env, $cwd): void {
        foreach ([false, true] as $value) {
            $command = GitCommand::prepare('')->withContext(['icase_pathspecs' => $value]);

            $t->same(['GIT_ICASE_PATHSPECS' => $value ? '1' : '0'], $env($command));
            $t->same(null, $cwd($command));
        }
    },
    'upstream prepare empty' => static function (TestRunner $t) use ($argv): void {
        $t->same([''], $argv(GitCommand::prepare('')));
    },
    'upstream prepare whitespace_only_without_shell' => static function (TestRunner $t) use ($argv): void {
        $t->same(['   '], $argv(GitCommand::prepare('   ')));
    },
    'upstream prepare whitespace_only_commands_with_auto_split_fall_back_to_shell' => static function (TestRunner $t) use ($argv): void {
        $t->same(
            ['sh', '-c', '   ', '--'],
            $argv(GitCommand::prepare('   ')->commandMayBeShellScriptAllowManualArgumentSplitting()),
        );
    },
    'upstream prepare single_and_multiple_arguments' => static function (TestRunner $t) use ($argv): void {
        $t->same(
            ['ls', 'first', 'second', 'third'],
            $argv(GitCommand::prepare('ls')->arg('first')->args(['second', 'third'])),
        );
    },
    'upstream prepare multiple_arguments_in_one_line_with_auto_split' => static function (TestRunner $t) use ($argv): void {
        $t->same(
            ['echo', 'first', 'second', 'third'],
            $argv(GitCommand::prepare('echo first second third')->commandMayBeShellScriptAllowManualArgumentSplitting()),
        );
    },
    'upstream prepare single_and_multiple_arguments_as_part_of_command' => static function (TestRunner $t) use ($argv): void {
        $t->same(['ls first second third'], $argv(GitCommand::prepare('ls first second third')));
    },
    'upstream prepare single_and_multiple_arguments_as_part_of_command_with_shell' => static function (TestRunner $t) use ($argv): void {
        $t->same(
            ['sh', '-c', 'ls first second third', '--'],
            $argv(GitCommand::prepare('ls first second third')->commandMayBeShellScript()),
        );
    },
    'upstream prepare single_and_multiple_arguments_as_part_of_command_with_given_shell' => static function (TestRunner $t) use ($argv): void {
        $t->same(
            ['/somepath/to/bash', '-c', 'ls first second third', '--'],
            $argv(
                GitCommand::prepare('ls first second third')
                    ->commandMayBeShellScript()
                    ->withShellProgram('/somepath/to/bash'),
            ),
        );
    },
    'upstream prepare single_and_complex_arguments_as_part_of_command_with_shell' => static function (TestRunner $t) use ($argv): void {
        $t->same(
            ['sh', '-c', 'ls --foo "a b" "$@"', '--', 'additional'],
            $argv(GitCommand::prepare('ls --foo "a b"')->arg('additional')->commandMayBeShellScript()),
        );
    },
    'upstream prepare single_and_complex_arguments_with_auto_split' => static function (TestRunner $t) use ($argv): void {
        $t->same(
            ['ls', '--foo=a b'],
            $argv(GitCommand::prepare('ls --foo="a b"')->commandMayBeShellScriptAllowManualArgumentSplitting()),
        );
    },
    'upstream prepare single_and_complex_arguments_without_auto_split' => static function (TestRunner $t) use ($argv): void {
        $t->same(
            ['sh', '-c', 'ls --foo="a b"', '--'],
            $argv(GitCommand::prepare('ls --foo="a b"')->commandMayBeShellScriptDisallowManualArgumentSplitting()),
        );
    },
    'upstream prepare single_and_simple_arguments_without_auto_split_with_shell' => static function (TestRunner $t) use ($argv): void {
        $t->same(
            ['sh', '-c', 'ls "$@"', '--', '--foo=a b'],
            $argv(GitCommand::prepare('ls')->arg('--foo=a b')->withShell()),
        );
    },
    'upstream prepare quoted_command_without_argument_splitting' => static function (TestRunner $t) use ($argv): void {
        $t->same(
            ['sh', '-c', '\'ls\' "$@"', '--', '--foo=a b'],
            $argv(GitCommand::prepare('ls')->arg('--foo=a b')->withShell()->withQuotedCommand()),
        );
    },
    'upstream prepare quoted_windows_command_without_argument_splitting' => static function (TestRunner $t) use ($argv): void {
        $t->same(
            ['sh', '-c', '\'C:\\Users\\O\'\\\'\'Shaughnessy\\with space.exe\' "$@"', '--', '--foo=\'a b\''],
            $argv(
                GitCommand::prepare('C:\\Users\\O\'Shaughnessy\\with space.exe')
                    ->arg('--foo=\'a b\'')
                    ->withShell()
                    ->withQuotedCommand(),
            ),
        );
    },
    'upstream prepare single_and_complex_arguments_will_not_auto_split_on_special_characters' => static function (TestRunner $t) use ($argv): void {
        $t->same(
            ['sh', '-c', 'ls --foo=~/path', '--'],
            $argv(GitCommand::prepare('ls --foo=~/path')->commandMayBeShellScriptAllowManualArgumentSplitting()),
        );
    },
    'upstream prepare tilde_path_and_multiple_arguments_as_part_of_command_with_shell' => static function (TestRunner $t) use ($argv): void {
        $t->same(
            ['sh', '-c', '~/bin/exe --foo "a b"', '--'],
            $argv(GitCommand::prepare('~/bin/exe --foo "a b"')->commandMayBeShellScript()),
        );
    },
    'upstream prepare script_with_dollar_at' => static function (TestRunner $t) use ($argv): void {
        $t->same(
            ['sh', '-c', 'echo "$@" >&2', '--', 'store'],
            $argv(GitCommand::prepare('echo "$@" >&2')->commandMayBeShellScript()->arg('store')),
        );
    },
    'upstream prepare script_with_dollar_at_has_no_quoting' => static function (TestRunner $t) use ($argv): void {
        $t->same(
            ['sh', '-c', 'echo "$@" >&2', '--', 'store'],
            $argv(
                GitCommand::prepare('echo "$@" >&2')
                    ->commandMayBeShellScript()
                    ->withQuotedCommand()
                    ->arg('store'),
            ),
        );
    },
];
