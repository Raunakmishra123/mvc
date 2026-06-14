<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the six flatmates and the "Flat 4B" group with its membership timeline.
     *
     * This reflects the real timeline described in the assignment:
     *   - Aisha, Rohan, Priya, Meera: present from February 2026
     *   - Meera: left at end of March 2026
     *   - Dev: Goa trip only (Mar 9–12 2026)
     *   - Sam: joined mid-April 2026
     *
     * All accounts use password "password". CSV import is intentionally NOT
     * run here — it must go through the UI so the Import Report is generated.
     */
    public function run(): void
    {
        // ── Users ──────────────────────────────────────────────────────────────
        $flatmates = [
            ['name' => 'Aisha', 'email' => 'aisha@flat4b.test'],
            ['name' => 'Rohan', 'email' => 'rohan@flat4b.test'],
            ['name' => 'Priya', 'email' => 'priya@flat4b.test'],
            ['name' => 'Meera', 'email' => 'meera@flat4b.test'],
            ['name' => 'Dev',   'email' => 'dev@flat4b.test'],
            ['name' => 'Sam',   'email' => 'sam@flat4b.test'],
        ];

        $users = [];
        foreach ($flatmates as $f) {
            $users[$f['name']] = User::firstOrCreate(
                ['email' => $f['email']],
                ['name' => $f['name'], 'password' => Hash::make('password')]
            );
        }

        // ── Group ───────────────────────────────────────────────────────────────
        $group = Group::firstOrCreate(
            ['name' => 'Flat 4B'],
            [
                'home_currency' => 'INR',
                'description'   => 'Shared flat expenses Feb–May 2026',
                'created_by'    => $users['Aisha']->id,
            ]
        );

        // ── Membership timeline ─────────────────────────────────────────────────
        // This is what the CsvImporter uses to validate split_with fields:
        //   "was this person an active member on the expense date?"
        $memberships = [
            // Aisha — joined Feb 2026, still active
            ['user' => 'Aisha', 'joined_on' => '2026-02-01', 'left_on' => null],
            // Rohan — joined Feb 2026, still active
            ['user' => 'Rohan', 'joined_on' => '2026-02-01', 'left_on' => null],
            // Priya — joined Feb 2026, still active
            ['user' => 'Priya', 'joined_on' => '2026-02-01', 'left_on' => null],
            // Meera — Feb 2026, left end of March 2026 (Sam's requirement)
            ['user' => 'Meera', 'joined_on' => '2026-02-01', 'left_on' => '2026-03-31'],
            // Dev — Goa trip only
            ['user' => 'Dev',   'joined_on' => '2026-03-09', 'left_on' => '2026-03-12'],
            // Sam — joined mid-April 2026 (Sam's requirement)
            ['user' => 'Sam',   'joined_on' => '2026-04-15', 'left_on' => null],
        ];

        foreach ($memberships as $m) {
            GroupMembership::firstOrCreate(
                [
                    'group_id'  => $group->id,
                    'user_id'   => $users[$m['user']]->id,
                    'joined_on' => $m['joined_on'],
                ],
                ['left_on' => $m['left_on']]
            );
        }

        $this->command->info('✓ Seeded 6 users + Flat 4B group with membership timeline.');
        $this->command->info('  Login with any name@flat4b.test / password');
        $this->command->info('  Then go to Flat 4B → Import CSV → upload expenses_export.csv');
    }
}
