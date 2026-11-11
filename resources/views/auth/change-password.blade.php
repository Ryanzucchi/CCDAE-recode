@extends('layouts.ccdae')
@section('title', 'Alterar senha')
@section('content')
<h1 style="font-size:28px;margin-bottom:24px">Alterar senha master</h1>
<form method="post" action="{{ route('master.password.update') }}" class="ccdae-map-card ccdae-form">@csrf @method('PUT')
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
    <label for="current-password">Senha atual</label><input id="current-password" name="current_password" type="password" autocomplete="current-password" required>
    <label for="new-password">Nova senha (mínimo 14 caracteres)</label><input id="new-password" name="password" type="password" autocomplete="new-password" minlength="14" required>
    <label for="confirm-password">Confirmar nova senha</label><input id="confirm-password" name="password_confirmation" type="password" autocomplete="new-password" minlength="14" required>
    <button class="ccdae-theme-toggle" type="submit">Alterar senha</button>
</form>
@endsection
