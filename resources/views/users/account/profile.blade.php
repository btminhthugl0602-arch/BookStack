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

            <!-- Face Registration Section -->
            <div class="setting-list">
                <div class="flex-container-row gap-l items-center wrap">
                    <div class="flex">
                        <label class="setting-list-label">🔐 Xác thực khuôn mặt</label>
                        <p class="text-small mb-none">Đăng ký khuôn mặt để đăng nhập bằng nhận diện khuôn mặt</p>
                    </div>
                    <div class="flex">
                        <button
                            id="face-register-btn"
                            type="button"
                            class="button outline">
                            📸 Đăng ký khuôn mặt
                        </button>
                    </div>
                </div>

                @if(user()->face_token)
                    <p class="text-small text-success">✅ Khuôn mặt đã được đăng ký</p>
                @endif

                <!-- Video and Canvas for Face Capture -->
                <video
                    id="face-register-video"
                    autoplay
                    muted
                    playsinline
                    style="width:100%; max-height: 400px; margin-top:10px; transform: scaleX(-1); display: none; border: 2px solid #ddd; border-radius: 4px; object-fit: cover;">
                </video>
                <canvas id="face-register-canvas" style="display:none;"></canvas>
            </div>

            <div class="form-group text-right">
                <a href="{{ url('/my-account/delete') }}" class="button outline">{{ trans('preferences.delete_account') }}</a>
                <button class="button">{{ trans('common.save') }}</button>
            </div>

        </form>
    </section>

    <!-- Face Recognition Section -->
    <section class="card content-wrap auto-height">
        <div class="flex-container-row gap-l items-center wrap">
            <div class="flex">
                <h2 class="list-heading">📸 Đăng ký khuôn mặt (Upload ảnh)</h2>
                <p class="text-small mb-none">Tải ảnh khuôn mặt để đăng ký nhận diện khuôn mặt. Ảnh phải có kích thước dưới 5MB.</p>
            </div>
        </div>
        
        <div class="setting-list">
            <form id="face-upload-form" enctype="multipart/form-data">
                {{ csrf_field() }}
                
                <div class="flex-container-row gap-l items-center wrap">
                    <div class="flex">
                        <label class="setting-list-label" for="face-image">Chọn ảnh khuôn mặt</label>
                        <p class="text-small mb-none">JPG, PNG - Tối đa 5MB</p>
                    </div>
                    <div class="flex stretch-inputs">
                        <input type="file" id="face-image" name="face_image" accept="image/jpeg,image/png" required>
                    </div>
                </div>
                
                <div class="form-group text-right">
                    <button type="submit" class="button" id="face-upload-btn">📸 Đăng ký khuôn mặt</button>
                </div>
            </form>
            
            <div id="face-upload-status" style="display:none; margin-top: 15px; padding: 10px; border-radius: 4px;">
                <p id="face-upload-message"></p>
            </div>
        </div>
    </section>

    <script>
        document.getElementById('face-upload-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const fileInput = document.getElementById('face-image');
            const statusDiv = document.getElementById('face-upload-status');
            const messageDiv = document.getElementById('face-upload-message');
            const btn = document.getElementById('face-upload-btn');
            
            if (!fileInput.files[0]) {
                messageDiv.textContent = '❌ Vui lòng chọn ảnh';
                statusDiv.style.backgroundColor = '#fee';
                statusDiv.style.color = '#c33';
                statusDiv.style.display = 'block';
                return;
            }
            
            const formData = new FormData();
            formData.append('face_image', fileInput.files[0]);
            formData.append('_token', document.querySelector('input[name="_token"]').value);
            
            btn.disabled = true;
            btn.textContent = '⏳ Đang xử lý...';
            statusDiv.style.display = 'none';
            
            try {
                const response = await fetch('{{ url("/user/face/upload-register") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    messageDiv.innerHTML = '✅ ' + data.message;
                    statusDiv.style.backgroundColor = '#efe';
                    statusDiv.style.color = '#383';
                    fileInput.value = '';
                } else {
                    messageDiv.innerHTML = '❌ ' + (data.error || 'Đã xảy ra lỗi');
                    statusDiv.style.backgroundColor = '#fee';
                    statusDiv.style.color = '#c33';
                }
                
                statusDiv.style.display = 'block';
            } catch (err) {
                console.error(err);
                messageDiv.textContent = '❌ Lỗi: ' + err.message;
                statusDiv.style.backgroundColor = '#fee';
                statusDiv.style.color = '#c33';
                statusDiv.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.textContent = '📸 Đăng ký khuôn mặt';
            }
        });
    </script>

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
