<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateOperator extends Command
{
    protected $signature = 'app:create-operator {name?} {email?} {role=admin}';

    protected $description = 'Provision a staff or administrator account from the server console.';

    public function handle(): int
    {
        $name = $this->argument('name') ?: $this->ask('Operator name');
        $email = strtolower(trim((string) ($this->argument('email') ?: $this->ask('Operator email'))));
        $role = (string) $this->argument('role');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'role' => $role,
        ], [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['staff', 'admin'])],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $password = $this->secret('Set a password (minimum 12 characters)');
        $passwordValidator = Validator::make(['password' => $password], [
            'password' => ['required', 'string', 'min:12', 'max:72'],
        ]);

        if ($passwordValidator->fails()) {
            $this->error($passwordValidator->errors()->first());

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'locale' => 'fr',
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'account.operator_provisioned',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'metadata' => ['role' => $role, 'provisioning' => 'server_console'],
        ]);

        $this->info("Provisioned {$role} account: {$user->email}");

        return self::SUCCESS;
    }
}
