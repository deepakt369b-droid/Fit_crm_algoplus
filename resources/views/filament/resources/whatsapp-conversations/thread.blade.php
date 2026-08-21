@php
    /** @var \App\Models\WhatsappConversation $conversation */
    $conversation = $getRecord();
    $messages = $conversation->messages()->orderBy('occurred_at')->get();
@endphp

<div class="space-y-3 max-h-[32rem] overflow-y-auto p-2">
    @forelse ($messages as $message)
        <div class="flex {{ $message->direction === 'out' ? 'justify-end' : 'justify-start' }}">
            <div class="max-w-md rounded-lg px-3 py-2 text-sm
                {{ $message->direction === 'out' ? 'bg-primary-100 dark:bg-primary-900' : 'bg-gray-100 dark:bg-gray-800' }}">
                @if ($message->type === 'template' && $message->template_name)
                    <p class="text-xs italic text-gray-500 dark:text-gray-400">
                        {{ __('app.whatsapp.template_label', ['name' => $message->template_name]) }}
                    </p>
                @endif

                <p class="whitespace-pre-wrap">{{ $message->body ?? '—' }}</p>

                <div class="mt-1 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <span>{{ $message->occurred_at->format('d M, H:i') }}</span>
                    @if ($message->direction === 'out')
                        <span>&middot; {{ __('app.whatsapp.message_statuses.'.$message->status) }}</span>
                    @endif
                </div>

                @if ($message->status === 'failed' && $message->error_message)
                    <p class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ $message->error_message }}</p>
                @endif
            </div>
        </div>
    @empty
        <p class="text-center text-sm text-gray-500 dark:text-gray-400">{{ __('app.whatsapp.no_messages_yet') }}</p>
    @endforelse
</div>
