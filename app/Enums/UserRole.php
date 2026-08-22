<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Member = 'member';
    case Viewer = 'viewer';
}
