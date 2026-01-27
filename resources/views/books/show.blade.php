@extends('layouts.tri')

{{-- --- LOGIC KIỂM TRA QUYỀN TRUY CẬP --- --}}
@php
    $statusCheck = \DB::table('duyet_bai')
        ->where('entity_id', $book->id)
        ->where('entity_type', 'book')
        ->first();

    // Nếu dự án đang chờ duyệt (hoặc mới tạo)
    if ($statusCheck && $statusCheck->trang_thai === 'cho_duyet') {
        $userId = auth()->id();
        
        // 1. Check quyền Sếp/Chủ dự án
        $isLeader = (auth()->user()->hasSystemRole('admin') || $userId == $book->owned_by);
        
        // 2. Check xem có phải Thành viên dự án không?
        $isMember = \DB::table('project_members')
            ->where('book_id', $book->id)
            ->where('user_id', $userId)
            ->exists();

        // Nếu không phải Sếp, không phải Chủ, cũng không phải Thành viên -> CHẶN
        if (!$isLeader && !$isMember) {
            echo "<div style='display:flex; justify-content:center; align-items:center; height:100vh; flex-direction:column; font-family:sans-serif; background:#f4f6f8;'>
                    <div style='background:white; padding:40px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1); text-align:center;'>
                        <div style='font-size:50px; margin-bottom:20px;'>🔒</div>
                        <h2 style='color:#e53e3e; margin:0 0 10px 0;'>Dự án nội bộ</h2>
                        <p style='color:#666; margin-bottom:25px;'>Bạn cần được Leader thêm vào dự án này mới có thể truy cập.</p>
                        <a href='".url('/')."' style='padding:10px 20px; background:#206ea7; color:white; text-decoration:none; border-radius:4px; font-weight:bold;'>Quay lại trang chủ</a>
                    </div>
                  </div>";
            exit;
        }
    }
@endphp

@section('container-attrs')
    component="entity-search"
    option:entity-search:entity-id="{{ $book->id }}"
    option:entity-search:entity-type="book"
@stop

@push('social-meta')
    <meta property="og:description" content="{{ Str::limit($book->description, 100, '...') }}">
    @if($book->coverInfo()->exists())
        <meta property="og:image" content="{{ $book->coverInfo()->getUrl() }}">
    @endif
@endpush

@include('entities.body-tag-classes', ['entity' => $book])

@section('body')

    <div class="mb-s print-hidden">
        @include('entities.breadcrumbs', ['crumbs' => [$book]])
    </div>

    <main class="content-wrap card">
        <h1 class="break-text">{{$book->name}}</h1>
        <div refs="entity-search@contentView" class="book-content">
            <div class="text-muted break-text">{!! $book->descriptionInfo()->getHtml() !!}</div>
            @if(count($bookChildren) > 0)
                <div class="entity-list book-contents">
                    @foreach($bookChildren as $childElement)
                        @if($childElement->isA('chapter'))
                            @include('chapters.parts.list-item', ['chapter' => $childElement])
                        @else
                            @include('pages.parts.list-item', ['page' => $childElement])
                        @endif
                    @endforeach
                </div>
            @else
                <div class="mt-xl">
                    <hr>
                    <p class="text-muted italic mb-m mt-xl">{{ trans('entities.books_empty_contents') }}</p>
                    <div class="icon-list block inline">
                        @if(userCan(\BookStack\Permissions\Permission::PageCreate, $book))
                            <a href="{{ $book->getUrl('/create-page') }}" class="icon-list-item text-page">
                                <span class="icon">@icon('page')</span>
                                <span>{{ trans('entities.books_empty_create_page') }}</span>
                            </a>
                        @endif
                        @if(userCan(\BookStack\Permissions\Permission::ChapterCreate, $book))
                            <a href="{{ $book->getUrl('/create-chapter') }}" class="icon-list-item text-chapter">
                                <span class="icon">@icon('chapter')</span>
                                <span>{{ trans('entities.books_empty_add_chapter') }}</span>
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
        @include('entities.search-results')
    </main>

@stop

