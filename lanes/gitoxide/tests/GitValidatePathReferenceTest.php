<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitValidate;

$allOpts = GitValidate::pathComponentOptions();
$noOpts = GitValidate::pathComponentOptions(false, false, false);
$unixOpts = GitValidate::pathComponentOptions(false, true, true);
$symlink = GitValidate::PATH_MODE_SYMLINK;

$assertPathComponentValid = static function (
    TestRunner $t,
    string $name,
    string $input,
    ?string $mode = null,
    ?array $options = null
) use ($allOpts): void {
    $t->same(null, GitValidate::validatePathComponent($input, $mode, $options ?? $allOpts), $name);
};

$assertPathComponentError = static function (
    TestRunner $t,
    string $name,
    string $input,
    string $expected,
    ?string $mode = null,
    ?array $options = null
) use ($allOpts): void {
    $t->same($expected, GitValidate::validatePathComponent($input, $mode, $options ?? $allOpts), $name);
};

$assertReferencePartialValid = static function (TestRunner $t, string $name, string $input): void {
    $t->same(null, GitValidate::validateReferenceNamePartial($input), $name);
};

$assertReferencePartialError = static function (TestRunner $t, string $name, string $input, string $expected): void {
    $t->same($expected, GitValidate::validateReferenceNamePartial($input), $name);
};

$assertReferenceNameValid = static function (TestRunner $t, string $name, string $input): void {
    $t->same(null, GitValidate::validateReferenceName($input), $name);
};

$assertReferenceNameError = static function (TestRunner $t, string $name, string $input, string $expected): void {
    $t->same($expected, GitValidate::validateReferenceName($input), $name);
};

$assertReferenceSanitized = static function (TestRunner $t, string $name, string $input, string $expected): void {
    $actual = GitValidate::sanitizeReferenceNamePartial($input);

    $t->same($expected, $actual, $name);
    $t->same(true, GitValidate::isValidReferenceNamePartial($actual), "{$name} sanitized partial is valid");
};

