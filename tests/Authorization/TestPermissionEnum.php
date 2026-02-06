<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

enum TestPermissionEnum: string
{
    case UsersRead   = 'users.base.read';
    case UsersUpdate = 'users.base.update';
    case PostsRead   = 'posts.base.read';
    case AdminAccess = 'admin.access';
}
