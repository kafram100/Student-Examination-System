// Proctoring system for examination security
class ExamProctoring {
    constructor() {
        this.isRecording = false;
        this.videoStream = null;
        this.audioStream = null;
        this.recordingChunks = [];
        this.mediaRecorder = null;
        this.webSocket = null;
        this.examStarted = false;

        this.init();
    }

    init() {
        // Check for required browser features
        if (!this.checkBrowserSupport()) {
            alert('Your browser does not support the required features for this exam. Please use a modern browser like Chrome or Firefox.');
            return;
        }

        // Set up event listeners for security measures
        this.setupSecurityListeners();
    }

    checkBrowserSupport() {
        return navigator.mediaDevices &&
            navigator.mediaDevices.getUserMedia &&
            window.MediaRecorder &&
            WebSocket;
    }

    async requestMediaAccess() {
        try {
            // Check if block overlay exists from previous error and remove it safely
            const oldOverlay = document.getElementById('proctoring-media-blocker');
            if (oldOverlay) oldOverlay.remove();

            // Request video and audio access
            this.videoStream = await navigator.mediaDevices.getUserMedia({
                video: { width: { ideal: 640 }, height: { ideal: 480 } },
                audio: true
            });

            // Display video preview
            const videoPreview = document.getElementById('proctoring-video-preview');
            if (videoPreview) {
                videoPreview.srcObject = this.videoStream;
                videoPreview.style.display = 'block';
            }

            // Start recording
            this.startRecording();

            // Connect to monitoring server
            this.connectMonitoringServer();

            // Check continually if stream is killed abruptly
            this.enforceStreamActivity();

        } catch (error) {
            console.error('Error accessing media devices:', error);
            this.showMediaBlockerOverlay();
        }
    }

    enforceStreamActivity() {
        setInterval(() => {
            if (!this.examStarted) return;

            if (!this.videoStream || !this.videoStream.active) {
                if (!document.getElementById('proctoring-media-blocker')) {
                    this.logSuspiciousActivity('camera_disabled', 'Student disabled camera or microphone during exam');
                    this.sendSecurityAlert('Student disabled camera or microphone');
                    this.showMediaBlockerOverlay();
                }
            } else {
                // Check if video tracks are actually outputting
                const videoTracks = this.videoStream.getVideoTracks();
                if (videoTracks.length === 0 || videoTracks[0].readyState === 'ended' || !videoTracks[0].enabled) {
                    if (!document.getElementById('proctoring-media-blocker')) {
                        this.logSuspiciousActivity('camera_disabled', 'Student disabled camera stream');
                        this.sendSecurityAlert('Student disabled camera stream');
                        this.showMediaBlockerOverlay();
                    }
                }
            }
        }, 3000);
    }

    showMediaBlockerOverlay() {
        // Prevent multiple overlays
        if (document.getElementById('proctoring-media-blocker')) return;

        const overlay = document.createElement('div');
        overlay.id = 'proctoring-media-blocker';
        Object.assign(overlay.style, {
            position: 'fixed',
            top: '0',
            left: '0',
            width: '100%',
            height: '100%',
            backgroundColor: 'rgba(15, 23, 42, 0.98)',
            backdropFilter: 'blur(15px)',
            zIndex: '9999999',
            display: 'flex',
            flexDirection: 'column',
            justifyContent: 'center',
            alignItems: 'center',
            padding: '2rem',
            textAlign: 'center'
        });

        overlay.innerHTML = `
            <div style="background: #1e293b; border: 1px solid #eab308; border-radius: 16px; padding: 3rem; max-width: 600px; box-shadow: 0 25px 50px -12px rgba(234, 179, 8, 0.25);">
                <i class="bi bi-camera-video-off" style="font-size: 5rem; color: #eab308; margin-bottom: 1.5rem; display: block;"></i>
                <h2 style="font-weight: 700; margin-bottom: 1rem; color: #f8fafc; font-family: 'Inter', sans-serif;">Hardware Access Required</h2>
                <p style="color: #cbd5e1; margin-bottom: 2rem; font-size: 1.15rem; line-height: 1.6;">You must enable your camera and microphone to take this proctored examination. You cannot proceed until access is granted through your browser settings.</p>
                
                <div style="padding: 1rem; background: rgba(234, 179, 8, 0.1); border-radius: 8px; margin-bottom: 2.5rem; border-left: 4px solid #eab308; text-align: left;">
                    <strong style="color: #eab308; display: block; margin-bottom: 0.5rem;"><i class="bi bi-info-circle"></i> Instructions:</strong>
                    <span style="color: #cbd5e1; font-size: 0.95rem;">Click the lock icon in your browser URL bar at the top, change Camera and Microphone to "Allow", and then click the button below to re-verify.</span>
                </div>
                
                <button id="btn-reverify-hardware" style="background: #eab308; color: #1e293b; border: none; padding: 1rem 2.5rem; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 1.1rem; transition: filter 0.2s;">
                    Verify Hardware Access
                </button>
            </div>
        `;

        document.body.appendChild(overlay);

        // Block all scrolling
        document.body.style.overflow = 'hidden';

        // Button Event Listener
        document.getElementById('btn-reverify-hardware').addEventListener('click', () => {
            this.requestMediaAccess();
        });
    }

