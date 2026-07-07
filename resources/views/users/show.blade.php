@extends('layouts.app')

@php
    $pageTitle = 'Detail User';
    $pageDescription = 'Ringkasan akun dan akses panel SIRIKA.';
    $isSelf = auth()->id() === $managedUser->id;
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <div class="section-header">
                <div>
                    <h2 class="panel-title">{{ $managedUser->name }}</h2>
                    <p class="panel-subtitle">{{ $managedUser->email }}</p>
                </div>
                <div class="table-actions">
                    <a class="button" href="{{ route('users.index') }}">Kembali</a>
                    <a class="button button-primary" href="{{ route('users.edit', $managedUser) }}">Edit User</a>
                </div>
            </div>

            <dl class="detail-grid layout-gap">
                <div>
                    <dt>Role</dt>
                    <dd>{{ $managedUser->roleLabel() }}</dd>
                </div>
                <div>
                    <dt>Status</dt>
                    <dd>{{ $managedUser->statusLabel() }}</dd>
                </div>
                <div>
                    <dt>Dibuat</dt>
                    <dd>{{ optional($managedUser->created_at)->format('d M Y H:i') ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Login Terakhir</dt>
                    <dd>{{ optional($managedUser->last_login_at)->format('d M Y H:i') ?? '-' }}</dd>
                </div>
            </dl>
        </div>
    </section>

    <section class="page-section panel">
        <div class="panel-body">
            <h2 class="panel-title">Aksi Berisiko</h2>
            <p class="panel-subtitle">Penghapusan akun bersifat permanen dan tidak menghapus histori audit lama.</p>

            @if ($isSelf)
                <x-alert type="warning" class="layout-gap">
                    Akun yang sedang digunakan tidak boleh dihapus.
                </x-alert>
            @else
                <form
                    method="POST"
                    action="{{ route('users.destroy', $managedUser) }}"
                    class="layout-gap"
                    x-data="{ confirmDelete: false }"
                >
                    @csrf
                    @method('DELETE')

                    <div class="form-actions">
                        <button
                            class="button button-danger"
                            type="button"
                            x-show="! confirmDelete"
                            x-on:click="confirmDelete = true"
                        >Hapus User</button>
                        <button
                            class="button button-danger"
                            type="submit"
                            x-cloak
                            x-show="confirmDelete"
                        >Konfirmasi Hapus</button>
                        <button
                            class="button"
                            type="button"
                            x-cloak
                            x-show="confirmDelete"
                            x-on:click="confirmDelete = false"
                        >Batal</button>
                    </div>
                </form>
            @endif
        </div>
    </section>
@endsection
