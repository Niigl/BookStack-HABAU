@extends('layouts.simple')
@section('body')

    @include('settings/parts/dept-navbar')

    <div class="container mt-xl">
        <div class="grid" style="grid-template-columns: 1.2fr 2.5fr 1.8fr; gap: 40px; align-items: start;">

            {{-- LINKS: Favouriten --}}
            <div>
                <h2 class="list-heading mb-m" style="font-size: 1.3em;">Meine Favoriten</h2>
                @if(count($favourites) > 0)
                    <div class="flex-container-column gap-s">
                        @foreach($favourites as $fav)
                            <a href="{{ $fav->getUrl() }}"
   class="card content-wrap auto-height flex-container-row gap-m items-center"
   style="text-decoration: none; padding: 18px 22px; width: 100%; box-sizing: border-box;">
                                    @icon($fav->getType())
                                </span>
                                <span style="font-size: 1.05em; font-weight: 500;">{{ $fav->name }}</span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="card content-wrap auto-height" style="padding: 24px;">
                        <p class="text-muted">Noch keine Favoriten gesetzt.</p>
                        <p class="text-small text-muted">Klicke auf das Stern-Symbol bei Büchern oder Seiten um sie hier anzuzeigen.</p>
                    </div>
                @endif
            </div>

            {{-- MITTE: Shelves --}}
            <div>
                <h2 class="list-heading mb-m" style="font-size: 1.3em;">Bereiche</h2>
                @php
                    $shelves = app(\BookStack\Entities\Queries\EntityQueries::class)
                        ->shelves->visibleForListWithCover()
                        ->orderBy('name', 'asc')
                        ->get();
                @endphp
                @if($shelves->count() > 0)
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 24px;">
                        @foreach($shelves as $shelf)
                            <a href="{{ $shelf->getUrl() }}" style="text-decoration: none;">
                                <div class="card content-wrap auto-height" style="padding: 0; overflow: hidden;">
                                    @if($shelf->cover)
                                        <img src="{{ $shelf->cover->getThumb(240, 160, true) }}"
                                             alt="{{ $shelf->name }}"
                                             style="width: 100%; height: 160px; object-fit: cover; display: block;">
                                    @else
                                        <div style="width: 100%; height: 160px; background: var(--color-primary); opacity: 0.12;"></div>
                                    @endif
                                    <div style="padding: 16px 18px;">
                                        <div style="font-weight: 600; font-size: 1.1em;">{{ $shelf->name }}</div>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="card content-wrap auto-height">
                        <p class="text-muted">Keine Bereiche vorhanden.</p>
                    </div>
                @endif
            </div>

            {{-- RECHTS: Letzte Änderungen --}}
            <div>
                <h2 class="list-heading mb-m" style="font-size: 1.3em;">Zuletzt geändert</h2>
                @if(count($recentlyUpdatedPages) > 0)
                    <div class="flex-container-column gap-s">
                        @foreach($recentlyUpdatedPages as $page)
                            <a href="{{ $page->getUrl() }}"
                               class="card content-wrap auto-height"
                               style="text-decoration: none; padding: 18px 22px; width: 100%; box-sizing: border-box;">
                                <div style="font-weight: 600; font-size: 1.05em; margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $page->name }}</div>
                                <div class="text-muted" style="font-size: 0.9em;">{{ $page->updated_at->diffForHumans() }}</div>
                                @if($page->book)
                                    <div class="text-muted" style="font-size: 0.85em; margin-top: 6px;">
                                        @icon('book') {{ $page->book->name }}
                                    </div>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="card content-wrap auto-height">
                        <p class="text-muted">Keine kürzlichen Änderungen.</p>
                    </div>
                @endif
            </div>

        </div>
    </div>

@stop