<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ setting('app-name') }} - Anmelden</title>
    <link rel="stylesheet" href="{{ url('/dist/styles.css') }}">
    <style>
        body {
            background: #f1f1f1;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .login-container {
            width: 100%;
            max-width: 500px;
            padding: 20px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header img {
            max-height: 60px;
        }
        .login-header h1 {
            margin-top: 10px;
            font-size: 1.2em;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="login-container">

        <div class="card content-wrap auto-height">
            <h1 class="list-heading">Anmelden</h1>

            @if(request()->get('error') === '1')
                <div class="text-neg mb-m">Ungültige Anmeldedaten</div>
            @endif

            @if(request()->get('prevent_auto') === 'true')
                <form action="{{ url('/local-login') }}" method="POST" class="mb-xl" accept-charset="UTF-8">
                    {!! csrf_field() !!}
                    <div class="form-group">
                        <label for="email">E-Mail</label>
                        <input type="email" id="email" name="email" class="input-fill-width" required autofocus>
                    </div>
                    <div class="form-group">
                        <label for="password">Passwort</label>
                        <input type="password" id="password" name="password" class="input-fill-width" required>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="remember"> Angemeldet bleiben
                        </label>
                    </div>
                    <div class="form-group text-right mt-m">
                        <button class="button">Anmelden</button>
                    </div>
                </form>
                <hr class="my-l">
            @endif

            @include('auth.parts.login-form-saml2')
        </div>
    </div>
</body>
</html>