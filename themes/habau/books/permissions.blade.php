@extends('layouts.simple')
@section('body')
    <div class="container">
        <div class="my-s">
            @include('entities.breadcrumbs', ['crumbs' => [
                $book,
                $book->getUrl('/permissions') => [
                    'text' => trans('entities.books_permissions'),
                    'icon' => 'lock',
                ]
            ]])
        </div>
        <main class="card content-wrap auto-height">
            @include('form.entity-permissions', ['model' => $book, 'title' => trans('entities.books_permissions')])
        </main>

        {{-- User-Permissions --}}
        <div class="card content-wrap auto-height mt-m">
            <h2 class="list-heading">Benutzer-Berechtigungen</h2>
            <p class="text-muted text-small mb-m">Berechtigungen für einzelne Benutzer.</p>

            <form action="{{ url('/habau/permissions/book/' . $book->id) }}" method="POST">
                @csrf
                <div id="user-permissions-list">
                    @foreach($userPermissions as $up)
                    <div class="item-list-row flex-container-row justify-space-between wrap items-center user-perm-row">
                        <div class="flex px-l py-m flex-container-row items-center gap-m">
                            <img src="{{ $up->user->getAvatar(32) }}" style="width:32px;height:32px;border-radius:50%;">
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
                                    <span>{{ trans('common.create') }}</span>
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

                <div class="mt-m mb-m flex-container-row gap-m items-center" style="position:relative;">
                    <div class="flex">
                        <input type="text" id="user-search" placeholder="Benutzer suchen..." class="outline" style="width:100%;" autocomplete="off">
                        <div id="user-search-results" style="position:absolute; background:var(--color-bg); border:1px solid var(--color-border); border-radius:4px; z-index:100; min-width:300px; display:none;"></div>
                    </div>
                    <button type="button" class="button outline" id="add-user-btn" style="display:none;">@icon('add') Hinzufügen</button>
                </div>

                <div class="form-group text-right">
                    <button type="submit" class="button">{{ trans('common.save_permission') }}</button>
                </div>
            </form>
        </div>
    </div>
@include('habau-user-perm-script')
@stop