@extends('layouts.app')

@php
    $pageTitle = 'Tambah User';
    $pageDescription = 'Buat akun internal SIRIKA dan tentukan role aksesnya.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <h2 class="panel-title">Data User Baru</h2>
            <p class="panel-subtitle">Gunakan email unik dan password awal minimal 8 karakter.</p>

            <div class="layout-gap">
                @include('users._form', [
                    'action' => route('users.store'),
                    'submitLabel' => 'Simpan User',
                    'cancelUrl' => route('users.index'),
                    'passwordRequired' => true,
                    'isSelf' => false,
                ])
            </div>
        </div>
    </section>
@endsection
