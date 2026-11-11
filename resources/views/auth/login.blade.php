@extends('layouts.ccdae')
@section('title', 'Entrar')
@section('content')
<h1 style="font-size:28px;margin-bottom:24px">Entrar no CCDAE</h1>
<form method="post" action="{{ route('login.store') }}" class="ccdae-map-card ccdae-form">
    @csrf
    <p>Use sua conta do CCDAE para gerenciar os cadastros.</p>
    @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
    <label for="email">E-mail</label><input id="email" name="email" type="email" autocomplete="username" required value="{{ old('email') }}">
    <label for="password">Senha</label><input id="password" name="password" type="password" autocomplete="current-password" required>
    <button class="ccdae-theme-toggle" type="submit">Entrar</button>
</form>
@endsection
