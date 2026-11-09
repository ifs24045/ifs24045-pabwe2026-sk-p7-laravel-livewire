<?php

namespace App\Features\Auth\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class RegisterUserAction
{
    /**
     * @param  array{name: string, username: string, email: string, password: string}  $data
     */
    public function execute(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => User::ROLE_MEMBER,
        ]);

        Auth::login($user);

        return $user;
    }
}