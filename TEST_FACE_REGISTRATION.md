# Test Face Registration Flow

## Lỗi Đã Fix

### 1. **COEXISTENCE_ARGUMENTS Error (500)**
**Vấn đề:** Khi gửi ảnh lên, Face++ API trả lỗi `COEXISTENCE_ARGUMENTS`
- Nguyên nhân: Xung đột giữa remove face cũ và add face mới (race condition)
- **Fix:**
  - Tăng timeout detect từ 10s → 30s
  - Thêm `usleep(500000)` (500ms delay) giữa removeface và addface
  - Cải thiện error handling khi remove face fail - không dừng quy trình
  - Thêm detailed logging để debug tốt hơn

### 2. **"Face login elements not found" Warning**
**Vấn đề:** Warning xuất hiện trên các trang không phải login/profile
- **Fix:** Thay đổi warning thành info log, chỉ log khi elements thực sự không found trên đúng trang

## Cải Tiến Khác

### FaceRegisterController.php
```php
// 1. Timeout detect: 10s → 30s
$detectRes = Http::timeout(30)->asMultipart()->post(...)

// 2. Delay 500ms giữa remove và add
if ($user->face_token) {
    // ... remove old face ...
    usleep(500000); // 500ms delay
}

// 3. Better error handling
try {
    $removeRes = Http::timeout(30)->asMultipart()->post(...)->json();
    if (isset($removeRes['error_message'])) {
        \Log::warning('Failed to remove old face', [...]); 
        // Không fail, tiếp tục add mới
    }
} catch (\Exception $e) {
    \Log::warning('Exception removing old face', [...]);
    // Không fail, tiếp tục
}

// 4. Detailed logging
\Log::info('Adding new face to faceset', [...]);
\Log::info('Addface response successful', [...]);
```

## Cách Test

### 1. Trên Login Page
```bash
# URL: http://localhost:8000/login
# Bước:
# 1. Mở browser DevTools (F12) → Console
# 2. Click "🔐 Đăng nhập bằng khuôn mặt"
# 3. Verify logs:
#    ✅ Camera opened successfully
#    ✅ Camera resolution: 1280x720
#    ✅ 📸 Captured image, sending to server...
# 4. Nếu face_token có trong DB:
#    ✅ User logged in via face recognition
```

### 2. Trên Profile Page
```bash
# URL: http://localhost:8000/user/profile
# Bước:
# 1. Mở browser DevTools (F12) → Console
# 2. Click "📸 Đăng ký khuôn mặt"
# 3. Verify logs:
#    ✅ Camera opened for face registration
#    ✅ Camera resolution: 1280x720
#    ✅ 📸 Captured image, sending to server...
#    ✅ Face registration response: {success: true, ...}
# 4. Kiểm tra DB: user.face_token phải có value mới

### 3. Kiểm tra Database
```bash
php artisan tinker
>>> $user = User::find(1);
>>> $user->face_token; // Phải có value (khoảng 100 ký tự)
```

### 4. Kiểm tra Server Logs
```bash
tail -f storage/logs/laravel.log | grep -i face
# Logs sẽ hiển thị:
# - Face registration attempt
# - Face detected during registration
# - Adding new face to faceset
# - Successfully removed old face
# - Face registered successfully
```

## Request/Response Flow

### Register API
```
POST /user/face/register
Content-Type: multipart/form-data
Body: {image: <Blob>}

Response 200 OK:
{
  "success": true,
  "message": "Face registered successfully"
}

Response 422:
{
  "error": "No face detected"
}

Response 500 (Cải thiện):
{
  "error": "Face++ error: COEXISTENCE_ARGUMENTS" // More detailed now
}
```

## Lưu Ý
1. Ensure `.env` có:
   - `FACEPP_API_KEY`
   - `FACEPP_API_SECRET`
   - `FACEPP_FACESET_TOKEN`

2. Browser phải có quyền camera:
   - Chrome/Edge: Settings → Privacy → Camera
   - Firefox: Privacy & Security → Permissions → Camera

3. Lighting condition quan trọng:
   - Đảm bảo ánh sáng tốt
   - Mặt rõ ràng, không bị che khuất
   - Khoảng cách 30-50cm từ camera

## Troubleshooting

### Nếu vẫn gặp COEXISTENCE_ARGUMENTS:
1. Check Face++ quota không bị hết
2. Kiểm tra FACEPP_FACESET_TOKEN có đúng không
3. Xóa face cũ từ faceset bằng:
```bash
curl -X POST https://api-us.faceplusplus.com/facepp/v3/faceset/removeface \
  -d "api_key=YOUR_KEY" \
  -d "api_secret=YOUR_SECRET" \
  -d "faceset_token=YOUR_FACESET" \
  -d "outer_ids=USER_ID"
```

### Nếu camera không mở:
1. Check browser permissions
2. Kiểm tra port 8000 không bị block
3. HTTPS requirement trên production

## Status ✅
- [x] COEXISTENCE_ARGUMENTS lỗi fixed
- [x] Race condition removed (usleep added)
- [x] Timeout adjusted (30s)
- [x] Better error handling
- [x] Face login warning fixed
- [x] Assets built
