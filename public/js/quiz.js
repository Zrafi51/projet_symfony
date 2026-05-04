class TravelQuiz {
    constructor() {
        this.sessionId = null;
        this.currentQuestion = null;
        this.score = 0;
        this.totalQuestions = 5;
        this.timer = null;
        this.timeLeft = 30;
        this.startTime = null;
        this.webcamStream = null;
        this.faceModel = null;
        this.proctoringActive = false;
        this.violations = [];
        
        this.init();
    }

    async init() {
        this.setupEventListeners();
        await this.initializeWebcam();
        await this.loadFaceModel();
        this.hideLoading();
    }

    setupEventListeners() {
        // Start button
        document.getElementById('start-btn').addEventListener('click', () => {
            this.startQuiz();
        });

        // Submit button
        document.getElementById('submit-btn').addEventListener('click', () => {
            this.submitAnswer();
        });

        // Next button
        document.getElementById('next-btn').addEventListener('click', () => {
            this.loadNextQuestion();
        });

        // Restart button
        document.getElementById('restart-btn').addEventListener('click', () => {
            this.restartQuiz();
        });

        // Home button
        document.getElementById('home-btn').addEventListener('click', () => {
            this.goHome();
        });

        // Enter key for answer input
        document.getElementById('answer-input').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.submitAnswer();
            }
        });

        // Tab switching detection
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && this.proctoringActive) {
                this.logViolation('TAB_SWITCH');
            }
        });

        // Window focus/blur detection
        window.addEventListener('blur', () => {
            if (this.proctoringActive) {
                this.logViolation('WINDOW_BLUR');
            }
        });

        // Prevent right-click on images
        document.addEventListener('contextmenu', (e) => {
            if (e.target.tagName === 'IMG') {
                e.preventDefault();
                return false;
            }
        });

        // Prevent copy/paste globally
        document.addEventListener('copy', (e) => {
            if (this.proctoringActive) {
                e.preventDefault();
                this.logViolation('COPY_ATTEMPT');
                return false;
            }
        });

        document.addEventListener('paste', (e) => {
            if (this.proctoringActive) {
                e.preventDefault();
                this.logViolation('PASTE_ATTEMPT');
                return false;
            }
        });
    }

    async initializeWebcam() {
        try {
            const video = document.getElementById('webcam');
            this.webcamStream = await navigator.mediaDevices.getUserMedia({ 
                video: { 
                    width: 160, 
                    height: 120,
                    facingMode: 'user'
                } 
            });
            video.srcObject = this.webcamStream;
            
            // Update status
            const status = document.getElementById('webcam-status');
            status.innerHTML = '<div class="status-dot active"></div><span>Active</span>';
            
        } catch (error) {
            console.error('Webcam access denied:', error);
            const status = document.getElementById('webcam-status');
            status.innerHTML = '<div class="status-dot error"></div><span>Camera Error</span>';
            
            // Show error message
            this.showError('Camera access is required for this quiz. Please allow camera access and refresh.');
        }
    }

    async loadFaceModel() {
        try {
            this.faceModel = await blazeface.load();
            console.log('Face detection model loaded');
        } catch (error) {
            console.error('Failed to load face model:', error);
        }
    }

    async startFaceDetection() {
        if (!this.faceModel || !this.proctoringActive) return;

        const video = document.getElementById('webcam');
        
        const detectFaces = async () => {
            if (!this.proctoringActive) return;

            try {
                const predictions = await this.faceModel.estimateFaces(video, false);
                
                if (predictions.length === 0) {
                    // No face detected
                    this.logViolation('NO_FACE');
                } else if (predictions.length > 1) {
                    // Multiple faces detected
                    this.logViolation('MULTIPLE_FACES');
                }
                
                // Check face size (too far away)
                if (predictions.length === 1) {
                    const face = predictions[0];
                    const faceSize = (face.bottomRight[0] - face.topLeft[0]) * 
                                   (face.bottomRight[1] - face.topLeft[1]);
                    const videoArea = 160 * 120;
                    const faceRatio = faceSize / videoArea;
                    
                    if (faceRatio < 0.1) { // Face is too small (too far away)
                        this.logViolation('FACE_TOO_FAR');
                    }
                }
                
            } catch (error) {
                console.error('Face detection error:', error);
            }
            
            // Schedule next detection
            setTimeout(detectFaces, 1000);
        };
        
        // Start detection loop
        setTimeout(detectFaces, 1000);
    }

    async startQuiz() {
        try {
            this.showLoading();
            
            // Start quiz session
            const response = await fetch('/api/quiz/start', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    total_questions: this.totalQuestions
                })
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'Failed to start quiz');
            }
            
            this.sessionId = data.session_id;
            this.score = 0;
            this.currentQuestion = 0;
            this.startTime = Date.now();
            
            // Update UI
            document.getElementById('total-questions').textContent = this.totalQuestions;
            document.getElementById('score').textContent = '0';
            
            // Switch to question screen
            this.showScreen('question-screen');
            
            // Start proctoring
            this.proctoringActive = true;
            this.startFaceDetection();
            
            // Load first question
            await this.loadNextQuestion();
            
        } catch (error) {
            console.error('Failed to start quiz:', error);
            this.showError('Failed to start quiz. Please try again.');
        } finally {
            this.hideLoading();
        }
    }

    async loadNextQuestion() {
        try {
            this.showLoading();
            
            // Reset UI for new question
            document.getElementById('answer-input').value = '';
            document.getElementById('answer-input').disabled = false;
            document.getElementById('submit-btn').style.display = 'inline-block';
            document.getElementById('next-btn').style.display = 'none';
            document.getElementById('destination-image').style.display = 'none';
            document.getElementById('image-placeholder').style.display = 'flex';
            
            // Get next question
            const response = await fetch(`/api/quiz/question?session_id=${this.sessionId}`);
            const data = await response.json();
            
            if (!response.ok) {
                if (response.status === 404) {
                    // No more questions - end quiz
                    await this.endQuiz();
                    return;
                }
                throw new Error(data.error || 'Failed to load question');
            }
            
            this.currentQuestion = data;
            
            // Load image
            const img = document.getElementById('destination-image');
            img.onload = () => {
                document.getElementById('image-placeholder').style.display = 'none';
                img.style.display = 'block';
                this.hideLoading();
            };
            
            img.onerror = () => {
                this.hideLoading();
                this.showError('Failed to load image. Please try again.');
            };
            
            img.src = data.imageUrl;
            
            // Start timer
            this.startTimer();
            
        } catch (error) {
            console.error('Failed to load question:', error);
            this.showError('Failed to load question. Please try again.');
            this.hideLoading();
        }
    }

    startTimer() {
        this.timeLeft = 30;
        this.updateTimerDisplay();
        
        // Clear existing timer
        if (this.timer) {
            clearInterval(this.timer);
        }
        
        this.timer = setInterval(() => {
            this.timeLeft--;
            this.updateTimerDisplay();
            
            if (this.timeLeft <= 0) {
                clearInterval(this.timer);
                this.timeUp();
            }
        }, 1000);
    }

    updateTimerDisplay() {
        const timerElement = document.getElementById('timer');
        timerElement.textContent = this.timeLeft;
        
        // Add warning class when time is low
        if (this.timeLeft <= 10) {
            timerElement.classList.add('warning');
        } else {
            timerElement.classList.remove('warning');
        }
    }

    async submitAnswer() {
        const answerInput = document.getElementById('answer-input');
        const answer = answerInput.value.trim();
        
        if (!answer) {
            this.showError('Please enter an answer.');
            return;
        }
        
        // Stop timer
        if (this.timer) {
            clearInterval(this.timer);
        }
        
        // Disable input
        answerInput.disabled = true;
        document.getElementById('submit-btn').disabled = true;
        
        try {
            const response = await fetch('/api/quiz/check', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    session_id: this.sessionId,
                    voyage_id: this.currentQuestion.idVoyage,
                    user_answer: answer
                })
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'Failed to check answer');
            }
            
            // Update score
            if (data.correct) {
                this.score++;
                document.getElementById('score').textContent = this.score;
                this.showSuccess('Correct! 🎉');
            } else {
                this.showError(`Incorrect. The answer was: ${data.correct_answer}`);
            }
            
            // Show next button
            document.getElementById('submit-btn').style.display = 'none';
            document.getElementById('next-btn').style.display = 'inline-block';
            
        } catch (error) {
            console.error('Failed to check answer:', error);
            this.showError('Failed to check answer. Please try again.');
            
            // Re-enable input for retry
            answerInput.disabled = false;
            document.getElementById('submit-btn').disabled = false;
        }
    }

    timeUp() {
        document.getElementById('answer-input').disabled = true;
        document.getElementById('submit-btn').disabled = true;
        
        this.showError('Time\'s up! ⏰');
        this.logViolation('TIME_UP');
        
        // Show next button
        document.getElementById('submit-btn').style.display = 'none';
        document.getElementById('next-btn').style.display = 'inline-block';
    }

    async endQuiz() {
        try {
            this.proctoringActive = false;
            
            // Get final results
            const response = await fetch(`/api/quiz/results/${this.sessionId}`);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'Failed to get results');
            }
            
            // Calculate stats
            const totalTime = Math.floor((Date.now() - this.startTime) / 1000);
            const accuracy = Math.round((data.score / data.total_questions) * 100);
            
            // Update results screen
            document.getElementById('final-score').textContent = data.score;
            document.getElementById('final-total').textContent = data.total_questions;
            document.getElementById('accuracy').textContent = accuracy + '%';
            document.getElementById('violations').textContent = data.violations;
            document.getElementById('total-time').textContent = totalTime + 's';
            
            // Show results screen
            this.showScreen('result-screen');
            
        } catch (error) {
            console.error('Failed to end quiz:', error);
            this.showError('Failed to get results. Please try again.');
        }
    }

    async logViolation(type) {
        if (!this.sessionId || !this.proctoringActive) return;
        
        try {
            await fetch('/api/proctoring/log', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    session_id: this.sessionId,
                    voyage_id: this.currentQuestion?.idVoyage,
                    violation_type: type
                })
            });
            
            this.violations.push({
                type: type,
                timestamp: Date.now()
            });
            
            // Update UI to show warning
            this.showWarning(`Violation detected: ${type}`);
            
        } catch (error) {
            console.error('Failed to log violation:', error);
        }
    }

    restartQuiz() {
        this.proctoringActive = false;
        this.sessionId = null;
        this.currentQuestion = null;
        this.score = 0;
        this.violations = [];
        
        if (this.timer) {
            clearInterval(this.timer);
        }
        
        this.showScreen('start-screen');
    }

    goHome() {
        window.location.href = '/';
    }

    // UI Helper Methods
    showScreen(screenId) {
        document.querySelectorAll('.screen').forEach(screen => {
            screen.classList.remove('active');
        });
        document.getElementById(screenId).classList.add('active');
    }

    showLoading() {
        document.getElementById('loading-overlay').style.display = 'flex';
    }

    hideLoading() {
        document.getElementById('loading-overlay').style.display = 'none';
    }

    showError(message) {
        this.showMessage(message, 'error');
    }

    showSuccess(message) {
        this.showMessage(message, 'success');
    }

    showWarning(message) {
        this.showMessage(message, 'warning');
    }

    showMessage(message, type) {
        // Remove existing messages
        const existing = document.querySelector('.quiz-message');
        if (existing) {
            existing.remove();
        }
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `quiz-message ${type}`;
        messageDiv.textContent = message;
        
        document.body.appendChild(messageDiv);
        
        // Auto-remove after 3 seconds
        setTimeout(() => {
            messageDiv.remove();
        }, 3000);
    }
}

// Initialize quiz when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new TravelQuiz();
});
