@props(['name'])
@error($name)<p {{ $attributes->merge(['class' => 'mt-1 text-sm text-rose-500']) }}>{{ $message }}</p>@enderror
