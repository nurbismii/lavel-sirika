@extends('layouts.app')

@php
    $pageTitle = 'Manajemen User';
    $pageDescription = 'Kelola akun, role, dan status akses panel SIRIKA.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <div class="section-header">
                <div>
                    <h2 class="panel-title">Daftar User</h2>
                    <p class="panel-subtitle">Hanya Super Admin yang dapat membuat dan mengubah akun.</p>
                </div>
                <a class="button button-primary" href="{{ route('users.create') }}">Tambah User</a>
            </div>

            <form method="GET" action="{{ route('users.index') }}" class="toolbar layout-gap">
                <label class="sr-only" for="q">Cari user</label>
                <input
                    id="q"
                    class="form-control toolbar-search"
                    name="q"
                    type="search"
                    value="{{ $search }}"
                    placeholder="Cari nama, email, atau role"
                    autocomplete="off"
                >
                <button class="button" type="submit">Cari</button>
                @if ($search !== '')
                    <a class="button" href="{{ route('users.index') }}">Reset</a>
                @endif
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Login Terakhir</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $managedUser)
                            <tr>
                                <td><strong>{{ $managedUser->name }}</strong></td>
                                <td>{{ $managedUser->email }}</td>
                                <td><span class="status-pill">{{ $managedUser->roleLabel() }}</span></td>
                                <td><span class="status-pill">{{ $managedUser->statusLabel() }}</span></td>
                                <td>{{ optional($managedUser->last_login_at)->format('d M Y H:i') ?? '-' }}</td>
                                <td>
                                    <div class="table-actions">
                                        <a class="button" href="{{ route('users.show', $managedUser) }}">Detail</a>
                                        <a class="button" href="{{ route('users.edit', $managedUser) }}">Edit</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">Belum ada user yang cocok dengan pencarian.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination-wrap">
                {{ $users->links() }}
            </div>
        </div>
    </section>
@endsection
