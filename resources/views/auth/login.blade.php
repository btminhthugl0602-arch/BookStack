
@extends('layouts.simple')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="container very-small">

    <div class="my-l">&nbsp;</div>

    <div class="card content-wrap auto-height">
        <h1 class="list-heading">{{ Str::title(trans('auth.log_in')) }}</h1>

        @include('auth.parts.login-message')

        @include('auth.parts.login-form-' . $authMethod)
        <hr class="my-m">

        <button
            id="face-login-btn"
            type="button"
            class="button outline full-width">
            🔐 Đăng nhập bằng khuôn mặt
        </button>

        <video
            id="face-video"
            autoplay
            muted
            playsinline
            style="width:100%; max-height: 400px; margin-top:10px; transform: scaleX(-1); display: none; border: 2px solid #ddd; border-radius: 4px; object-fit: cover;">
        </video>

        <canvas id="face-canvas" style="display:none;"></canvas>

            @if(count($socialDrivers) > 0)
                <hr class="my-l">
                @foreach($socialDrivers as $driver => $name)
                    <div>
                        <a id="social-login-{{$driver}}" class="button outline svg" href="{{ url("/login/service/" . $driver) }}">
                            @icon('auth/' . $driver)
                            <span>{{ trans('auth.log_in_with', ['socialDriver' => $name]) }}</span>
                        </a>
                    </div>
                @endforeach
            @endif

            @if(setting('registration-enabled') && config('auth.method') === 'standard')
                <div class="text-center pb-s">
                    <hr class="my-l">
                    <a href="{{ url('/register') }}">{{ trans('auth.dont_have_account') }}</a>
                </div>
            @endif
        </div>
    </div>

@stop
