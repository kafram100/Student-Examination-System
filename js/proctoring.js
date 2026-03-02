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
        
        // Request camera and microphone access
        this.requestMediaAccess();
        
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
            
        } catch (error) {
            console.error('Error accessing media devices:', error);
            alert('Camera and microphone access is required for this exam. Please grant permission and reload the page.');
        }
    }
    
    startRecording() {
        if (!this.videoStream) return;
        
        this.mediaRecorder = new MediaRecorder(this.videoStream);
        this.mediaRecorder.ondataavailable = (event) => {
            if (event.data.size > 0) {
                this.recordingChunks.push(event.data);
            }
        };
        
        this.mediaRecorder.onstop = () => {
            this.saveRecording();
        };
        
        this.mediaRecorder.start();
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
    
    saveRecording() {
        if (this.recordingChunks.length === 0) return;
        
        const blob = new Blob(this.recordingChunks, { type: 'video/webm' });
        const formData = new FormData();
        formData.append('recording', blob, `exam_${Date.now()}.webm`);
        formData.append('exam_attempt_id', this.getExamAttemptId());
        
        fetch('save_recording.php', {
            method: 'POST',
            body: formData
        }).then(response => {
            if (!response.ok) {
                throw new Error('Failed to save recording');
            }
        }).catch(error => {
            console.error('Error saving recording:', error);
        });
        
        this.recordingChunks = [];
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
        switch(data.type) {
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
            if (document.hidden) {
                this.logSuspiciousActivity('tab_switch', 'Student switched away from exam tab');
                this.sendSecurityAlert('Student switched away from exam tab');
            }
        });
        
        // Detect window focus loss
        window.addEventListener('blur', () => {
            this.logSuspiciousActivity('window_blur', 'Exam window lost focus');
            this.sendSecurityAlert('Exam window lost focus');
        });
        
        // Prevent print
        window.addEventListener('beforeprint', (e) => {
            e.preventDefault();
            this.logSuspiciousActivity('print_attempt', 'Student attempted to print');
            this.sendSecurityAlert('Student attempted to print exam');
        });
        
        // Prevent right-click
        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();
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
                this.logSuspiciousActivity('dev_tools', 'Student attempted to open developer tools');
                this.sendSecurityAlert('Student attempted to open developer tools');
            }
        });
        
        // Prevent copy/paste
        document.addEventListener('copy', (e) => {
            e.preventDefault();
            this.logSuspiciousActivity('copy_attempt', 'Student attempted to copy content');
            this.sendSecurityAlert('Student attempted to copy content');
        });
        
        document.addEventListener('paste', (e) => {
            e.preventDefault();
            this.logSuspiciousActivity('paste_attempt', 'Student attempted to paste content');
            this.sendSecurityAlert('Student attempted to paste content');
        });
    }
    
    logSuspiciousActivity(type, description) {
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
            <div class="alert alert-warning">
                <strong>Security Notice:</strong> ${message}
            </div>
        `;
        
        document.body.appendChild(alertDiv);
        
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
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
            if (!document.fullscreenElement) {
                this.logSuspiciousActivity('exit_fullscreen', 'Student exited fullscreen mode');
                this.sendSecurityAlert('Student exited fullscreen mode');
                
                // Re-enter fullscreen
                this.enterFullscreen();
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