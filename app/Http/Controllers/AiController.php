<?php

namespace BookStack\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use BookStack\Entities\Queries\PageQueries;
use BookStack\Http\Controller;

class AiController extends Controller
{
    protected PageQueries $pageQueries;

    public function __construct(PageQueries $pageQueries)
    {
        $this->pageQueries = $pageQueries;
    }

    public function summarize(Request $request)
    {
        try {
            // --- BƯỚC 1: LẤY NỘI DUNG TRANG (Phần bạn bị thiếu) ---
            $pageId = $request->input('page_id');
            if (!$pageId) {
                return response()->json(['success' => false, 'message' => 'Không tìm thấy ID trang.']);
            }

            // Tìm trang trong CSDL
            $page = $this->pageQueries->findVisibleByIdOrFail($pageId);
            
            // Lấy text thuần, cắt bớt để tránh quá tải token (giới hạn 8000 ký tự)
            $content = substr(strip_tags($page->html), 0, 8000); 

            // --- BƯỚC 2: CHUẨN BỊ GỌI AI ---
            // Dọn dẹp API Key
            $apiKey = trim(env('GEMINI_API_KEY')); 
            if (!$apiKey) {
                 return response()->json(['success' => false, 'message' => 'Chưa cấu hình API Key trong .env']);
            }

            $apiKey = trim(env('GEMINI_API_KEY')); 
            
            // SỬA DÒNG NÀY: Chuyển sang v1beta và gemini-2.5-flash (Chuẩn mới nhất)
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

            $prompt = "Bạn là trợ lý ảo. Hãy tóm tắt nội dung sau thành 3 gạch đầu dòng ngắn gọn bằng tiếng Việt:\n\n" . $content;
            // --- BƯỚC 3: GỌI API ---
            $response = Http::withoutVerifying()->withHeaders([
                'Content-Type' => 'application/json',
            ])->post($url, [
                'contents' => [
                    ['parts' => [['text' => $prompt]]]
                ]
            ]);

            // --- BƯỚC 4: XỬ LÝ KẾT QUẢ ---
            if ($response->successful()) {
                $data = $response->json();
                $summary = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'AI không trả lời được.';
                
                return response()->json([
                    'success' => true,
                    'summary' => $summary
                ]);
            } else {
                Log::error('Gemini API Error: ' . $response->body());
                return response()->json([
                    'success' => false, 
                    'message' => 'Lỗi Google: ' . $response->status()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('AiController Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Lỗi Server: ' . $e->getMessage()
            ]);
        }
    }
}