    startRecording() {
        if (!this.videoStream) return;

        this.mediaRecorder = new MediaRecorder(this.videoStream);
        this.recordingChunks = [];
        this.mediaRecorder.ondataavailable = (event) => {
            if (event.data.size > 0) {
                this.recordingChunks.push(event.data);
                // Keep the rolling buffer at approximately the last 10 seconds 
                // 5 chunks x 2000ms timeslices = ~10s of memory
                if (this.recordingChunks.length > 5) {
                    this.recordingChunks.shift();
                }
            }
        };

        this.mediaRecorder.onstop = () => {
            this.saveEvidence('final_submission');
        };

        this.mediaRecorder.start(2000); // Trigger data chunk every 2 seconds
        this.isRecording = true;
    }

    stopRecording() {
        if (this.mediaRecorder && this.isRecording) {
            this.mediaRecorder.stop();
            this.isRecording = false;
        }

        if (this.videoStream) {
            this.videoStream.getTracks().forEach(track => track.stop());
        }
    }

    captureImageEvidence(violationType, description) {
        // Capture an image from the current video stream
        if (!this.videoStream) return;

        const canvas = document.createElement('canvas');
        const videoElement = document.createElement('video');
        
        // Get the video track from the stream
        const videoTrack = this.videoStream.getVideoTracks()[0];
        if (!videoTrack) return;
        
        // Create a temporary video element
        videoElement.srcObject = this.videoStream;
        videoElement.play();
        
        videoElement.addEventListener('loadedmetadata', () => {
            canvas.width = videoElement.videoWidth;
            canvas.height = videoElement.videoHeight;
            
            const ctx = canvas.getContext('2d');
            ctx.drawImage(videoElement, 0, 0, canvas.width, canvas.height);
            
            // Convert to data URL
            const imageData = canvas.toDataURL('image/jpeg', 0.8);
            
            // Send image to server
            this.sendImageToServer(imageData, violationType, description);
        });
    }
    
    sendImageToServer(imageData, violationType, description) {
        const formData = new FormData();
        formData.append('image_data', imageData);
        formData.append('exam_attempt_id', this.getExamAttemptId());
        formData.append('activity_type', violationType);
        formData.append('description', description);
        formData.append('severity', this.getSeverityForActivity(violationType));
        formData.append('csrf_token', this.getCSRFToken());

        fetch('capture_image.php', {
            method: 'POST',
            body: formData
        }).catch(error => {
            console.error('Error saving evidence image:', error);
        });
    }
    
    getSeverityForActivity(activityType) {
        const severityMap = {
            'tab_switch': 'high',
            'window_blur': 'medium',
            'print_attempt': 'medium',
            'right_click': 'low',
            'dev_tools': 'high',
            'copy_attempt': 'medium',
            'paste_attempt': 'medium',
            'screenshot_attempt': 'high',
            'exit_fullscreen': 'high',
            'camera_disabled': 'critical'
        };
        return severityMap[activityType] || 'medium';
    }

    connectMonitoringServer() {
        // Connect to WebSocket server for real-time monitoring
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        this.webSocket = new WebSocket(`${protocol}//${window.location.host}/ws/proctoring`);

        this.webSocket.onopen = () => {
            console.log('Connected to proctoring server');
        };

        this.webSocket.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.handleMonitoringMessage(data);
        };

