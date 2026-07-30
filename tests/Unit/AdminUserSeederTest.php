<?php

namespace Tests\Unit;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
    }

    public function test_skips_when_email_is_empty(): void
    {
        config([
            'admin.email' => '',
            'admin.password' => 'secure-password',
        ]);

        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_skips_when_password_is_empty(): void
    {
        config([
            'admin.email' => 'admin@example.com',
            'admin.password' => '',
        ]);

        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_skips_when_email_is_invalid(): void
    {
        config([
            'admin.email' => 'not-an-email',
            'admin.password' => 'secure-password',
        ]);

        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_creates_admin_when_missing(): void
    {
        config([
            'admin.email' => '  Admin@Example.COM  ',
            'admin.password' => '  secure-password  ',
            'admin.name' => '  Panel Admin  ',
        ]);

        $this->seed(AdminUserSeeder::class);

        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->assertSame('Panel Admin', $user->name);
        $this->assertTrue(Hash::check('secure-password', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->is_accepted_policy);
    }

    public function test_does_not_overwrite_existing_admin_password(): void
    {
        $existing = User::factory()->create([
            'email' => 'admin@example.com',
            'name' => 'Existing Admin',
            'password' => Hash::make('original-password'),
        ]);

        config([
            'admin.email' => 'admin@example.com',
            'admin.password' => 'new-password-from-env',
            'admin.name' => 'Overwritten Name',
        ]);

        $this->seed(AdminUserSeeder::class);

        $existing->refresh();

        $this->assertDatabaseCount('users', 1);
        $this->assertSame('Existing Admin', $existing->name);
        $this->assertTrue(Hash::check('original-password', $existing->password));
        $this->assertFalse(Hash::check('new-password-from-env', $existing->password));
    }

    public function test_seeder_is_idempotent(): void
    {
        config([
            'admin.email' => 'admin@example.com',
            'admin.password' => 'secure-password',
        ]);

        $this->seed(AdminUserSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseCount('users', 1);
    }
}
