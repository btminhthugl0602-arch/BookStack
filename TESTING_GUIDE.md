# 🧪 HƯỚNG DẪN KIỂM TRA HỆ THỐNG FACE++

## ✅ TRẠNG THÁI HỆ THỐNG

```
✅ PHP Controllers: Valid (no syntax errors)
✅ Database Migration: Applied  
✅ Routes Configuration: Configured
✅ Frontend Assets: Compiled
✅ Templates: Updated with face elements
✅ Configuration: Complete
✅ Cache: Refreshed
✅ Ready for Testing: YES
```

---

## 🎯 Test Plan

### PHASE 1: Face Registration Test

**Mục đích**: Xác minh quy trình đăng ký khuôn mặt hoạt động đúng

**Các bước**:

1. **Đăng nhập vào hệ thống**
   ```
   URL: http://127.0.0.1:8000/login
   Email: vu2xx5@gmail.com
   Password: [your password]
   ```

2. **Điều hướng đến trang profile**
   ```
   URL: http://127.0.0.1:8000/my-account/profile
   ```

3. **Nhấp nút "📸 Đăng ký khuôn mặt"**
   - ID nút: `#face-register-btn`
   - Duyệt web sẽ yêu cầu quyền truy cập camera
   - Chấp nhận yêu cầu

4. **Chụp ảnh khuôn mặt**
   - Định vị khuôn mặt vào khung video
   - Đảm bảo:
     - ✅ Ánh sáng tốt (không quá tối, không quá sáng)
     - ✅ Khuôn mặt chiếm ~30-40% khung hình
     - ✅ Không có kính mắt, mũ hoặc vật cản
     - ✅ Mặt thẳng với camera
   - Nhấp để chụp

5. **Kết quả dự kiến**
   ```
   ✅ SUCCESS: "Khuôn mặt đã được đăng ký"
   Trang sẽ hiển thị: "✅ Khuôn mặt đã được đăng ký"
   ```

**Kiểm tra Logs**:
```bash
# Terminal 1: Watch logs in real-time
tail -f storage/logs/laravel.log

# Bạn sẽ thấy:
[Step 1] ✅ Face detected: face_token=...
[Step 2] Searching for existing face...
[Step 2.2] ✅ Old face cleaned
[Step 3] ✅ New face added to faceset
[Step 4] ✅ face_token saved to database: 8c5d3f...::outer::3
```

**Database Verification**:
```bash
cd /Users/tranquangvu/BookStack
php artisan tinker
```
```php
# In tinker:
$user = \BookStack\Users\Models\User::find(3);
echo $user->face_token;  // Should show: 8c5d3f...::outer::3 (not NULL)
exit();
```

---

### PHASE 2: Face Login Test

**Mục đích**: Xác minh đăng nhập bằng khuôn mặt hoạt động

**Điều kiện tiên quyết**: 
- ✅ Phase 1 đã hoàn thành thành công
- ✅ `User 3 face_token` có giá trị trong database

**Các bước**:

1. **Đăng xuất khỏi hệ thống**
   ```
   Click "Logout" hoặc đi đến URL: /logout
   ```

2. **Điều hướng đến trang đăng nhập**
   ```
   URL: http://127.0.0.1:8000/login
   ```

3. **Nhấp nút "🔐 Đăng nhập bằng khuôn mặt"**
   - ID nút: `#face-login-btn`
   - Duyệt web sẽ yêu cầu quyền truy cập camera
   - Chấp nhận yêu cầu

4. **Chụp ảnh khuôn mặt**
   - Cùng điều kiện ánh sáng và góc nhìn như lúc đăng ký
   - Nhấp để chụp

5. **Kết quả dự kiến**
   ```
   ✅ SUCCESS: Auto-login + Redirect to dashboard
   Trang sẽ tự động chuyển hướng đến: /my-account
   Session sẽ xác thực với User 3 (vu2xx5)
   ```

**Kiểm tra Logs**:
```bash
# Watch logs:
tail -f storage/logs/laravel.log

# Bạn sẽ thấy:
[Step 1] ✅ Face detected: face_token=...
[Step 2] Searching faceset...
[Confidence] Score: XX.X (>= 75%) ✅
[Step 3] ✅ User found: User 3 (vu2xx5)
[Step 4] ✅ User logged in successfully
```

