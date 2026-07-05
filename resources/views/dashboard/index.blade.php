<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard SIRIKA</title>
</head>
<body>
    <h1>Dashboard SIRIKA</h1>
    <p>Segmen rute aktif: {{ $activeRoadSegments }}</p>
    <p>User aktif: {{ $activeUsers }}</p>
    <p>Izin aktif: {{ $activePermits }}</p>
    <p>Perlu review: {{ $reviewPermits }}</p>
    <p>Scan hari ini: {{ $todayScans }}</p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Logout</button>
    </form>
</body>
</html>
