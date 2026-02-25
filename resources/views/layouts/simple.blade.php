@extends('layouts.base')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
    <div class="flex-fill flex">
        <div class="content flex">
            <div id="main-content" class="scroll-body">
                @yield('body')
            </div>
        </div>
    </div>

@stop
