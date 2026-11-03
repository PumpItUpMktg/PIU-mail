<?php

use App\Enums\Role;

test('role enum has exactly the three roles', function () {
    expect(array_map(fn (Role $r) => $r->value, Role::cases()))->toBe(['super_admin', 'office', 'tech']);
});

test('tech can create but not approve or send', function () {
    expect(Role::Tech->canCreateTargetAreas())->toBeTrue()
        ->and(Role::Tech->canApproveTargetAreas())->toBeFalse()
        ->and(Role::Tech->canSendMailings())->toBeFalse()
        ->and(Role::Office->canApproveTargetAreas())->toBeTrue()
        ->and(Role::Office->canSendMailings())->toBeTrue();
});

test('only super admin is tenant-less', function () {
    expect(Role::SuperAdmin->isTenantBound())->toBeFalse()
        ->and(Role::Office->isTenantBound())->toBeTrue()
        ->and(Role::Tech->isTenantBound())->toBeTrue();
});
