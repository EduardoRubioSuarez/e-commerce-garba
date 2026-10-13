<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrator = 'administrator';
    case Installer = 'installer';
}
