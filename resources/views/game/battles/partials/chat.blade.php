@php
    $viewer = $viewer ?? request()->user();
    $messages = $battle->messages ?? collect();
@endphp

<section class="battle-chat-card rounded-md border border-zinc-800 bg-zinc-900 p-3">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-white">Чат боя</h2>
        </div>
        <span class="rounded-md border border-zinc-700 px-2 py-0.5 text-xs text-zinc-300">
            {{ $messages->count() }} сообщ.
        </span>
    </div>

    <div class="mt-2 max-h-36 space-y-1.5 overflow-y-auto rounded-md border border-zinc-800 bg-zinc-950 p-2">
        @forelse ($messages as $message)
            <article class="rounded-md border px-2 py-1.5 text-xs {{ $message->user_id === $viewer?->id ? 'border-emerald-500/40 bg-emerald-500/10' : 'border-zinc-800 bg-zinc-900' }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-medium text-white">{{ $message->user?->name ?? 'Игрок' }}</span>
                    <time class="text-xs text-zinc-500" datetime="{{ $message->created_at?->toISOString() }}">
                        {{ $message->created_at?->format('H:i:s') }}
                    </time>
                </div>
                <p class="mt-1 whitespace-pre-wrap text-zinc-300">{{ $message->message }}</p>
            </article>
        @empty
            <div class="rounded-md border border-dashed border-zinc-700 px-3 py-5 text-center text-sm text-zinc-400">
                Сообщений пока нет.
            </div>
        @endforelse
    </div>

    <form method="POST" action="{{ route('arena.battles.messages.store', $battle) }}" class="mt-2 grid gap-2 sm:grid-cols-[1fr_auto]" data-battle-chat-form>
        @csrf
        <label class="sr-only" for="battle_message_{{ $battle->id }}">Сообщение</label>
        <input
            id="battle_message_{{ $battle->id }}"
            name="message"
            maxlength="500"
            autocomplete="off"
            placeholder="Написать сопернику..."
            class="w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100"
            data-battle-chat-input
        >
        <button type="submit" class="rounded-md bg-emerald-500 px-4 py-2 text-sm font-medium text-zinc-950 hover:bg-emerald-400">
            Отправить
        </button>
    </form>

    <p class="mt-2 hidden text-sm text-rose-100" data-battle-chat-error></p>
</section>
