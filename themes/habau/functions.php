<?php

use BookStack\Theming\ThemeEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

// Eigene Route für lokalen Login
Route::middleware('web')->post('/local-login', function (Request $request) {
    $email = $request->input('email');
    $password = $request->input('password');
    $user = \BookStack\Users\Models\User::where('email', $email)->first();

    if ($user && Hash::check($password, $user->password)) {
        $request->session()->flush();
        $request->session()->regenerate();
        $request->session()->put(auth()->getName(), $user->id);
        $request->session()->put('local_login', true);
        $request->session()->save();

        return redirect('/');
    }

    return redirect('/login?prevent_auto=true&error=1');
});

// Eigene Route für lokalen Logout
Route::middleware('web')->post('/local-logout', function (Request $request) {
    auth()->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/login?prevent_auto=true');
});

// Theme Events
Theme::listen(ThemeEvents::WEB_MIDDLEWARE_BEFORE, function (Request $request) {

    // Logout abfangen: wenn lokaler Login → lokalen Logout verwenden
    if ($request->is('saml2/logout') && $request->isMethod('post')) {
        if (session()->get('local_login') === true) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            header('Location: ' . url('/login?prevent_auto=true'));
            exit;
        }
    }

    // Auto-Redirect zu SAML bei normalem GET Login
    if ($request->is('login') && $request->isMethod('get') && !$request->has('prevent_auto')) {
        $loginPath = url('/saml2/login');
        $token = csrf_token();
        echo '<html><body><form id="f" method="POST" action="' . $loginPath . '"><input type="hidden" name="_token" value="' . $token . '"></form><script>document.getElementById("f").submit();</script></body></html>';
        exit;
    }
});