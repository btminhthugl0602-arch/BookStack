# 🚨 Comprehensive Error Troubleshooting Guide

## Pre-Flight Checks (Before Testing)

### ✅ Step 1: Verify All Files Exist

```bash
# Check controller files
ls -lh app/Http/Controllers/Face*.php

# Check TypeScript files
ls -lh resources/js/face-*.ts

# Check view templates
ls -lh resources/views/auth/login.blade.php
ls -lh resources/views/users/account/profile.blade.php

# Check database
php artisan migrate:status | grep face_token
```

### ✅ Step 2: Verify Configuration

```bash
# Check if Face++ credentials are loaded
php artisan config:show services.facepp

# Check routes
php artisan route:list | grep face
```

### ✅ Step 3: Clear All Caches

```bash
php artisan cache:clear
php artisan config:cache
php artisan route:cache
npm run build:js:dev
```

---

## Common Errors & Solutions

### Error 1: "Button Not Working" (Click Does Nothing)

**Symptoms:**
- Click on "📸 Đăng ký khuôn mặt" button → nothing happens
- No console errors
- No API calls made

**Possible Causes:**

1. **JavaScript Not Loaded**
   ```
   Fix: Check browser console (F12)
   - Should see: "Face register profile JS loaded ✅"
   - If missing: run `npm run build:js:dev`
   ```

2. **DOM Elements Missing**
   ```
   Fix: In browser console, run:
   - document.getElementById('face-register-btn')
   - Should return the button element, not null
   
   If null: Check resources/views/users/account/profile.blade.php
   - Ensure HTML IDs match exactly: face-register-btn, face-register-video, face-register-canvas
   ```

3. **Camera Permission Not Set**
   ```
   Fix: Browser prompt should appear
   - Click "Allow" when browser asks for camera access
   - Check browser camera settings in Settings → Privacy
   ```

---

### Error 2: "Camera Not Opening"

**Symptoms:**
- Button click works
- No camera prompt appears
- Error message: "❌ Bạn chưa cấp quyền camera"

**Solutions:**

1. **Check Browser Permissions**
   ```
   Chrome: Settings → Privacy & Security → Site Settings → Camera
   - Find 127.0.0.1 or localhost
   - Set to "Allow"
   ```

2. **Check System Permissions**
   - macOS: System Preferences → Security & Privacy → Camera
   - Windows: Settings → Privacy & Security → Camera
   - Ensure browser has camera access

3. **Restart Browser**
   ```bash
   # Close all browser windows
   # Reopen and try again
   ```

4. **Check if Camera is in Use**
   ```bash
   # Kill any other camera apps
   lsof | grep camera  # (macOS)
   # Close applications using camera
   ```

---

### Error 3: "No Face Detected" (422 Error)

**Symptoms:**
- Camera opens fine
- Image captured
- Response: `{"error": "No face detected"}`

**Possible Causes:**

1. **Poor Lighting**
   ```
   Fix: Move to well-lit area
   - Natural window light is best
   - Avoid backlighting
   - Face should be clearly visible
   ```

2. **Face Too Small or Too Large**
   ```
   Fix: Position 30-50cm from camera
   - Not too close (>20cm)
   - Not too far (<80cm)
   - Face should fill ~30% of frame
   ```

3. **Face Partially Obscured**
   ```
   Fix: Remove obstructions
   - No glasses (sunglasses especially)
   - No hat/head covering
   - No hand in front of face
   - Clear view of both eyes and nose
   ```

4. **Camera Angle Wrong**
   ```
   Fix: Position camera straight-on
   - Not looking down
   - Not looking up
   - Camera at eye level
   ```

5. **Image Quality Issues**
   ```
   Fix: Check image being sent
   In browser console:
   1. Right-click page → Inspect
   2. Go to Network tab
   3. Click register button
   4. Find POST /user/face/register request
   5. Check Form Data → image file size
   
   Should be: ~50-200KB (JPEG)
   ```

---

### Error 4: "COEXISTENCE_ARGUMENTS" (Step 3 Fails)

**Symptoms:**
```
Server response:
{
  "error": "Unable to register face. Please try uploading a different photo.",
  "request_id": "abc123def456"
}
```

**Root Cause:**
- Orphaned outer_id still exists in Face++ faceset from previous failed attempts
- New fix automatically handles this in Step 2.2

**Solutions:**

1. **Automatic Remediation (Should Trigger)**
   ```
   Controller automatically:
   1. Wait 3 seconds
   2. Retry with unique outer_id: {userId}_{timestamp}
   3. If succeeds: saves unique outer_id to database
   ```

