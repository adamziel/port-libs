<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitValidate;

$assertSubmoduleError = static function (TestRunner $t, string $name, string $input, string $expected): void {
    $t->same($expected, GitValidate::validateSubmoduleName($input), $name);
};

$assertTagValid = static function (TestRunner $t, string $name, string $input): void {
    $t->same(null, GitValidate::validateTagName($input), $name);
};

$assertTagError = static function (TestRunner $t, string $name, string $input, string $expected): void {
    $t->same($expected, GitValidate::validateTagName($input), $name);
};

$assertTagSanitized = static function (TestRunner $t, string $name, string $input, string $expected): void {
    $actual = GitValidate::sanitizeReferenceNamePartial($input);

    $t->same($expected, $actual, $name);
    $t->same(true, GitValidate::isValidReferenceNamePartial($actual), "{$name} sanitized partial is valid");
};

return [
    'upstream gix-validate submodule name valid' => static function (TestRunner $t): void {
        foreach (['a/./b/..[', '..a/./b/', '..a\\./b\\', '你好'] as $name) {
            $t->same(null, GitValidate::validateSubmoduleName($name), "{$name} should be valid");
        }
    },

    'upstream gix-validate submodule name invalid' => static function (TestRunner $t) use ($assertSubmoduleError): void {
        foreach ([
            ['empty', '', GitValidate::ERROR_EMPTY],
            ['starts_with_parent_component', '../', GitValidate::ERROR_PARENT_COMPONENT],
            ['parent_component_in_middle', 'hi/../ho', GitValidate::ERROR_PARENT_COMPONENT],
            ['ends_with_parent_component', 'hi/ho/..', GitValidate::ERROR_PARENT_COMPONENT],
            ['only_parent_component', '..', GitValidate::ERROR_PARENT_COMPONENT],
            ['starts_with_parent_component_backslash', '..\\', GitValidate::ERROR_PARENT_COMPONENT],
            ['parent_component_in_middle_backslash', 'hi\\..\\ho', GitValidate::ERROR_PARENT_COMPONENT],
            ['ends_with_parent_component_backslash', 'hi\\ho\\..', GitValidate::ERROR_PARENT_COMPONENT],
            ['traversal_after_a_benign_double_dot_is_rejected', 'a..b/../../../.git/', GitValidate::ERROR_PARENT_COMPONENT],
            ['backslash_traversal_after_a_benign_double_dot_is_rejected', 'a..b\\..\\..\\..\\.git\\', GitValidate::ERROR_PARENT_COMPONENT],
        ] as [$name, $input, $expected]) {
            $assertSubmoduleError($t, $name, $input, $expected);
        }
    },

    'upstream gix-validate tag name valid' => static function (TestRunner $t) use ($assertTagValid): void {
        foreach ([
            ['an_at_sign', '@'],
            ['chinese_utf8', '你好吗'],
            ['non_text', '😅🙌'],
            ['contains_an_at', 'hello@foo'],
            ['contains_dot_lock', 'file.lock.ext'],
            ['contains_brackets', 'this_{is-fine}_too'],
            ['contains_brackets_and_at', 'this_{@is-fine@}_too'],
            ['dot_in_the_middle', 'token.other'],
            ['slash_inbetween', 'hello/world'],
        ] as [$name, $input]) {
            $assertTagValid($t, $name, $input);
        }
    },

    'upstream gix-validate tag reference sanitizer' => static function (TestRunner $t) use ($assertTagSanitized): void {
        foreach ([
            ['an_at_sign_san', '@', '@'],
            ['chinese_utf8_san', '你好吗', '你好吗'],
            ['non_text_san', '😅🙌', '😅🙌'],
            ['contains_an_at_san', 'hello@foo', 'hello@foo'],
            ['contains_dot_lock_san', 'file.lock.ext', 'file.lock.ext'],
            ['contains_brackets_san', 'this_{is-fine}_too', 'this_{is-fine}_too'],
            ['contains_brackets_and_at_san', 'this_{@is-fine@}_too', 'this_{@is-fine@}_too'],
            ['dot_in_the_middle_san', 'token.other', 'token.other'],
            ['slash_inbetween_san', 'hello/world', 'hello/world'],
            ['contains_ref_log_portion_san', 'this_looks_like_a_@{reflog}', 'this_looks_like_a_@-reflog}'],
            ['too_many_dots_san', '......', '-'],
            ['too_many_dots_and_slashes_san', '//....///....///', '-/-'],
            ['suffix_is_dot_lock_san', 'prefix.lock', 'prefix'],
            ['suffix_is_dot_lock_multiple_san', 'prefix.lock.lock', 'prefix'],
            ['empty_component_san', 'prefix//suffix', 'prefix/suffix'],
            ['ends_with_slash_san', 'prefix/', 'prefix'],
            ['dot_lock_in_component_san', 'foo.lock/baz.lock/bar', 'foo/baz/bar'],
            ['dot_lock_in_each_component_san', 'foo.lock/baz.lock/bar.lock', 'foo/baz/bar'],
            ['multiple_dot_lock_in_each_component_san', 'foo.lock.lock/baz.lock.lock/bar.lock.lock', 'foo/baz/bar'],
            ['dot_lock_in_each_component_special_san', '...lock/..lock//lock', '-lock/lock'],
            ['is_dot_lock_san', '.lock', '-lock'],
            ['contains_double_dot_san', 'with..double-dot', 'with.double-dot'],
            ['starts_with_double_dot_san', '..with-double-dot', '-with-double-dot'],
            ['ends_with_double_dot_san', 'with-double-dot..', 'with-double-dot-'],
            ['starts_with_asterisk_san', '*suffix', '-suffix'],
            ['starts_with_slash_san', '/suffix', 'suffix'],
            ['ends_with_asterisk_san', 'prefix*', 'prefix-'],
            ['contains_asterisk_san', 'prefix*suffix', 'prefix-suffix'],
            ['contains_null_san', "prefix\0suffix", 'prefix-suffix'],
            ['contains_bell_san', "prefix\x07suffix", 'prefix-suffix'],
            ['contains_backspace_san', "prefix\x08suffix", 'prefix-suffix'],
            ['contains_vertical_tab_san', "prefix\x0bsuffix", 'prefix-suffix'],
            ['contains_form_feed_san', "prefix\x0csuffix", 'prefix-suffix'],
            ['contains_ctrl_z_san', "prefix\x1asuffix", 'prefix-suffix'],
            ['contains_esc_san', "prefix\x1bsuffix", 'prefix-suffix'],
            ['contains_colon_san', 'prefix:suffix', 'prefix-suffix'],
            ['contains_questionmark_san', 'prefix?suffix', 'prefix-suffix'],
            ['contains_open_bracket_san', 'prefix[suffix', 'prefix-suffix'],
            ['contains_backslash_san', 'prefix\\suffix', 'prefix-suffix'],
            ['contains_circumflex_san', 'prefix^suffix', 'prefix-suffix'],
            ['contains_tilde_san', 'prefix~suffix', 'prefix-suffix'],
            ['contains_space_san', 'prefix suffix', 'prefix-suffix'],
            ['contains_tab_san', "prefix\tsuffix", 'prefix-suffix'],
            ['contains_newline_san', "prefix\nsuffix", 'prefix-suffix'],
            ['contains_carriage_return_san', "prefix\rsuffix", 'prefix-suffix'],
            ['starts_with_dot_san', '.with-dot', '-with-dot'],
            ['ends_with_dot_san', 'with-dot.', 'with-dot-'],
            ['empty_san', '', '-'],
        ] as [$name, $input, $expected]) {
            $assertTagSanitized($t, $name, $input, $expected);
        }
    },

    'upstream gix-validate tag name invalid' => static function (TestRunner $t) use ($assertTagError): void {
        foreach ([
            ['contains_ref_log_portion', 'this_looks_like_a_@{reflog}', GitValidate::ERROR_REFLOG_PORTION],
            ['suffix_is_dot_lock', 'prefix.lock', GitValidate::ERROR_LOCK_FILE_SUFFIX],
            ['too_many_dots', '......', GitValidate::ERROR_REPEATED_DOT],
            ['suffix_is_dot_lock_multiple', 'prefix.lock.lock', GitValidate::ERROR_LOCK_FILE_SUFFIX],
            ['ends_with_slash', 'prefix/', GitValidate::ERROR_ENDS_WITH_SLASH],
            ['empty_component', 'prefix//suffix', GitValidate::ERROR_REPEATED_SLASH],
            ['is_dot_lock', '.lock', GitValidate::ERROR_STARTS_WITH_DOT],
            ['dot_lock_in_component', 'foo.lock/baz.lock/bar', GitValidate::ERROR_LOCK_FILE_SUFFIX],
            ['contains_double_dot', 'with..double-dot', GitValidate::ERROR_REPEATED_DOT],
            ['starts_with_double_dot', '..with-double-dot', GitValidate::ERROR_REPEATED_DOT],
            ['ends_with_double_dot', 'with-double-dot..', GitValidate::ERROR_REPEATED_DOT],
            ['starts_with_asterisk', '*suffix', GitValidate::ERROR_ASTERISK],
            ['starts_with_slash', '/suffix', GitValidate::ERROR_STARTS_WITH_SLASH],
            ['ends_with_asterisk', 'prefix*', GitValidate::ERROR_ASTERISK],
            ['contains_asterisk', 'prefix*suffix', GitValidate::ERROR_ASTERISK],
            ['contains_null', "prefix\0suffix", GitValidate::ERROR_INVALID_BYTE],
            ['contains_bell', "prefix\x07suffix", GitValidate::ERROR_INVALID_BYTE],
            ['contains_backspace', "prefix\x08suffix", GitValidate::ERROR_INVALID_BYTE],
            ['contains_vertical_tab', "prefix\x0bsuffix", GitValidate::ERROR_INVALID_BYTE],
            ['contains_form_feed', "prefix\x0csuffix", GitValidate::ERROR_INVALID_BYTE],
            ['contains_ctrl_z', "prefix\x1asuffix", GitValidate::ERROR_INVALID_BYTE],
            ['contains_esc', "prefix\x1bsuffix", GitValidate::ERROR_INVALID_BYTE],
            ['contains_colon', 'prefix:suffix', GitValidate::ERROR_INVALID_BYTE],
            ['contains_questionmark', 'prefix?suffix', GitValidate::ERROR_INVALID_BYTE],
            ['contains_open_bracket', 'prefix[suffix', GitValidate::ERROR_INVALID_BYTE],
            ['contains_backslash', 'prefix\\suffix', GitValidate::ERROR_INVALID_BYTE],
            ['contains_circumflex', 'prefix^suffix', GitValidate::ERROR_INVALID_BYTE],
            ['contains_tilde', 'prefix~suffix', GitValidate::ERROR_INVALID_BYTE],
            ['contains_space', 'prefix suffix', GitValidate::ERROR_INVALID_BYTE],
            ['contains_tab', "prefix\tsuffix", GitValidate::ERROR_INVALID_BYTE],
            ['contains_newline', "prefix\nsuffix", GitValidate::ERROR_INVALID_BYTE],
            ['contains_carriage_return', "prefix\rsuffix", GitValidate::ERROR_INVALID_BYTE],
            ['starts_with_dot', '.with-dot', GitValidate::ERROR_STARTS_WITH_DOT],
            ['ends_with_dot', 'with-dot.', GitValidate::ERROR_ENDS_WITH_DOT],
            ['empty', '', GitValidate::ERROR_EMPTY],
        ] as [$name, $input, $expected]) {
            $assertTagError($t, $name, $input, $expected);
        }
    },
];
