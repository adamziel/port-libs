<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitSecurity;

return [
    'upstream gix-sec trust::ordering' => static function (TestRunner $t): void {
        $t->true(GitSecurity::trustCompare(GitSecurity::TRUST_REDUCED, GitSecurity::TRUST_FULL) < 0);
    },

    'upstream gix-sec permission::check' => static function (TestRunner $t): void {
        $t->same('hi', GitSecurity::permissionCheck(GitSecurity::PERMISSION_ALLOW, 'hi'));
        $t->same(null, GitSecurity::permissionCheck(GitSecurity::PERMISSION_DENY, 'hi'));
        $t->throws(RuntimeException::class, static fn () => GitSecurity::permissionCheck(GitSecurity::PERMISSION_FORBID, 'hi'));
    },

    'upstream gix-sec permission::check_opt' => static function (TestRunner $t): void {
        $t->same('hi', GitSecurity::permissionCheckOpt(GitSecurity::PERMISSION_ALLOW, 'hi'));
        $t->same(null, GitSecurity::permissionCheckOpt(GitSecurity::PERMISSION_DENY, 'hi'));
        $t->same(null, GitSecurity::permissionCheckOpt(GitSecurity::PERMISSION_FORBID, 'hi'));
    },

    'upstream gix-sec permission::is_allowed' => static function (TestRunner $t): void {
        $t->same(true, GitSecurity::permissionIsAllowed(GitSecurity::PERMISSION_ALLOW));
        $t->same(false, GitSecurity::permissionIsAllowed(GitSecurity::PERMISSION_DENY));
        $t->same(false, GitSecurity::permissionIsAllowed(GitSecurity::PERMISSION_FORBID));
    },
];
