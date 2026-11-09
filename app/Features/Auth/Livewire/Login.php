<?php

namespace App\Features\Auth\Livewire;

use App\Features\Auth\Actions\LoginUserAction;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Login extends Component
{
    public string $username = '';

    public string $password = '';

    public bool $remember = false;

    public function authenticate(LoginUserAction $action)
    {
        $this->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ], [
            'username.required' => 'Username atau email wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ]);

        if (! $action->execute($this->username, $this->password, $this->remember)) {
            $this->addError('username', 'Username/email atau password salah.');

            return;
        }

        session()->regenerate();

        return redirect()->route(
            Auth::user()->isAdmin() ? 'admin.users.index' : 'stocks.index'
        );
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}