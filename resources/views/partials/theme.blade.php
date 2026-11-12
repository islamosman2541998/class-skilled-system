{{-- Values are validated hex colours and fixed font stacks, so they are printed unescaped. --}}
<style>:root{@foreach (\App\Support\Theme::cssVariables() as $name => $value){{ $name }}:{!! $value !!};@endforeach}</style>
