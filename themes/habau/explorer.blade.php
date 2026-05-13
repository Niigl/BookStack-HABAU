@extends('layouts.simple')
@section('body')

<div class="container mt-xl mb-xl">
    <h1 class="list-heading mb-l">Explorer</h1>

    @foreach($shelves as $shelf)
    <div class="card content-wrap auto-height mb-l">

        {{-- SHELF --}}
        <div class="flex-container-row gap-m items-center mb-m">
            @if($shelf->cover)
                <img src="{{ $shelf->cover->getThumb(40, 40, true) }}"
                     style="width:40px; height:40px; object-fit:cover; border-radius:4px; flex-shrink:0;">
            @endif
            <a href="{{ $shelf->getUrl() }}" style="font-size:1.3em; font-weight:700; text-decoration:none; color: var(--color-primary);">
                @icon('bookshelf') {{ $shelf->name }}
            </a>
        </div>

        {{-- BOOKS --}}
        @foreach($shelf->visibleBooks as $book)
        <div style="margin-left: 24px; margin-bottom: 16px; border-left: 3px solid var(--color-border); padding-left: 16px;">

            <a href="{{ $book->getUrl() }}" style="font-size:1.1em; font-weight:600; text-decoration:none; color: var(--color-text);">
                @icon('book') {{ $book->name }}
            </a>

            {{-- CHAPTERS --}}
            @foreach($book->chapters as $chapter)
            <div style="margin-left: 24px; margin-top: 8px; border-left: 2px solid var(--color-border); padding-left: 12px;">
                <a href="{{ $chapter->getUrl() }}" style="font-weight:500; text-decoration:none; color: var(--color-text);">
                    @icon('chapter') {{ $chapter->name }}
                </a>

                {{-- PAGES in CHAPTER --}}
                @foreach($chapter->pages as $page)
                <div style="margin-left: 20px; margin-top: 4px;">
                    <a href="{{ $page->getUrl() }}" style="text-decoration:none; color: var(--color-link); font-size:0.95em;">
                        @icon('page') {{ $page->name }}
                    </a>
                </div>
                @endforeach
            </div>
            @endforeach

            {{-- DIRECT PAGES (ohne Chapter) --}}
            @foreach($book->directPages as $page)
            <div style="margin-left: 24px; margin-top: 4px; padding-left: 12px;">
                <a href="{{ $page->getUrl() }}" style="text-decoration:none; color: var(--color-link); font-size:0.95em;">
                    @icon('page') {{ $page->name }}
                </a>
            </div>
            @endforeach

        </div>
        @endforeach

    </div>
    @endforeach

    {{-- Einzelberechtigungen via User-Proxy --}}
    @php
        $allShelfBookIds = $shelves->flatMap(fn($s) => $s->visibleBooks->pluck('id'))->toArray();
        $filteredProxyBooks = $proxyBooks->filter(fn($b) => !in_array($b->id, $allShelfBookIds));

        // Pages die bereits in einem Proxy-Book angezeigt werden herausfiltern
        $proxyBookPageIds = $filteredProxyBooks->flatMap(function($b) {
            $pageIds = $b->directPages->pluck('id');
            $chapterPageIds = $b->chapters->flatMap(fn($c) => $c->pages->pluck('id'));
            return $pageIds->merge($chapterPageIds);
        })->toArray();

        // Pages die bereits in einem Shelf-Book sichtbar sind herausfiltern
        $allShelfPageIds = $shelves->flatMap(function($s) {
            return $s->visibleBooks->flatMap(function($b) {
                $pageIds = $b->directPages->pluck('id');
                $chapterPageIds = $b->chapters->flatMap(fn($c) => $c->pages->pluck('id'));
                return $pageIds->merge($chapterPageIds);
            });
        })->toArray();

        $filteredProxyPages = $proxyPages->filter(
            fn($p) => !in_array($p->id, $proxyBookPageIds) && !in_array($p->id, $allShelfPageIds)
        );
    @endphp

    @if($filteredProxyBooks->count() > 0 || $filteredProxyPages->count() > 0)
    <div class="card content-wrap auto-height mb-l">
        <div class="flex-container-row gap-m items-center mb-m">
            <span style="font-size:1.3em; font-weight:700; color: var(--color-primary);">
                @icon('lock-open') Meine Einzelberechtigungen
            </span>
        </div>

        {{-- Proxy Books --}}
        @foreach($filteredProxyBooks as $book)
        <div style="margin-left: 24px; margin-bottom: 16px; border-left: 3px solid var(--color-border); padding-left: 16px;">
            <a href="{{ $book->getUrl() }}" style="font-size:1.1em; font-weight:600; text-decoration:none; color: var(--color-text);">
                @icon('book') {{ $book->name }}
            </a>

            @foreach($book->chapters as $chapter)
            <div style="margin-left: 24px; margin-top: 8px; border-left: 2px solid var(--color-border); padding-left: 12px;">
                <a href="{{ $chapter->getUrl() }}" style="font-weight:500; text-decoration:none; color: var(--color-text);">
                    @icon('chapter') {{ $chapter->name }}
                </a>
                @foreach($chapter->pages as $page)
                <div style="margin-left: 20px; margin-top: 4px;">
                    <a href="{{ $page->getUrl() }}" style="text-decoration:none; color: var(--color-link); font-size:0.95em;">
                        @icon('page') {{ $page->name }}
                    </a>
                </div>
                @endforeach
            </div>
            @endforeach

            @foreach($book->directPages as $page)
            <div style="margin-left: 24px; margin-top: 4px; padding-left: 12px;">
                <a href="{{ $page->getUrl() }}" style="text-decoration:none; color: var(--color-link); font-size:0.95em;">
                    @icon('page') {{ $page->name }}
                </a>
            </div>
            @endforeach
        </div>
        @endforeach

        {{-- Proxy Pages --}}
        @if($filteredProxyPages->count() > 0)
        <div style="margin-left: 24px; border-left: 3px solid var(--color-border); padding-left: 16px;">
            @foreach($filteredProxyPages as $page)
            <div style="margin-top: 4px;">
                <a href="{{ $page->getUrl() }}" style="text-decoration:none; color: var(--color-link); font-size:0.95em;">
                    @icon('page') {{ $page->name }}
                    @if($page->book)
                        <span class="text-muted" style="font-size:0.85em;">— {{ $page->book->name }}</span>
                    @endif
                </a>
            </div>
            @endforeach
        </div>
        @endif

    </div>
    @endif

</div>

@stop