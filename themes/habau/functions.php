<?php

use BookStack\Theming\ThemeEvents;
use Illuminate\Http\Request;

Theme::listen(ThemeEvents::WEB_MIDDLEWARE_BEFORE, function (Request $request) {
    if ($request->is('login') && $request->isMethod('get') && !$request->has('prevent_auto')) {
        $loginPath = url('/saml2/login');
        $token = csrf_token();
        echo '<html><body><form id="f" method="POST" action="' . $loginPath . '"><input type="hidden" name="_token" value="' . $token . '"></form><script>document.getElementById("f").submit();</script></body></html>';
        exit;
    }
});