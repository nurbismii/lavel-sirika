<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login SIRIKA</title>
    <meta name="description" content="Login admin Sistem Rute Izin Kendaraan">
    <link rel="stylesheet" href="{{ mix('css/app.css') }}">
    <script src="{{ mix('js/app.js') }}" defer></script>
</head>
<body class="login-body">
    <main class="login-shell">
        <section class="login-hero" aria-labelledby="login-title">
            <p class="login-brand">SIRIKA</p>
            <h1 id="login-title">Login SIRIKA</h1>
            <p class="login-copy">Sistem Rute Izin Kendaraan untuk operasional izin masuk, QR, scan security, dan validasi rute internal.</p>

            <div class="login-signal-grid" aria-label="Modul aktif">
                <div class="login-signal">
                    <span class="login-signal__value">QR</span>
                    <span class="login-signal__label">Digital & cetak</span>
                </div>
                <div class="login-signal">
                    <span class="login-signal__value">Scan</span>
                    <span class="login-signal__label">Security gate</span>
                </div>
                <div class="login-signal">
                    <span class="login-signal__value">Rute</span>
                    <span class="login-signal__label">Peta VDNI</span>
                </div>
            </div>
        </section>

        <section class="login-card panel" aria-label="Form login">
            <div class="panel-body">
                <h2 class="panel-title">Masuk ke panel admin</h2>
                <p class="panel-subtitle">Gunakan akun yang sudah dibuat oleh Super Admin.</p>

                @if ($errors->any())
                    <div class="alert alert--danger layout-gap" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form
                    method="POST"
                    action="{{ route('login.attempt') }}"
                    class="form-stack layout-gap"
                    x-data="{ showPassword: false, submitting: false }"
                    x-on:submit="submitting = true"
                >
                    @csrf

                    <div class="form-field">
                        <label for="email">Email</label>
                        <input
                            id="email"
                            class="form-control"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            autocomplete="email"
                            required
                            autofocus
                        >
                        @error('email')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-field">
                        <label for="password">Password</label>
                        <div class="password-field">
                            <input
                                id="password"
                                class="form-control"
                                name="password"
                                x-bind:type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                required
                            >
                            <button
                                class="button"
                                type="button"
                                x-on:click="showPassword = ! showPassword"
                                x-text="showPassword ? 'Sembunyikan' : 'Tampilkan'"
                            >Tampilkan</button>
                        </div>
                        @error('password')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="checkbox-field">
                        <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                        <span>Ingat sesi ini</span>
                    </label>

                    <button class="button button-primary" type="submit" x-bind:disabled="submitting">
                        <span x-show="! submitting">Masuk</span>
                        <span x-cloak x-show="submitting">Memproses...</span>
                    </button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
