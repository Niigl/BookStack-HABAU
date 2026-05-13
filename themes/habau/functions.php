<?php

use BookStack\Theming\ThemeEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

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

    // Shelf Permissions GET abfangen
    if (preg_match('#^shelves/([^/]+)/permissions$#', $request->path(), $m) && $request->isMethod('get')) {
        if (!auth()->check()) return;

        $shelf = \BookStack\Entities\Queries\EntityQueries::class;
        $entityQueries = app(\BookStack\Entities\Queries\EntityQueries::class);
        $shelf = $entityQueries->shelves->findVisibleBySlugOrFail($m[1]);

        $userPermissions = \DB::table('user_entity_permissions')
            ->where('entity_type', 'bookshelf')
            ->where('entity_id', $shelf->id)
            ->get()
            ->map(function($up) {
                $up->user = \BookStack\Users\Models\User::find($up->user_id);
                return $up;
            })
            ->filter(fn($up) => $up->user !== null);

        echo view('shelves.permissions', [
            'shelf'           => $shelf,
            'data'            => new \BookStack\Permissions\PermissionFormData($shelf),
            'userPermissions' => $userPermissions,
        ])->render();
        exit;
    }

    // Book Permissions GET
    if (preg_match('#^books/([^/]+)/permissions$#', $request->path(), $m) && $request->isMethod('get')) {
        if (!auth()->check()) return;
        $entityQueries = app(\BookStack\Entities\Queries\EntityQueries::class);
        $book = $entityQueries->books->findVisibleBySlugOrFail($m[1]);
        $userPermissions = \DB::table('user_entity_permissions')
            ->where('entity_type', 'book')
            ->where('entity_id', $book->id)
            ->get()
            ->map(function($up) {
                $up->user = \BookStack\Users\Models\User::find($up->user_id);
                return $up;
            })
            ->filter(fn($up) => $up->user !== null);
        echo view('books.permissions', [
            'book'            => $book,
            'data'            => new \BookStack\Permissions\PermissionFormData($book),
            'userPermissions' => $userPermissions,
        ])->render();
        exit;
    }

    // Page Permissions GET
    if (preg_match('#^books/([^/]+)/page/([^/]+)/permissions$#', $request->path(), $m) && $request->isMethod('get')) {
        if (!auth()->check()) return;
        $entityQueries = app(\BookStack\Entities\Queries\EntityQueries::class);
        $page = $entityQueries->pages->findVisibleBySlugsOrFail($m[1], $m[2]);
        $userPermissions = \DB::table('user_entity_permissions')
            ->where('entity_type', 'page')
            ->where('entity_id', $page->id)
            ->get()
            ->map(function($up) {
                $up->user = \BookStack\Users\Models\User::find($up->user_id);
                return $up;
            })
            ->filter(fn($up) => $up->user !== null);
        echo view('pages.permissions', [
            'page'            => $page,
            'data'            => new \BookStack\Permissions\PermissionFormData($page),
            'userPermissions' => $userPermissions,
        ])->render();
        exit;
    }
});

