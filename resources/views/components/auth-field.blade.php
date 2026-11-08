@props(['name', 'label', 'type' => 'text', 'icon' => null, 'showError' => true])

<div class="auth-field">
    <label for="{{ $name }}">{{ $label }}</label>
    <div @class(['auth-input', 'has-icon' => $icon, 'has-error' => $errors->has($name)])>
        @if ($icon)
            <x-icon :name="$icon" class="auth-i auth-input-icon" />
        @endif
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @if ($type !== 'password') value="{{ old($name) }}" @endif
            {{ $attributes }}
        >
        @if ($type === 'password')
            <button type="button" class="auth-toggle" data-toggle-password="{{ $name }}" aria-pressed="false">Show</button>
        @endif
    </div>
    @if ($showError)
        @error($name)
            <p class="auth-error">{{ $message }}</p>
        @enderror
    @endif
</div>
