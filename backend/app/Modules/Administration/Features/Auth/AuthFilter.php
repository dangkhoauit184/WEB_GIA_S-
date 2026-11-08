<?php
namespace App\Modules\Administration\Features\Auth;

final class AuthFilter
{
    public static function user(): array
    {
        return \App\Shared\Access::user();
    }
}