// Avatar von Microsoft Graph nach SAML Login holen
Theme::listen(ThemeEvents::AUTH_LOGIN, function ($service, $user) {
    if ($service !== 'saml2') return;

    try {
        $tenantId = env('GRAPH_TENANT_ID');
        $clientId = env('GRAPH_CLIENT_ID');
        $clientSecret = env('GRAPH_CLIENT_SECRET');

        if (!$tenantId || !$clientId || !$clientSecret) return;

        // Token holen
        $tokenResponse = Http::asForm()->post(
            "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
            [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
            ]
        );

        if (!$tokenResponse->ok()) return;
        $token = $tokenResponse->json('access_token');

        // Prüfen ob sich das Foto geändert hat
        $metaResponse = Http::withToken($token)
            ->get("https://graph.microsoft.com/v1.0/users/{$user->email}/photo");

        if (!$metaResponse->ok()) return;

        $lastModified = $metaResponse->json('@odata.mediaEtag', '');
        $cacheKey = 'avatar_etag_' . $user->id;

        // Nur updaten wenn sich das Bild geändert hat
        if ($user->image_id && cache()->get($cacheKey) === $lastModified) return;

        // Profilbild holen
        $photoResponse = Http::withToken($token)
            ->get("https://graph.microsoft.com/v1.0/users/{$user->email}/photo/\$value");

        if (!$photoResponse->ok()) return;

        // Altes Avatar löschen falls vorhanden
        if ($user->image_id) {
            $oldImage = \BookStack\Uploads\Image::find($user->image_id);
            if ($oldImage) {
                Storage::disk('s3')->delete(ltrim($oldImage->path, '/'));
                $oldImage->delete();
            }
        }

        // Neues Bild in S3 speichern
        $disk = Storage::disk('s3');
        $path = 'uploads/images/user/' . date('Y-m') . '/' . \Illuminate\Support\Str::random(10) . '-avatar.png';
        $disk->put($path, $photoResponse->body());

        // BookStack Image-Eintrag erstellen
        $image = new \BookStack\Uploads\Image();
        $image->name = $user->name . ' Avatar';
        $image->path = '/' . $path;
        $image->url = url('/' . $path);
        $image->type = 'user';
        $image->uploaded_to = $user->id;
        $image->created_by = $user->id;
        $image->updated_by = $user->id;
        $image->save();

        // User aktualisieren
        $user->image_id = $image->id;
        $user->save();

        // ETag cachen damit nicht bei jedem Login neu geladen wird
        cache()->put($cacheKey, $lastModified, now()->addDays(1));

    } catch (\Exception $e) {
        \Log::warning('Avatar sync failed for ' . $user->email . ': ' . $e->getMessage());
    }
});

// Navigation Settings - GET
Route::middleware('web')->get('/settings/navigation', function (Request $request) {
    if (!auth()->check() || !auth()->user()->can('settings-manage')) {
        abort(403);
    }

    $links = json_decode(setting('app-dept-links', '[]'), true) ?: [];
    $roles = \BookStack\Users\Models\Role::orderBy('display_name')->get(['id', 'display_name']);
    $allowedRoles = json_decode(setting('app-dept-nav-roles', '[]'), true) ?: [];

    echo view('settings/navigation', [
        'selected'     => 'navigation',
        'links'        => $links,
        'roles'        => $roles,
        'allowedRoles' => $allowedRoles,
    ])->render();
    exit;
});

// Navigation Settings - POST
Route::middleware('web')->post('/settings/navigation', function (Request $request) {
    if (!auth()->check() || !auth()->user()->can('settings-manage')) {
        abort(403);
    }

    $labels = $request->input('label', []);
    $urls   = $request->input('url', []);
    $icons  = $request->input('icon', []);
    $roles  = $request->input('roles', []);

    $links = [];
    foreach ($labels as $i => $label) {
        if (!empty(trim($label))) {
            $links[] = [
                'label' => trim($label),
                'icon'  => trim($icons[$i] ?? ''),
                'url'   => trim($urls[$i] ?? ''),
                'roles' => array_map('intval', $roles[$i] ?? []),
            ];
        }
    }

    setting()->put('app-dept-links', json_encode($links));
    return redirect('/settings/navigation')->with('success', 'Navigation gespeichert');
});

Theme::listen(ThemeEvents::WEB_MIDDLEWARE_BEFORE, function (Request $request) {
    if (($request->path() === '/' || $request->path() === '') && $request->isMethod('get') && setting('app-homepage-type') === 'habau') {
        if (!auth()->check()) return;
        try {
            $habauFavourites = app(\BookStack\Entities\Queries\QueryTopFavourites::class)->run(6);
            $habauRecentPages = app(\BookStack\Entities\Queries\EntityQueries::class)
                ->pages->visibleForList()
                ->where('draft', false)
                ->orderBy('updated_at', 'desc')
                ->take(10)
                ->get();

            $rendered = view('home.habau', [
                'favourites'           => $habauFavourites,
                'recentlyUpdatedPages' => $habauRecentPages,
                'activity'             => collect(),
                'recents'              => collect(),
                'draftPages'           => collect(),
            ])->render();
            echo $rendered;
            exit;
        } catch (\Exception $e) {
        }
    }
});

