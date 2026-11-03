<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;

class CreateSuperAdmin extends Command
{
    protected $signature = 'piu:create-super-admin {email} {name}';

    protected $description = 'Create (or promote) a super admin user; prompts for the password';

    public function handle(): int
    {
        $user = User::firstOrNew(['email' => strtolower((string) $this->argument('email'))]);
        $user->fill([
            'name' => (string) $this->argument('name'),
            'role' => Role::SuperAdmin,
            'tenant_id' => null,
        ]);

        if (! $user->exists) {
            $user->password = password('Password', required: true, validate: fn ($v) => strlen($v) < 12 ? 'Use at least 12 characters.' : null);
        }

        $user->save();
        $this->info("Super admin ready: {$user->email}");

        return self::SUCCESS;
    }
}
