@extends('layouts.simple')
@section('body')
    <div class="container">
        <div class="my-s">
            @include('entities.breadcrumbs', ['crumbs' => [
                $shelf,
                $shelf->getUrl('/permissions') => [
                    'text' => trans('entities.shelves_permissions'),
                    'icon' => 'lock',
                ]
            ]])
        </div>

        {{-- Bestehende Rollen-Permissions --}}
        <div class="card content-wrap auto-height">
            @include('form.entity-permissions', ['model' => $shelf, 'title' => trans('entities.shelves_permissions')])
        </div>

        {{-- User-Permissions --}}
        <div class="card content-wrap auto-height">
            <h2 class="list-heading">Benutzer-Berechtigungen</h2>
            <p class="text-muted text-small mb-m">Berechtigungen für einzelne Benutzer. Diese haben Vorrang vor Rollen-Berechtigungen.</p>

            <form action="{{ url('/habau/permissions/shelf/' . $shelf->id) }}" method="POST">
                @csrf

                <div id="user-permissions-list">
                    @foreach($userPermissions as $up)
                    <div class="item-list-row flex-container-row justify-space-between wrap items-center user-perm-row">
                        <div class="flex px-l py-m flex-container-row items-center gap-m">
                            <img src="{{ $up->user->getAvatar(32) }}" class="avatar" style="width:32px;height:32px;border-radius:50%;">
                            <div>
                                <strong>{{ $up->user->name }}</strong><br>
                                <small class="text-muted">{{ $up->user->email }}</small>
                            </div>
                            <input type="hidden" name="user_permissions[{{ $loop->index }}][user_id]" value="{{ $up->user_id }}">
                        </div>
                        <div class="flex-container-row justify-space-between gap-x-xl wrap items-center">
                            <div class="px-l">
                                <label class="flex-container-row gap-xs items-center">
                                    <input type="checkbox" name="user_permissions[{{ $loop->index }}][view]" value="1" {{ $up->view ? 'checked' : '' }}>
                                    <span>{{ trans('common.view') }}</span>
                                </label>
                            </div>
                            <div class="px-l">
                                <label class="flex-container-row gap-xs items-center">
                                    <input type="checkbox" name="user_permissions[{{ $loop->index }}][create]" value="1" {{ $up->create ? 'checked' : '' }}>
                                    <span>{{ trans('common.create') }} *</span>
                                </label>
                            </div>
                            <div class="px-l">
                                <label class="flex-container-row gap-xs items-center">
                                    <input type="checkbox" name="user_permissions[{{ $loop->index }}][update]" value="1" {{ $up->update ? 'checked' : '' }}>
                                    <span>{{ trans('common.update') }}</span>
                                </label>
                            </div>
                            <div class="px-l">
                                <label class="flex-container-row gap-xs items-center">
                                    <input type="checkbox" name="user_permissions[{{ $loop->index }}][delete]" value="1" {{ $up->delete ? 'checked' : '' }}>
                                    <span>{{ trans('common.delete') }}</span>
                                </label>
                            </div>
                            <div class="px-m">
                                <button type="button" class="text-neg p-m icon-button" onclick="removeUserRow(this)">
                                    @icon('close')
                                </button>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- User hinzufügen --}}
                <div class="mt-m mb-m flex-container-row gap-m items-center">
                    <div class="flex">
                        <input type="text"
                               id="user-search"
                               placeholder="Benutzer suchen..."
                               class="outline"
                               style="width: 100%;"
                               autocomplete="off">
                        <div id="user-search-results"
                             style="position:absolute; background:var(--color-bg); border:1px solid var(--color-border); border-radius:4px; z-index:100; min-width:300px; display:none;">
                        </div>
                    </div>
                    <button type="button" class="button outline" id="add-user-btn" style="display:none;">
                        @icon('add') Hinzufügen
                    </button>
                </div>

                <div class="form-group text-right">
                    <button type="submit" class="button">{{ trans('common.save') }}</button>
                </div>
            </form>
        </div>

        {{-- Copy to books --}}
        <div class="card content-wrap auto-height flex-container-row items-center gap-x-xl wrap">
            <div class="flex">
                <h2 class="list-heading">{{ trans('entities.shelves_copy_permissions_to_books') }}</h2>
                <p>{{ trans('entities.shelves_copy_permissions_explain') }}</p>
            </div>
            <form action="{{ $shelf->getUrl('/copy-permissions') }}" method="post" class="flex text-right">
                {{ csrf_field() }}
                <button class="button">{{ trans('entities.shelves_copy_permissions') }}</button>
            </form>
        </div>
    </div>

