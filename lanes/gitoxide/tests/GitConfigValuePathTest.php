<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitConfigValue;

$joinPath = static function (string $base, string $suffix): string {
    $trimmed = rtrim($base, "\\/");
    if ($trimmed === '') {
        $trimmed = $base[0] ?? '';
    }

    if ($suffix === '') {
        return $trimmed;
    }

    if ($trimmed === '/' || $trimmed === '\\') {
        return $trimmed . $suffix;
    }

    return $trimmed . DIRECTORY_SEPARATOR . $suffix;
};

return [
    'path::interpolate::backslash_is_not_special_and_they_are_not_escaping_anything' => static function (TestRunner $t): void {
        foreach (['C:\\foo\\bar', '/foo/bar'] as $path) {
            $t->same($path, GitConfigValue::interpolatePath($path));
        }
    },

    'path::interpolate::empty_path_is_error' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => GitConfigValue::interpolatePath(''));
    },

    'path::interpolate::prefix_substitutes_git_install_dir' => static function (TestRunner $t) use ($joinPath): void {
        foreach (['/tmp/git', 'C:\\git'] as $gitInstallDir) {
            foreach ([['%(prefix)/foo/bar', 'foo/bar'], ['%(prefix)/foo\\bar', 'foo\\bar']] as [$value, $suffix]) {
                $t->same(
                    $joinPath($gitInstallDir, $suffix),
                    GitConfigValue::interpolatePath($value, ['installPrefix' => $gitInstallDir]),
                    "prefix interpolation for {$value} under {$gitInstallDir}",
                );
            }
        }
    },

    'path::interpolate::prefix_substitution_skipped_with_dot_slash' => static function (TestRunner $t): void {
        $path = './%(prefix)/foo/bar';
        $t->same($path, GitConfigValue::interpolatePath($path, ['installPrefix' => '/tmp/git']));
    },

    'path::interpolate::tilde_alone_does_not_interpolate' => static function (TestRunner $t): void {
        $t->same('~', GitConfigValue::interpolatePath('~'));
    },

    'path::interpolate::tilde_slash_substitutes_current_user' => static function (TestRunner $t) use ($joinPath): void {
        $home = __DIR__;
        $t->same($joinPath($home, 'user/bar'), GitConfigValue::interpolatePath('~/user/bar', ['homeDir' => $home]));
    },

    'path::interpolate::tilde_with_given_user' => static function (TestRunner $t) use ($joinPath): void {
        $home = __DIR__;
        $userHome = $joinPath($home, 'user');

        foreach (['foo/bar', 'foo\\bar', ''] as $suffix) {
            $path = '~user/' . $suffix;
            $t->same(
                $joinPath($userHome, $suffix),
                GitConfigValue::interpolatePath($path, ['userHomeDirs' => ['user' => $userHome]]),
                "named user interpolation for {$path}",
            );
        }
    },

    'path::optional_prefix::path_without_optional_prefix_is_not_optional' => static function (TestRunner $t): void {
        $path = GitConfigValue::parsePath('/some/path');
        $t->same(false, $path['isOptional'], 'path without prefix should not be optional');
        $t->same('/some/path', $path['value']);
    },

    'path::optional_prefix::path_with_optional_prefix_is_optional' => static function (TestRunner $t): void {
        $path = GitConfigValue::parsePath(':(optional)/some/path');
        $t->same(true, $path['isOptional'], 'path with :(optional) prefix should be optional');
        $t->same('/some/path', $path['value'], 'prefix should be stripped');
    },

    'path::optional_prefix::optional_prefix_with_relative_path' => static function (TestRunner $t): void {
        $path = GitConfigValue::parsePath(':(optional)relative/path');
        $t->same(true, $path['isOptional']);
        $t->same('relative/path', $path['value']);
    },

    'path::optional_prefix::optional_prefix_with_tilde_expansion' => static function (TestRunner $t): void {
        $path = GitConfigValue::parsePath(':(optional)~/config/file');
        $t->same(true, $path['isOptional']);
        $t->same('~/config/file', $path['value'], 'tilde should be preserved for interpolation');
    },

    'path::optional_prefix::optional_prefix_with_prefix_substitution' => static function (TestRunner $t): void {
        $path = GitConfigValue::parsePath(':(optional)%(prefix)/share/git');
        $t->same(true, $path['isOptional']);
        $t->same('%(prefix)/share/git', $path['value'], 'prefix should be preserved for interpolation');
    },

    'path::optional_prefix::optional_prefix_with_windows_path' => static function (TestRunner $t): void {
        $path = GitConfigValue::parsePath(':(optional)C:\\Users\\file');
        $t->same(true, $path['isOptional']);
        $t->same('C:\\Users\\file', $path['value']);
    },

    'path::optional_prefix::optional_prefix_followed_by_empty_path' => static function (TestRunner $t): void {
        $path = GitConfigValue::parsePath(':(optional)');
        $t->same(true, $path['isOptional']);
        $t->same('', $path['value'], 'empty path after prefix is valid');
    },

    'path::optional_prefix::partial_optional_string_is_not_treated_as_prefix' => static function (TestRunner $t): void {
        $path = GitConfigValue::parsePath(':(opt)ional/path');
        $t->same(false, $path['isOptional'], 'incomplete prefix should not be treated as optional marker');
        $t->same(':(opt)ional/path', $path['value']);
    },

    'path::optional_prefix::optional_prefix_case_sensitive' => static function (TestRunner $t): void {
        $path = GitConfigValue::parsePath(':(OPTIONAL)/some/path');
        $t->same(false, $path['isOptional'], 'prefix should be case-sensitive');
        $t->same(':(OPTIONAL)/some/path', $path['value']);
    },

    'path::optional_prefix::optional_prefix_with_spaces' => static function (TestRunner $t): void {
        $path = GitConfigValue::parsePath(':(optional) /path/with/space');
        $t->same(true, $path['isOptional']);
        $t->same(' /path/with/space', $path['value'], 'space after prefix should be preserved');
    },

    'path::optional_prefix::borrowed_path_stays_borrowed_after_prefix_stripping' => static function (TestRunner $t): void {
        $path = GitConfigValue::parsePath(':(optional)/some/path');
        $t->same(true, $path['isOptional']);
        $t->same('/some/path', $path['value']);
    },

    'path::optional_prefix::owned_path_stays_owned_after_prefix_stripping' => static function (TestRunner $t): void {
        $ownedInput = implode('', [':(optional)', '/some/path']);
        $path = GitConfigValue::parsePath($ownedInput);
        $t->same(true, $path['isOptional']);
        $t->same('/some/path', $path['value']);
    },
];
