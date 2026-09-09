<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Contacto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
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

    /**
     * H3 · solicitud de reset password (paso 1: envío del enlace).
     *
     * Respuesta genérica siempre — no revela si el email existe (misma
     * defensa contra enumeración que login()). Genera token opaco, lo
     * guarda en cache 1h y envía mail con firma signedRoute que valida
     * la ruta /portal/password/reset.
     */
    public function olvidePassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:150']]);

        $key = 'portal.olvide:' . md5(strtolower($data['email']));
        if (Cache::add($key, 1, 300)) { // 1 solicitud por 5 min por email
            $contacto = Contacto::query()
                ->where('email', $data['email'])
                ->where('activo', true)
                ->where('portal_habilitado', true)
                ->first();

            if ($contacto) {
                $token = Str::random(64);
                Cache::put('portal.reset:' . hash('sha256', $token), $contacto->id, now()->addHour());
                $url = URL::temporarySignedRoute('portal.password.reset.form', now()->addHour(), [
                    'token' => $token, 'email' => $data['email'],
                ]);
                try {
                    \Illuminate\Support\Facades\Mail::raw(
                        "Hola {$contacto->nombre_completo},\n\nRecibimos una solicitud para restablecer tu contraseña del portal GREAT BABY.\n\nAbre este enlace (expira en 1 hora):\n{$url}\n\nSi no fuiste tú, ignora este mensaje.",
                        function ($m) use ($contacto) {
                            $m->to($contacto->email)->subject('Restablece tu contraseña · Portal GREAT BABY');
                        }
                    );
                } catch (\Throwable $e) {
                    Log::warning('[portal.olvide] fallo envío mail', ['email' => $data['email'], 'error' => $e->getMessage()]);
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * H3 · form de nueva contraseña (paso 2: renderiza page con token).
     */
    public function resetForm(Request $request): Response
    {
        return Inertia::render('Portal/Auth/Reset', [
            'token' => (string) $request->query('token'),
            'email' => (string) $request->query('email'),
        ]);
    }

    /**
     * H3 · submit del reset (paso 3).
     */
    public function resetSubmit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $cacheKey = 'portal.reset:' . hash('sha256', $data['token']);
        $contactoId = Cache::pull($cacheKey);
        if (! $contactoId) {
            throw ValidationException::withMessages(['token' => 'El enlace expiró o ya fue usado.']);
        }

        $contacto = Contacto::where('id', $contactoId)
            ->where('email', $data['email'])
            ->where('activo', true)
            ->where('portal_habilitado', true)
            ->first();
        if (! $contacto) {
            throw ValidationException::withMessages(['email' => 'El enlace no corresponde a esta cuenta.']);
        }

        $contacto->forceFill(['password' => Hash::make($data['password'])])->save();
        return redirect('/portal/login')->with('flash', ['success' => 'Contraseña actualizada. Ingresa con la nueva.']);
    }
}
