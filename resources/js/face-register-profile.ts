console.log('Face register profile JS loaded');

document.addEventListener('DOMContentLoaded', () => {
    const updateBtnEl = document.getElementById('face-update-btn') as HTMLButtonElement | null;
    const deleteBtnEl = document.getElementById('face-delete-btn') as HTMLButtonElement | null;
    const videoEl = document.getElementById('face-register-video') as HTMLVideoElement | null;
    const canvasEl = document.getElementById('face-register-canvas') as HTMLCanvasElement | null;
    const statusText = document.getElementById('face-status-text') as HTMLParagraphElement | null;

    if (!updateBtnEl || !deleteBtnEl || !videoEl || !canvasEl) {
        console.log('Face register profile page not detected, skipping initialization');
        return;
    }

    const updateBtn = updateBtnEl;
    const deleteBtn = deleteBtnEl;
    const video = videoEl;
    const canvas = canvasEl;

    let stream: MediaStream | null = null;
    let isProcessing = false;
    let currentAction: 'update' | 'delete' | null = null;
    let hasExistingFace = updateBtn.dataset.hasFace === 'true';

    updateBtn.addEventListener('click', () => startAction('update'));
    deleteBtn.addEventListener('click', () => startAction('delete'));

    async function startAction(action: 'update' | 'delete') {
        if (isProcessing) return;
        if (action === 'delete' && !hasExistingFace) {
            setStatus('Bạn chưa đăng ký khuôn mặt để xóa.');
            resetButtons();
            return;
        }
        isProcessing = true;
        currentAction = action;
        setButtonsState(true, action === 'update' ? 'Đang mở camera...' : 'Đang mở camera...');
        setStatus('Đang mở camera...');

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                    facingMode: 'user'
                },
                audio: false
            });

            console.log('Camera opened for face action');
            video.srcObject = stream;
            video.style.display = 'block';

            video.onloadedmetadata = () => {
                console.log(`Camera resolution: ${video.videoWidth}x${video.videoHeight}`);
                setButtonsState(true, 'Chụp ảnh...');
                setStatus('Đang chụp ảnh...');
                setTimeout(() => {
                    captureAndSend();
                }, 800);
            };

        } catch (err: any) {
            isProcessing = false;
            resetButtons();
            setStatus('Không thể mở camera');

            console.error('Camera error:', err);

            if (err.name === 'NotAllowedError') {
                alert('Bạn chưa cấp quyền camera. Vui lòng kiểm tra cài đặt quyền của trình duyệt');
            } else if (err.name === 'NotFoundError') {
                alert('Không tìm thấy camera. Vui lòng kiểm tra kết nối');
            } else if (err.name === 'NotReadableError') {
                alert('Camera đang bị sử dụng bởi ứng dụng khác');
            } else {
                alert(`Lỗi camera: ${err.message}`);
            }
        }
    }

    async function captureAndSend() {
        if (!stream || !video || !canvas || !currentAction) {
            resetButtons();
            return;
        }

        const ctx = canvas.getContext('2d');
        if (!ctx) {
            resetButtons();
            return;
        }

        try {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            ctx.scale(-1, 1);
            ctx.drawImage(video, -video.videoWidth, 0);
            ctx.scale(-1, 1);

            stream.getTracks().forEach(track => track.stop());
            stream = null;
            video.style.display = 'none';

            console.log('Captured image, sending to server...');
            setStatus(currentAction === 'update' && hasExistingFace ? 'Đang xác thực...' : 'Đang xử lý...');

            canvas.toBlob(
                async (blob) => {
                    if (!blob) {
                        resetButtons();
                        alert('Lỗi xử lý ảnh');
                        return;
                    }

                    const csrf = document.querySelector(
                        'meta[name="csrf-token"]'
                    ) as HTMLMetaElement | null;

                    const headers: Record<string, string> = csrf
                        ? { 'X-CSRF-TOKEN': csrf.content }
                        : {};

                    if (currentAction === 'update') {
                        let verifyResult: 'ok' | 'no-face' | 'fail' = 'ok';
                        if (hasExistingFace) {
                            verifyResult = await verifyFace(blob, headers, 'update');
                            if (verifyResult === 'fail') {
                                resetButtons();
                                return;
                            }
                        }

                        if (verifyResult === 'no-face') {
                            hasExistingFace = false;
                        }

                        const registered = await registerFace(blob, headers);
                        if (registered) {
                            hasExistingFace = true;
                        }
                    } else {
                        const verifyResult = await verifyFace(blob, headers, 'delete');
                        if (verifyResult !== 'ok') {
                            resetButtons();
                            return;
                        }

                        const deleted = await deleteFace(headers);
                        if (deleted) {
                            hasExistingFace = false;
                        }
                    }
                },
                'image/jpeg',
                0.95
            );

        } catch (err) {
            console.error('Capture error:', err);
            resetButtons();
            alert('Lỗi chụp ảnh');
        }
    }

    async function verifyFace(
        blob: Blob,
        headers: Record<string, string>,
        purpose: 'update' | 'delete'
    ) {
        try {
            const formData = new FormData();
            formData.append('image', blob, 'face.jpg');
            formData.append('purpose', purpose);
            setButtonsState(true, 'Đang xác thực...');
            const res = await fetch('/user/face/verify', {
                method: 'POST',
                headers,
                credentials: 'same-origin',
                body: formData
            });

            const data = await parseJsonSafe(res);
            if (res.ok && data.success) {
                setStatus('Xác thực thành công');
                return 'ok' as const;
            }

            if (res.status === 400 && data.error === 'No registered face') {
                setStatus('Chưa có khuôn mặt, sẽ đăng ký mới.');
                return 'no-face' as const;
            }

            handleError(data, res.status);
            return 'fail' as const;
        } catch (err) {
            console.error('Verify error:', err);
            resetButtons();
            alert('Lỗi xác thực');
            return 'fail' as const;
        }
    }

    async function registerFace(blob: Blob, headers: Record<string, string>) {
        try {
            const formData = new FormData();
            formData.append('image', blob, 'face.jpg');
            setButtonsState(true, 'Đang cập nhật...');

            const res = await fetch('/user/face/register', {
                method: 'POST',
                headers,
                credentials: 'same-origin',
                body: formData
            });

            const data = await parseJsonSafe(res);
            console.log('Face registration response:', data);

            if (res.ok && data.success) {
                setButtonsState(true, 'Cập nhật thành công');
                setStatus('Khuôn mặt đã được cập nhật thành công.');
                setTimeout(() => {
                    location.reload();
                }, 1200);
                return true;
            } else {
                handleError(data, res.status);
            }

        } catch (err) {
            console.error('Register error:', err);
            resetButtons();
            alert('Lỗi gửi ảnh tới server');
        }

        return false;
    }

    async function deleteFace(headers: Record<string, string>) {
        try {
            setButtonsState(true, 'Đang xóa...');

            const res = await fetch('/user/face/delete', {
                method: 'POST',
                headers,
                credentials: 'same-origin'
            });

            const data = await parseJsonSafe(res);
            console.log('Face delete response:', data);

            if (res.ok && data.success) {
                setButtonsState(true, 'Đã xóa khuôn mặt');
                setStatus('Khuôn mặt đã được xóa.');
                setTimeout(() => {
                    location.reload();
                }, 1200);
                return true;
            } else {
                handleError(data, res.status);
            }

        } catch (err) {
            console.error('Delete error:', err);
            resetButtons();
            alert('Lỗi gửi yêu cầu xóa');
        }

        return false;
    }

    async function parseJsonSafe(res: Response) {
        const contentType = res.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            return {} as any;
        }

        try {
            return await res.json();
        } catch {
            return {} as any;
        }
    }

    function handleError(data: any, status: number) {
        resetButtons();
        let message = 'Lỗi không xác định';

        if (status === 422 && data.error === 'No face detected') {
            message = 'Không phát hiện khuôn mặt. Vui lòng:\n- Đảm bảo ánh sáng tốt\n- Mặt rõ ràng, không bị che khuất\n- Thử lại';
        } else if (status === 401) {
            message = data.error || 'Xác thực khuôn mặt không thành công';
        } else if (status === 503 || status === 504) {
            message = data.error || 'Dịch vụ xác thực khuôn mặt đang tạm thời quá tải, vui lòng thử lại sau ít phút.';
        } else if (status === 500) {
            message = `Lỗi server: ${data.error || 'Unknown error'}`;
        } else if (data.error) {
            message = `${data.error}`;
        }

        alert(message);
        setStatus(message);
    }

    function setButtonsState(disabled: boolean, text: string) {
        updateBtn.disabled = disabled;
        deleteBtn.disabled = disabled;
        if (currentAction === 'update') {
            updateBtn.textContent = text;
        } else if (currentAction === 'delete') {
            deleteBtn.textContent = text;
        }
    }

    function resetButtons() {
        isProcessing = false;
        currentAction = null;
        updateBtn.disabled = false;
        deleteBtn.disabled = false;
        updateBtn.textContent = 'Cập nhật khuôn mặt';
        deleteBtn.textContent = 'Xóa khuôn mặt';
    }

    function setStatus(text: string) {
        if (statusText) {
            statusText.textContent = text;
        }
    }
});
