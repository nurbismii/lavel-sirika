@extends('layouts.app')

@php
    $pageTitle = 'Tambah Segmen Rute';
    $pageDescription = 'Simpan segmen sebagai draft, lalu lengkapi jalurnya melalui editor Leaflet.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <form method="POST" action="{{ route('road-segments.store') }}" class="form-stack">
                @csrf
                <div class="form-field"><label for="code">Kode Segmen</label><input id="code" name="code" value="{{ old('code') }}" required></div>
                <div class="form-field"><label for="name">Nama Segmen</label><input id="name" name="name" value="{{ old('name') }}" required></div>
                <div class="form-field"><label for="start_location">Lokasi Awal</label><input id="start_location" name="start_location" value="{{ old('start_location') }}" required></div>
                <div class="form-field"><label for="end_location">Lokasi Akhir</label><input id="end_location" name="end_location" value="{{ old('end_location') }}" required></div>
                <div class="table-actions"><button class="button button-primary" type="submit">Simpan Draft</button><a class="button" href="{{ route('road-segments.index') }}">Batal</a></div>
            </form>
        </div>
    </section>
@endsection
