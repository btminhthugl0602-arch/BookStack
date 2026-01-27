<?php

namespace BookStack\Entities\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use BookStack\Entities\Models\Book; // Nhớ import Model Book

trait HasApprovalWorkflow
{
    public function syncApprovalStatus()
    {
        $user = Auth::user();
        if (!$user) return;

        $status = 'cho_duyet'; // Mặc định là ẩn (Private)

        // ❌ XÓA HOẶC COMMENT DÒNG CŨ NÀY:
        // if ($this->isA('book')) {
        //     $status = 'da_duyet'; 
        // } 

        // ✅ THAY BẰNG LOGIC NÀY:
        // Nếu là Book: Vẫn để là 'cho_duyet' (Để chỉ Owner mới thấy)
        // Trừ khi bạn muốn sách auto public cho cả công ty thì mới set 'da_duyet'.
        
        // Logic cho Page/Chapter (Giữ nguyên)
        if (!$this->isA('book')) {
            $bookId = $this->book_id;
            if ($bookId) {
                $bookOwnerId = DB::table('entities')
                                ->where('id', $bookId)
                                ->where('type', 'book')
                                ->value('owned_by');

                if ($bookOwnerId && $user->id == $bookOwnerId) {
                    $status = 'da_duyet';
                }
            }
        } else {
            // Nếu là Book và người tạo chính là người đang login -> Vẫn set cho_duyet 
            // để bảo mật (chỉ owner thấy).
            // Nếu muốn Owner tự duyệt chính mình:
             $status = 'da_duyet'; // ⚠️ CHÚ Ý: Đổi thành 'cho_duyet' nếu muốn Private tuyệt đối
             // NHƯNG KHOAN!
             // Nếu set 'cho_duyet', thì Scope "project_members" sẽ hoạt động đúng.
             // Nếu set 'da_duyet', Scope sẽ cho tất cả Member nhìn thấy.
             
             // 👉 QUYẾT ĐỊNH: Sửa dòng này thành 'cho_duyet'
             $status = 'cho_duyet';
        }

        // Lưu vào DB
        DB::table('duyet_bai')->updateOrInsert(
            ['entity_id' => $this->id, 'entity_type' => $this->getType()],
            [
                'trang_thai' => $status,
                'user_id'    => $user->id,
                'updated_at' => now()
            ]
        );
    }
}