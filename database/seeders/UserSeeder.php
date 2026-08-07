<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::where('code', 'ABC1')->first()
            ?? Branch::where('is_active', true)->orderBy('id')->first();

        $fullAccess = [
            '1', '2', '3', '5', '6', '12', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25',
            '27', '28', '29', '31', '32', '33', '34', '35', '36', '37', '38', '39', '40', '41',
        ];

        $managerAccess = [
            '1', '2', '3', '6', '12', '14', '15', '16', '17', '18', '19', '20', '22', '24', '29', '31', '32',
            '33', '34', '36', '38', '39', '40', '41',
        ];

        $cashierAccess = ['2', '3', '14', '15', '16', '29', '32', '39', '40', '41'];
        $staffAccess = ['2', '3', '33', '34', '36', '38', '39', '40', '41'];

        $users = [
            [
                'fname' => 'System',
                'lname' => 'Administrator',
                'username' => 'superadmin',
                'password' => 'superadmin',
                'role' => User::ROLE_SUPER_ADMIN,
                'branch_id' => null,
                'access' => [],
                'pos_layout' => 'grid',
            ],
            [
                'fname' => 'NizPhone',
                'lname' => 'Admin',
                'username' => 'admin',
                'password' => 'admin',
                'role' => User::ROLE_ADMINISTRATOR,
                'branch_id' => $branch?->id,
                'access' => $fullAccess,
                'pos_layout' => 'grid',
            ],
            [
                'fname' => 'NizPhone',
                'lname' => 'Manager',
                'username' => 'manager',
                'password' => 'manager',
                'role' => User::ROLE_MANAGER,
                'branch_id' => $branch?->id,
                'access' => $managerAccess,
                'pos_layout' => 'grid',
            ],
            [
                'fname' => 'NizPhone',
                'lname' => 'Cashier',
                'username' => 'cashier',
                'password' => 'cashier',
                'role' => User::ROLE_CASHIER,
                'branch_id' => $branch?->id,
                'access' => $cashierAccess,
                'pos_layout' => 'grid',
            ],
            [
                'fname' => 'NizPhone',
                'lname' => 'Staff',
                'username' => 'staff',
                'password' => 'staff',
                'role' => User::ROLE_CASHIER,
                'branch_id' => $branch?->id,
                'access' => $staffAccess,
                'pos_layout' => 'grid',
            ],
        ];

        foreach ($users as $data) {
            $plainPassword = $data['password'];
            $data['password'] = Hash::make($plainPassword);

            User::updateOrCreate(['username' => $data['username']], $data);
        }

        User::where('role', User::ROLE_SUPER_ADMIN)->update([
            'branch_id' => null,
            'access' => [],
        ]);

        User::where('role', User::ROLE_ADMINISTRATOR)->get()->each(function (User $user) use ($branch, $fullAccess) {
            $user->forceFill([
                'branch_id' => $user->branch_id ?? $branch?->id,
                'access' => $fullAccess,
            ])->save();
        });

        User::where('role', User::ROLE_MANAGER)->get()->each(function (User $user) use ($branch, $managerAccess) {
            $user->forceFill([
                'branch_id' => $user->branch_id ?? $branch?->id,
                'access' => $managerAccess,
            ])->save();
        });

        User::where('role', User::ROLE_CASHIER)->get()->each(function (User $user) use ($branch, $cashierAccess, $staffAccess) {
            $access = in_array(strtolower($user->username), ['staff', 'inventory.staff'], true)
                ? $staffAccess
                : $cashierAccess;

            $user->forceFill([
                'branch_id' => $user->branch_id ?? $branch?->id,
                'access' => $access,
            ])->save();
        });

        $this->command->info('✓ NizPhone users seeded and existing users repaired with role-based access');
    }
}
