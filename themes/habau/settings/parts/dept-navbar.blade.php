@php
    $deptLinks = json_decode(setting('app-dept-links', '[]'), true) ?: [];
    $userRoleIds = auth()->check() ? auth()->user()->roles->pluck('id')->toArray() : [];
@endphp

@php
    $visibleLinks = array_filter($deptLinks, function($link) use ($userRoleIds) {
        if (empty($link['roles'])) return true;
        return count(array_intersect($link['roles'], $userRoleIds)) > 0;
    });
@endphp

@if(count($visibleLinks) > 0)
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
<div class="dept-navbar">
    <nav class="dept-navbar-inner">
        @foreach($visibleLinks as $link)
            <a href="{{ $link['url'] }}" class="dept-navbar-link">
                @if(!empty($link['icon']))
                    <i class="mdi mdi-{{ $link['icon'] }}"></i>
                @endif
                {{ $link['label'] }}
            </a>
        @endforeach
    </nav>
</div>
<style>
.dept-navbar {
    background: transparent;
    border-bottom: 2px solid var(--color-border);
    width: 100%;
    padding: 0;
}
.dept-navbar-inner {
    display: flex;
    flex-direction: row;
    justify-content: center;
    flex-wrap: wrap;
    gap: 8px;
    padding: 12px 24px;
    max-width: 1400px;
    margin: 0 auto;
}
.dept-navbar-link {
    color: var(--color-text-muted);
    padding: 10px 24px;
    border-radius: 6px;
    font-size: 1em;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.15s, color 0.15s;
    display: flex;
    align-items: center;
    gap: 8px;
    letter-spacing: 0.01em;
}
.dept-navbar-link:hover {
    background: var(--color-bg-alt);
    color: var(--color-primary);
}
.dept-navbar-link .mdi {
    font-size: 1.4em;
}
</style>
@endif