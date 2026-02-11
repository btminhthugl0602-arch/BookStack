console.log('Face register profile JS loaded');

document.addEventListener('DOMContentLoaded', () => {
    const updateBtn = document.getElementById('face-update-btn') as HTMLButtonElement | null;
    const deleteBtn = document.getElementById('face-delete-btn') as HTMLButtonElement | null;
    const video = document.getElementById('face-register-video') as HTMLVideoElement | null;
    const canvas = document.getElementById('face-register-canvas') as HTMLCanvasElement | null;
    const statusText = document.getElementById('face-status-text') as HTMLParagraphElement | null;

    if (!updateBtn || !deleteBtn || !video || !canvas) {
        console.log('Face register profile page not detected, skipping initialization');
        return;
    }

    let stream: MediaStream | null = null;
    let isProcessing = false;
    let currentAction: 'update' | 'delete' | null = null;

    updateBtn.addEventListener('click', () => startAction('update'));
    deleteBtn.addEventListener('click', () => startAction('delete'));

    async function startAction(action: 'update' | 'delete') {
        if (isProcessing) return;
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
            setStatus('Đang xác thực...');

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

                    const headers = csrf ? { 'X-CSRF-TOKEN': csrf.content } : {};

                    if (currentAction === 'update') {
                        const verified = await verifyFace(blob, headers);
                        if (!verified) {
                            resetButtons();
                            return;
                        }
                        await registerFace(blob, headers);
                    } else {
                        await deleteFace(blob, headers);
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

    async function verifyFace(blob: Blob, headers: Record<string, string>) {
        try {
            const formData = new FormData();
            formData.append('image', blob, 'face.jpg');
            setButtonsState(true, 'Đang xác thực...');
            const res = await fetch('/user/face/verify', {
                method: 'POST',
                headers,
                body: formData
            });

            const data = await res.json();
            if (res.ok && data.success) {
                setStatus('Xác thực thành công');
                return true;
            }

            handleError(data, res.status);
            return false;
        } catch (err) {
            console.error('Verify error:', err);
            resetButtons();
            alert('Lỗi xác thực');
            return false;
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
                body: formData
            });

            const data = await res.json();
            console.log('Face registration response:', data);

            if (res.ok && data.success) {
                setButtonsState(true, 'Cập nhật thành công');
                setStatus('Khuôn mặt đã được cập nhật thành công.');
                setTimeout(() => {
                    location.reload();
                }, 1200);
            } else {
                handleError(data, res.status);
            }

        } catch (err) {
            console.error('Register error:', err);
            resetButtons();
            alert('Lỗi gửi ảnh tới server');
        }
    }

    async function deleteFace(blob: Blob, headers: Record<string, string>) {
        try {
            const formData = new FormData();
            formData.append('image', blob, 'face.jpg');
            setButtonsState(true, 'Đang xóa...');

            const res = await fetch('/user/face/delete', {
                method: 'POST',
                headers,
                body: formData
            });

            const data = await res.json();
            console.log('Face delete response:', data);

            if (res.ok && data.success) {
                setButtonsState(true, 'Đã xóa khuôn mặt');
                setStatus('Khuôn mặt đã được xóa.');
                setTimeout(() => {
                    location.reload();
                }, 1200);
            } else {
                handleError(data, res.status);
            }

        } catch (err) {
            console.error('Delete error:', err);
            resetButtons();
            alert('Lỗi gửi yêu cầu xóa');
        }
    }

    function handleError(data: any, status: number) {
        resetButtons();
        let message = 'Lỗi không xác định';

        if (status === 422 && data.error === 'No face detected') {
            message = 'Không phát hiện khuôn mặt. Vui lòng:\n- Đảm bảo ánh sáng tốt\n- Mặt rõ ràng, không bị che khuất\n- Thử lại';
        } else if (status === 401) {
            message = data.error || 'Xác thực khuôn mặt không thành công';
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
