<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']], (bool) ($data['remember'] ?? false))) {
            throw ValidationException::withMessages([
                'email' => 'Correo o contraseña incorrectos.',
            ]);
        }

        $request->session()->regenerate();

        $u = $request->user();
        $home = $u->rutaInicial();

        // `intended` recuerda la última URL que el visitante intentó abrir, y
        // puede ser una que su rol no tiene permitida (quedaba entrando a un
        // 403 recién logueado). Sólo la respetamos para los perfiles que ven
        // todo el sistema; los demás van siempre a su pantalla de trabajo.
        $puedeDeepLink = $u->esAracely() || $u->hasRole('Gerente');
        if (! $puedeDeepLink) {
            $request->session()->forget('url.intended');
            return redirect($home);
        }

        return redirect()->intended($home);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/app/login');
    }
}