@section('right')

    {{-- 1. KHUNG QUẢN LÝ THÀNH VIÊN (CHỈ HIỆN CHO LEADER) --}}
    @if($book->owned_by === auth()->id())
    <div class="mb-xl">
        <h5>👥 Quản lý Thành viên</h5>
        <div style="background-color: var(--color-page-background); border: 1px solid var(--color-border); border-radius: 4px; padding: 12px;">
            
            <form action="{{ $book->getUrl('/member') }}" method="POST" class="flex-container-row gap-x-s items-center mb-m">
                {{ csrf_field() }}
                <input type="email" name="email" placeholder="Email nhân viên..." style="width: 100%; padding: 6px; border: 1px solid var(--color-border); border-radius: 3px; font-size: 13px;" required>
                <button type="submit" class="button outline small" style="margin-left: 5px;">Thêm</button>
            </form>

            <hr class="primary-background my-s">

            @php
                $members = \DB::table('project_members')
                    ->join('users', 'project_members.user_id', '=', 'users.id')
                    ->where('book_id', $book->id)
                    ->select('users.id', 'users.name', 'users.email')
                    ->get();
            @endphp

            @if($members->isEmpty())
                <p class="text-muted italic text-small">Dự án chưa có thành viên nào.</p>
            @else
                <ul class="list-unstyled text-small">
                    @foreach($members as $mem)
                    <li class="flex-container-row justify-between items-center mb-xs" style="padding: 4px 0; border-bottom: 1px dashed var(--color-border);">
                        <div class="text-truncate" title="{{ $mem->email }}">
                            👤 <strong>{{ $mem->name }}</strong>
                        </div>
                        <form action="{{ $book->getUrl('/member/' . $mem->id) }}" method="POST" style="margin:0;">
                            {{ csrf_field() }}
                            {{ method_field('DELETE') }}
                            <button type="submit" class="text-neg hover:underline" style="background: none; border: none; cursor: pointer; font-size: 12px;" onclick="return confirm('Mời thành viên này ra khỏi dự án?')">❌ Xóa</button>
                        </form>
                    </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
    @endif

    {{-- 2. KHUNG CHI TIẾT (DETAILS) --}}
    <div class="mb-xl">
        <h5>{{ trans('common.details') }}</h5>
        <div class="blended-links">
            @include('entities.meta', ['entity' => $book, 'watchOptions' => $watchOptions])
            @if($book->hasPermissions())
                <div class="active-restriction">
                    @if(userCan(\BookStack\Permissions\Permission::RestrictionsManage, $book))
                        <a href="{{ $book->getUrl('/permissions') }}" class="entity-meta-item">
                            @icon('lock')
                            <div>{{ trans('entities.books_permissions_active') }}</div>
                        </a>
                    @else
                        <div class="entity-meta-item">
                            @icon('lock')
                            <div>{{ trans('entities.books_permissions_active') }}</div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- 3. KHUNG HÀNH ĐỘNG (ACTIONS) --}}
    <div class="actions mb-xl">
        <h5>{{ trans('common.actions') }}</h5>
        <div class="icon-list text-link">
            {{-- Biến check quyền Leader --}}
            @php $isLeader = (auth()->id() === $book->owned_by) || auth()->user()->hasSystemRole('admin'); @endphp

            {{-- 1. CÁC NÚT TẠO (Ai cũng thấy nếu có quyền) --}}
            @if(userCan(\BookStack\Permissions\Permission::PageCreate, $book))
                <a href="{{ $book->getUrl('/create-page') }}" data-shortcut="new" class="icon-list-item">
                    <span>@icon('add')</span><span>{{ trans('entities.pages_new') }}</span>
                </a>
            @endif
            @if(userCan(\BookStack\Permissions\Permission::ChapterCreate, $book))
                <a href="{{ $book->getUrl('/create-chapter') }}" data-shortcut="new" class="icon-list-item">
                    <span>@icon('add')</span><span>{{ trans('entities.chapters_new') }}</span>
                </a>
            @endif

            <hr class="primary-background">

            {{-- 2. CÁC NÚT QUẢN TRỊ (CHỈ LEADER) --}}
            @if($isLeader)
                {{-- Sửa cấu hình sách --}}
                <a href="{{ $book->getUrl('/edit') }}" data-shortcut="edit" class="icon-list-item">
                    <span>@icon('edit')</span><span>{{ trans('common.edit') }}</span>
                </a>
                <a href="{{ $book->getUrl('/sort') }}" data-shortcut="sort" class="icon-list-item">
                    <span>@icon('sort')</span><span>{{ trans('common.sort') }}</span>
                </a>
                {{-- Copy Sách --}}
                <a href="{{ $book->getUrl('/copy') }}" data-shortcut="copy" class="icon-list-item">
                    <span>@icon('copy')</span><span>{{ trans('common.copy') }}</span>
                </a>
                {{-- Phân quyền --}}
                <a href="{{ $book->getUrl('/permissions') }}" data-shortcut="permissions" class="icon-list-item">
                    <span>@icon('lock')</span><span>{{ trans('entities.permissions') }}</span>
                </a>
                {{-- Xóa Sách --}}
                <a href="{{ $book->getUrl('/delete') }}" data-shortcut="delete" class="icon-list-item">
                    <span>@icon('delete')</span><span>{{ trans('common.delete') }}</span>
                </a>
                <hr class="primary-background">
            @endif

            {{-- Các nút tiện ích chung --}}
            @if($watchOptions->canWatch() && !$watchOptions->isWatching())
                @include('entities.watch-action', ['entity' => $book])
            @endif
            @if(userCan(\BookStack\Permissions\Permission::ContentExport))
                @include('entities.export-menu', ['entity' => $book])
            @endif
        </div>
    </div>

@stop

@section('left')
    @include('entities.search-form', ['label' => trans('entities.books_search_this')])

    @if($book->tags->count() > 0)
        <div class="mb-xl">
            @include('entities.tag-list', ['entity' => $book])
        </div>
    @endif

    @if(count($bookParentShelves) > 0)
        <div class="actions mb-xl">
            <h5>{{ trans('entities.shelves') }}</h5>
            @include('entities.list', ['entities' => $bookParentShelves, 'style' => 'compact'])
        </div>
    @endif

    @if(count($activity) > 0)
        <div id="recent-activity" class="mb-xl">
            <h5>{{ trans('entities.recent_activity') }}</h5>
            @include('common.activity-list', ['activity' => $activity])
        </div>
    @endif
@stop