<?php

namespace BookStack\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ApprovalScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $user = Auth::user();
        $userId = $user ? $user->id : 0;

        // 1. ADMIN & SẾP: Xem hết mọi thứ
        if ($user && ($user->hasSystemRole('admin') || $user->roles()->whereIn('id', [7, 8])->exists())) {
             return; 
        }

        // 2. LOGIC LỌC DỮ LIỆU
        $builder->where(function ($query) use ($userId) {
            
            // A. Bài/Sách đã được duyệt (Công khai nội bộ)
            $query->whereExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                         ->from('duyet_bai')
                         ->whereColumn('duyet_bai.entity_id', 'entities.id')
                         ->where('duyet_bai.trang_thai', 'da_duyet');
            })
            
            // B. Tác giả bài viết (Luôn xem được cái mình tạo)
            ->orWhere('entities.created_by', '=', $userId)
            
            // C. LEADER DỰ ÁN (Chủ Sách)
            ->orWhereIn('entities.book_id', function($subQuery) use ($userId) {
                $subQuery->select('id')
                         ->from('entities')
                         ->where('type', 'book')
                         ->where('owned_by', $userId);
            })
            // (Fix cho trường hợp entity chính là cuốn sách)
            ->orWhere(function($q) use ($userId) {
                $q->where('entities.type', 'book')
                  ->where('entities.owned_by', $userId);
            })

            // 👉👉👉 D. THÀNH VIÊN DỰ ÁN (MỚI THÊM) 👈👈👈
            // Logic: Nếu tôi có tên trong bảng project_members của cuốn sách này -> Cho tôi xem
            ->orWhereExists(function ($subQuery) use ($userId) {
                $subQuery->select(DB::raw(1))
                         ->from('project_members')
                         ->where('project_members.user_id', $userId)
                         ->where(function($joinQ) {
                             // Nếu entity là Sách -> check book_id = id
                             $joinQ->whereColumn('project_members.book_id', 'entities.id')
                                   ->where('entities.type', 'book')
                             // Nếu entity là Trang/Chương -> check book_id = entity.book_id
                             ->orWhere(function($deepQ) {
                                 $deepQ->whereColumn('project_members.book_id', 'entities.book_id')
                                       ->where('entities.type', '!=', 'book');
                             });
                         });
            });
        });
    }
}