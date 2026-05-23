<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

/**
 * Focused native model of OneDrive permission metadata write planning.
 *
 * Upstream stores permissions as OneDrive JSON metadata, then sorts queued
 * permissions into remove, add, and update queues before making Graph calls.
 * This class models those local decisions without contacting OneDrive.
 */
final class OneDrivePermissionPlanner
{
    public const DRIVE_TYPE_PERSONAL = 'personal';
    public const DRIVE_TYPE_BUSINESS = 'business';
    public const DRIVE_TYPE_SHAREPOINT = 'documentLibrary';

    /**
     * @param list<array<string, mixed>> $currentPermissions
     * @param array<string, mixed> $metadata
     * @param array{driveType?: string, metadataPermissions?: string, addOnly?: bool, operationErrors?: array<string, string>} $options
     * @return array<string, mixed>
     */
    public static function fromMetadata(array $currentPermissions, array $metadata, array $options = []): array
    {
        if (!array_key_exists('permissions', $metadata)) {
            return self::emptyPlan(self::driveType($options['driveType'] ?? null), (bool) ($options['addOnly'] ?? false), false);
        }

        $mode = (string) ($options['metadataPermissions'] ?? $options['metadata_permissions'] ?? 'write');
        if (!self::modeHas($mode, 'write')) {
            $plan = self::emptyPlan(self::driveType($options['driveType'] ?? null), (bool) ($options['addOnly'] ?? false), self::modeHas($mode, 'failok'));
            $plan['skipped'][] = [
                'reason' => 'metadata-permissions-write-disabled',
                'id' => null,
            ];

            return $plan;
        }

        $queued = self::decodePermissionsMetadata($metadata['permissions']);
        $options['failOk'] = (bool) ($options['failOk'] ?? self::modeHas($mode, 'failok'));
        $options['write'] = true;

        return self::plan($currentPermissions, $queued, $options);
    }

