<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitPath;

$assertRelativePathError = static function (TestRunner $t, string $expected, string $path): void {
    $t->same($expected, GitPath::relativePathError($path));
};

return [
    'upstream gix-path path/convert/mod.rs assure_unix_separators' => static function (TestRunner $t): void {
        $t->same('no-backslash', GitPath::toUnixSeparators('no-backslash'));
        $t->same('/a/b//', GitPath::toUnixSeparators('\\a\\b\\\\'));
    },

    'upstream gix-path path/convert/mod.rs assure_windows_separators' => static function (TestRunner $t): void {
        $t->same('no-backslash', GitPath::toWindowsSeparators('no-backslash'));
        $t->same('\\a\\b\\\\', GitPath::toWindowsSeparators('/a/b//'));
    },

    'upstream gix-path path/convert/mod.rs join_bstr_unix_pathsep::typical_with_double_slash_avoidance' => static function (TestRunner $t): void {
        $t->same('base/path', GitPath::joinBstrUnixPathsep('base', 'path'));
        $t->same('base/path', GitPath::joinBstrUnixPathsep('base/', 'path'), 'no double slashes');
        $t->same('/base/path', GitPath::joinBstrUnixPathsep('/base', 'path'));
        $t->same('/base/path', GitPath::joinBstrUnixPathsep('/base/', 'path'));
    },

    'upstream gix-path path/convert/mod.rs join_bstr_unix_pathsep::relative_base_or_path_are_nothing_special' => static function (TestRunner $t): void {
        $t->same('base/.', GitPath::joinBstrUnixPathsep('base', '.'));
        $t->same('base/..', GitPath::joinBstrUnixPathsep('base', '..'));
        $t->same('base/../dir', GitPath::joinBstrUnixPathsep('base', '../dir'));
    },

    'upstream gix-path path/convert/mod.rs join_bstr_unix_pathsep::absolute_path_produces_double_slashes' => static function (TestRunner $t): void {
        $t->same('/base//root', GitPath::joinBstrUnixPathsep('/base', '/root'));
        $t->same('base//root', GitPath::joinBstrUnixPathsep('base/', '/root'));
    },

    'upstream gix-path path/convert/mod.rs join_bstr_unix_pathsep::empty_path_makes_base_end_with_a_slash' => static function (TestRunner $t): void {
        $t->same('base/', GitPath::joinBstrUnixPathsep('base', ''));
        $t->same('base/', GitPath::joinBstrUnixPathsep('base/', ''));
    },

    'upstream gix-path path/convert/mod.rs join_bstr_unix_pathsep::empty_base_leaves_everything_untouched' => static function (TestRunner $t): void {
        $t->same('', GitPath::joinBstrUnixPathsep('', ''));
        $t->same('hi', GitPath::joinBstrUnixPathsep('', 'hi'));
        $t->same('/hi', GitPath::joinBstrUnixPathsep('', '/hi'));
    },

    'upstream gix-path path/convert/mod.rs relativize_with_prefix::basics' => static function (TestRunner $t): void {
        $t->same('.', GitPath::relativizeWithPrefix('a', 'a'), "reaching the prefix is signalled by a '.', the current dir");
        $t->same('c', GitPath::relativizeWithPrefix('a/b/c', 'a/b'), "'c' is clearly within the current directory");
        $t->same('../../c/b/c', GitPath::relativizeWithPrefix('c/b/c', 'a/b'), 'when there is a complete disjoint prefix, we have to get out of it with ../');
        $t->same('../a', GitPath::relativizeWithPrefix('a/a', 'a/b'), 'when there is mismatch, we have to get out of the CWD');
        $t->same('a/a', GitPath::relativizeWithPrefix('a/a', ''), 'empty prefix means nothing happens (and no work is done)');
        $t->same('', GitPath::relativizeWithPrefix('', ''), 'empty stays empty');
    },

    'upstream gix-path path/convert/normalize.rs no_change_if_there_are_no_trailing_relative_components' => static function (TestRunner $t): void {
        foreach (['./a/b/c/d', '/absolute/path', 'C:\\hello\\world'] as $input) {
            $t->same($input, GitPath::normalize($input, '/cwd'));
        }
    },

    'upstream gix-path path/convert/normalize.rs special_cases_around_cwd' => static function (TestRunner $t): void {
        $cwd = '/users/name/project';

        $t->same(
            '/users/.git/modules/src/llvm-project',
            GitPath::normalize('./../../.git/modules/src/llvm-project', $cwd),
            "'.' is handled specifically to not fail to swap in the CWD"
        );
        $t->same($cwd, GitPath::normalize($cwd, $cwd), 'absolute inputs yield absolute outputs');
        $t->same('/users/name', GitPath::normalize('a/../..', $cwd), 'it automatically extends the pop-able items by using the current working dir');
        $t->same('.', GitPath::normalize('a/..', $cwd), 'absolute CWDs are always shortened');
        $t->same('.', GitPath::normalize('./a/..', $cwd), 'like this as well');
        $t->same($cwd, GitPath::normalize($cwd, $cwd), 'but only if there were relative to begin with.');
        $t->same('.', GitPath::normalize('.', $cwd), 'and this means that `.`. stays `.`');
        $t->same($cwd, GitPath::normalize($cwd . '/../project', $cwd), 'absolute input paths stay absolute');
    },

    'upstream gix-path path/convert/normalize.rs parent_dirs_cause_the_cwd_to_be_used' => static function (TestRunner $t): void {
        $t->same('/users', GitPath::normalize('./a/b/../../..', '/users/name'));
    },

    'upstream gix-path path/convert/normalize.rs multiple_parent_dir_movements_eat_into_the_current_dir' => static function (TestRunner $t): void {
        $t->same('/users/name/d/e', GitPath::normalize('../../../d/e', '/users/name/a/b/c'));
        $t->same('/users/name/d/e', GitPath::normalize('c/../../../d/e', '/users/name/a/b'));
    },

    'upstream gix-path path/convert/normalize.rs walking_up_too_much_yield_none' => static function (TestRunner $t): void {
        $t->same(null, GitPath::normalize('./a/b/../../../../../.', '/users/name'));
        $t->same(null, GitPath::normalize('./a/../../../..', '/users/name'));
    },

    'upstream gix-path path/convert/normalize.rs trailing_directories_after_too_numerous_parent_dirs_yield_none' => static function (TestRunner $t): void {
        $t->same(null, GitPath::normalize('./a/b/../../../../../actually-invalid', '/users'));
        $t->same(null, GitPath::normalize('/a/b/../../..', '/does-not/matter'));
    },

    'upstream gix-path path/convert/normalize.rs trailing_relative_components_are_resolved' => static function (TestRunner $t): void {
        $cwd = '/a/b/c';
        $cases = [
            './a/b/./c/../d/..' => './a/b',
            'a/./b/c/.././..' => 'a',
            '/a/b/c/.././../.' => '/a',
            './a/..' => '.',
            'a/..' => '.',
            './a' => './a',
            './a/./b' => './a/./b',
            './a/./b/..' => './a/.',
            '/a/./b/c/.././../.' => '/a',
            '/a/./b' => '/a/./b',
            '././/a/./b' => '././/a/./b',
            '/a/././c/.././../.' => '/',
            '/a/b/../c/../..' => '/',
            'C:/hello/../a' => 'C:/a',
            './a/../b/..' => './',
            '/a/../b' => '/b',
        ];

        foreach ($cases as $input => $expected) {
            $t->same($expected, GitPath::normalize($input, $cwd), "'{$input}' got an unexpected result");
        }
    },

    'upstream gix-path path/relative_path.rs absolute_paths_return_err' => static function (TestRunner $t) use ($assertRelativePathError): void {
        $assertRelativePathError($t, GitPath::RELATIVE_PATH_IS_ABSOLUTE, '/refs/heads');
        $assertRelativePathError($t, GitPath::RELATIVE_PATH_IS_ABSOLUTE, '/refs/heads');
        $assertRelativePathError($t, GitPath::RELATIVE_PATH_IS_ABSOLUTE, '/refs/heads');
        $assertRelativePathError($t, GitPath::RELATIVE_PATH_IS_ABSOLUTE, '/refs/heads');
        $assertRelativePathError($t, GitPath::RELATIVE_PATH_IS_ABSOLUTE, '/refs/heads');
    },

    'upstream gix-path path/relative_path.rs dots_in_paths_return_err' => static function (TestRunner $t) use ($assertRelativePathError): void {
        $assertRelativePathError($t, GitPath::RELATIVE_PATH_CONTAINS_INVALID_COMPONENT, './heads');
        $assertRelativePathError($t, GitPath::RELATIVE_PATH_CONTAINS_INVALID_COMPONENT, './heads');
        $assertRelativePathError($t, GitPath::RELATIVE_PATH_CONTAINS_INVALID_COMPONENT, './heads');
        $assertRelativePathError($t, GitPath::RELATIVE_PATH_CONTAINS_INVALID_COMPONENT, './heads');
    },

    'upstream gix-path path/relative_path.rs dots_in_paths_with_backslashes_return_err' => static function (TestRunner $t) use ($assertRelativePathError): void {
        $assertRelativePathError($t, GitPath::RELATIVE_PATH_CONTAINS_INVALID_COMPONENT, '.\\heads');
        $assertRelativePathError($t, GitPath::RELATIVE_PATH_CONTAINS_INVALID_COMPONENT, '.\\heads');
        $assertRelativePathError($t, GitPath::RELATIVE_PATH_CONTAINS_INVALID_COMPONENT, '.\\heads');
        $assertRelativePathError($t, GitPath::RELATIVE_PATH_CONTAINS_INVALID_COMPONENT, '.\\heads');
    },

    'upstream gix-path path/relative_path.rs double_dots_in_paths_return_err' => static function (TestRunner $t) use ($assertRelativePathError): void {
        $assertRelativePathError($t, GitPath::RELATIVE_PATH_CONTAINS_INVALID_COMPONENT, '../heads');
        $assertRelativePathError($t, GitPath::RELATIVE_PATH_CONTAINS_INVALID_COMPONENT, '../heads');
        $assertRelativePathError($t, GitPath::RELATIVE_PATH_CONTAINS_INVALID_COMPONENT, '../heads');
        $assertRelativePathError($t, GitPath::RELATIVE_PATH_CONTAINS_INVALID_COMPONENT, '../heads');
    },

    'upstream gix-path path/relative_path.rs double_dots_in_paths_with_backslashes_return_err' => static function (TestRunner $t) use ($assertRelativePathError): void {
        $assertRelativePathError($t, GitPath::RELATIVE_PATH_CONTAINS_INVALID_COMPONENT, '..\\heads');
        $assertRelativePathError($t, GitPath::RELATIVE_PATH_CONTAINS_INVALID_COMPONENT, '..\\heads');
        $assertRelativePathError($t, GitPath::RELATIVE_PATH_CONTAINS_INVALID_COMPONENT, '..\\heads');
        $assertRelativePathError($t, GitPath::RELATIVE_PATH_CONTAINS_INVALID_COMPONENT, '..\\heads');
    },

    'upstream gix-path path/util.rs is_absolute::absolute_linux_path_is_true' => static function (TestRunner $t): void {
        $t->same(true, GitPath::isAbsolute('/'));
        $t->same(true, GitPath::isAbsolute('/abs/path'));
    },

    'upstream gix-path path/util.rs is_absolute::relative_linux_path_is_false' => static function (TestRunner $t): void {
        $t->same(false, GitPath::isAbsolute('./relative/path'));
        $t->same(false, GitPath::isAbsolute('relative/path'));
    },

    'upstream gix-path path/util.rs is_absolute::not_on_windows::drive_prefixes_are_false' => static function (TestRunner $t): void {
        $t->same(false, GitPath::isAbsolute('c:\\abs/path'));
        $t->same(false, GitPath::isAbsolute('c:\\abs\\path'));
    },
];