return [
    'upstream gix-validate path component_is_windows_device' => static function (TestRunner $t): void {
        foreach (['con', 'CONIN$', 'lpt1.txt', 'AUX', 'Prn', 'NUL', 'COM9', 'nul.a.b '] as $device) {
            $t->same(true, GitValidate::isWindowsDevicePathComponent($device), "{$device} should be a Windows device");
        }

        foreach (['coni', 'CONIN', 'lpt', 'AUXi', 'aPrn', 'NULl', 'COM', 'a.nul.b '] as $notDevice) {
            $t->same(false, GitValidate::isWindowsDevicePathComponent($notDevice), "{$notDevice} should not be a Windows device");
        }
    },

    'upstream gix-validate path component valid mktest cases' => static function (TestRunner $t) use (
        $assertPathComponentValid,
        $allOpts,
        $noOpts,
        $unixOpts,
        $symlink
    ): void {
        foreach ([
            ['ascii', 'ascii-only_and-that'],
            ['unicode', '😁👍👌'],
            ['backslashes_on_unix', '\\', null, $unixOpts],
            ['drive_letters_on_unix', 'c:', null, $unixOpts],
            ['virtual_drive_letters_on_unix', '֍:', null, $unixOpts],
            ['unc_path_on_unix', '\\\\?\\pictures', null, $unixOpts],
            ['not_dot_git_longer', '.gitu', null, $noOpts],
            ['not_dot_git_longer_all', '.gitu'],
            ['not_dot_gitmodules_shorter', '.gitmodule', $symlink, $noOpts],
            ['not_dot_gitmodules_shorter_all', '.gitmodule', $symlink, $allOpts],
            ['not_dot_gitmodules_longer', '.gitmodulesa', $symlink, $noOpts],
            ['not_dot_gitmodules_longer_all', '.gitmodulesa', $symlink, $allOpts],
            ['dot_gitmodules_as_file', '.gitmodules', null, $unixOpts],
            ['starts_with_dot_git_with_backslashes_on_linux', '.git\\hooks\\pre-commit', null, $unixOpts],
            ['not_dot_git_shorter', '.gi', null, $noOpts],
            ['not_dot_git_shorter_ntfs_8_3', 'gi~1'],
            ['not_dot_git_longer_ntfs_8_3', 'gitu~1'],
            ['not_dot_git_shorter_ntfs_8_3_disabled', 'git~1', null, $noOpts],
            ['not_dot_git_longer_hfs', ".g\u{200c}itu"],
            ['not_dot_git_shorter_hfs', ".g\u{200c}i"],
            ['com_0_lower', 'com0'],
            ['com_without_number_0_lower', 'comm'],
            ['conout_without_dollar_with_extension', 'conout.c'],
            ['conin_without_dollar_with_extension', 'conin.c'],
            ['conin_without_dollar', 'conin'],
            ['not_con', 'com'],
            ['also_not_con', 'co'],
            ['con_as_middle', 'x.CON.zip'],
            ['con_after_space', ' CON'],
            ['con_after_space_mixed', ' coN.tar.xz'],
            ['not_nul', 'null'],
            ['not_dot_gitmodules_shorter_hfs', ".gitm\u{200c}odule", $symlink, $unixOpts],
            ['dot_gitmodules_as_file_hfs', ".g\u{200c}itmodules", null, $unixOpts],
            ['dot_gitmodules_ntfs_8_3_disabled', 'gItMOD~1', $symlink, $noOpts],
            ['not_dot_gitmodules_longer_hfs', "\u{200c}.gitmodulesa", $symlink, $unixOpts],
        ] as $case) {
            [$name, $input, $mode, $options] = $case + [2 => null, 3 => $allOpts];
            $assertPathComponentValid($t, $name, $input, $mode, $options);
        }
    },

    'upstream gix-validate path component invalid mktest cases' => static function (TestRunner $t) use (
        $assertPathComponentError,
        $allOpts,
        $noOpts,
        $symlink
    ): void {
        foreach ([
            ['empty', '', GitValidate::ERROR_EMPTY],
            ['dot_git_lower', '.git', GitValidate::ERROR_DOT_GIT_DIR, null, $noOpts],
            ['dot_git_lower_hfs', ".g\u{200c}it", GitValidate::ERROR_DOT_GIT_DIR],
            ['dot_git_mixed_hfs_simple', '.Git', GitValidate::ERROR_DOT_GIT_DIR],
            ['dot_git_upper', '.GIT', GitValidate::ERROR_DOT_GIT_DIR, null, $noOpts],
            ['starts_with_dot_git_with_backslashes_on_windows', '.git\\hooks\\pre-commit', GitValidate::ERROR_PATH_SEPARATOR],
            ['dot_git_upper_hfs', ".GIT\u{200e}", GitValidate::ERROR_DOT_GIT_DIR],
            ['dot_git_upper_ntfs_8_3', 'GIT~1', GitValidate::ERROR_DOT_GIT_DIR],
            ['dot_git_mixed', '.gIt', GitValidate::ERROR_DOT_GIT_DIR, null, $noOpts],
            ['dot_git_mixed_ntfs_8_3', 'gIt~1', GitValidate::ERROR_DOT_GIT_DIR],
            ['dot_gitmodules_mixed', '.gItmodules', GitValidate::ERROR_SYMLINKED_GIT_MODULES, $symlink, $noOpts],
            ['dot_git_mixed_hfs', "\u{206e}.gIt", GitValidate::ERROR_DOT_GIT_DIR],
            ['dot_git_ntfs_8_3_numbers_only', '~1000000', GitValidate::ERROR_SYMLINKED_GIT_MODULES, $symlink, $allOpts],
            ['dot_git_ntfs_8_3_numbers_only_too', '~9999999', GitValidate::ERROR_SYMLINKED_GIT_MODULES, $symlink, $allOpts],
            ['dot_gitmodules_mixed_hfs', "\u{206e}.gItmodules", GitValidate::ERROR_SYMLINKED_GIT_MODULES, $symlink, $allOpts],
            ['dot_gitmodules_mixed_ntfs_8_3', 'gItMOD~1', GitValidate::ERROR_SYMLINKED_GIT_MODULES, $symlink, $allOpts],
            ['dot_gitmodules_mixed_ntfs_stream', '.giTmodUles:$DATA', GitValidate::ERROR_SYMLINKED_GIT_MODULES, $symlink, $allOpts],
            ['dot_gitmodules_lower_ntfs_stream_default_implicit', '.gitmodules::$DATA', GitValidate::ERROR_SYMLINKED_GIT_MODULES, $symlink, $allOpts],
            ['ntfs_stream_default_implicit', 'file::$DATA', GitValidate::ERROR_WINDOWS_ILLEGAL_CHARACTER],
            ['ntfs_stream_explicit', 'file:ANYTHING_REALLY:$DATA', GitValidate::ERROR_WINDOWS_ILLEGAL_CHARACTER],
            ['dot_gitmodules_lower_ntfs_stream', '.gitmodules:$DATA:$DATA', GitValidate::ERROR_SYMLINKED_GIT_MODULES, $symlink, $allOpts],
            ['not_gitmodules_trailing_space', '.gitmodules x ', GitValidate::ERROR_WINDOWS_ILLEGAL_CHARACTER],
            ['not_gitmodules_trailing_stream', '.gitmodules,:$DATA', GitValidate::ERROR_WINDOWS_ILLEGAL_CHARACTER],
            ['path_separator_slash_between', 'a/b', GitValidate::ERROR_PATH_SEPARATOR],
            ['path_separator_slash_leading', '/a', GitValidate::ERROR_PATH_SEPARATOR],
            ['path_separator_slash_trailing', 'a/', GitValidate::ERROR_PATH_SEPARATOR],
            ['path_separator_slash_only', '/', GitValidate::ERROR_PATH_SEPARATOR],
            ['slashes_on_windows', '/', GitValidate::ERROR_PATH_SEPARATOR, null, $allOpts],
            ['backslashes_on_windows', '\\', GitValidate::ERROR_PATH_SEPARATOR, null, $allOpts],
            ['path_separator_backslash_between', 'a\\b', GitValidate::ERROR_PATH_SEPARATOR],
            ['path_separator_backslash_leading', '\\a', GitValidate::ERROR_PATH_SEPARATOR],
            ['path_separator_backslash_trailing', 'a\\', GitValidate::ERROR_PATH_SEPARATOR],
            ['aux_mixed', 'Aux', GitValidate::ERROR_WINDOWS_RESERVED_NAME],
            ['aux_with_extension', 'aux.c', GitValidate::ERROR_WINDOWS_RESERVED_NAME],
            ['com_lower', 'com1', GitValidate::ERROR_WINDOWS_RESERVED_NAME],
            ['com_upper_with_extension', 'COM9.c', GitValidate::ERROR_WINDOWS_RESERVED_NAME],
            ['trailing_space', 'win32 ', GitValidate::ERROR_WINDOWS_ILLEGAL_CHARACTER],
            ['trailing_dot', 'win32.', GitValidate::ERROR_WINDOWS_ILLEGAL_CHARACTER],
            ['trailing_dot_dot', 'win32 . .', GitValidate::ERROR_WINDOWS_ILLEGAL_CHARACTER],
            ['colon_inbetween', 'colon:separates', GitValidate::ERROR_WINDOWS_ILLEGAL_CHARACTER],
            ['left_arrow', 'arrow<left', GitValidate::ERROR_WINDOWS_ILLEGAL_CHARACTER],
            ['right_arrow', 'arrow>right', GitValidate::ERROR_WINDOWS_ILLEGAL_CHARACTER],
            ['apostrophe', 'a"b', GitValidate::ERROR_WINDOWS_ILLEGAL_CHARACTER],
            ['pipe', 'a|b', GitValidate::ERROR_WINDOWS_ILLEGAL_CHARACTER],
            ['questionmark', 'a?b', GitValidate::ERROR_WINDOWS_ILLEGAL_CHARACTER],
            ['asterisk', 'a*b', GitValidate::ERROR_WINDOWS_ILLEGAL_CHARACTER],
            ['lpt_mixed_with_number', 'LPt8', GitValidate::ERROR_WINDOWS_RESERVED_NAME],
            ['nul_mixed', 'NuL', GitValidate::ERROR_WINDOWS_RESERVED_NAME],
            ['prn_mixed_with_extension', 'PrN.abc', GitValidate::ERROR_WINDOWS_RESERVED_NAME],
            ['con', 'CON', GitValidate::ERROR_WINDOWS_RESERVED_NAME],
            ['con_with_extension', 'CON.abc', GitValidate::ERROR_WINDOWS_RESERVED_NAME],
            ['con_with_middle', 'CON.tar.xz', GitValidate::ERROR_WINDOWS_RESERVED_NAME],
            ['con_mixed_with_middle', 'coN.tar.xz ', GitValidate::ERROR_WINDOWS_RESERVED_NAME],
            ['dot_dot', '..', GitValidate::ERROR_RELATIVE],
            ['dot_dot_no_opts', '..', GitValidate::ERROR_RELATIVE, null, $noOpts],
            ['single_dot', '.', GitValidate::ERROR_RELATIVE],
            ['single_dot_no_opts', '.', GitValidate::ERROR_RELATIVE, null, $noOpts],
            ['conout_mixed_with_extension', 'ConOut$  .xyz', GitValidate::ERROR_WINDOWS_RESERVED_NAME],
            ['conin_mixed', 'conIn$  ', GitValidate::ERROR_WINDOWS_RESERVED_NAME],
            ['drive_letters', 'c:', GitValidate::ERROR_WINDOWS_PATH_PREFIX, null, $allOpts],
            ['virtual_drive_letters', '֍:', GitValidate::ERROR_WINDOWS_PATH_PREFIX, null, $allOpts],
            ['unc_net_path', '\\\\host', GitValidate::ERROR_PATH_SEPARATOR, null, $allOpts],
            ['unc_path', '\\\\?\\pictures', GitValidate::ERROR_PATH_SEPARATOR, null, $allOpts],
            ['unc_device_path', '\\\\.\\pictures', GitValidate::ERROR_PATH_SEPARATOR, null, $allOpts],
            ['unc_nt_obj_path', '\\??\\pictures', GitValidate::ERROR_PATH_SEPARATOR, null, $allOpts],
        ] as $case) {
            [$name, $input, $expected, $mode, $options] = $case + [3 => null, 4 => $allOpts];
            $assertPathComponentError($t, $name, $input, $expected, $mode, $options);
        }
    },

    'upstream gix-validate path component ntfs_gitmodules' => static function (TestRunner $t) use (
        $assertPathComponentError,
        $assertPathComponentValid,
        $allOpts,
        $symlink
    ): void {
        foreach ([
            '.gitmodules',
            '.Gitmodules',
            '.gitmoduleS',
            '.gitmodules ',
            '.gitmodules.',
            '.gitmodules  ',
            '.gitmodules. ',
            '.gitmodules .',
            '.gitmodules..',
            '.gitmodules   ',
            '.gitmodules.  ',
            '.gitmodules . ',
            '.gitmodules  .',
            '.Gitmodules ',
            '.Gitmodules.',
            '.Gitmodules  ',
            '.Gitmodules. ',
            '.Gitmodules .',
            '.Gitmodules..',
            '.Gitmodules   ',
            '.Gitmodules.  ',
            '.Gitmodules . ',
            '.Gitmodules  .',
            'GITMOD~1',
            'gitmod~1',
            'GITMOD~2',
            'giTmod~3',
            'GITMOD~4',
            'GITMOD~1 ',
            'gitMod~2.',
            'GITMOD~3  ',
            'gitmod~4. ',
            'GITMoD~1 .',
            'gitmod~2   ',
            'GITMOD~3.  ',
            'gitmoD~4 . ',
            'GI7EBA~1',
            'gi7eba~9',
            'GI7EB~10',
            'GI7EB~11',
            'GI7EB~99',
            'GI7EB~10',
            'GI7E~100',
            'GI7E~101',
            'GI7E~999',
            '.gitmodules:$DATA',
            'gitmod~4 . :$DATA',
        ] as $invalid) {
            $assertPathComponentError(
                $t,
                "ntfs_gitmodules invalid {$invalid}",
                $invalid,
                GitValidate::ERROR_SYMLINKED_GIT_MODULES,
                $symlink,
                $allOpts
            );
        }

        foreach ([
            '.gitmodules x',
            '.gitmodules .x',
            ' .gitmodules',
            '..gitmodules',
            'gitmodules',
            '.gitmodule',
            '.gitmodules .x',
            'GI7EBA~',
            'GI7EBA~0',
            'GI7EBA~~1',
            'GI7EBA~X',
            'Gx7EBA~1',
            'GI7EBX~1',
            'GI7EB~1',
            'GI7EB~01',
            'GI7EB~1X',
        ] as $valid) {
            $assertPathComponentValid($t, "ntfs_gitmodules valid {$valid}", $valid, $symlink, $allOpts);
        }
    },

    'upstream gix-validate reference name_partial valid mktest cases' => static function (TestRunner $t) use (
        $assertReferencePartialValid
    ): void {
        foreach ([
            ['refs_path', 'refs/heads/main'],
            ['main_worktree_pseudo_ref', 'main-worktree/HEAD'],
            ['main_worktree_ref', 'main-worktree/refs/bisect/good'],
            ['other_worktree_pseudo_ref', 'worktrees/id/HEAD'],
            ['other_worktree_ref', 'worktrees/id/refs/bisect/good'],
            ['worktree_private_ref', 'refs/worktree/private'],
            ['refs_path_with_file_extension', 'refs/heads/main.ext'],
            ['refs_path_underscores_and_dashes', 'refs/heads/main-2nd_ext'],
            ['relative_path', 'etc/foo'],
            ['all_uppercase', 'MAIN'],
            ['all_uppercase_with_underscore', 'NEW_HEAD'],
            ['partial_name_lowercase', 'main'],
            ['chinese_utf8', 'heads/你好吗'],
            ['parentheses_special_case_upload_pack', '(null)'],
        ] as [$name, $input]) {
            $assertReferencePartialValid($t, "name_partial valid {$name}", $input);
        }
    },

    'upstream gix-validate reference name_partial valid mktests sanitizer cases' => static function (TestRunner $t) use (
        $assertReferenceSanitized
    ): void {
        foreach ([
            ['refs_path_san', 'refs/heads/main', 'refs/heads/main'],
            ['main_worktree_pseudo_ref_san', 'main-worktree/HEAD', 'main-worktree/HEAD'],
            ['main_worktree_ref_san', 'main-worktree/refs/bisect/good', 'main-worktree/refs/bisect/good'],
            ['other_worktree_pseudo_ref_san', 'worktrees/id/HEAD', 'worktrees/id/HEAD'],
            ['other_worktree_ref_san', 'worktrees/id/refs/bisect/good', 'worktrees/id/refs/bisect/good'],
            ['worktree_private_ref_san', 'refs/worktree/private', 'refs/worktree/private'],
            ['refs_path_with_file_extension_san', 'refs/heads/main.ext', 'refs/heads/main.ext'],
            ['refs_path_underscores_and_dashes_san', 'refs/heads/main-2nd_ext', 'refs/heads/main-2nd_ext'],
            ['relative_path_san', 'etc/foo', 'etc/foo'],
            ['all_uppercase_san', 'MAIN', 'MAIN'],
            ['all_uppercase_with_underscore_san', 'NEW_HEAD', 'NEW_HEAD'],
            ['partial_name_lowercase_san', 'main', 'main'],
            ['chinese_utf8_san', 'heads/你好吗', 'heads/你好吗'],
            ['parentheses_special_case_upload_pack_san', '(null)', '(null)'],
        ] as [$name, $input, $expected]) {
            $assertReferenceSanitized($t, "name_partial valid {$name}", $input, $expected);
        }
    },

    'upstream gix-validate reference name_partial invalid mktest cases' => static function (TestRunner $t) use (
        $assertReferencePartialError
    ): void {
        foreach ([
            ['refs_path_double_dot', 'refs/../somewhere', GitValidate::ERROR_STARTS_WITH_DOT],
            ['refs_path_name_starts_with_dot', '.refs/somewhere', GitValidate::ERROR_STARTS_WITH_DOT],
            ['refs_path_name_starts_with_multi_dot', '..refs/somewhere', GitValidate::ERROR_REPEATED_DOT],
            ['refs_path_component_is_singular_dot', 'refs/./still-inside-but-not-cool', GitValidate::ERROR_STARTS_WITH_DOT],
            ['any_path_starts_with_slash', '/etc/foo', GitValidate::ERROR_STARTS_WITH_SLASH],
            ['empty_path', '', GitValidate::ERROR_EMPTY],
            ['refs_starts_with_slash', '/refs/heads/main', GitValidate::ERROR_STARTS_WITH_SLASH],
            ['ends_with_slash', 'refs/heads/main/', GitValidate::ERROR_ENDS_WITH_SLASH],
            ['path_with_duplicate_slashes', 'refs//heads/main', GitValidate::ERROR_REPEATED_SLASH],
            ['path_with_spaces', 'refs/heads/name with spaces', GitValidate::ERROR_INVALID_BYTE],
            ['path_with_backslashes', 'refs\\heads/name with spaces', GitValidate::ERROR_INVALID_BYTE],
        ] as [$name, $input, $expected]) {
            $assertReferencePartialError($t, "name_partial invalid {$name}", $input, $expected);
        }
    },

    'upstream gix-validate reference name_partial invalid mktests sanitizer cases' => static function (TestRunner $t) use (
        $assertReferenceSanitized
    ): void {
        foreach ([
            ['refs_path_double_dot_san', 'refs/../somewhere', 'refs/-/somewhere'],
            ['refs_path_name_starts_with_multi_dot_san', '..refs/somewhere', '-refs/somewhere'],
            ['refs_path_name_starts_with_dot_san', '.refs/somewhere', '-refs/somewhere'],
            ['refs_path_component_is_singular_dot_san', 'refs/./still-inside-but-not-cool', 'refs/-/still-inside-but-not-cool'],
            ['any_path_starts_with_slash_san', '/etc/foo', 'etc/foo'],
            ['empty_path_san', '', '-'],
            ['refs_starts_with_slash_san', '/refs/heads/main', 'refs/heads/main'],
            ['ends_with_slash_san', 'refs/heads/main/', 'refs/heads/main'],
            ['path_with_duplicate_slashes_san', 'refs//heads/main', 'refs/heads/main'],
            ['path_with_spaces_san', 'refs//heads////name with spaces', 'refs/heads/name-with-spaces'],
            ['path_with_backslashes_san', 'refs\\heads/name with spaces', 'refs-heads/name-with-spaces'],
        ] as [$name, $input, $expected]) {
            $assertReferenceSanitized($t, "name_partial invalid {$name}", $input, $expected);
        }
    },

    'upstream gix-validate reference name valid mktest cases' => static function (TestRunner $t) use (
        $assertReferenceNameValid
    ): void {
        foreach ([
            ['main_worktree_pseudo_ref', 'main-worktree/HEAD'],
            ['main_worktree_ref', 'main-worktree/refs/bisect/good'],
            ['other_worktree_pseudo_ref', 'worktrees/id/HEAD'],
            ['other_worktree_ref', 'worktrees/id/refs/bisect/good'],
            ['worktree_private_ref', 'refs/worktree/private'],
            ['refs_path', 'refs/heads/main'],
            ['refs_path_with_file_extension', 'refs/heads/main.ext'],
            ['refs_path_underscores_and_dashes', 'refs/heads/main-2nd_ext'],
            ['relative_path', 'etc/foo'],
            ['all_uppercase', 'MAIN'],
            ['all_uppercase_with_underscore', 'NEW_HEAD'],
            ['chinese_utf8', 'refs/heads/你好吗'],
            ['dot_in_directory_component', 'this./totally./works'],
        ] as [$name, $input]) {
            $assertReferenceNameValid($t, "name valid {$name}", $input);
        }
    },

    'upstream gix-validate reference name valid mktests sanitizer cases' => static function (TestRunner $t) use (
        $assertReferenceSanitized
    ): void {
        foreach ([
            ['main_worktree_pseudo_ref_san', 'main-worktree/HEAD', 'main-worktree/HEAD'],
            ['main_worktree_ref_san', 'main-worktree/refs/bisect/good', 'main-worktree/refs/bisect/good'],
            ['other_worktree_pseudo_ref_san', 'worktrees/id/HEAD', 'worktrees/id/HEAD'],
            ['other_worktree_ref_san', 'worktrees/id/refs/bisect/good', 'worktrees/id/refs/bisect/good'],
            ['worktree_private_ref_san', 'refs/worktree/private', 'refs/worktree/private'],
            ['refs_path_san', 'refs/heads/main', 'refs/heads/main'],
            ['refs_path_with_file_extension_san', 'refs/heads/main.ext', 'refs/heads/main.ext'],
            ['refs_path_underscores_and_dashes_san', 'refs/heads/main-2nd_ext', 'refs/heads/main-2nd_ext'],
            ['relative_path_san', 'etc/foo', 'etc/foo'],
            ['all_uppercase_san', 'MAIN', 'MAIN'],
            ['all_uppercase_with_underscore_san', 'NEW_HEAD', 'NEW_HEAD'],
            ['chinese_utf8_san', 'refs/heads/你好吗', 'refs/heads/你好吗'],
            ['dot_in_directory_component_san', 'this./totally./works', 'this./totally./works'],
        ] as [$name, $input, $expected]) {
            $assertReferenceSanitized($t, "name valid {$name}", $input, $expected);
        }
    },

    'upstream gix-validate reference name invalid mktest cases' => static function (TestRunner $t) use (
        $assertReferenceNameError
    ): void {
        foreach ([
            ['refs_path_double_dot', 'refs/../somewhere', GitValidate::ERROR_STARTS_WITH_DOT],
            ['refs_name_special_case_upload_pack', '(null)', GitValidate::ERROR_SOME_LOWERCASE],
            ['refs_path_name_starts_with_dot', '.refs/somewhere', GitValidate::ERROR_STARTS_WITH_DOT],
            ['refs_path_name_starts_with_dot_in_name', 'refs/.somewhere', GitValidate::ERROR_STARTS_WITH_DOT],
            ['refs_path_name_ends_with_dot_in_name', 'refs/somewhere.', GitValidate::ERROR_ENDS_WITH_DOT],
            ['refs_path_component_is_singular_dot', 'refs/./still-inside-but-not-cool', GitValidate::ERROR_STARTS_WITH_DOT],
            ['capitalized_name_without_path', 'Main', GitValidate::ERROR_SOME_LOWERCASE],
            ['lowercase_name_without_path', 'main', GitValidate::ERROR_SOME_LOWERCASE],
            ['any_path_starts_with_slash', '/etc/foo', GitValidate::ERROR_STARTS_WITH_SLASH],
            ['empty_path', '', GitValidate::ERROR_EMPTY],
            ['refs_starts_with_slash', '/refs/heads/main', GitValidate::ERROR_STARTS_WITH_SLASH],
            ['ends_with_slash', 'refs/heads/main/', GitValidate::ERROR_ENDS_WITH_SLASH],
            ['ends_with_slash_multiple', 'refs/heads/main///', GitValidate::ERROR_ENDS_WITH_SLASH],
            ['a_path_with_duplicate_slashes', 'refs//heads/main', GitValidate::ERROR_REPEATED_SLASH],
        ] as [$name, $input, $expected]) {
            $assertReferenceNameError($t, "name invalid {$name}", $input, $expected);
        }
    },

    'upstream gix-validate reference name invalid mktests sanitizer cases' => static function (TestRunner $t) use (
        $assertReferenceSanitized
    ): void {
        foreach ([
            ['refs_path_double_dot_san', 'refs/../somewhere', 'refs/-/somewhere'],
            ['refs_name_special_case_upload_pack_san', '(null)', '(null)'],
            ['refs_path_name_starts_with_dot_san', '.refs/somewhere', '-refs/somewhere'],
            ['refs_path_name_starts_with_dot_in_name_san', 'refs/.somewhere', 'refs/-somewhere'],
            ['refs_path_name_ends_with_dot_in_name_san', 'refs/somewhere.', 'refs/somewhere-'],
            ['refs_path_component_is_singular_dot_an', 'refs/./still-inside-but-not-cool', 'refs/-/still-inside-but-not-cool'],
            ['capitalized_name_without_path_san', 'Main', 'Main'],
            ['lowercase_name_without_path_san', 'main', 'main'],
            ['any_path_starts_with_slash_san', '/etc/foo', 'etc/foo'],
            ['empty_path_san', '', '-'],
            ['refs_starts_with_slash_san', '/refs/heads/main', 'refs/heads/main'],
            ['ends_with_slash_san', 'refs/heads/main/', 'refs/heads/main'],
            ['ends_with_slash_multiple_san', 'refs/heads/main///', 'refs/heads/main'],
            ['a_path_with_duplicate_slashes_san', 'refs//heads/main', 'refs/heads/main'],
        ] as [$name, $input, $expected]) {
            $assertReferenceSanitized($t, "name invalid {$name}", $input, $expected);
        }
    },

    'upstream gix-validate reference branch_name explicit tests' => static function (TestRunner $t): void {
        $t->same(null, GitValidate::validateBranchName('refs/heads/main'), 'refs_heads_main');
        $t->same(null, GitValidate::validateBranchName('refs/heads/HEAd'), 'refs_heads_head_different_case');
        $t->same(GitValidate::ERROR_RESERVED, GitValidate::validateBranchName('refs/heads/HEAD'), 'refs_heads_head_is_reserved');
        $t->same(GitValidate::ERROR_REPEATED_SLASH, GitValidate::validateBranchName('refs//heads/main'), 'invalid_refname_is_wrapped');
    },
];