---

## 🔴 Xử lý Lỗi

### Error 1: "No image provided" (422)

**Nguyên nhân**: Không gửi ảnh hoặc ảnh không được chọn

**Giải pháp**:
1. Reload trang: Ctrl+R hoặc Cmd+R
2. Kiểm tra DevTools Console (F12): Có lỗi JavaScript không?
3. Cho phép lại quyền camera
4. Thử lại

---

### Error 2: "No face detected" (422)

**Nguyên nhân**: Hệ thống Face++ không thể phát hiện khuôn mặt

**Kiểm tra**:
- [ ] Ánh sáng: Đủ sáng, không quá tối, ánh sáng đều
- [ ] Khuôn mặt: Chiếm ≥20% khung hình
- [ ] Góc nhìn: Mặt thẳng, không quay chếch
- [ ] Vật cản: Không có kính, mũ, hay che khuôn mặt

**Giải pháp**:
```
1. Di chuyển gần camera hơn
2. Điều chỉnh ánh sáng: Đứng gần cửa sổ hoặc bật đèn
3. Loại bỏ kính mắt/mũ
4. Định vị mặt thẳng với camera
5. Thử lại
```

---

### Error 3: "User not found" (404)

**Nguyên nhân**: outer_id được trích xuất không khớp với user nào

**Kiểm tra**: Check logs để xem outer_id được trích xuất là gì
```bash
grep "User lookup" storage/logs/laravel.log | tail -1
```

**Giải pháp**:
```php
# Tinker debug
$users = \BookStack\Users\Models\User::whereNotNull('face_token')->get();
foreach ($users as $u) {
    echo "User {$u->id}: {$u->name} - Token: {$u->face_token}\n";
}
exit();
```

---

### Error 4: "Low confidence" (401 / 403)

**Nguyên nhân**: Khuôn mặt không khớp đủ tốt (điểm confidence < 75%)

**Kiểm tra logs**:
```bash
grep "Confidence Score" storage/logs/laravel.log | tail -1
```

**Giải pháp**:
1. Đảm bảo ánh sáng giống lúc đăng ký
2. Góc nhìn giống như lúc đăng ký
3. Hoàn cảnh tương tự
4. Nếu vẫn thất bại: **Đăng ký lại khuôn mặt** (xóa face_token cũ)
   ```php
   # Terminal:
   php artisan tinker
   $user = \BookStack\Users\Models\User::find(3);
   $user->face_token = null;
   $user->save();
   exit();
   
   # Sau đó: Làm lại PHASE 1
   ```

---

### Error 5: "COEXISTENCE_ARGUMENTS" (500)

**Nguyên nhân**: Face++ phát hiện outer_id bị trùng hoặc bị lỗi trong faceset

**Kiểm tra logs**:
```bash
grep "COEXISTENCE_ARGUMENTS\|Remediation" storage/logs/laravel.log | tail -5
```

**Giải pháp** (hệ thống tự động):
- Hệ thống sẽ tự động:
  1. Xoá outer_id cũ
  2. Kiểm tra faceset
  3. Thêm lại với outer_id mới có timestamp
  
- Nếu vẫn lỗi: **Reset database**
  ```php
  php artisan tinker
  # Xóa toàn bộ face_token
  \BookStack\Users\Models\User::query()->update(['face_token' => null]);
  
  # Xác nhận
  echo \BookStack\Users\Models\User::whereNotNull('face_token')->count(); // Should be 0
  exit();
  ```

---

### Error 6: "Camera won't open" (Permission Denied)

**Nguyên nhân**: Browser/System không có quyền truy cập camera

**Kiểm tra**:
- [ ] Browser permission: Kiểm tra icon tại địa chỉ bar
- [ ] System permission: Settings → Security & Privacy
- [ ] Camera bị dùng bởi app khác

**Giải pháp**:
```bash
# macOS:
1. System Preferences → Security & Privacy → Camera
2. Kiểm tra Chrome/Firefox có quyền không
3. Nếu không: Click "+" và thêm browser
4. Restart browser
5. Thử lại
```

---

### Error 7: "500 Server Error"

**Nguyên nhân**: Lỗi PHP hoặc configuration

