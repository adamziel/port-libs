<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitPrompt;

return [
    'options::apply_environment::git_askpass_overrides_everything_and_ssh_askpass_does_not' => static function (TestRunner $t): void {
        $options = new GitPrompt(askpass: 'current');

        $applied = $options->applyEnvironment(true, true, false, [
            'GIT_ASKPASS' => 'override',
            'SSH_ASKPASS' => 'does not matter',
        ]);

        $t->same('override', $applied->askpass);
    },

    'options::apply_environment::git_askpass_is_used_first_and_sets_unset_askpass_values' => static function (TestRunner $t): void {
        $applied = (new GitPrompt())->applyEnvironment(true, true, false, [
            'GIT_ASKPASS' => 'from-env',
            'SSH_ASKPASS' => 'does not matter',
        ]);

        $t->same('from-env', $applied->askpass);
    },

    'options::apply_environment::ssh_askpass_is_used_as_fallback' => static function (TestRunner $t): void {
        $options = new GitPrompt(mode: GitPrompt::MODE_VISIBLE);

        $applied = $options->applyEnvironment(true, true, false, [
            'SSH_ASKPASS' => 'fallback',
        ]);

        $t->same('fallback', $applied->askpass);
    },

    'options::apply_environment::ssh_askpass_does_not_override_current_value' => static function (TestRunner $t): void {
        $options = new GitPrompt(askpass: 'current');

        $applied = $options->applyEnvironment(true, true, false, [
            'SSH_ASKPASS' => 'fallback',
        ]);

        $t->same('current', $applied->askpass);
    },

    'options::apply_environment::mode_is_left_untouched_if_terminal_prompt_is_trueish' => static function (TestRunner $t): void {
        $options = new GitPrompt(mode: GitPrompt::MODE_HIDDEN);

        $applied = $options->applyEnvironment(false, false, true, [
            'GIT_TERMINAL_PROMPT' => 'true',
        ]);

        $t->same(GitPrompt::MODE_HIDDEN, $applied->mode);
    },

    'options::apply_environment::mode_is_disabled_if_terminal_prompt_is_falseish' => static function (TestRunner $t): void {
        $options = new GitPrompt(mode: GitPrompt::MODE_HIDDEN);

        $applied = $options->applyEnvironment(false, false, true, [
            'GIT_TERMINAL_PROMPT' => '0',
        ]);

        $t->same(GitPrompt::MODE_DISABLE, $applied->mode);
    },

    'options::apply_environment::mode_is_unchanged_if_git_terminal_prompt_is_not_set' => static function (TestRunner $t): void {
        $options = new GitPrompt(mode: GitPrompt::MODE_HIDDEN);

        $applied = $options->applyEnvironment(false, false, true, []);

        $t->same(GitPrompt::MODE_HIDDEN, $applied->mode);
    },
];