    /**
     * @param list<array<string, mixed>> $currentPermissions
     * @param list<array<string, mixed>|null> $queuedPermissions
     * @param array{driveType?: string, write?: bool, addOnly?: bool, failOk?: bool, operationErrors?: array<string, string>} $options
     * @return array<string, mixed>
     */
    public static function plan(array $currentPermissions, array $queuedPermissions, array $options = []): array
    {
        $driveType = self::driveType($options['driveType'] ?? null);
        $addOnly = (bool) ($options['addOnly'] ?? false);
        $failOk = (bool) ($options['failOk'] ?? false);
        $plan = self::emptyPlan($driveType, $addOnly, $failOk);

        if (!(bool) ($options['write'] ?? true)) {
            $plan['skipped'][] = [
                'reason' => 'metadata-permissions-write-disabled',
                'id' => null,
            ];

            return $plan;
        }

        $current = self::normalizePermissions($currentPermissions);
        $queued = self::normalizePermissions($queuedPermissions);
        [$add, $update, $remove] = self::sortPermissions($current, $queued, $driveType, $addOnly, $plan['skipped']);
        $operationErrors = is_array($options['operationErrors'] ?? null) ? $options['operationErrors'] : [];

        foreach ($remove as $permission) {
            $id = self::permissionId($permission);
            $operation = [
                'action' => 'remove',
                'method' => 'DELETE',
                'path' => '/permissions/' . $id,
                'id' => $id,
                'roles' => self::roles($permission),
            ];
            $plan['operations'][] = $operation;
            $plan['counts']['remove']++;
            self::appendOperationError($operationErrors, $operation, $plan);
        }

        foreach ($add as $permission) {
            $operation = self::addOperation($permission, $driveType, $plan);
            if ($operation === null) {
                continue;
            }

            $plan['operations'][] = $operation;
            $plan['counts']['add']++;
            self::appendOperationError($operationErrors, $operation, $plan);
        }

        foreach ($update as $permission) {
            $operation = self::updateOperation($permission, $plan);
            if ($operation === null) {
                continue;
            }

            $plan['operations'][] = $operation;
            $plan['counts']['update']++;
            self::appendOperationError($operationErrors, $operation, $plan);
        }

        if ($plan['errors'] !== []) {
            $error = self::aggregateErrors($plan['errors']);
            if ($failOk) {
                $plan['suppressedErrors'][] = $error;
                $plan['errors'] = [];
            } else {
                $plan['error'] = $error;
            }
        }

        return $plan;
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyPlan(string $driveType, bool $addOnly, bool $failOk): array
    {
        return [
            'driveType' => $driveType,
            'addOnly' => $addOnly,
            'failOk' => $failOk,
            'counts' => [
                'remove' => 0,
                'add' => 0,
                'update' => 0,
            ],
            'operations' => [],
            'skipped' => [],
            'errors' => [],
            'suppressedErrors' => [],
            'error' => null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function decodePermissionsMetadata(mixed $permissions): array
    {
        if (is_array($permissions)) {
            if ($permissions === []) {
                return [];
            }
            if (!array_is_list($permissions)) {
                throw new \RuntimeException('failed to unmarshal permissions: permissions metadata must be a JSON array');
            }

            return self::normalizePermissions($permissions);
        }

        $decoded = json_decode((string) $permissions, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('failed to unmarshal permissions: ' . json_last_error_msg());
        }
        if (!is_array($decoded)) {
            throw new \RuntimeException('failed to unmarshal permissions: permissions metadata must be a JSON array');
        }
        if ($decoded !== [] && !array_is_list($decoded)) {
            throw new \RuntimeException('failed to unmarshal permissions: permissions metadata must be a JSON array');
        }

        return self::normalizePermissions($decoded);
    }

    private static function driveType(mixed $value): string
    {
        $driveType = self::optionalString($value);
        if ($driveType === null || $driveType === '') {
            return self::DRIVE_TYPE_PERSONAL;
        }

        return match (strtolower($driveType)) {
            self::DRIVE_TYPE_PERSONAL => self::DRIVE_TYPE_PERSONAL,
            self::DRIVE_TYPE_BUSINESS => self::DRIVE_TYPE_BUSINESS,
            'documentlibrary', 'sharepoint' => self::DRIVE_TYPE_SHAREPOINT,
            default => $driveType,
        };
    }

    private static function modeHas(string $mode, string $flag): bool
    {
        $mode = strtolower(trim($mode));
        if ($mode === '' || in_array($mode, ['off', 'false', '0', 'none'], true)) {
            return false;
        }

        return in_array($flag, preg_split('/[|,\s+]+/', $mode) ?: [], true);
    }

    /**
     * @param array<int, array<string, mixed>|null> $permissions
     * @return list<array<string, mixed>>
     */
    private static function normalizePermissions(array $permissions): array
    {
        $normalized = [];
        foreach ($permissions as $permission) {
            if (is_array($permission)) {
                $normalized[] = $permission;
            }
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $current
     * @param list<array<string, mixed>> $queued
     * @param list<array{reason: string, id: ?string, roles?: list<string>}> $skipped
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>, 2: list<array<string, mixed>>}
     */
    private static function sortPermissions(array $current, array $queued, string $driveType, bool $addOnly, array &$skipped): array
    {
        if ($current === [] || $addOnly) {
            self::orderPermissions($queued, $driveType);

            return [$queued, [], []];
        }

        $add = [];
        $update = [];
        $remove = [];

        foreach ($queued as $permission) {
            $id = self::permissionId($permission);
            if ($id !== '') {
                if (!self::hasValidRoleUpdate($permission, $current)) {
                    $skipped[] = [
                        'reason' => 'invalid-update-roles',
                        'id' => $id,
                        'roles' => self::roles($permission),
                    ];
                    continue;
                }

                if ($driveType !== self::DRIVE_TYPE_PERSONAL && self::linkWebUrl($permission) !== '') {
                    $remove[] = $permission;
                    $add[] = $permission;
                    continue;
                }

                $update[] = $permission;
                continue;
            }

            $add[] = $permission;
        }

        foreach ($current as $permission) {
            $id = self::permissionId($permission);
            if (self::hasRole($permission, 'owner')) {
                $skipped[] = [
                    'reason' => 'owner-role-remove-suppressed',
                    'id' => $id !== '' ? $id : null,
                    'roles' => self::roles($permission),
                ];
                continue;
            }

            if ($id !== '' && !self::queuedHasId($queued, $id)) {
                $remove[] = $permission;
            }
        }

        self::orderPermissions($add, $driveType);
        self::orderPermissions($update, $driveType);
        self::orderPermissions($remove, $driveType);

        return [$add, $update, $remove];
    }

    /**
     * @param array<string, mixed> $permission
     * @param list<array<string, mixed>> $current
     */
    private static function hasValidRoleUpdate(array $permission, array $current): bool
    {
        $id = self::permissionId($permission);
        $newRoles = self::roles($permission);
        foreach ($current as $old) {
            $oldRoles = self::roles($old);
            if (self::permissionId($old) === $id
                && $oldRoles !== $newRoles
                && $oldRoles !== []
                && $newRoles !== []
                && !in_array('owner', $oldRoles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $permissions
     */
    private static function queuedHasId(array $permissions, string $id): bool
    {
        foreach ($permissions as $permission) {
            if (self::permissionId($permission) === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $permissions
     */
    private static function orderPermissions(array &$permissions, string $driveType): void
    {
        usort(
            $permissions,
            static function (array $left, array $right) use ($driveType): int {
                $leftHasUser = self::hasUserIdentity($left, $driveType);
                $rightHasUser = self::hasUserIdentity($right, $driveType);
                if ($leftHasUser && !$rightHasUser) {
                    return -1;
                }
                if (!$leftHasUser && $rightHasUser) {
                    return 1;
                }

                return 0;
            },
        );
    }

    /**
     * @param array<string, mixed> $permission
     * @param array<string, mixed> $plan
     * @return null|array<string, mixed>
     */
    private static function addOperation(array $permission, string $driveType, array &$plan): ?array
    {
        $roles = self::roles($permission);
        $recipients = self::recipients($permission, $driveType);
        $publicLink = self::linkScope($permission) === 'anonymous';

        if ($publicLink) {
            $webUrl = self::linkWebUrl($permission);
            $permission['link']['webUrl'] = $webUrl !== '' ? $webUrl : 'public-link';
        }

        if ($recipients === [] && !$publicLink) {
            $plan['skipped'][] = [
                'reason' => 'add-missing-recipient',
                'id' => self::nullablePermissionId($permission),
                'roles' => $roles,
            ];

            return null;
        }
        if ($roles === []) {
            $plan['errors'][] = 'at least one role is required to add a permission (choices: read, write, owner, member)';

            return null;
        }
        if (in_array('owner', $roles, true)) {
            $plan['skipped'][] = [
                'reason' => 'add-owner-role-suppressed',
                'id' => self::nullablePermissionId($permission),
                'roles' => $roles,
            ];

            return null;
        }

        return [
            'action' => 'add',
            'method' => 'POST',
            'path' => '/invite',
            'id' => self::nullablePermissionId($permission),
            'roles' => $roles,
            'recipients' => $recipients,
            'publicLink' => $publicLink,
            'invite' => $recipients !== [],
            'linkScope' => self::linkScope($permission),
        ];
    }

    /**
     * @param array<string, mixed> $permission
     * @param array<string, mixed> $plan
     * @return null|array<string, mixed>
     */
    private static function updateOperation(array $permission, array &$plan): ?array
    {
        $roles = self::roles($permission);
        if ($roles === []) {
            $plan['errors'][] = 'at least one role is required to update a permission (choices: read, write, owner, member)';

            return null;
        }

        $id = self::permissionId($permission);

        return [
            'action' => 'update',
            'method' => 'PATCH',
            'path' => '/permissions/' . $id,
            'id' => $id,
            'roles' => $roles,
        ];
    }

    /**
     * @param array<string, string> $operationErrors
     * @param array<string, mixed> $operation
     * @param array<string, mixed> $plan
     */
    private static function appendOperationError(array $operationErrors, array $operation, array &$plan): void
    {
        $id = self::optionalString($operation['id'] ?? null) ?? '*';
        $keys = [
            ($operation['action'] ?? '') . ':' . $id,
            ($operation['action'] ?? '') . ':*',
        ];
        foreach ($keys as $key) {
            if (isset($operationErrors[$key]) && $operationErrors[$key] !== '') {
                $plan['errors'][] = $operationErrors[$key];

                return;
            }
        }
    }

    /**
     * @param array<string, mixed> $permission
     * @return list<array<string, string>>
     */
    private static function recipients(array $permission, string $driveType): array
    {
        $recipients = [];
        $seen = [];
        foreach (self::recipientIdentitySets($permission, $driveType) as $identitySet) {
            foreach (['user', 'siteUser', 'group', 'siteGroup', 'application', 'device'] as $key) {
                $identity = is_array($identitySet[$key] ?? null) ? $identitySet[$key] : [];
                if ($identity === []) {
                    continue;
                }

                $recipient = self::recipientFromIdentity($identity);
                if ($recipient === null) {
                    continue;
                }
                $dedupe = $recipient['email'] ?? $recipient['objectId'] ?? '';
                if ($dedupe === '' || isset($seen[$dedupe])) {
                    continue;
                }

                $seen[$dedupe] = true;
                $recipients[] = $recipient;
            }
        }

        return $recipients;
    }

    /**
     * @param array<string, mixed> $permission
     * @return list<array<string, mixed>>
     */
    private static function recipientIdentitySets(array $permission, string $driveType): array
    {
        $identitiesKey = $driveType === self::DRIVE_TYPE_PERSONAL ? 'grantedToIdentities' : 'grantedToIdentitiesV2';
        $singleKey = $driveType === self::DRIVE_TYPE_PERSONAL ? 'grantedTo' : 'grantedToV2';
        $identitySets = self::identitySetList($permission[$identitiesKey] ?? null);
        if (is_array($permission[$singleKey] ?? null)) {
            $identitySets[] = $permission[$singleKey];
        }

        return $identitySets;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function identitySetList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        if ($value === []) {
            return [];
        }
        if (!array_is_list($value)) {
            return [$value];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => is_array($item)));
    }

    /**
     * @param array<string, mixed> $identity
     * @return null|array<string, string>
     */
    private static function recipientFromIdentity(array $identity): ?array
    {
        $email = self::optionalString($identity['email'] ?? $identity['Email'] ?? null);
        $id = self::optionalString($identity['id'] ?? $identity['ID'] ?? null) ?? '';
        $displayName = self::optionalString($identity['displayName'] ?? $identity['display_name'] ?? $identity['DisplayName'] ?? null) ?? '';

        if ($email !== null && str_contains($email, '@')) {
            return ['email' => $email];
        }
        if (str_contains($id, '@')) {
            return ['email' => $id];
        }
        if (str_contains($displayName, '@')) {
            return ['email' => $displayName];
        }
        if ($id !== '') {
            return ['objectId' => $id];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $permission
     */
    private static function hasUserIdentity(array $permission, string $driveType): bool
    {
        foreach (self::recipientIdentitySets($permission, $driveType) as $identitySet) {
            $user = is_array($identitySet['user'] ?? null) ? $identitySet['user'] : [];
            if ($user === []) {
                continue;
            }
            foreach (['id', 'ID', 'displayName', 'display_name', 'DisplayName', 'email', 'Email', 'loginName', 'LoginName'] as $key) {
                $value = self::optionalString($user[$key] ?? null);
                if ($value !== null && $value !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $permission
     */
    private static function permissionId(array $permission): string
    {
        return self::optionalString($permission['id'] ?? $permission['ID'] ?? null) ?? '';
    }

    /**
     * @param array<string, mixed> $permission
     */
    private static function nullablePermissionId(array $permission): ?string
    {
        $id = self::permissionId($permission);

        return $id !== '' ? $id : null;
    }

    /**
     * @param array<string, mixed> $permission
     * @return list<string>
     */
    private static function roles(array $permission): array
    {
        $roles = $permission['roles'] ?? $permission['Roles'] ?? [];
        if (is_scalar($roles)) {
            return [(string) $roles];
        }
        if (!is_array($roles)) {
            return [];
        }

        $strings = [];
        foreach ($roles as $role) {
            if (is_scalar($role) && (string) $role !== '') {
                $strings[] = (string) $role;
            }
        }

        return $strings;
    }

    /**
     * @param array<string, mixed> $permission
     */
    private static function hasRole(array $permission, string $role): bool
    {
        return in_array($role, self::roles($permission), true);
    }

    /**
     * @param array<string, mixed> $permission
     */
    private static function linkScope(array $permission): ?string
    {
        $link = is_array($permission['link'] ?? null) ? $permission['link'] : [];

        return self::optionalString($link['scope'] ?? $link['Scope'] ?? null);
    }

    /**
     * @param array<string, mixed> $permission
     */
    private static function linkWebUrl(array $permission): string
    {
        $link = is_array($permission['link'] ?? null) ? $permission['link'] : [];

        return self::optionalString($link['webUrl'] ?? $link['webURL'] ?? $link['WebURL'] ?? null) ?? '';
    }

    /**
     * @param list<string> $errors
     */
    private static function aggregateErrors(array $errors): string
    {
        $last = end($errors);
        $last = is_string($last) ? $last : 'unknown permission error';
        if (count($errors) === 1) {
            return 'failed to process permissions: failed to set permissions: ' . $last;
        }

        return sprintf(
            'failed to process permissions: failed to set permissions: %d errors: last error: %s',
            count($errors),
            $last,
        );
    }

    private static function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return null;
    }
}
