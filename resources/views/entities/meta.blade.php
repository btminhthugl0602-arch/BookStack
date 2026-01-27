<div class="entity-meta">
    @if($entity->isA('revision'))
        <div class="entity-meta-item">
            @icon('history')
            <div>
                {{ trans('entities.pages_revision') }}
                {{ trans('entities.pages_revisions_number') }}{{ $entity->revision_number == 0 ? '' : $entity->revision_number }}
            </div>
        </div>
    @endif

    @if ($entity->isA('page'))
        <a href="{{ $entity->getUrl('/revisions') }}" class="entity-meta-item">
            @icon('history'){{ trans('entities.meta_revision', ['revisionCount' => $entity->revision_count]) }}
        </a>
    @endif

    @if ($entity->ownedBy && $entity->owned_by !== $entity->created_by)
        <div class="entity-meta-item">
            @icon('user')
            <div>
                {!! trans('entities.meta_owned_name', [
                    'user' => "<a href='{$entity->ownedBy->getProfileUrl()}'>".e($entity->ownedBy->name). "</a>"
                ]) !!}
            </div>
        </div>
    @endif

    @if ($entity->createdBy)
        <div class="entity-meta-item">
            @icon('star')
            <div>
                {!! trans('entities.meta_created_name', [
                    'timeLength' => '<span title="'. $dates->absolute($entity->created_at) .'">'. $dates->relative($entity->created_at) . '</span>',
                    'user' => "<a href='{$entity->createdBy->getProfileUrl()}'>".e($entity->createdBy->name). "</a>"
                ]) !!}
            </div>
        </div>
    @else
        <div class="entity-meta-item">
            @icon('star')
            <span title="{{ $dates->absolute($entity->created_at) }}">{{ trans('entities.meta_created', ['timeLength' => $dates->relative($entity->created_at)]) }}</span>
        </div>
    @endif

    @if ($entity->updatedBy)
        <div class="entity-meta-item">
            @icon('edit')
            <div>
                {!! trans('entities.meta_updated_name', [
                    'timeLength' => '<span title="' . $dates->absolute($entity->updated_at) .'">' . $dates->relative($entity->updated_at) .'</span>',
                    'user' => "<a href='{$entity->updatedBy->getProfileUrl()}'>".e($entity->updatedBy->name). "</a>"
                ]) !!}
            </div>
        </div>
    @elseif (!$entity->isA('revision'))
        <div class="entity-meta-item">
            @icon('edit')
            <span title="{{ $dates->absolute($entity->updated_at) }}">{{ trans('entities.meta_updated', ['timeLength' => $dates->relative($entity->updated_at)]) }}</span>
        </div>
    @endif

    @if($referenceCount ?? 0)
        <a href="{{ $entity->getUrl('/references') }}" class="entity-meta-item">
            @icon('reference')
            <div>
                {{ trans_choice('entities.meta_reference_count', $referenceCount, ['count' => $referenceCount]) }}
            </div>
        </a>
    @endif

    @if($watchOptions?->canWatch())
        @if($watchOptions->isWatching())
            @include('entities.watch-controls', [
                'entity' => $entity,
                'watchLevel' => $watchOptions->getWatchLevel(),
                'label' => trans('entities.watch_detail_' . $watchOptions->getWatchLevel()),
                'ignoring' => $watchOptions->getWatchLevel() === 'ignore',
            ])
        @elseif($watchedParent = $watchOptions->getWatchedParent())
            @include('entities.watch-controls', [
                'entity' => $entity,
                'watchLevel' => $watchOptions->getWatchLevel(),
                'label' => trans('entities.watch_detail_parent_' . $watchedParent->type . ($watchedParent->ignoring() ? '_ignore' : '')),
                'ignoring' => $watchedParent->ignoring(),
            ])
        @endif
    @endif
{{-- PHẦN KIỂM TRA DUYỆT BÀI CHI TIẾT --}}
@php
    $checkDuyet = \DB::table('duyet_bai')
        ->where('entity_id', $entity->id)
        ->where('entity_type', $entity->getType())
        ->first();
    
    $trangThai = $checkDuyet ? $checkDuyet->trang_thai : 'da_duyet';
    $user = auth()->user();
    $currentUserId = auth()->id();
    
    // Tìm ID chủ dự án (Người tạo ra cuốn sách)
    $bookOwnerId = ($entity->isA('book')) ? $entity->owned_by : ($entity->book ? $entity->book->owned_by : null);

    $canApprove = false;
    if ($trangThai === 'cho_duyet') {
        // 1. Admin System luôn thấy nút duyệt
        if ($user->email === 'adminsystem@admin.com') {
            $canApprove = true;
        } 
        // 2. Chủ dự án được duyệt bài người khác, nhưng KHÔNG ĐƯỢC tự duyệt bài mình
        elseif ($currentUserId == $bookOwnerId && $currentUserId != $entity->owned_by) {
            $canApprove = true;
        }
    }
@endphp

@if($canApprove)
    <div style="margin-top: 15px; background: #d4edda; padding: 15px; border: 1px solid #c3e6cb;">
        <form action="{{ url('/approve-entity/' . $entity->getType() . '/' . $entity->id) }}" method="POST">
            @csrf
            <button type="submit" class="button" style="background-color: #28a745;">PHÊ DUYỆT NGAY</button>
        </form>
    </div>
@endif
</div>