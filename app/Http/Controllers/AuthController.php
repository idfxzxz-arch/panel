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
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($data, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email atau password salah.'])->onlyInput('email');
        } $request->session()->regenerate();

        return redirect()->intended(route('projects.index'));
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

            return redirect()->intended(route('projects.index'));

        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('login')->withErrors(['github' => 'Gagal login dengan GitHub. Silakan coba lagi.']);
        }
    }
}
