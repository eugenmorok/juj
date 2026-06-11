@extends('layouts.app', ['title' => 'Инвентарь'])

@section('content')
    <div class="space-y-6">
        <div>
            <p class="text-sm font-medium uppercase text-emerald-300">Предметы</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Инвентарь</h1>
        </div>

        <div class="grid gap-3 sm:grid-cols-5">
            @for ($slot = 1; $slot <= $user->inventory_slots; $slot++)
                <div class="flex aspect-square items-center justify-center rounded-md border border-dashed border-zinc-700 bg-zinc-900 text-sm text-zinc-500">
                    {{ $slot }}
                </div>
            @endfor
        </div>
    </div>
@endsection
