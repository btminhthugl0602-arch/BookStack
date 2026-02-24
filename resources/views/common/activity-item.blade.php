{{-- D:\kimoanh\laragon\www\BookStack\resources\views\common\activity-item.blade.php --}}

<div>
    @if($activity->user)
        <img class="avatar" src="{{ $activity->user->getAvatar(30) }}" alt="{{ $activity->user->name ?? 'User' }}">
    @endif
</div>

<div>
    @php
        // 1. XỬ LÝ AN TOÀN CHO LINK PROFILE NGƯỜI DÙNG
        $userProfileUrl = '#';
        $userName = trans('common.deleted_user');
        
        if ($activity->user) {
            $userName = $activity->user->name;
            try {
                // Thử lấy link profile. Nếu user bị lỗi dữ liệu, sẽ rơi vào catch
                $userProfileUrl = $activity->user->getProfileUrl();
            } catch (\Exception $e) {
                // Phương án dự phòng an toàn tuyệt đối
                $userProfileUrl = url('/user/' . $activity->user->id);
            }
        }

        // 2. XỬ LÝ AN TOÀN CHO LINK NỘI DUNG (TRANG/CHƯƠNG/SÁCH)
        $entityUrl = '';
        $entityName = '(Nội dung không tồn tại hoặc đã bị xóa)';
        $isEntityValid = false;

        if ($activity->loggable) {
            // Lấy tên nếu có
            $entityName = $activity->loggable->name ?? $entityName;
            
            try {
                // ĐÂY LÀ NƠI GÂY RA LỖI "SLUG ON NULL"
                // Nếu hàm getUrl() bị lỗi (do thiếu Sách cha, thiếu slug...), catch sẽ chặn lại ngay lập tức
                $entityUrl = $activity->loggable->getUrl();
                $isEntityValid = true;
            } catch (\Exception $e) {
                // Bắt lỗi thành công, đánh dấu là không hợp lệ
                $isEntityValid = false;
            }
        }
    @endphp

    {{-- HIỂN THỊ TÊN NGƯỜI DÙNG VÀ ĐIỀU HƯỚNG SANG PROFILE --}}
    @if($activity->user)
        <a href="{{ $userProfileUrl }}" class="font-weight-bold">{{ $userName }}</a>
    @else
        <span class="text-muted">{{ $userName }}</span>
    @endif

    {{-- HIỂN THỊ HÀNH ĐỘNG (ví dụ: "đã cập nhật trang") --}}
    {{ $activity->getText() }}

    {{-- HIỂN THỊ ĐỐI TƯỢNG (KÈM LINK NẾU HỢP LỆ) --}}
    @if($isEntityValid && !empty($entityUrl))
        <a href="{{ $entityUrl }}">"{{ $entityName }}"</a>
    @elseif($activity->loggable)
        {{-- Nếu đối tượng còn lưu nhưng bị lỗi (mất Sách cha, mất slug), dẫn về Trang chủ để tránh 404 --}}
        <a href="{{ url('/') }}" class="text-muted" title="Dữ liệu lỗi - Quay về trang chủ">"{{ $entityName }}"</a>
    @endif

    <br>

    <span class="text-muted" title="{{ $dates->absolute($activity->created_at) }}">
        <small>@icon('time'){{ $dates->relative($activity->created_at) }}</small>
    </span>
</div>