**Kiểm tra**:
```bash
# 1. Check PHP syntax
php -l app/Http/Controllers/FaceRegisterController.php
php -l app/Http/Controllers/FaceLoginController.php

# 2. Check logs
tail -50 storage/logs/laravel.log

# 3. Check config cache
php artisan config:cache
php artisan route:cache

# 4. Clear compiled
php artisan view:clear
php artisan cache:clear
```

**Giải pháp**:
```bash
# Full reset
php artisan config:cache
php artisan route:cache
php artisan view:clear
npm run build:js:dev

# Thử lại
```

---

## 🔧 Debugging Workflow

**Khi gặp lỗi, thực hiện theo thứ tự này**:

### Step 1: Check Browser Console
```bash
F12 → Console tab
- Có error message đỏ không?
- JavaScript có được load không?
- Network requests có HTTP errors không?
```

### Step 2: Check Laravel Logs
```bash
tail -100 storage/logs/laravel.log

# Tìm:
- [Step 1], [Step 2], [Step 3], [Step 4]
- error_message từ Face++
- Exception traces
```

### Step 3: Check Configuration
```bash
# Kiểm tra credentials được load
grep "FACEPP" .env

# Check cached config
php artisan config:cache
php artisan route:cache

# Verify import
grep -r "FACEPP_API_KEY" config/
```

### Step 4: Check Database
```php
php artisan tinker

# Check migration
\BookStack\Users\Models\User::find(3)->face_token

# Check if column exists
\Illuminate\Support\Facades\DB::select("
  SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_NAME='users' AND COLUMN_NAME='face_token'
")

exit()
```

### Step 5: Manual Face++ API Test
```php
# Test API directly
php artisan tinker

$response = Http::post('https://api-us.faceplusplus.com/facepp/v3/detect', [
    'api_key' => config('services.facepp.key'),
    'api_secret' => config('services.facepp.secret'),
    'image_url' => 'https://example.com/face.jpg'
]);

dd($response->json());
exit()
```

---

## 📊 Expected Performance

| Operation | Time | Status |
|-----------|------|--------|
| Face Detection | ~500ms | ✅ OK |
| Faceset Search | ~300ms | ✅ OK |
| Face Add | ~500ms | ✅ OK |
| Full Registration | 1-2s | ✅ OK |
| Full Login | 1-2s | ✅ OK |

---

## 📋 Pre-Test Checklist

Trước khi bắt đầu test, kiểm tra:

- [ ] Laravel server đang chạy: `php artisan serve`
- [ ] Database kết nối: Check `php artisan tinker`
- [ ] User 3 tồn tại: `User::find(3)`
- [ ] Assets đã build: `npm run build:js:dev`
- [ ] Cache refreshed: `php artisan config:cache`
- [ ] Logs writable: `ls -la storage/logs/`
- [ ] Browser DevTools ready: F12
- [ ] Terminal tail ready: `tail -f storage/logs/laravel.log`

---

## 🚀 Quick Start Commands

```bash
# Terminal 1: Watch logs
cd /Users/tranquangvu/BookStack
tail -f storage/logs/laravel.log

# Terminal 2: Start Laravel server
cd /Users/tranquangvu/BookStack
php artisan serve

# Terminal 3: Debug with tinker
cd /Users/tranquangvu/BookStack
php artisan tinker
$user = \BookStack\Users\Models\User::find(3);
echo $user->face_token;
exit()
```

---

## 🎯 Success Criteria

### Registration Success ✅
```
Browser: Shows "✅ Khuôn mặt đã được đăng ký"
Database: User 3 has face_token value
Logs: All 4 steps show SUCCESS
```

### Login Success ✅
```
Browser: Auto-login + Redirect to dashboard
Session: User 3 authenticated
Logs: Confidence >= 75%, User found, Logged in
```

---

## 📞 Support

Nếu vẫn gặp vấn đề:

1. **Check ERROR_TROUBLESHOOTING.md** để xem chi tiết từng lỗi
2. **Check SYSTEM_CHECK.md** cho danh sách kiểm tra toàn hệ thống
3. **Face++ Support**: https://console.faceplusplus.com/

---

*Last Updated: System Ready for Testing*
*Status: All checks passed - Ready to begin PHASE 1*
