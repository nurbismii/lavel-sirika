@extends('layouts.app')

@php
    $pageTitle = 'Edit User';
    $pageDescription = 'Perbarui profil, role, status, atau password akun internal.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <h2 class="panel-title">{{ $managedUser->name }}</h2>
            <p class="panel-subtitle">{{ $managedUser->email }}</p>

            <div class="layout-gap">
                @include('users._form', [
                    'action' => route('users.update', $managedUser),
                    'method' => 'PUT',
                    'submitLabel' => 'Simpan Perubahan',
                    'cancelUrl' => route('users.show', $managedUser),
                    'passwordRequired' => false,
                    'isSelf' => $isSelf,
                ])
            </div>
        </div>
    </section>
@endsection