<script>
let selectedUser = null;
let userRowIndex = {{ count($userPermissions) }};

const searchInput = document.getElementById('user-search');
const resultsBox = document.getElementById('user-search-results');
const addBtn = document.getElementById('add-user-btn');

searchInput.addEventListener('input', async function() {
    const q = this.value.trim();
    if (q.length < 2) { resultsBox.style.display = 'none'; return; }

    const res = await fetch('/habau/users/search?q=' + encodeURIComponent(q));
    const users = await res.json();

    if (users.length === 0) { resultsBox.style.display = 'none'; return; }

    resultsBox.innerHTML = users.map(u => `
    <div style="padding: 10px 14px; cursor:pointer; border-bottom:1px solid var(--color-border); display:flex; align-items:center; gap:10px;"
         onmousedown="selectUser(${u.id}, '${u.name.replace(/'/g, "\\'")}', '${u.email.replace(/'/g, "\\'")}', '${u.avatar}')">
        <img src="${u.avatar}" style="width:32px;height:32px;border-radius:50%;flex-shrink:0;">
        <div>
            <strong>${u.name}</strong><br>
            <small style="color:var(--color-text-muted)">${u.email}</small>
        </div>
    </div>
    `).join('');
    resultsBox.style.display = 'block';
});

function selectUser(id, name, email, avatar) {
    selectedUser = {id, name, email, avatar};
    searchInput.value = name;
    resultsBox.style.display = 'none';
    addBtn.style.display = 'inline-flex';
}

addBtn.addEventListener('click', function() {
    if (!selectedUser) return;

    const list = document.getElementById('user-permissions-list');
    const i = userRowIndex++;
    const div = document.createElement('div');
    div.className = 'item-list-row flex-container-row justify-space-between wrap items-center user-perm-row';
    div.innerHTML = `
        <div class="flex px-l py-m flex-container-row items-center gap-m">
            <img src="${selectedUser.avatar}" class="avatar" style="width:32px;height:32px;border-radius:50%;">
            <div>
                <strong>${selectedUser.name}</strong><br>
                <small class="text-muted">${selectedUser.email}</small>
            </div>
            <input type="hidden" name="user_permissions[${i}][user_id]" value="${selectedUser.id}">
        </div>
        <div class="flex-container-row justify-space-between gap-x-xl wrap items-center">
            <div class="px-l">
                <label class="flex-container-row gap-xs items-center">
                    <input type="checkbox" name="user_permissions[${i}][view]" value="1" checked>
                    <span>Anzeigen</span>
                </label>
            </div>
            <div class="px-l">
                <label class="flex-container-row gap-xs items-center">
                    <input type="checkbox" name="user_permissions[${i}][create]" value="1">
                    <span>Erstellen *</span>
                </label>
            </div>
            <div class="px-l">
                <label class="flex-container-row gap-xs items-center">
                    <input type="checkbox" name="user_permissions[${i}][update]" value="1">
                    <span>Bearbeiten</span>
                </label>
            </div>
            <div class="px-l">
                <label class="flex-container-row gap-xs items-center">
                    <input type="checkbox" name="user_permissions[${i}][delete]" value="1">
                    <span>Löschen</span>
                </label>
            </div>
            <div class="px-m">
                <button type="button" class="text-neg p-m icon-button" onclick="removeUserRow(this)">✕</button>
            </div>
        </div>
    `;
    list.appendChild(div);
    selectedUser = null;
    searchInput.value = '';
    addBtn.style.display = 'none';
});

function removeUserRow(btn) {
    btn.closest('.user-perm-row').remove();
}
</script>
@stop