Route::middleware('web')->get('/explorer', function (Request $request) {
    if (!auth()->check()) {
        return redirect('/login');
    }

    $entityQueries = app(\BookStack\Entities\Queries\EntityQueries::class);

    $shelves = $entityQueries->shelves->visibleForListWithCover()
        ->orderBy('name', 'asc')
        ->get();

    foreach ($shelves as $shelf) {
        $shelf->visibleBooks = $shelf->visibleBooks()
            ->orderBy('name', 'asc')
            ->get();

        foreach ($shelf->visibleBooks as $book) {
            $book->chapters = $entityQueries->chapters->visibleForList()
                ->where('book_id', $book->id)
                ->orderBy('priority', 'asc')
                ->get();

            foreach ($book->chapters as $chapter) {
                $chapter->pages = $entityQueries->pages->visibleForList()
                    ->where('chapter_id', $chapter->id)
                    ->where('draft', false)
                    ->orderBy('priority', 'asc')
                    ->get();
            }

            $book->directPages = $entityQueries->pages->visibleForList()
                ->where('book_id', $book->id)
                ->whereNull('chapter_id')
                ->where('draft', false)
                ->orderBy('priority', 'asc')
                ->get();
        }
    }

    // Proxy-Berechtigungen für aktuellen User laden
    $userId = auth()->id();
    $proxyRole = \BookStack\Users\Models\Role::where('system_name', 'user-proxy-' . $userId)->first();

    $proxyBooks = collect();
    $proxyPages = collect();

    if ($proxyRole) {
        // Books via entity_permissions wo Proxy-Rolle berechtigt ist
        $proxyBookIds = \DB::table('entity_permissions')
            ->where('entity_type', 'book')
            ->where('role_id', $proxyRole->id)
            ->where('view', 1)
            ->pluck('entity_id');

        $existingBookIds = $shelves->flatMap->visibleBooks->pluck('id');

        $proxyBooks = \BookStack\Entities\Models\Book::whereIn('id', $proxyBookIds)
            ->whereNotIn('id', $existingBookIds)
            ->get();

        foreach ($proxyBooks as $book) {
            $book->chapters = $entityQueries->chapters->visibleForList()
                ->where('book_id', $book->id)
                ->orderBy('priority', 'asc')
                ->get();

            foreach ($book->chapters as $chapter) {
                $chapter->pages = $entityQueries->pages->visibleForList()
                    ->where('chapter_id', $chapter->id)
                    ->where('draft', false)
                    ->orderBy('priority', 'asc')
                    ->get();
            }

            $book->directPages = $entityQueries->pages->visibleForList()
                ->where('book_id', $book->id)
                ->whereNull('chapter_id')
                ->where('draft', false)
                ->orderBy('priority', 'asc')
                ->get();
        }

        // Pages via entity_permissions
        $proxyPageIds = \DB::table('entity_permissions')
            ->where('entity_type', 'page')
            ->where('role_id', $proxyRole->id)
            ->where('view', 1)
            ->pluck('entity_id');

        $proxyPages = \BookStack\Entities\Models\Page::whereIn('id', $proxyPageIds)
            ->where('draft', false)
            ->with('book')
            ->get();
    }

    echo view('explorer', [
        'shelves'    => $shelves,
        'proxyBooks' => $proxyBooks,
        'proxyPages' => $proxyPages,
    ])->render();
    exit;
});

// User-Permissions: Shelf GET wird über Controller gehandelt, wir überschreiben nur POST + Search
Route::middleware('web')->get('/habau/users/search', function (Request $request) {
    if (!auth()->check()) abort(403);
    $q = $request->input('q', '');
    $users = \BookStack\Users\Models\User::query()
        ->where(function($query) use ($q) {
            $query->where('name', 'like', '%' . $q . '%')
                  ->orWhere('email', 'like', '%' . $q . '%');
        })
        ->take(10)
        ->get(['id', 'name', 'email', 'image_id'])
        ->map(fn($u) => [
            'id'     => $u->id,
            'name'   => $u->name,
            'email'  => $u->email,
            'avatar' => $u->getAvatar(32),
        ]);
    return response()->json($users);
});

