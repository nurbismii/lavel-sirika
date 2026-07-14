@extends('layouts.app')

@php
    $pageTitle = 'Edit Segmen Rute';
    $pageDescription = 'Perbarui metadata segmen tanpa menghapus riwayat rute dan izin.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            @include('road-segments._form', [
                'formAction' => route('road-segments.update', $roadSegment),
                'formMethod' => 'PUT',
                'submitLabel' => 'Simpan Perubahan',
            ])
        </div>
    </section>
@endsection
