@extends('layouts.simple')
@section('body')
<div class="container small">
    @include('settings.parts.navbar', ['selected' => 's3-images'])

    {{-- Erfolgsmeldung --}}
    @if(session('success'))
        <div class="notification pos mb-m">{{ session('success') }}</div>
    @endif

    {{-- Statistiken --}}
    <div class="card content-wrap auto-height mb-m">
        <div class="flex-container-row justify-space-between items-center mb-m">
            <h2 class="list-heading">Statistiken</h2>
            <span class="text-muted text-small">Bucket: {{ config('filesystems.disks.s3.bucket') }}</span>
        </div>
        <div class="grid" style="grid-template-columns: repeat(5, 1fr); gap: 12px;">
            <div style="text-align: center; padding: 8px; background: #f8f9fa; border-radius: 4px;">
                <div class="text-muted text-small">Originale</div>
                <div style="font-size: 22px; font-weight: 700;">{{ $stats['originals'] }}</div>
            </div>
            <div style="text-align: center; padding: 8px; background: #f8f9fa; border-radius: 4px;">
                <div class="text-muted text-small">Thumbnails</div>
                <div style="font-size: 22px; font-weight: 700;">{{ $stats['thumbnails'] }}</div>
            </div>
            <div style="text-align: center; padding: 8px; background: #f8f9fa; border-radius: 4px;">
                <div class="text-muted text-small">Skaliert</div>
                <div style="font-size: 22px; font-weight: 700;">{{ $stats['scaled'] }}</div>
            </div>
            <div style="text-align: center; padding: 8px; background: #f8f9fa; border-radius: 4px;">
                <div class="text-muted text-small">Gesamt</div>
                <div style="font-size: 22px; font-weight: 700;">{{ $stats['totalFiles'] }}</div>
            </div>
            <div style="text-align: center; padding: 8px; background: #f8f9fa; border-radius: 4px;">
                <div class="text-muted text-small">Speicher</div>
                <div style="font-size: 22px; font-weight: 700;">
                    @if($stats['totalSize'] > 1073741824)
                        {{ number_format($stats['totalSize'] / 1073741824, 1) }} GB
                    @elseif($stats['totalSize'] > 1048576)
                        {{ number_format($stats['totalSize'] / 1048576, 1) }} MB
                    @else
                        {{ number_format($stats['totalSize'] / 1024, 1) }} KB
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Upload --}}
    <div class="card content-wrap auto-height mb-m">
        <h2 class="list-heading mb-m">Bilder hochladen</h2>
        <div id="uploadArea" onclick="document.getElementById('fileInput').click()"
             style="border: 2px dashed #ccc; border-radius: 4px; padding: 24px; text-align: center; cursor: pointer; transition: border-color 0.2s;">
            <p style="margin: 0; font-weight: 600;">Klicke hier oder ziehe Dateien in diesen Bereich</p>
            <p class="text-muted text-small" style="margin: 4px 0 0;">PNG, JPG, GIF, SVG, WebP</p>
            <form action="/s3-admin/upload" method="POST" enctype="multipart/form-data" id="uploadForm">
                @csrf
                <input type="file" name="images[]" id="fileInput" multiple accept="image/*" style="display: none;">
            </form>
        </div>
    </div>

    {{-- Suche & Filter --}}
    <div class="card content-wrap auto-height mb-m">
        <form class="flex-container-row gap-xs items-center wrap" method="GET" action="/s3-admin">
            <input type="text" name="search" placeholder="Bilder suchen..." value="{{ $search }}" style="flex: 1; min-width: 200px;">
            <select name="filter" onchange="this.form.submit()">
                <option value="all">Alle Ordner</option>
                @foreach($folders as $folder)
                    <option value="{{ $folder }}" {{ $currentFilter === $folder ? 'selected' : '' }}>{{ $folder }}</option>
                @endforeach
            </select>
            <button type="submit" class="button outline small">Suchen</button>
            @if($search || $currentFilter !== 'all')
                <a href="/s3-admin" class="button outline small">Zurücksetzen</a>
            @endif
            <div style="margin-left: auto;">
                <form action="/s3-admin/delete-all" method="POST" style="display: inline;"
                      onsubmit="return confirm('ACHTUNG: Wirklich ALLE Bilder unwiderruflich löschen?')">
                    @csrf
                    <button type="submit" class="button outline small text-neg">Alle löschen</button>
                </form>
            </div>
        </form>
    </div>

    {{-- Bilder nach Ordner --}}
    <form action="/s3-admin/delete" method="POST" id="deleteForm">
        @csrf

        @forelse($grouped as $folder => $images)
            <div class="card content-wrap auto-height mb-m" style="padding: 0;">
                <div class="flex-container-row justify-space-between items-center"
                     style="padding: 10px 16px; background: #f8f9fa; border-bottom: 1px solid #e5e5e5; cursor: pointer;"
                     onclick="var b=this.nextElementSibling; b.style.display=b.style.display==='none'?'block':'none'; this.querySelector('.toggle').textContent=b.style.display==='none'?'▶':'▼';">
                    <div class="flex-container-row items-center gap-xs">
                        <span class="toggle">▼</span>
                        <strong>{{ $folder }}</strong>
                        <span class="text-muted text-small">({{ count($images) }} Bilder)</span>
                    </div>
                </div>
                <div style="padding: 12px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 10px;">
                        @foreach($images as $image)
                            <div class="image-card" style="border: 1px solid #e5e5e5; border-radius: 4px; overflow: hidden; position: relative; cursor: pointer;"
                                 onclick="var cb=this.querySelector('input[type=checkbox]'); if(event.target.type!=='checkbox') cb.checked=!cb.checked; this.style.outline=cb.checked?'2px solid #206ea7':'none'; updateSelection();">
                                <input type="checkbox" name="paths[]" value="{{ $image['path'] }}"
                                       style="position: absolute; top: 6px; left: 6px; width: 16px; height: 16px;">
                                <img src="/uploads/images/{{ str_replace('uploads/images/', '', $image['path']) }}"
                                     alt="{{ $image['name'] }}" loading="lazy"
                                     style="width: 100%; height: 120px; object-fit: cover; display: block; background: #f3f4f6;"
                                     onerror="this.style.background='#eee'">
                                <div style="padding: 6px 8px;">
                                    <div style="font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $image['name'] }}">{{ $image['name'] }}</div>
                                    <div class="text-muted" style="font-size: 11px;">{{ round($image['size'] / 1024, 1) }} KB · {{ date('d.m.Y', $image['date']) }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @empty
            <div class="card content-wrap auto-height" style="text-align: center; padding: 40px 0;">
                <p class="text-muted">Keine Bilder vorhanden</p>
            </div>
        @endforelse

        {{-- Selection Bar --}}
        <div id="selectionBar" style="display: none; position: sticky; bottom: 16px; background: #333; color: #fff; padding: 12px 20px; border-radius: 4px; margin-top: 16px; justify-content: space-between; align-items: center;">
            <span id="selectedCount">0 ausgewählt</span>
            <div style="display: flex; gap: 8px;">
                <button type="button" class="button outline small" style="color: #fff; border-color: rgba(255,255,255,0.3);" onclick="selectNone()">Aufheben</button>
                <button type="submit" class="button small" style="background: #d33; color: #fff;" onclick="return confirm('Ausgewählte Bilder löschen?')">Löschen</button>
            </div>
        </div>
    </form>
</div>

<script>
    var uploadArea = document.getElementById('uploadArea');
    var fileInput = document.getElementById('fileInput');
    var uploadForm = document.getElementById('uploadForm');

    ['dragenter','dragover'].forEach(function(e) {
        uploadArea.addEventListener(e, function(ev) { ev.preventDefault(); uploadArea.style.borderColor = '#206ea7'; });
    });
    ['dragleave','drop'].forEach(function(e) {
        uploadArea.addEventListener(e, function(ev) { ev.preventDefault(); uploadArea.style.borderColor = '#ccc'; });
    });
    uploadArea.addEventListener('drop', function(ev) { fileInput.files = ev.dataTransfer.files; uploadForm.submit(); });
    fileInput.addEventListener('change', function() { if (fileInput.files.length > 0) uploadForm.submit(); });

    function updateSelection() {
        var checked = document.querySelectorAll('input[name="paths[]"]:checked');
        var bar = document.getElementById('selectionBar');
        document.getElementById('selectedCount').textContent = checked.length + ' ausgewählt';
        bar.style.display = checked.length > 0 ? 'flex' : 'none';
    }
    function selectNone() {
        document.querySelectorAll('input[name="paths[]"]').forEach(function(cb) { cb.checked = false; cb.closest('.image-card').style.outline = 'none'; });
        updateSelection();
    }
</script>
@stop