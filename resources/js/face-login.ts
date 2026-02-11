console.log('Face login JS loaded');

document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('face-login-btn') as HTMLButtonElement | null;
    const video = document.getElementById('face-video') as HTMLVideoElement | null;
    const canvas = document.getElementById('face-canvas') as HTMLCanvasElement | null;

    // Nếu không phải trang login, thoát
    if (!btn || !video || !canvas) {
        console.log('Face login page not detected, skipping initialization');
        return;
    }

    let stream: MediaStream | null = null;
    let isProcessing = false;

    btn.addEventListener('click', async () => {
        if (isProcessing) return;
        isProcessing = true;
        btn.disabled = true;
        btn.textContent = 'Đang mở camera...';

        try {
            // Yêu cầu quyền camera
            // Sử dụng constraints tối ưu cho face detection
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                    facingMode: 'user'
                },
                audio: false
            });

            console.log('Camera opened successfully');
            video.srcObject = stream;
            video.style.display = 'block';

            // Đợi video stream ổn định
            video.onloadedmetadata = () => {
                console.log(`Camera resolution: ${video.videoWidth}x${video.videoHeight}`);
                btn.textContent = 'Chụp ảnh...';
                setTimeout(() => {
                    captureAndSend();
                }, 800);
            };

        } catch (err: any) {
            isProcessing = false;
            btn.disabled = false;
            btn.textContent = 'Đăng nhập bằng khuôn mặt';

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
            // Set canvas size theo video resolution
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            // Mirror image (giống như sử dụng transform: scaleX(-1))
            ctx.scale(-1, 1);
            ctx.drawImage(video, -video.videoWidth, 0);
            ctx.scale(-1, 1);

            // Dừng stream ngay sau khi draw
            stream.getTracks().forEach(track => track.stop());
            stream = null;
            video.style.display = 'none';

            console.log('Captured image, sending to server...');

            canvas.toBlob(
                async (blob) => {
                    if (!blob) {
                        console.error('Failed to create blob');
                        resetButton();
                        alert('Lỗi xử lý ảnh');
                        return;
                    }

                    const formData = new FormData();
                    formData.append('image', blob, 'face.jpg');

                    const csrf = document.querySelector(
                        'meta[name="csrf-token"]'
                    ) as HTMLMetaElement | null;

                    btn.textContent = 'Đang nhận diện...';

                    try {
                        const res = await fetch('/login/face', {
                            method: 'POST',
                            headers: csrf ? { 'X-CSRF-TOKEN': csrf.content } : {},
                            body: formData
                        });

                        const data = await res.json();
                        console.log('Server response:', data);

                        if (res.ok && data.success) {
                            btn.textContent = 'Nhận diện thành công';
                            setTimeout(() => {
                                window.location.href = '/';
                            }, 500);
                        } else {
                            handleError(data, res.status);
                        }

                    } catch (err) {
                        console.error('Fetch error:', err);
                        resetButton();
                        alert('Lỗi gửi ảnh tới server');
                    }
                },
                'image/jpeg',
                0.95
            );

        } catch (err) {
            console.error('Capture error:', err);
            resetButton();
            alert('Lỗi chụp ảnh');
        }
    }

    function handleError(data: any, status: number) {
        resetButton();
        let message = 'Lỗi không xác định';

        if (status === 422 && data.error === 'No face detected') {
            message = 'Không phát hiện khuôn mặt. Vui lòng:\n- Đảm bảo ánh sáng tốt\n- Mặt rõ ràng, không bị che khuất\n- Thử lại';
        } else if (status === 401 && data.error === 'Face not recognized') {
            message = 'Khuôn mặt không được nhận diện. Vui lòng đăng ký khuôn mặt trước';
        } else if (status === 401 && data.error === 'Low confidence') {
            message = `Độ tin cậy thấp (${data.confidence || 0}%). Vui lòng thử lại với ánh sáng tốt hơn`;
        } else if (status === 404) {
            message = 'Người dùng không tồn tại';
        } else if (data.error) {
            message = `${data.error}`;
        }

        alert(message);
    }

    function resetButton() {
        isProcessing = false;
        btn.disabled = false;
        btn.textContent = 'Đăng nhập bằng khuôn mặt';
    }
});
