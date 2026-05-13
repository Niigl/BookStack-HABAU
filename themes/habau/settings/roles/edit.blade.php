@extends('layouts.simple')
@section('body')
    <div class="container">
        @include('settings.parts.navbar', ['selected' => 'roles'])

        @if(session('success'))
            <div class="notification pos mb-m">{{ session('success') }}</div>
        @endif

        <div class="card content-wrap">
            <h1 class="list-heading">{{ trans('settings.role_edit') }}</h1>
            <form action="{{ url("/settings/roles/{$role->id}") }}" method="POST">
                {{ csrf_field() }}
                {{ method_field('PUT') }}
                @include('settings.roles.parts.form', ['role' => $role])
                <div class="form-group text-right">
                    <a href="{{ url("/settings/roles") }}" class="button outline">{{ trans('common.cancel') }}</a>
                    <a href="{{ url("/settings/roles/new?copy_from={$role->id}") }}" class="button outline">{{ trans('common.copy') }}</a>
                    <a href="{{ url("/settings/roles/delete/{$role->id}") }}" class="button outline">{{ trans('settings.role_delete') }}</a>
                    <button type="submit" class="button">{{ trans('settings.role_save') }}</button>
                </div>
            </form>
        </div>

        {{-- Habau Tag --}}
        @php
            $roleTag = \DB::table('habau_role_tags')->where('role_id', $role->id)->first();
            $allTags = \DB::table('habau_role_tags')
                ->join('roles', 'habau_role_tags.role_id', '=', 'roles.id')
                ->select('habau_role_tags.*', 'roles.display_name as role_name')
                ->orderBy('roles.display_name')
                ->get();
            $uniqueTags = $allTags->unique('tag')->values();
        @endphp
        <div class="card content-wrap auto-height">
            <h2 class="list-heading">Rollen-Tag</h2>
            <p class="text-muted text-small mb-m">Füge einen Tag hinzu der in der Rollen-Übersicht angezeigt wird.</p>

            <form action="{{ url('/habau/role-tags/' . $role->id) }}" method="POST" id="tag-form">
                @csrf
                <input type="hidden" name="clear" id="clear-input" value="0">

                {{-- Vorhandene Tags als Chips --}}
                @if($uniqueTags->count() > 0)
                <div class="mb-m">
                    <label class="text-small text-muted">Vorhandene Tags auswählen:</label>
                    <div class="flex-container-row gap-s wrap mt-xs">
                        @foreach($uniqueTags as $t)
                        <div onclick="selectTag('{{ $t->tag }}', '{{ $t->color }}')"
                             style="background: {{ $t->color }}; color:#fff; font-size:0.85em; padding: 5px 14px; border-radius:20px; cursor:pointer; transition: opacity 0.15s;"
                             onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
                            {{ $t->tag }}
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Eingabe --}}
                <div class="flex-container-row gap-m mb-m">
                    <div class="flex">
                        <label class="text-small text-muted">Tag-Text</label>
                        <input type="text"
                               name="tag"
                               id="tag-input"
                               placeholder="z.B. Admin, Read-Only, ..."
                               value="{{ $roleTag->tag ?? '' }}"
                               class="outline"
                               style="width: 100%;">
                    </div>
                    <div style="width: 120px;">
                        <label class="text-small text-muted">Farbe</label>
                        <input type="color"
                               name="color"
                               id="color-input"
                               value="{{ $roleTag->color ?? '#1b71a1' }}"
                               style="width: 100%; height: 38px; padding: 2px; border: 1px solid var(--color-border); border-radius: 4px; cursor: pointer;">
                    </div>
                </div>

                {{-- Vorschau --}}
                <div class="mb-m">
                    <label class="text-small text-muted">Vorschau</label>
                    <div style="padding: 14px 20px; background: var(--color-bg-alt); border-radius: 6px; border: 1px solid var(--color-border);">
                        <div class="flex-container-row items-center gap-m">
                            <span style="color: var(--color-primary); font-weight:500;">{{ $role->display_name }}</span>
                            <span id="tag-preview"
                                  style="background: {{ $roleTag->color ?? '#1b71a1' }}; color:#fff; font-size:0.75em; padding: 3px 10px; border-radius:20px; font-weight:600; letter-spacing:0.03em;">
                                {{ $roleTag->tag ?? 'Tag' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="form-group text-right">
                    @if($roleTag)
                        <div class="form-group text-right">
                            <form action="{{ url('/habau/role-tags/' . $role->id) }}" method="POST" style="display:inline;">
                                @csrf
                                <input type="hidden" name="tag" value="">
                                <input type="hidden" name="color" value="#1b71a1">
                                <input type="hidden" name="clear" value="1">
                                <button type="submit" class="button outline text-neg">Tag entfernen</button>
                            </form>
                        </div>
                    @endif
                    <button type="submit" class="button">Speichern</button>
                </div>
            </form>

            {{-- Alle vorhandenen Tags --}}
            @if($allTags->count() > 0)
            <div style="border-top: 1px solid var(--color-border); margin-top: 20px; padding-top: 16px;">
                <p class="text-small text-muted mb-s">Alle vergebenen Tags:</p>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 8px;">
                    @foreach($allTags as $t)
                    <div class="flex-container-row gap-s items-center" style="background: var(--color-bg-alt); padding: 8px 12px; border-radius: 6px; border: 1px solid var(--color-border);">
                        <span class="text-small" style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $t->role_name }}">{{ $t->role_name }}</span>
                        <span style="background: {{ $t->color }}; color:#fff; font-size:0.72em; padding: 2px 10px; border-radius:20px; font-weight:600; flex-shrink:0;">{{ $t->tag }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Benutzer in dieser Rolle --}}
        <div class="card content-wrap auto-height">
            <h2 class="list-heading">{{ trans('settings.role_users') }}</h2>
            @if(count($role->users ?? []) > 0)
                <div class="grid third">
                    @foreach($role->users as $user)
                        <div class="user-list-item">
                            <div>
                                <img class="avatar small" src="{{ $user->getAvatar(40) }}" alt="{{ $user->name }}">
                            </div>
                            <div>
                                @if(userCan(\BookStack\Permissions\Permission::UsersManage) || user()->id == $user->id)
                                    <a href="{{ url("/settings/users/{$user->id}") }}">
                                @endif
                                {{ $user->name }}
                                @if(userCan(\BookStack\Permissions\Permission::UsersManage) || user()->id == $user->id)
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-muted">{{ trans('settings.role_users_none') }}</p>
            @endif
        </div>
    </div>

<script>
const colorInput = document.getElementById('color-input');
const tagInput = document.getElementById('tag-input');
const preview = document.getElementById('tag-preview');

colorInput.addEventListener('input', function() {
    preview.style.background = this.value;
});

tagInput.addEventListener('input', function() {
    preview.textContent = this.value || 'Tag';
});

function selectTag(tag, color) {
    tagInput.value = tag;
    colorInput.value = color;
    preview.textContent = tag;
    preview.style.background = color;
}

function clearTag() {
    document.getElementById('tag-input').value = '';
    document.getElementById('clear-input').value = '1';
    document.getElementById('tag-form').submit();
}
</script>
@stop