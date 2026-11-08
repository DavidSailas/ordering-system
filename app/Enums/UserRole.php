<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Owner = 'owner';
    case Cashier = 'cashier';
    case Runner = 'runner';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Owner => 'Owner',
            self::Cashier => 'Cashier',
            self::Runner => 'Runner',
            self::Customer => 'Customer',
        };
    }

    /**
     * Name of the route a user with this role lands on after logging in.
     */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Admin, self::Owner => 'admin.dashboard',
            self::Cashier => 'dashboard',
            self::Runner => 'runner.dashboard',
            self::Customer => 'home',
        };
    }

    /**
     * Roles that belong to one canteen (they always have a canteen_id).
     *
     * @return array<int, self>
     */
    public static function canteenRoles(): array
    {
        return [self::Owner, self::Cashier, self::Runner];
    }

    /**
     * Roles a canteen owner may hand out to their own staff.
     *
     * @return array<int, self>
     */
    public static function assignableByOwner(): array
    {
        return [self::Cashier, self::Runner];
    }

    /**
     * Roles the staff page offers an administrator (owners are created per canteen).
     *
     * @return array<int, self>
     */
    public static function assignableByAdmin(): array
    {
        return [self::Owner, self::Cashier, self::Runner];
    }

    public function belongsToCanteen(): bool
    {
        return in_array($this, self::canteenRoles(), true);
    }
}
