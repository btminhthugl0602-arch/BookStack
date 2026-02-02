console.log('Face register JS loaded ✅');

document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('face-register-btn') as HTMLButtonElement | null;
    const video = document.getElementById('face-register-video') as HTMLVideoElement | null;
    const canvas = document.getElementById('face-register-canvas') as HTMLCanvasElement | null;

    if (!btn || !video || !canvas) {
        console.warn('Face register elements not found');
        return;
    }

    let stream: MediaStream | null = null;
    let isProcessing = false;

    btn.addEventListener('click', async () => {
        if (isProcessing) return;
        isProcessing = true;
        btn.disabled = true;
        btn.textContent = '⏳ Đang mở camera...';

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                    facingMode: 'user'
                },
                audio: false
            });

            console.log('✅ Camera opened for registration');
            video.srcObject = stream;
            video.style.display = 'block';

            video.onloadedmetadata = () => {
                console.log(`Camera resolution: ${video.videoWidth}x${video.videoHeight}`);
                btn.textContent = '📸 Chụp ảnh...';
                setTimeout(() => {
                    captureAndSend();
                }, 800);
            };

        } catch (err: any) {
            isProcessing = false;
            btn.disabled = false;
            btn.textContent = '📸 Đăng ký khuôn mặt';

            console.error('Camera error:', err);

            if (err.name === 'NotAllowedError') {
                alert('❌ Bạn chưa cấp quyền camera');
            } else if (err.name === 'NotFoundError') {
                alert('❌ Không tìm thấy camera');
            } else if (err.name === 'NotReadableError') {
                alert('❌ Camera đang bị sử dụng');
            } else {
                alert(`❌ Lỗi camera: ${err.message}`);
            }
        }
    });

    async function captureAndSend() {
        if (!stream || !video || !canvas) {
            resetButton();
            return;
        }

        const ctx = canvas.getContext('2d');
        if (!ctx) {
            resetButton();
            return;
        }

        try {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            // Mirror image
            ctx.scale(-1, 1);
            ctx.drawImage(video, -video.videoWidth, 0);
            ctx.scale(-1, 1);

            stream.getTracks().forEach(track => track.stop());
            stream = null;
            video.style.display = 'none';

            console.log('📸 Captured image, sending to server...');

            canvas.toBlob(
                async (blob) => {
                    if (!blob) {
                        resetButton();
                        alert('❌ Lỗi xử lý ảnh');
                        return;
                    }

                    const formData = new FormData();
                    formData.append('image', blob, 'face.jpg');

                    const csrf = document.querySelector(
                        'meta[name="csrf-token"]'
                    ) as HTMLMetaElement | null;

                    btn.textContent = '🔍 Đang xử lý...';

                    try {
                        const res = await fetch('/user/face/register', {
                            method: 'POST',
                            headers: csrf ? { 'X-CSRF-TOKEN': csrf.content } : {},
                            body: formData
                        });

                        const data = await res.json();
                        console.log('Server response:', data);

                        if (res.ok && data.success) {
                            btn.textContent = '✅ Đăng ký thành công!';
                            alert('✅ Khuôn mặt đã được đăng ký thành công!');
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        } else {
                            handleError(data, res.status);
                        }

                    } catch (err) {
                        console.error('Fetch error:', err);
                        resetButton();
                        alert('❌ Lỗi gửi ảnh');
                    }
                },
                'image/jpeg',
                0.95
            );

        } catch (err) {
            console.error('Capture error:', err);
            resetButton();
            alert('❌ Lỗi chụp ảnh');
        }
    }

    function handleError(data: any, status: number) {
        resetButton();
        let message = '❌ Lỗi không xác định';

        if (status === 422 && data.error === 'No face detected') {
            message = '❌ Không phát hiện khuôn mặt. Vui lòng:\n- Đảm bảo ánh sáng tốt\n- Mặt rõ ràng, không bị che khuất\n- Thử lại';
        } else if (data.error) {
            message = `❌ ${data.error}`;
        }

        alert(message);
    }

    function resetButton() {
        isProcessing = false;
        btn.disabled = false;
        btn.textContent = '📸 Đăng ký khuôn mặt';
    }
});
