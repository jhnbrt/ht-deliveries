@props(['status'])
<span {{ $attributes->class(['inline-flex items-center gap-1.5 whitespace-nowrap text-sm font-medium', $status->color()]) }}>
    <x-icon :name="$status->icon()" :class="'size-4 '.($status === \App\Enums\DeliveryStatus::Processing ? 'animate-spin' : '')" />
    {{ $status->label() }}
</span>