2. **Manual Reset (If Issue Persists)**
   ```bash
   php artisan tinker << 'EOF'
   // Clear user 3's face_token
   $user = \BookStack\Users\Models\User::find(3);
   $user->face_token = null;
   $user->save();
   echo "✅ User 3 reset. Try registering again.\n";
   exit();
   EOF
   ```

3. **Contact Face++ Support**
   ```
   If error persists:
   1. Note the request_id from error response
   2. Contact Face++ support with request_id
   3. Ask them to verify/clean user 3 in faceset
   ```

---

### Error 5: "Low Confidence" (Confidence < 75%)

**Symptoms:**
```
Login attempt fails with:
{
  "error": "Low confidence",
  "confidence": 65
}
```

**Cause:**
- Face quality too poor for reliable recognition
- Too different from registered photo

**Solutions:**

1. **Re-Register with Better Photo**
   ```
   1. Go to profile → click register button again
   2. Ensure same lighting as when you'll login
   3. Face at same angle and distance
   ```

2. **Improve Login Photo**
   ```
   When logging in:
   - Use same lighting as registration
   - Face at same angle
   - Clearer, more direct view to camera
   ```

3. **Adjust Threshold** (if needed)
   ```php
   // In app/Http/Controllers/FaceLoginController.php
   // Change minimum confidence:
   $minConfidence = 75; // ← Adjust this (lower = more lenient)
   ```

---

### Error 6: "500 Server Error" (JSON Parse)

**Symptoms:**
- Browser error: "is not valid JSON"
- Server returning HTML instead of JSON
- Response starts with `<!DOCTYPE html>`

**Solutions:**

1. **Check Controller Syntax**
   ```bash
   php -l app/Http/Controllers/FaceRegisterController.php
   php -l app/Http/Controllers/FaceLoginController.php
   # Should output: "No syntax errors detected"
   ```

2. **Check Laravel Logs**
   ```bash
   tail -f storage/logs/laravel.log
   # Look for:
   - Parse errors
   - "Trying to access property" errors
   - Call to undefined method
   ```

3. **Debug: Print Server Response**
   ```javascript
   // In browser console, add debugging:
   const res = await fetch('/user/face/register', {
       method: 'POST',
       headers: { 'X-CSRF-TOKEN': csrf.content },
       body: formData
   });
   
   console.log('Status:', res.status);
   console.log('Content-Type:', res.headers.get('content-type'));
   
   const text = await res.text();
   console.log('Raw Response:', text);
   ```

4. **Check Route Middleware**
   ```bash
   # Verify route exists and has correct middleware
   php artisan route:list | grep face/register
   # Should show: POST /user/face/register ← auth middleware
   ```

---

### Error 7: "User Not Found" (401 on Login)

**Symptoms:**
```
Login attempt returns:
{
  "error": "User not found",
  "confidence": 85
}
```

**Cause:**
- Face is registered but user ID cannot be extracted from outer_id

**Solutions:**

1. **Check outer_id Format**
   ```bash
   # In logs, look for:
   # Face search result: outer_id = ?
   
   # Should be either:
   # - Simple numeric: "3"
   # - Legacy format: "bs_3_xyz"
   ```

2. **Verify User Exists**
   ```bash
   php artisan tinker << 'EOF'
   // If outer_id was "3"
   $user = \BookStack\Users\Models\User::find(3);
   echo $user->name;  # Should output user name
   exit();
   EOF
   ```

3. **Check User ID Extraction Logic**
   ```php
   // In FaceLoginController.php, add debugging:
   \Log::info('Extracted user_id', ['outer_id' => $outer, 'user_id' => $userId]);
   ```

---

### Error 8: "Face Not Recognized" (401 on Login)

**Symptoms:**
```
Login attempt returns:
{
  "error": "Face not recognized"
}
```

**Cause:**
- Registered face does not match the face in login attempt
- Different person
- Same person but significantly different appearance

**Solutions:**

1. **Register Again with Multiple Angles**
   ```
   Try registering multiple times from different angles:
   - Straight on
   - Slight left turn
   - Slight right turn
   - Confidence in Face++ will increase
   ```

2. **Ensure Same Conditions**
   ```
   When registering and logging in:
   - Same lighting (or similarly lit)
   - Same glasses/accessories (or consistently without)
   - Similar face position
   - Similar distance from camera
   ```

3. **Check Confidence Threshold**
   ```
   If consistently above 70% but below 75%:
   - Lower threshold in FaceLoginController.php
   - $minConfidence = 70; (instead of 75)
   ```

---

### Error 9: "Timeout" (30-second wait)

**Symptoms:**
- Request hangs for 30 seconds
- Then returns 500 error with timeout message

