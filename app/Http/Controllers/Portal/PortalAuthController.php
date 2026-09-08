<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Contacto;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PortalAuthController extends Controller
{
    public function showLogin(): Response
    {
        return Inertia::render('Portal/Auth/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string', 'min:8'], // QA-D Bloque2: min 8 (era 6)
        ]);

        // Regla: solo contactos b2b + activos + portal habilitado + con password seteado.
        $contacto = Contacto::query()
            ->where('email', $data['email'])
            ->where('activo', true)
            ->where('portal_habilitado', true)
            ->whereNotNull('password')
            ->first();

        // QA-D Bloque2: normalizar timing con Hash::check dummy si el email no existe.
        // Evita enumeración de emails por diferencia de tiempo de respuesta.
        if (! $contacto) {
            Hash::check($data['password'], '$2y$12$0000000000000000000000000000000000000000000000000000000');
            throw ValidationException::withMessages([
                'email' => 'Credenciales inválidas o portal no habilitado. Comunícate con GREAT BABY.',
            ]);
        }

        if (! Hash::check($data['password'], $contacto->password)) {
            throw ValidationException::withMessages([
                'email' => 'Credenciales inválidas o portal no habilitado. Comunícate con GREAT BABY.',
            ]);
        }

        Auth::guard('cliente')->login($contacto, (bool) $request->boolean('remember'));
        $contacto->forceFill(['ultimo_login_at' => now()])->saveQuietly();

        $request->session()->regenerate();
        return redirect()->intended('/portal');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('cliente')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/portal/login');
    }
}
