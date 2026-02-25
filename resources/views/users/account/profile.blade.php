@extends('users.account.layout')

@section('main')

    <section class="card content-wrap auto-height">
        <form action="{{ url('/my-account/profile') }}" method="post" enctype="multipart/form-data">
            {{ method_field('put') }}
            {{ csrf_field() }}

            <div class="flex-container-row gap-l items-center wrap justify-space-between">
                <h1 class="list-heading">{{ trans('preferences.profile') }}</h1>
                <div>
                    <a href="{{ user()->getProfileUrl() }}" class="button outline">{{ trans('preferences.profile_view_public') }}</a>
                </div>
            </div>

            <p class="text-muted text-small mb-none">{{ trans('preferences.profile_desc') }}</p>

            <div class="setting-list">

                <div class="flex-container-row gap-l items-center wrap">
                    <div class="flex">
                        <label class="setting-list-label" for="name">{{ trans('auth.name') }}</label>
                        <p class="text-small mb-none">{{ trans('preferences.profile_name_desc') }}</p>
                    </div>
                    <div class="flex stretch-inputs">
                        @include('form.text', ['name' => 'name'])
                    </div>
                </div>

                <div>
                    <div class="flex-container-row gap-l items-center wrap">
                        <div class="flex">
                            <label class="setting-list-label" for="email">{{ trans('auth.email') }}</label>
                            <p class="text-small mb-none">{{ trans('preferences.profile_email_desc') }}</p>
                        </div>
                        <div class="flex stretch-inputs">
                            @include('form.text', ['name' => 'email', 'disabled' => !userCan(\BookStack\Permissions\Permission::UsersManage)])
                        </div>
                    </div>
                    @if(!userCan(\BookStack\Permissions\Permission::UsersManage))
                        <p class="text-small text-muted">{{ trans('preferences.profile_email_no_permission') }}</p>
                    @endif
                </div>

                <div class="grid half gap-xl">
                    <div>
                        <label for="user-avatar"
                               class="setting-list-label">{{ trans('settings.users_avatar') }}</label>
                        <p class="text-small">{{ trans('preferences.profile_avatar_desc') }}</p>
                    </div>
                    <div>
                        @include('form.image-picker', [
                            'resizeHeight' => '512',
                            'resizeWidth' => '512',
                            'showRemove' => false,
                            'defaultImage' => url('/user_avatar.png'),
                            'currentImage' => user()->getAvatar(80),
                            'currentId' => user()->image_id,
                            'name' => 'profile_image',
                            'imageClass' => 'avatar large'
                        ])
                    </div>
                </div>

                @include('users.parts.language-option-row', ['value' => old('language') ?? user()->getLocale()->appLocale()])

            </div>

            <div class="form-group text-right">
                <a href="{{ url('/my-account/delete') }}" class="button outline">{{ trans('preferences.delete_account') }}</a>
                <button class="button">{{ trans('common.save') }}</button>
            </div>

        </form>
    </section>

    <section class="card content-wrap auto-height">
        <div class="flex-container-row gap-l items-center wrap">
            <div class="flex">
                <h2 class="list-heading">Xác thực khuôn mặt</h2>
                <p class="text-small mb-none">Chụp khuôn mặt để cập nhật hoặc xóa. Hệ thống sẽ xác thực trước khi thực hiện.</p>
                @if(user()->face_token)
                    <p class="text-small text-success">Khuôn mặt của bạn đã được đăng ký.</p>
                @else
                    <p class="text-small text-muted">Bạn chưa có dữ liệu khuôn mặt. Hãy cập nhật để bật đăng nhập bằng khuôn mặt.</p>
                @endif
            </div>
            <div class="flex gap-s">
                <button
                    id="face-update-btn"
                    type="button"
                    class="button outline"
                    data-has-face="{{ user()->face_token ? 'true' : 'false' }}">
                    Cập nhật khuôn mặt
                </button>
                <button
                    id="face-delete-btn"
                    type="button"
                    class="button outline negative">
                    Xóa khuôn mặt
                </button>
            </div>
        </div>

        <video
            id="face-register-video"
            autoplay
            muted
            playsinline
            style="width:100%; max-height: 400px; margin-top:10px; transform: scaleX(-1); display: none; border: 2px solid #ddd; border-radius: 4px; object-fit: cover;">
        </video>
        <canvas id="face-register-canvas" style="display:none;"></canvas>
        <p id="face-status-text" class="text-small text-muted" style="margin-top:10px;"></p>
    </section>

    @if(userCan(\BookStack\Permissions\Permission::UsersManage))
        <section class="card content-wrap auto-height">
            <div class="flex-container-row gap-l items-center wrap">
                <div class="flex">
                    <h2 class="list-heading">{{ trans('preferences.profile_admin_options') }}</h2>
                    <p class="text-small">{{ trans('preferences.profile_admin_options_desc') }}</p>
                </div>
                <div class="text-m-right">
                    <a class="button outline" href="{{ user()->getEditUrl() }}">{{ trans('common.open') }}</a>
                </div>
            </div>
        </section>
    @endif
@stop