**Cause:**
- Face++ API not responding
- Network connectivity issue

**Solutions:**

1. **Check Internet Connection**
   ```bash
   ping api-us.faceplusplus.com
   # Should show responses
   ```

2. **Check API Status**
   ```
   Visit: https://www.faceplusplus.com
   - Check if service status page shows outages
   ```

3. **Check Firewall**
   ```
   If behind corporate firewall:
   - Allow outbound HTTPS to api-us.faceplusplus.com:443
   - Check proxy settings
   ```

4. **Try Again Later**
   ```
   Temporary network issue - just retry
   ```

---

### Error 10: "Face Token Already Exists" (Unique Constraint)

**Symptoms:**
```
Database error:
"UNIQUE constraint failed: users.face_token"
```

**Cause:**
- Same face_token assigned to multiple users
- Rare but possible with duplicate registrations

**Solutions:**

1. **Identify Duplicates**
   ```bash
   php artisan tinker << 'EOF'
   use Illuminate\Support\Facades\DB;
   
   // Find duplicate face_tokens
   $dupes = DB::table('users')
       ->whereNotNull('face_token')
       ->groupBy('face_token')
       ->havingRaw('COUNT(*) > 1')
       ->get();
   
   if ($dupes->count() > 0) {
       foreach ($dupes as $d) {
           $users = DB::table('users')
               ->where('face_token', $d->face_token)
               ->get();
           echo "Duplicate: " . json_encode($users) . "\n";
       }
   } else {
       echo "No duplicates found\n";
   }
   exit();
   EOF
   ```

2. **Fix Duplicates**
   ```bash
   php artisan tinker << 'EOF'
   // Clear face_token for all users except oldest
   $faceToken = 'some_token_value';
   $users = \BookStack\Users\Models\User::where('face_token', $faceToken)
       ->orderBy('created_at')
       ->get();
   
   foreach ($users->skip(1) as $user) {
       $user->face_token = null;
       $user->save();
       echo "Cleared user {$user->id}\n";
   }
   exit();
   EOF
   ```

---

## Debugging Workflow

### When Something Breaks:

1. **Check Logs First**
   ```bash
   tail -100 storage/logs/laravel.log
   # Look for [Step X] entries or [ERROR]
   ```

2. **Check Browser Console**
   ```
   F12 → Console tab
   - Look for JavaScript errors
   - Check that face*.ts files loaded
   ```

3. **Check Network Tab**
   ```
   F12 → Network tab
   - Find POST /user/face/register
   - Check Response status (200/422/500)
   - Check Response body (JSON or HTML)
   ```

4. **Enable Debug Mode** (Temporary)
   ```bash
   # In .env:
   APP_DEBUG=true
   
   # Now errors show detailed stack trace
   # Then set back to false for production
   APP_DEBUG=false
   ```

5. **Check Database State**
   ```bash
   php artisan tinker << 'EOF'
   $user = \BookStack\Users\Models\User::find(3);
   echo "face_token: " . $user->face_token . "\n";
   exit();
   EOF
   ```

---

## Performance Diagnostics

### If Registration is Slow:

```bash
# Check Face++ API response time
php artisan tinker << 'EOF'
$start = microtime(true);
$res = \Illuminate\Support\Facades\Http::timeout(30)->get('https://api-us.faceplusplus.com/');
$time = (microtime(true) - $start) * 1000;
echo "API Response time: {$time}ms\n";
exit();
EOF
```

### If Photo Upload is Slow:

```
Check:
1. Browser upload speed (Network tab)
2. Image size (should be <500KB)
3. Network bandwidth
```

---

## Final Verification Checklist

After any error, verify:

- [ ] `php artisan config:cache` ✅
- [ ] `php artisan route:cache` ✅
- [ ] `npm run build:js:dev` ✅
- [ ] `php -l app/Http/Controllers/Face*.php` ✅ (no syntax errors)
- [ ] Logs cleared: `rm -f storage/logs/laravel.log` ✅
- [ ] Database state: `User 3 face_token = null` ✅
- [ ] Browser cache cleared: Ctrl+Shift+Delete ✅
- [ ] Browser refreshed: Ctrl+F5 ✅

---

**If all else fails:**
```bash
# Nuclear reset (safe to run):
php artisan config:cache && \
php artisan route:cache && \
npm run build:js:dev && \
php artisan tinker << 'EOF'
\BookStack\Users\Models\User::find(3)->update(['face_token' => null]);
echo "✅ System reset complete\n";
exit();
EOF
```

---

*Last Updated: 2026-02-01*  
*Version: Final*
