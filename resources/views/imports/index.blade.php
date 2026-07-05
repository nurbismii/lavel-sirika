@extends('layouts.app')

@php
    $pageTitle = 'Import Excel';
    $pageDescription = 'Upload database izin kendaraan, validasi isi file, lalu commit data yang aman.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <h2 class="panel-title">Upload Excel</h2>
            <p class="panel-subtitle">Format yang diterima: .xlsx atau .xls, maksimal 10 MB. Data akan masuk preview terlebih dahulu.</p>

            <form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" class="form-stack">
                @csrf
                <div class="form-field">
                    <label for="file">File Excel</label>
                    <input id="file" name="file" type="file" accept=".xlsx,.xls" required>
                    @error('file')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <button class="button button-primary" type="submit">Upload dan Preview</button>
            </form>
        </div>
    </section>

    <section class="page-section panel">
        <div class="panel-body">
            <h2 class="panel-title">Daftar Batch Import</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Valid</th>
                            <th>Invalid</th>
                            <th>Review</th>
                            <th>Uploader</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($batches as $batch)
                            <tr>
                                <td>{{ $batch->filename }}</td>
                                <td><span class="status-pill">{{ $batch->status }}</span></td>
                                <td>{{ $batch->total_rows }}</td>
                                <td>{{ $batch->success_rows }}</td>
                                <td>{{ $batch->failed_rows }}</td>
                                <td>{{ $batch->review_rows }}</td>
                                <td>{{ optional($batch->uploader)->name ?? '-' }}</td>
                                <td><a class="button" href="{{ route('imports.show', $batch) }}">Preview</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">Belum ada batch import.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination-wrap">
                {{ $batches->links() }}
            </div>
        </div>
    </section>
@endsection
