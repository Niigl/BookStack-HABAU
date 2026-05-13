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
        <p class="text-muted text-small mb-m">Verwalte die Abteilungs-Links. Icons von <a href="https://pictogrammers.com/library/mdi/" target="_blank">Material Design Icons</a>. Keine Rollen-Auswahl = alle sehen den Link.</p>

        <form action="{{ url('/settings/navigation') }}" method="POST" id="nav-form">
            @csrf

            <div id="links-container">
                @foreach($links as $i => $link)
                <div class="link-row card content-wrap auto-height mb-m" style="padding: 16px;">

                    <div class="flex-container-row gap-m items-center mb-s">
                        {{-- Label --}}
                        <div class="flex">
                            <label class="text-small text-muted">Bezeichnung</label>
                            <input type="text"
                                   name="label[]"
                                   placeholder="z.B. MCE-D"
                                   value="{{ $link['label'] }}"
                                   class="outline"
                                   style="width: 100%;">
                        </div>

                        {{-- Icon --}}
                        <div style="width: 180px;">
                            <label class="text-small text-muted">MDI Icon</label>
                            <div class="flex-container-row gap-xs items-center">
                                <i class="mdi mdi-{{ $link['icon'] ?? 'help-circle' }}"
                                   style="font-size: 1.4em; color: var(--color-primary); width: 24px;"
                                   id="preview-{{ $i }}"></i>
                                <input type="text"
                                       name="icon[]"
                                       placeholder="z.B. home"
                                       value="{{ $link['icon'] ?? '' }}"
                                       class="outline"
                                       style="width: 100%;"
                                       oninput="updatePreview(this, 'preview-{{ $i }}')">
                            </div>
                        </div>

                        {{-- URL --}}
                        <div class="flex" style="flex: 2;">
                            <label class="text-small text-muted">URL</label>
                            <input type="text"
                                   name="url[]"
                                   placeholder="https://..."
                                   value="{{ $link['url'] }}"
                                   class="outline"
                                   style="width: 100%;">
                        </div>

                        {{-- Delete --}}
                        <div style="margin-top: 18px;">
                            <button type="button" class="button outline small text-neg" onclick="removeRow(this)">
                                @icon('close')
                            </button>
                        </div>
                    </div>

                    {{-- Rollen pro Link --}}
                    <div style="border-top: 1px solid var(--color-border); padding-top: 12px; margin-top: 4px;">
                        <p class="text-small text-muted mb-s">Sichtbar für Rollen (keine Auswahl = alle):</p>
                        <div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 6px;">
                            @foreach($roles as $role)
                                @php $roleTag = \DB::table('habau_role_tags')->where('role_id', $role->id)->first(); @endphp
                                <label class="flex-container-row gap-s items-center" style="cursor:pointer;">
                                    <input type="checkbox"
                                        name="roles[{{ $i }}][]"
                                        value="{{ $role->id }}"
                                        {{ in_array($role->id, $link['roles'] ?? []) ? 'checked' : '' }}>
                                    <span class="text-small">
                                        {{ $role->display_name }}
                                        @if($roleTag)
                                            <span style="background: {{ $roleTag->color }}; color:#fff; font-size:0.7em; padding: 1px 6px; border-radius:3px; margin-left:4px;">{{ $roleTag->tag }}</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
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
const allRoles = @json($roles->map(fn($r) => ['id' => $r->id, 'display_name' => $r->display_name]));

function updatePreview(input, previewId) {
    const icon = document.getElementById(previewId);
    icon.className = 'mdi mdi-' + (input.value || 'help-circle');
}

function addRow() {
    const container = document.getElementById('links-container');
    const i = rowIndex++;
    const id = 'preview-new-' + i;

    const rolesHtml = allRoles.map(role => `
        <label class="flex-container-row gap-s items-center" style="cursor:pointer;">
            <input type="checkbox" name="roles[${i}][]" value="${role.id}">
            <span class="text-small">${role.display_name}</span>
        </label>
    `).join('');

    const div = document.createElement('div');
    div.className = 'link-row card content-wrap auto-height mb-m';
    div.style.padding = '16px';
    div.innerHTML = `
        <div class="flex-container-row gap-m items-center mb-s">
            <div class="flex">
                <label class="text-small text-muted">Bezeichnung</label>
                <input type="text" name="label[]" placeholder="z.B. MCE-D" class="outline" style="width: 100%;">
            </div>
            <div style="width: 180px;">
                <label class="text-small text-muted">MDI Icon</label>
                <div class="flex-container-row gap-xs items-center">
                    <i class="mdi mdi-help-circle" style="font-size: 1.4em; color: var(--color-primary); width: 24px;" id="${id}"></i>
                    <input type="text" name="icon[]" placeholder="z.B. home" class="outline" style="width: 100%;" oninput="updatePreview(this, '${id}')">
                </div>
            </div>
            <div class="flex" style="flex: 2;">
                <label class="text-small text-muted">URL</label>
                <input type="text" name="url[]" placeholder="https://..." class="outline" style="width: 100%;">
            </div>
            <div style="margin-top: 18px;">
                <button type="button" class="button outline small text-neg" onclick="removeRow(this)">✕</button>
            </div>
        </div>
        <div style="border-top: 1px solid var(--color-border); padding-top: 12px; margin-top: 4px;">
            <p class="text-small text-muted mb-s">Sichtbar für Rollen (keine Auswahl = alle):</p>
            <div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 6px;">
                ${rolesHtml}
            </div>
        </div>
    `;
    container.appendChild(div);
}

function removeRow(btn) {
    btn.closest('.link-row').remove();
}
</script>
@stop