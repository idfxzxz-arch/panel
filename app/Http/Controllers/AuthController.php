<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $loginField = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'name';
        $credentials = [
            $loginField => $data['login'],
            'password' => $data['password'],
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['login' => 'Username/email atau password salah.'])->onlyInput('login');
        }
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function redirectToGithub()
    {
        return Socialite::driver('github')->scopes(['read:user', 'repo'])->redirect();
    }

    public function handleGithubCallback()
    {
        try {
            $githubUser = Socialite::driver('github')->user();

            // Find or create user
            $user = User::where('email', $githubUser->getEmail())->first();

            if (! $user) {
                $user = User::create([
                    'name' => $githubUser->getName() ?? $githubUser->getNickname(),
                    'email' => $githubUser->getEmail(),
                    'password' => bcrypt(str()->random(24)),
                ]);
            }

            // Add GitHub account to user
            $user->githubAccounts()->updateOrCreate(
                ['username' => $githubUser->getNickname()],
                [
                    'token' => $githubUser->token,
                    'name' => $githubUser->getName() ?? $githubUser->getNickname(),
                ]
            );

            Auth::login($user, true);
            $request = request();
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));

        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('login')->withErrors(['github' => 'Gagal login dengan GitHub. Silakan coba lagi.']);
        }
    }
}
