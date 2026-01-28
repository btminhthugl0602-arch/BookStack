<?php

namespace BookStack\Http\Controllers;

use BookStack\Entities\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
// 👇 Dòng quan trọng mới thêm: Dùng Controller gốc của Laravel
use Illuminate\Routing\Controller as BaseController; 

class AiController extends BaseController
{
    /**
     * Hàm xử lý tóm tắt nội dung bằng AI Gemini 2.5
     */
    public function summarize(Request $request, $pageId)
    {
        // 1. Tìm trang bài viết theo ID
        $page = Page::findOrFail($pageId);

        // 2. Lấy nội dung HTML và lọc bỏ thẻ để lấy chữ thôi
        $content = strip_tags($page->html);
        
        // Cắt bớt nếu dài quá 
        $content = mb_substr($content, 0, 50000); 

        // 3. Chuẩn bị câu lệnh (Prompt)
        $prompt = "Bạn là trợ lý ảo. Hãy tóm tắt văn bản sau thành 3-5 gạch đầu dòng quan trọng nhất:\n\n" . $content;

        // 4. Gọi API Google Gemini
        $apiKey = env('GEMINI_API_KEY');
        
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);

            $result = $response->json();
            $summaryText = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'Không có kết quả trả về từ AI.';
            
            return response()->json([
                'status' => 'success',
                'summary' => $summaryText
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }
}