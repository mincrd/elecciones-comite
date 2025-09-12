<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'kaking.choi@cultura.gob.do'], // Busca un usuario con este email
            [
                'name' => 'Kaking Choi',
                'password' => Hash::make('password'), // La contraseña será "password"
                'rol' => User::ROL_ADMIN, // Asigna el rol de administrador
            ]
        );
    }
}
