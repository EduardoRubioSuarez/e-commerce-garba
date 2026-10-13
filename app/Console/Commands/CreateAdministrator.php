<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdministrator extends Command
{
    protected $signature = 'store:create-admin {--email= : Correo del administrador} {--name=Administrador : Nombre visible}';

    protected $description = 'Crea un administrador activo mediante contraseña oculta e interactiva';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Ejecuta este comando de forma interactiva para ingresar una contraseña oculta.');

            return self::FAILURE;
        }
        $email = mb_strtolower(trim((string) $this->option('email')));
        $name = trim((string) $this->option('name'));
        $validator = Validator::make(['email' => $email, 'name' => $name], ['email' => ['required', 'email', 'max:255', 'unique:users,email'], 'name' => ['required', 'string', 'max:255']]);
        if ($validator->fails()) {
            $this->error('Correo o nombre inválido, o la cuenta ya existe. No se modificó ninguna cuenta.');

            return self::FAILURE;
        }
        $password = $this->secret('Contraseña (mínimo 12 caracteres)', false);
        $confirmation = $this->secret('Confirma la contraseña', false);
        $validator = Validator::make(['password' => $password, 'password_confirmation' => $confirmation], ['password' => ['required', 'string', 'max:1024', Password::min(12), 'confirmed']]);
        if ($validator->fails()) {
            $this->error('La contraseña debe tener al menos 12 caracteres y coincidir con la confirmación.');

            return self::FAILURE;
        }
        $user = new User;
        $user->forceFill(['name' => $name, 'email' => $email, 'password' => $password, 'role' => UserRole::Administrator, 'is_active' => true, 'activated_at' => now(), 'suspended_at' => null])->save();
        $this->info('Administrador creado correctamente.');

        return self::SUCCESS;
    }
}
