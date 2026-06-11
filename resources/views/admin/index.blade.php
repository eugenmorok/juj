@extends('layouts.app', ['title' => 'Админ'])

@section('content')
    <div class="space-y-6">
        <div>
            <p class="text-sm font-medium uppercase text-amber-300">Администрирование</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Админ-панель</h1>
        </div>

        <div class="rounded-md border border-amber-500/30 bg-amber-500/10 p-5 text-amber-100">
            Доступ администратора активен.
        </div>
    </div>
@endsection
