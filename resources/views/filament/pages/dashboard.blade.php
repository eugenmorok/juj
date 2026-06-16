<div class="space-y-6">
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-7">
        @foreach ($stats as $stat)
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</div>
                <div class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $stat['value'] }}</div>
            </div>
        @endforeach
    </section>

    <section class="grid gap-4 lg:grid-cols-3">
        @foreach ($links as $link)
            <a
                href="{{ route($link['route']) }}"
                class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary-500 dark:border-gray-800 dark:bg-gray-900"
            >
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ $link['label'] }}</h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $link['description'] }}</p>
                <div class="mt-4 text-sm font-medium text-primary-600 dark:text-primary-400">Открыть</div>
            </a>
        @endforeach
    </section>
</div>
