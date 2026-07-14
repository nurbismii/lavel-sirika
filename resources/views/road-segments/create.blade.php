@extends('layouts.app')

@php
    $pageTitle = 'Tambah Segmen Rute';
    $pageDescription = 'Simpan segmen sebagai draft, lalu lengkapi jalurnya melalui editor Leaflet.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            @include('road-segments._form', [
                'formAction' => route('road-segments.store'),
                'formMethod' => 'POST',
                'submitLabel' => 'Simpan Draft',
            ])
        </div>
    </section>
@endsection
