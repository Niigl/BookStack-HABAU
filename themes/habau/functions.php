<?php

use BookStack\Theming\ThemeEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

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

    // ==============================
    // S3 Bildverwaltung
    // ==============================

    // S3 Admin - Bildübersicht
    if ($request->is('s3-admin') && $request->isMethod('get')) {
        if (!auth()->check() || !auth()->user()->can('settings-manage')) {
            abort(403);
        }

        $disk = Storage::disk('s3');
        $filter = $request->get('filter', 'all');
        $search = $request->get('search', '');

        // Alle Dateien holen
        $allFiles = $disk->allFiles('uploads/images');

        // Statistiken berechnen
        $totalSize = 0;
        $originals = [];
        $thumbnails = 0;
        $scaled = 0;

        foreach ($allFiles as $file) {
            $size = $disk->size($file);
            $totalSize += $size;

            if (str_contains($file, 'thumbs-')) {
                $thumbnails++;
            } elseif (str_contains($file, 'scaled-')) {
                $scaled++;
            } else {
                $originals[] = [
                    'path' => $file,
                    'name' => basename($file),
                    'size' => $size,
                    'date' => $disk->lastModified($file),
                    'folder' => dirname(str_replace('uploads/images/', '', $file)),
                ];
            }
        }

        // Nach Monat/Ordner gruppieren
        $grouped = [];
        foreach ($originals as $img) {
            $folder = $img['folder'];
            if (!isset($grouped[$folder])) {
                $grouped[$folder] = [];
            }
            $grouped[$folder][] = $img;
        }
        krsort($grouped);

        // Suche
        if ($search) {
            foreach ($grouped as $folder => &$images) {
                $images = array_filter($images, function ($img) use ($search) {
                    return str_contains(strtolower($img['name']), strtolower($search));
                });
            }
            $grouped = array_filter($grouped);
        }

        // Filter
        if ($filter !== 'all' && isset($grouped[$filter])) {
            $grouped = [$filter => $grouped[$filter]];
        }

        $stats = [
            'totalFiles' => count($allFiles),
            'originals' => count($originals),
            'thumbnails' => $thumbnails,
            'scaled' => $scaled,
            'totalSize' => $totalSize,
        ];

        $folders = array_keys($grouped);

        echo view('s3-images', [
            'grouped' => $grouped,
            'stats' => $stats,
            'folders' => $folders,
            'currentFilter' => $filter,
            'search' => $search,
        ])->render();
        exit;
    }

    // S3 Admin - Bild hochladen
    if ($request->is('s3-admin/upload') && $request->isMethod('post')) {
        if (!auth()->check() || !auth()->user()->can('settings-manage')) {
            abort(403);
        }

        $files = $request->file('images');
        $disk = Storage::disk('s3');
        $uploaded = 0;
        $month = date('Y-m');

        if ($files) {
            foreach ($files as $file) {
                $name = \Illuminate\Support\Str::random(3) . $file->getClientOriginalName();
                $path = 'uploads/images/gallery/' . $month . '/' . $name;
                $disk->put($path, file_get_contents($file));
                $uploaded++;
            }
        }

        session()->flash('success', $uploaded . ' Bilder hochgeladen');
        session()->save();
        header('Location: ' . url('/s3-admin'));
        exit;
    }

    // S3 Admin - Ausgewählte Bilder löschen
    if ($request->is('s3-admin/delete') && $request->isMethod('post')) {
        if (!auth()->check() || !auth()->user()->can('settings-manage')) {
            abort(403);
        }

        $paths = $request->input('paths', []);
        $disk = Storage::disk('s3');
        $deleted = 0;

        foreach ($paths as $path) {
            if (str_starts_with($path, 'uploads/images/')) {
                $disk->delete($path);
                $dir = dirname($path);
                $name = basename($path);
                $disk->delete($dir . '/thumbs-150-150/' . $name);
                $disk->delete($dir . '/scaled-1680-/' . $name);
                \BookStack\Uploads\Image::where('path', '/' . $path)->delete();
                $deleted++;
            }
        }

        session()->flash('success', $deleted . ' Bilder gelöscht');
        session()->save();
        header('Location: ' . url('/s3-admin'));
        exit;
    }

    // S3 Admin - Alle Bilder löschen
    if ($request->is('s3-admin/delete-all') && $request->isMethod('post')) {
        if (!auth()->check() || !auth()->user()->can('settings-manage')) {
            abort(403);
        }

        $disk = Storage::disk('s3');
        $allFiles = $disk->allFiles('uploads/images');
        foreach ($allFiles as $file) {
            $disk->delete($file);
        }
        $count = count($allFiles);
        \BookStack\Uploads\Image::query()->delete();

        session()->flash('success', $count . ' Dateien gelöscht');
        session()->save();
        header('Location: ' . url('/s3-admin'));
        exit;
    }

    // ==============================
    // Logout abfangen
    // ==============================

    if ($request->is('saml2/logout') && $request->isMethod('post')) {
        if (session()->get('local_login') === true) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            header('Location: ' . url('/login?prevent_auto=true'));
            exit;
        }
    }

    // ==============================
    // Auto-Redirect zu SAML
    // ==============================

    if ($request->is('login') && $request->isMethod('get') && !$request->has('prevent_auto')) {
        $loginPath = url('/saml2/login');
        $token = csrf_token();
        echo '<html><body><form id="f" method="POST" action="' . $loginPath . '"><input type="hidden" name="_token" value="' . $token . '"></form><script>document.getElementById("f").submit();</script></body></html>';
        exit;
    }
});