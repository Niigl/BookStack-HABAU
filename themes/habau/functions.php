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

// S3 Admin - Ausgewählte Bilder löschen
Route::middleware('web')->post('/s3-admin/delete', function (Request $request) {
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

            $image = \BookStack\Uploads\Image::where('path', '/' . $path)->first();
            if ($image) {
                \DB::table('entity_page_data')
                    ->where('html', 'like', '%' . basename($path) . '%')
                    ->get()
                    ->each(function ($page) use ($path) {
                        $cleanHtml = preg_replace(
                            '/<img[^>]*' . preg_quote(basename($path), '/') . '[^>]*>/',
                            '',
                            $page->html
                        );
                        $cleanMarkdown = $page->markdown ? preg_replace(
                            '/!\[[^\]]*\]\([^)]*' . preg_quote(basename($path), '/') . '[^)]*\)/',
                            '',
                            $page->markdown
                        ) : $page->markdown;

                        \DB::table('entity_page_data')
                            ->where('page_id', $page->page_id)
                            ->update(['html' => $cleanHtml, 'markdown' => $cleanMarkdown]);
                    });

                $image->delete();
            }

            $deleted++;
        }
    }

    return redirect('/s3-admin')->with('success', $deleted . ' Bilder gelöscht');
});

// S3 Admin - Alle Bilder löschen
Route::middleware('web')->post('/s3-admin/delete-all', function (Request $request) {
    if (!auth()->check() || !auth()->user()->can('settings-manage')) {
        abort(403);
    }

    $disk = Storage::disk('s3');
    $allFiles = $disk->allFiles('uploads/images');
    foreach ($allFiles as $file) {
        $disk->delete($file);
    }
    $count = count($allFiles);

    \DB::table('entity_page_data')
        ->where('html', 'like', '%/uploads/images/%')
        ->get()
        ->each(function ($page) {
            $cleanHtml = preg_replace('/<img[^>]*\/uploads\/images\/[^>]*>/', '', $page->html);
            $cleanMarkdown = $page->markdown ? preg_replace(
                '/!\[[^\]]*\]\([^)]*\/uploads\/images\/[^)]*\)/',
                '',
                $page->markdown
            ) : $page->markdown;

            \DB::table('entity_page_data')
                ->where('page_id', $page->page_id)
                ->update(['html' => $cleanHtml, 'markdown' => $cleanMarkdown]);
        });

    \BookStack\Uploads\Image::query()->delete();

    return redirect('/s3-admin')->with('success', $count . ' Dateien gelöscht');
});

// Theme Events
Theme::listen(ThemeEvents::WEB_MIDDLEWARE_BEFORE, function (Request $request) {

    // S3 Admin - Bildübersicht (GET)
    if ($request->is('s3-admin') && $request->isMethod('get')) {
        if (!auth()->check() || !auth()->user()->can('settings-manage')) {
            abort(403);
        }

        $disk = Storage::disk('s3');
        $filter = $request->get('filter', 'all');
        $search = $request->get('search', '');

        $allFiles = $disk->allFiles('uploads/images');

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

        $grouped = [];
        foreach ($originals as $img) {
            $folder = $img['folder'];
            if (!isset($grouped[$folder])) {
                $grouped[$folder] = [];
            }
            $grouped[$folder][] = $img;
        }
        krsort($grouped);

        if ($search) {
            foreach ($grouped as $folder => &$images) {
                $images = array_filter($images, function ($img) use ($search) {
                    return str_contains(strtolower($img['name']), strtolower($search));
                });
            }
            $grouped = array_filter($grouped);
        }

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

    // Logout abfangen
    if ($request->is('saml2/logout') && $request->isMethod('post')) {
        if (session()->get('local_login') === true) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            header('Location: ' . url('/login?prevent_auto=true'));
            exit;
        }
    }

    // Auto-Redirect zu SAML
    if ($request->is('login') && $request->isMethod('get') && !$request->has('prevent_auto')) {
        $loginPath = url('/saml2/login');
        $token = csrf_token();
        echo '<html><body><form id="f" method="POST" action="' . $loginPath . '"><input type="hidden" name="_token" value="' . $token . '"></form><script>document.getElementById("f").submit();</script></body></html>';
        exit;
    }
});