        this.webSocket.onerror = (error) => {
            console.error('WebSocket error:', error);
        };
    }

    handleMonitoringMessage(data) {
        // Handle messages from monitoring server
        switch (data.type) {
            case 'alert':
                this.showSecurityAlert(data.message);
                break;
            case 'terminate':
                this.terminateExam(data.reason);
                break;
        }
    }

    setupSecurityListeners() {
        // Detect tab switching
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && this.examStarted) {
                this.captureImageEvidence('tab_switch', 'Student switched away from exam tab');
                this.logSuspiciousActivity('tab_switch', 'Student switched away from exam tab');
                this.sendSecurityAlert('Student switched away from exam tab');
                this.showViolationOverlay('Tab switching is strictly prohibited during the exam. Your activity has been permanently recorded.');
            }
        });

        // Detect window focus loss
        window.addEventListener('blur', () => {
            if (this.examStarted) {
                this.captureImageEvidence('window_blur', 'Exam window lost focus');
                this.logSuspiciousActivity('window_blur', 'Exam window lost focus');
                this.sendSecurityAlert('Exam window lost focus');
                this.showViolationOverlay('You are not allowed to leave the exam window. Your activity has been permanently recorded.');
            }
        });

        // Prevent print
        window.addEventListener('beforeprint', (e) => {
            e.preventDefault();
            this.captureImageEvidence('print_attempt', 'Student attempted to print');
            this.logSuspiciousActivity('print_attempt', 'Student attempted to print');
            this.sendSecurityAlert('Student attempted to print exam');
        });

        // Prevent right-click
        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            this.captureImageEvidence('right_click', 'Student attempted to right-click');
            this.logSuspiciousActivity('right_click', 'Student attempted to right-click');
            this.sendSecurityAlert('Student attempted to right-click');
        });

        // Prevent F12 and developer tools (as much as possible)
        document.addEventListener('keydown', (e) => {
            // F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+U
            if (e.keyCode === 123 ||
                (e.ctrlKey && e.shiftKey && (e.keyCode === 73 || e.keyCode === 74)) ||
                (e.ctrlKey && e.keyCode === 85)) {
                e.preventDefault();
                this.captureImageEvidence('dev_tools', 'Student attempted to open developer tools');
                this.logSuspiciousActivity('dev_tools', 'Student attempted to open developer tools');
                this.sendSecurityAlert('Student attempted to open developer tools');
            }
        });

        // Prevent copy/paste
        document.addEventListener('copy', (e) => {
            e.preventDefault();
            this.captureImageEvidence('copy_attempt', 'Student attempted to copy content');
            this.logSuspiciousActivity('copy_attempt', 'Student attempted to copy content');
            this.sendSecurityAlert('Student attempted to copy content');
        });

        document.addEventListener('paste', (e) => {
            e.preventDefault();
            this.captureImageEvidence('paste_attempt', 'Student attempted to paste content');
            this.logSuspiciousActivity('paste_attempt', 'Student attempted to paste content');
            this.sendSecurityAlert('Student attempted to paste content');
        });
    }

    logSuspiciousActivity(type, description) {
        // Capture image for this exact violation
        this.captureImageEvidence(type, description);

        const activityData = {
            type: type,
            description: description,
            timestamp: new Date().toISOString(),
            exam_attempt_id: this.getExamAttemptId()
        };

        fetch('log_activity.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(activityData)
        }).catch(error => {
            console.error('Error logging activity:', error);
        });
    }

    sendSecurityAlert(message) {
        if (this.webSocket && this.webSocket.readyState === WebSocket.OPEN) {
            this.webSocket.send(JSON.stringify({
                type: 'security_alert',
                message: message,
                exam_attempt_id: this.getExamAttemptId(),
                timestamp: new Date().toISOString()
            }));
        }
    }

    showSecurityAlert(message) {
        // Show a visual alert to the student
        const alertDiv = document.createElement('div');
        alertDiv.className = 'proctoring-alert';
        alertDiv.innerHTML = `
            <div class="alert alert-warning" style="position: fixed; top: 20px; right: 20px; z-index: 999999; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <strong>Security Notice:</strong> ${message}
            </div>
        `;

        document.body.appendChild(alertDiv);

        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }

    showViolationOverlay(message) {
        // Hide the exam content immediately to prevent reading during violation
        const contentArea = document.getElementById('exam-content-area');
        if (contentArea) contentArea.style.display = 'none';

        // Prevent multiple overlays
        if (document.getElementById('proctoring-violation-overlay')) return;

        const overlay = document.createElement('div');
        overlay.id = 'proctoring-violation-overlay';
        Object.assign(overlay.style, {
            position: 'fixed',
            top: '0',
            left: '0',
            width: '100%',
            height: '100%',
            backgroundColor: 'rgba(15, 23, 42, 0.98)',
            backdropFilter: 'blur(15px)',
            zIndex: '9999999',
            display: 'flex',
            flexDirection: 'column',
            justifyContent: 'center',
            alignItems: 'center',
            padding: '2rem',
            textAlign: 'center'
        });

        overlay.innerHTML = `
            <div style="background: #1e293b; border: 1px solid #ef4444; border-radius: 16px; padding: 3rem; max-width: 600px; box-shadow: 0 25px 50px -12px rgba(239, 68, 68, 0.25);">
                <i class="bi bi-exclamation-octagon-fill" style="font-size: 5rem; color: #ef4444; margin-bottom: 1.5rem; display: block;"></i>
                <h2 style="font-weight: 700; margin-bottom: 1rem; color: #f8fafc; font-family: 'Inter', sans-serif;">Security Violation Detected</h2>
                <p style="color: #cbd5e1; margin-bottom: 2rem; font-size: 1.15rem; line-height: 1.6;">${message}</p>
                
                <div style="padding: 1rem; background: rgba(239, 68, 68, 0.1); border-radius: 8px; margin-bottom: 2.5rem; border-left: 4px solid #ef4444; text-align: left;">
                    <strong style="color: #ef4444; display: block; margin-bottom: 0.5rem;"><i class="bi bi-camera-video"></i> Proctoring Note:</strong>
                    <span style="color: #cbd5e1; font-size: 0.95rem;">Please be advised that your professor has been instantly notified of this violation. Repeated violations may result in immediate automatic exam submission and academic review.</span>
                </div>
                
                <button id="btn-return-exam" style="background: #ef4444; color: white; border: none; padding: 1rem 2.5rem; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 1.1rem; transition: background 0.2s;">
                    I Understand, Return to Exam
                </button>
            </div>
        `;

        document.body.appendChild(overlay);

        // Button Event Listener
        document.getElementById('btn-return-exam').addEventListener('click', () => {
            overlay.remove();
            this.enterFullscreen(); // Always force fullscreen after a violation

            // Show content only when we are sure
            setTimeout(() => {
                const contentArea = document.getElementById('exam-content-area');
                if (contentArea) contentArea.style.display = 'block';
            }, 500);
        });
    }

    terminateExam(reason) {
        alert(`Exam terminated: ${reason}`);

        // Stop recording
        this.stopRecording();

        // Submit exam if possible
        if (typeof submitExam !== 'undefined') {
            submitExam();
        }

        // Redirect to results or error page
        window.location.href = 'exam_terminated.php?reason=' + encodeURIComponent(reason);
    }

    getExamAttemptId() {
        // Get exam attempt ID from URL or hidden field
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('attempt_id') || document.getElementById('exam_attempt_id')?.value || 'unknown';
    }

    startExam() {
        this.examStarted = true;

        // Enter fullscreen mode
        this.enterFullscreen();

        // Additional security measures during exam
        this.enforceSecurityMeasures();

        // Make an AJAX call to set the session variable
        this.enableProctoringSession();
    }

    enableProctoringSession() {
        fetch('enable_proctoring.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'token=' + encodeURIComponent(this.getCSRFToken())
        })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Failed to enable proctoring session:', data.message);
                }
            })
            .catch(error => {
                console.error('Error enabling proctoring session:', error);
            });
    }

    getCSRFToken() {
        // Try to get CSRF token from a meta tag or hidden input
        const tokenInput = document.querySelector('input[name="csrf_token"]');
        if (tokenInput) {
            return tokenInput.value;
        }
        return '';
    }

    enterFullscreen() {
        const elem = document.documentElement;
        if (elem.requestFullscreen) {
            elem.requestFullscreen();
        } else if (elem.mozRequestFullScreen) { /* Firefox */
            elem.mozRequestFullScreen();
        } else if (elem.webkitRequestFullscreen) { /* Chrome, Safari & Opera */
            elem.webkitRequestFullscreen();
        } else if (elem.msRequestFullscreen) { /* IE/Edge */
            elem.msRequestFullscreen();
        }
    }

    enforceSecurityMeasures() {
        // Continuously enforce security measures
        setInterval(() => {
            if (!this.examStarted) return;

            // Check if window is still focused
            if (!document.hasFocus()) {
                this.logSuspiciousActivity('window_unfocused', 'Exam window is not focused');
            }

            // Check if we're still in fullscreen
            if (!document.fullscreenElement && !document.mozFullScreenElement && !document.webkitFullscreenElement && !document.msFullscreenElement) {
                // Ignore if violation overlay is already up
                if (!document.getElementById('proctoring-violation-overlay')) {
                    this.logSuspiciousActivity('exit_fullscreen', 'Student exited fullscreen mode');
                    this.sendSecurityAlert('Student exited fullscreen mode');

                    // Immediately show violation to force a user click to re-enter fullscreen
                    this.showViolationOverlay('You exited fullscreen view. Exams must be taken in fullscreen mode to ensure integrity.');
                }
            }
        }, 1000);
    }
}

// Initialize proctoring when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Only initialize if on exam page
    if (window.location.pathname.includes('take_exam.php')) {
        window.examProctoring = new ExamProctoring();
    }
});