// Hilfsfunktion: Proxy-Rolle für User holen oder erstellen
function getOrCreateUserProxyRole(int $userId): \BookStack\Users\Models\Role {
    $systemName = 'user-proxy-' . $userId;
    $role = \BookStack\Users\Models\Role::where('system_name', $systemName)->first();
    
    if (!$role) {
        $user = \BookStack\Users\Models\User::find($userId);
        $role = new \BookStack\Users\Models\Role();
        $role->display_name = $user ? $user->name : 'User #' . $userId;
        $role->description = 'Auto-generated user permission proxy';
        $role->system_name = $systemName;
        $role->save();
        
        if ($user) {
            $user->roles()->attach($role->id);
        }
    }
    
    return $role;
}

// Shelf User-Permissions speichern - Route ersetzen
Route::middleware('web')->post('/habau/permissions/shelf/{id}', function (Request $request, $id) {
    \Log::info('SHELF PERM POST REACHED, id: ' . $id . ', data: ' . json_encode($request->input('user_permissions')));
    if (!auth()->check() || !auth()->user()->can('restrictions-manage-all')) abort(403);

    try {
        // Alte user_entity_permissions für dieses Shelf laden
        $oldPerms = \DB::table('user_entity_permissions')
            ->where('entity_type', 'bookshelf')
            ->where('entity_id', $id)
            ->get();

        // Alte entity_permissions für Proxy-Rollen dieser User entfernen
        foreach ($oldPerms as $old) {
            $role = \BookStack\Users\Models\Role::where('system_name', 'user-proxy-' . $old->user_id)->first();
            if ($role) {
                \DB::table('entity_permissions')
                    ->where('entity_type', 'bookshelf')
                    ->where('entity_id', $id)
                    ->where('role_id', $role->id)
                    ->delete();
            }
        }

        // Alte user_entity_permissions löschen
        \DB::table('user_entity_permissions')
            ->where('entity_type', 'bookshelf')
            ->where('entity_id', $id)
            ->delete();

        // Neue Permissions speichern
        foreach ($request->input('user_permissions', []) as $up) {
            if (empty($up['user_id'])) continue;
            $userId = (int) $up['user_id'];
            \Log::info('Processing user: ' . $userId);

            $role = getOrCreateUserProxyRole($userId);
            \Log::info('Proxy role id: ' . $role->id);

            // In unsere eigene Tabelle speichern
            \DB::table('user_entity_permissions')->insert([
                'entity_id'   => $id,
                'entity_type' => 'bookshelf',
                'user_id'     => $userId,
                'view'        => isset($up['view']) ? 1 : 0,
                'create'      => isset($up['create']) ? 1 : 0,
                'update'      => isset($up['update']) ? 1 : 0,
                'delete'      => isset($up['delete']) ? 1 : 0,
            ]);
            \Log::info('user_entity_permissions inserted');

            // In BookStack's entity_permissions für Proxy-Rolle speichern
            \DB::table('entity_permissions')->updateOrInsert(
                [
                    'entity_type' => 'bookshelf',
                    'entity_id'   => $id,
                    'role_id'     => $role->id,
                ],
                [
                    'view'   => isset($up['view']) ? 1 : 0,
                    'create' => isset($up['create']) ? 1 : 0,
                    'update' => isset($up['update']) ? 1 : 0,
                    'delete' => isset($up['delete']) ? 1 : 0,
                ]
            );
            \Log::info('entity_permissions inserted');
        }

        // BookStack Joint-Permissions neu aufbauen
        $shelf = \BookStack\Entities\Models\Bookshelf::find($id);
        app(\BookStack\Permissions\JointPermissionBuilder::class)->rebuildForEntity($shelf);
        \Log::info('Joint permissions rebuilt');

    } catch (\Exception $e) {
        \Log::error('SHELF PERM ERROR: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
        return redirect()->back()->with('error', 'Fehler: ' . $e->getMessage());
    }

    return redirect()->back()->with('success', 'Benutzer-Berechtigungen gespeichert');
});

// Book User-Permissions speichern
Route::middleware('web')->post('/habau/permissions/book/{id}', function (Request $request, $id) {
    if (!auth()->check() || !auth()->user()->can('restrictions-manage-all')) abort(403);
    try {
        $oldPerms = \DB::table('user_entity_permissions')
            ->where('entity_type', 'book')->where('entity_id', $id)->get();
        foreach ($oldPerms as $old) {
            $role = \BookStack\Users\Models\Role::where('system_name', 'user-proxy-' . $old->user_id)->first();
            if ($role) {
                \DB::table('entity_permissions')
                    ->where('entity_type', 'book')->where('entity_id', $id)->where('role_id', $role->id)->delete();
            }
        }
        \DB::table('user_entity_permissions')->where('entity_type', 'book')->where('entity_id', $id)->delete();
        foreach ($request->input('user_permissions', []) as $up) {
            if (empty($up['user_id'])) continue;
            $userId = (int) $up['user_id'];
            $role = getOrCreateUserProxyRole($userId);
            \DB::table('user_entity_permissions')->insert([
                'entity_id' => $id, 'entity_type' => 'book', 'user_id' => $userId,
                'view' => isset($up['view']) ? 1 : 0, 'create' => isset($up['create']) ? 1 : 0,
                'update' => isset($up['update']) ? 1 : 0, 'delete' => isset($up['delete']) ? 1 : 0,
            ]);
            \DB::table('entity_permissions')->updateOrInsert(
                ['entity_type' => 'book', 'entity_id' => $id, 'role_id' => $role->id],
                ['view' => isset($up['view']) ? 1 : 0, 'create' => isset($up['create']) ? 1 : 0,
                 'update' => isset($up['update']) ? 1 : 0, 'delete' => isset($up['delete']) ? 1 : 0]
            );
        }
        $book = \BookStack\Entities\Models\Book::find($id);
        app(\BookStack\Permissions\JointPermissionBuilder::class)->rebuildForEntity($book);
    } catch (\Exception $e) {
        \Log::error('BOOK PERM ERROR: ' . $e->getMessage());
        return redirect()->back()->with('error', 'Fehler: ' . $e->getMessage());
    }
    return redirect()->back()->with('success', 'Benutzer-Berechtigungen gespeichert');
});

// Page User-Permissions speichern
Route::middleware('web')->post('/habau/permissions/page/{id}', function (Request $request, $id) {
    if (!auth()->check() || !auth()->user()->can('restrictions-manage-all')) abort(403);
    try {
        $oldPerms = \DB::table('user_entity_permissions')
            ->where('entity_type', 'page')->where('entity_id', $id)->get();
        foreach ($oldPerms as $old) {
            $role = \BookStack\Users\Models\Role::where('system_name', 'user-proxy-' . $old->user_id)->first();
            if ($role) {
                \DB::table('entity_permissions')
                    ->where('entity_type', 'page')->where('entity_id', $id)->where('role_id', $role->id)->delete();
            }
        }
        \DB::table('user_entity_permissions')->where('entity_type', 'page')->where('entity_id', $id)->delete();
        foreach ($request->input('user_permissions', []) as $up) {
            if (empty($up['user_id'])) continue;
            $userId = (int) $up['user_id'];
            $role = getOrCreateUserProxyRole($userId);
            \DB::table('user_entity_permissions')->insert([
                'entity_id' => $id, 'entity_type' => 'page', 'user_id' => $userId,
                'view' => isset($up['view']) ? 1 : 0, 'create' => 0,
                'update' => isset($up['update']) ? 1 : 0, 'delete' => isset($up['delete']) ? 1 : 0,
            ]);
            \DB::table('entity_permissions')->updateOrInsert(
                ['entity_type' => 'page', 'entity_id' => $id, 'role_id' => $role->id],
                ['view' => isset($up['view']) ? 1 : 0, 'create' => 0,
                 'update' => isset($up['update']) ? 1 : 0, 'delete' => isset($up['delete']) ? 1 : 0]
            );
        }
        $page = \BookStack\Entities\Models\Page::find($id);
        app(\BookStack\Permissions\JointPermissionBuilder::class)->rebuildForEntity($page);
    } catch (\Exception $e) {
        \Log::error('PAGE PERM ERROR: ' . $e->getMessage());
        return redirect()->back()->with('error', 'Fehler: ' . $e->getMessage());
    }
    return redirect()->back()->with('success', 'Benutzer-Berechtigungen gespeichert');
});