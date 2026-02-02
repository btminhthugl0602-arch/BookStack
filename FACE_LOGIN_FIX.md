# 🔐 Face Detection Fix Guide

## ✅ Các lỗi đã sửa

### 1. **Lỗi Config Environment Variables** ❌→✅
**Vấn đề:** Tên biến trong `.env` không khớp với `services.php`
```php
// ❌ Cũ (services.php)
'key' => env('FACEPP_KEY'),
'secret' => env('FACEPP_SECRET'),
'faceset' => env('FACEPP_FACESET'),

// ✅ Mới (services.php)
'key' => env('FACEPP_API_KEY'),      // ← Khớp với .env
'secret' => env('FACEPP_API_SECRET'),
'faceset' => env('FACEPP_FACESET_TOKEN'),
```

### 2. **Lỗi Camera Không Bật** ❌→✅
**Vấn đề:** 
- Không có error handling cho camera permissions
- Constraints camera không tối ưu
- Timeout chương trình

**Fix:**
```typescript
// ✅ Camera constraints tối ưu
stream = await navigator.mediaDevices.getUserMedia({
    video: {
        width: { ideal: 1280 },
        height: { ideal: 720 },
        facingMode: 'user'
    },
    audio: false
});

// ✅ Chi tiết error messages
if (err.name === 'NotAllowedError') {
    alert('❌ Bạn chưa cấp quyền camera');
} else if (err.name === 'NotFoundError') {
    alert('❌ Không tìm thấy camera');
}
// ... etc
```

### 3. **Lỗi Face Detection** ❌→✅
**Vấn đề:**
- Không check Face++ API errors
- Logging không rõ ràng
- Confidence threshold cứng

**Fix:**
```php
// ✅ Check Face++ errors
if (isset($detect['error_message'])) {
    return response()->json([
        'error' => 'Face++ service error: ' . $detect['error_message']
    ], 500);
}

// ✅ Logging chi tiết
\Log::info('Face detected', ['faces_count' => count($detect['faces'])]);

// ✅ Configurable confidence threshold
$minConfidence = 75;
if ($confidence < $minConfidence) {
    return response()->json(['error' => 'Low confidence'], 401);
}
```

### 4. **Timeout & Connection Issues** ❌→✅
```php
// ✅ Thêm timeout cho HTTP requests
Http::timeout(10)->asMultipart()->post(...)
```

---

## 🚀 Cách sử dụng

### **Step 1: Rebuild Assets (Important!)**
```bash
cd /Users/tranquangvu/BookStack
npm run build    # Hoặc yarn build / pnpm build
```

### **Step 2: Test Face Registration**
1. Đăng nhập vào tài khoản của bạn
2. Vào profile/settings
3. Click "📸 Đăng ký khuôn mặt"
4. Cho phép camera
5. Khuôn mặt sẽ được lưu vào Face++ Faceset

### **Step 3: Test Face Login**
1. Logout
2. Vào trang login
3. Click "🔐 Đăng nhập bằng khuôn mặt"
4. Cho phép camera
5. Khuôn mặt sẽ được nhận diện và tự động login

---

## 🐛 Debugging Tips

### **Kiểm tra Console Browser (F12)**
```javascript
// Xem logs
console.log('✅ Camera opened successfully');
console.log('Face detected', ['faces_count' => 1]);
console.log('Server response:', data);
```

### **Kiểm tra Network Tab (F12 → Network)**
- POST `/login/face` - check response 200 hay 401/422
- POST `/user/face/register` - check response 200 hay 422
- Request payload - check `image` được gửi

### **Kiểm tra Server Logs**
```bash
tail -f /Users/tranquangvu/BookStack/storage/logs/laravel.log
```

Tìm lines:
- `Face login attempt`
- `Face detected`
- `Face not recognized`
- `Face++ service error`

---

## ⚠️ Common Issues & Solutions

### **Issue 1: "No face detected" lúc nào cũng báo**

**Nguyên nhân:**
- Ánh sáng yếu
- Mặt bị che khuất (kính mắt, khẩu trang)
- Ảnh chụp mờ
- Face++ API bị lỗi

**Giải pháp:**
- Kiểm tra ánh sáng (tối/sáng chính xác)
- Mặt nên chiếm 50-70% frame
- Thử zoom vào mặt
- Check logs server: `No face detected`

### **Issue 2: "Face not recognized" sau khi đã đăng ký**

**Nguyên nhân:**
- Faceset_token sai
- outer_id không khớp
- Ảnh đăng ký vs ảnh đăng nhập quá khác

**Giải pháp:**
- Verify .env variables:
  ```bash
  FACEPP_API_KEY=...
  FACEPP_API_SECRET=...
  FACEPP_FACESET_TOKEN=7ce8f1b8dd7f42f2986f7202d2a071d2  # Check cái này
  ```
- Check database:
  ```sql
  SELECT id, face_token FROM users WHERE id = YOUR_USER_ID;
  ```

### **Issue 3: "Low confidence" error**

**Nguyên nhân:** 
- Confidence < 75% (default)
- Ảnh đăng ký khác với ảnh đang nhập

**Giải pháp:**
- Đăng ký lại khuôn mặt với ánh sáng tốt
- Có thể hạ threshold trong controller:
  ```php
  $minConfidence = 70;  // Từ 75 xuống 70
  ```

### **Issue 4: Camera permission denied**

**Nguyên nhân:**
- Browser chưa cấp quyền
- HTTPS required (nếu production)

**Giải pháp:**
- Chrome: Settings → Privacy → Camera → Allow localhost:8000
- Hoặc tạo HTTPS trong local dev

---

## 📋 Test Checklist

- [ ] Config environment variables match (.env vs services.php)
- [ ] Run `npm run build` after code changes
- [ ] Camera opens and shows video
- [ ] Face registration works (face_token saved to DB)
- [ ] Face login detects face and redirects to /
- [ ] Confidence score > 75%
- [ ] Error messages clear and helpful
- [ ] No console errors (F12)
- [ ] Server logs show success messages

---

## 💡 Pro Tips

1. **Testing khác devices:** 
   - Đăng ký trên device A
   - Thử login từ device B (cùng wifi)
   - App sẽ thiết lập quyền camera cho mỗi device

2. **Multiple faces:**
   - Một user có thể đăng ký nhiều khuôn mặt
   - Mỗi lần đăng ký sẽ replace face_token cũ
   - Để lưu nhiều faces, cần thay đổi database schema

3. **Performance:**
   - Video resolution 1280x720 tối ưu (không quá nặng)
   - Confidence threshold 75% cân bằng giữa security & UX
   - Face++ API response thường < 500ms

---

## 📞 Quick Support

Nếu còn lỗi sau khi fix:
1. Check server logs: `tail -f storage/logs/laravel.log`
2. Check browser console: F12 → Console
3. Verify .env variables match services.php
4. Run `npm run build` if changed JS
5. Clear cache: `php artisan cache:clear`
