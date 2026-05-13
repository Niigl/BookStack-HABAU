@extends('layouts.simple')
@section('body')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
<div class="container">
    @include('settings.parts.navbar', ['selected' => 'navigation'])

    @if(session('success'))
        <div class="notification pos mb-m">{{ session('success') }}</div>
    @endif

    <div class="card content-wrap auto-height">
        <h1 class="list-heading">Abteilungs-Navigation</h1>
        <p class="text-muted text-small mb-m">Verwalte die Abteilungs-Links welche auf der Homepage angezeigt werden. Icons von <a href="https://pictogrammers.com/library/mdi/" target="_blank">Material Design Icons</a> – einfach den Icon-Namen eingeben (z.B. <code>home</code>, <code>office-building</code>).</p>

        <form action="{{ url('/settings/navigation') }}" method="POST" id="nav-form">
            @csrf

            <div class="flex-container-row gap-m mb-s text-muted text-small" style="padding: 0 4px;">
                <div class="flex">Bezeichnung</div>
                <div style="width: 160px;">MDI Icon</div>
                <div class="flex" style="flex: 2;">URL</div>
                <div style="width: 40px;"></div>
            </div>

            <div id="links-container">
                @foreach($links as $i => $link)
                <div class="link-row flex-container-row gap-m items-center mb-s">
                    <div class="flex">
                        <input type="text"
                               name="label[]"
                               placeholder="Abteilung (z.B. MCE-D)"
                               value="{{ $link['label'] }}"
                               class="outline"
                               style="width: 100%;">
                    </div>
                    <div style="width: 160px;">
                        <div class="flex-container-row gap-xs items-center">
                            <i class="mdi mdi-{{ $link['icon'] ?? 'help-circle' }}" style="font-size: 1.4em; color: var(--color-primary); width: 24px;" id="preview-{{ $i }}"></i>
                            <input type="text"
                                   name="icon[]"
                                   placeholder="z.B. home"
                                   value="{{ $link['icon'] ?? '' }}"
                                   class="outline"
                                   style="width: 100%;"
                                   oninput="updatePreview(this, 'preview-{{ $i }}')">
                        </div>
                    </div>
                    <div class="flex" style="flex: 2;">
                        <input type="text"
                               name="url[]"
                               placeholder="URL (z.B. https://...)"
                               value="{{ $link['url'] }}"
                               class="outline"
                               style="width: 100%;">
                    </div>
                    <div>
                        <button type="button" class="button outline small text-neg" onclick="removeRow(this)">
                            @icon('close')
                        </button>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="mb-m mt-s">
                <button type="button" class="button outline" onclick="addRow()">
                    @icon('add')Abteilung hinzufügen
                </button>
            </div>

            <div class="form-group text-right">
                <button type="submit" class="button">Speichern</button>
            </div>
        </form>
    </div>
</div>

<script>
let rowIndex = {{ count($links) }};

function updatePreview(input, previewId) {
    const icon = document.getElementById(previewId);
    icon.className = 'mdi mdi-' + (input.value || 'help-circle');
}

function addRow() {
    const container = document.getElementById('links-container');
    const id = 'preview-new-' + rowIndex++;
    const div = document.createElement('div');
    div.className = 'link-row flex-container-row gap-m items-center mb-s';
    div.innerHTML = `
        <div class="flex">
            <input type="text" name="label[]" placeholder="Abteilung (z.B. MCE-D)" class="outline" style="width: 100%;">
        </div>
        <div style="width: 160px;">
            <div class="flex-container-row gap-xs items-center">
                <i class="mdi mdi-help-circle" style="font-size: 1.4em; color: var(--color-primary); width: 24px;" id="${id}"></i>
                <input type="text" name="icon[]" placeholder="z.B. home" class="outline" style="width: 100%;" oninput="updatePreview(this, '${id}')">
            </div>
        </div>
        <div class="flex" style="flex: 2;">
            <input type="text" name="url[]" placeholder="URL (z.B. https://...)" class="outline" style="width: 100%;">
        </div>
        <div>
            <button type="button" class="button outline small text-neg" onclick="removeRow(this)">✕</button>
        </div>
    `;
    container.appendChild(div);
}

function removeRow(btn) {
    btn.closest('.link-row').remove();
}
</script>
@stop