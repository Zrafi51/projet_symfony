import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = [
        'uploadSection', 
        'fileInput', 
        'fileLabel', 
        'fileInfo', 
        'fileName',
        'submitButton', 
        'loadingSpinner', 
        'resultSection', 
        'errorSection',
        'errorMessage',
        'monumentName', 
        'location', 
        'description', 
        'confidence',
        'resultImage',
        'addToRequestButton'
    ]

    connect() {
        // Enable drag and drop
        this.setupDragAndDrop()
    }

    setupDragAndDrop() {
        const uploadSection = this.uploadSectionTarget

        uploadSection.addEventListener('dragover', (e) => {
            e.preventDefault()
            uploadSection.classList.add('dragover')
        })

        uploadSection.addEventListener('dragleave', (e) => {
            e.preventDefault()
            uploadSection.classList.remove('dragover')
        })

        uploadSection.addEventListener('drop', (e) => {
            e.preventDefault()
            uploadSection.classList.remove('dragover')

            const files = e.dataTransfer.files
            if (files.length > 0) {
                this.fileInputTarget.files = files
                this.handleFileSelect()
            }
        })
    }

    handleFileSelect() {
        const file = this.fileInputTarget.files[0]
        
        if (!file) {
            this.hideFileInfo()
            return
        }

        // Validate file type
        const validTypes = ['image/jpeg', 'image/png', 'image/webp']
        if (!validTypes.includes(file.type)) {
            this.showError('Please upload a valid image file (JPEG, PNG, or WebP)')
            this.fileInputTarget.value = ''
            this.hideFileInfo()
            return
        }

        // Validate file size (10MB max)
        const maxSize = 10 * 1024 * 1024 // 10MB in bytes
        if (file.size > maxSize) {
            this.showError('File size must be less than 10MB')
            this.fileInputTarget.value = ''
            this.hideFileInfo()
            return
        }

        this.showFileInfo(file.name)
        this.hideError()
        this.hideResult()
    }

    showFileInfo(fileName) {
        this.fileInfoTarget.style.display = 'block'
        this.fileNameTarget.textContent = `Selected: ${fileName}`
        this.submitButtonTarget.disabled = false
    }

    hideFileInfo() {
        this.fileInfoTarget.style.display = 'none'
        this.fileNameTarget.textContent = ''
        this.submitButtonTarget.disabled = true
    }

    async handleSubmit(event) {
        event.preventDefault()
        
        const file = this.fileInputTarget.files[0]
        if (!file) {
            this.showError('Please select a file to upload')
            return
        }

        // Create FormData
        const formData = new FormData()
        formData.append('monument_image', file)

        // Show loading state
        this.showLoading()
        this.hideError()
        this.hideResult()

        try {
            const response = await fetch('/monument/scan', {
                method: 'POST',
                body: formData
            })

            const data = await response.json()

            if (data.success) {
                this.showResult(data.monumentScan)
            } else {
                this.showError(data.error || 'Failed to recognize monument')
            }
        } catch (error) {
            console.error('Upload error:', error)
            this.showError('Network error. Please try again.')
        } finally {
            this.hideLoading()
        }
    }

    showLoading() {
        this.loadingSpinnerTarget.classList.add('active')
        this.submitButtonTarget.disabled = true
        this.fileLabelTarget.disabled = true
        this.uploadSectionTarget.style.pointerEvents = 'none'
    }

    hideLoading() {
        this.loadingSpinnerTarget.classList.remove('active')
        this.submitButtonTarget.disabled = false
        this.fileLabelTarget.disabled = false
        this.uploadSectionTarget.style.pointerEvents = 'auto'
    }

    showResult(monumentScan) {
        // Update result content
        this.monumentNameTargets.forEach(target => {
            target.textContent = monumentScan.monumentName
        })

        const location = monumentScan.city && monumentScan.country 
            ? `${monumentScan.city}, ${monumentScan.country}`
            : monumentScan.city || monumentScan.country || 'Unknown location'
        this.locationTarget.textContent = location

        this.descriptionTarget.textContent = monumentScan.description
        this.confidenceTarget.textContent = `Confidence: ${Math.round(monumentScan.confidence * 100)}%`
        this.resultImageTarget.src = monumentScan.imagePath
        this.resultImageTarget.alt = monumentScan.monumentName

        // Store scan data for add to request functionality
        this.currentScanData = monumentScan

        // Update add to request button
        if (monumentScan.addedToRequest) {
            this.addToRequestTarget.textContent = '✓ Added to Request'
            this.addToRequestTarget.disabled = true
            this.addToRequestTarget.classList.remove('btn-primary')
            this.addToRequestTarget.classList.add('btn-secondary')
        } else {
            this.addToRequestTarget.innerHTML = '<span>✈️</span><span>Add to Travel Request</span>'
            this.addToRequestTarget.disabled = false
            this.addToRequestTarget.classList.remove('btn-secondary')
            this.addToRequestTarget.classList.add('btn-primary')
        }

        // Show result section
        this.resultSectionTarget.classList.add('active')
        
        // Scroll to result
        this.resultSectionTarget.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }

    hideResult() {
        this.resultSectionTarget.classList.remove('active')
    }

    showError(message) {
        this.errorMessageTarget.textContent = message
        this.errorSectionTarget.classList.add('active')
        
        // Scroll to error
        this.errorSectionTarget.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }

    hideError() {
        this.errorSectionTarget.classList.remove('active')
    }

    async addToRequest() {
        if (!this.currentScanData) {
            this.showError('No scan data available')
            return
        }

        if (this.currentScanData.addedToRequest) {
            this.showError('Monument already added to request')
            return
        }

        try {
            this.addToRequestTarget.disabled = true
            this.addToRequestTarget.innerHTML = '<span>⏳</span><span>Adding...</span>'

            const response = await fetch(`/monument/add-to-request/${this.currentScanData.id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            })

            const data = await response.json()

            if (data.success) {
                // Update button state
                this.addToRequestTarget.innerHTML = '<span>✓</span><span>Added to Request</span>'
                this.addToRequestTarget.classList.remove('btn-primary')
                this.addToRequestTarget.classList.add('btn-secondary')
                
                // Update current scan data
                this.currentScanData.addedToRequest = true

                // Show success message and redirect after a delay
                setTimeout(() => {
                    if (data.redirectUrl) {
                        window.location.href = data.redirectUrl
                    }
                }, 1500)
            } else {
                this.showError(data.error || 'Failed to add monument to request')
                this.addToRequestTarget.disabled = false
                this.addToRequestTarget.innerHTML = '<span>✈️</span><span>Add to Travel Request</span>'
            }
        } catch (error) {
            console.error('Add to request error:', error)
            this.showError('Network error. Please try again.')
            this.addToRequestTarget.disabled = false
            this.addToRequestTarget.innerHTML = '<span>✈️</span><span>Add to Travel Request</span>'
        }
    }

    scanAnother() {
        // Reset form
        this.fileInputTarget.value = ''
        this.hideFileInfo()
        this.hideResult()
        this.hideError()
        
        // Reset current scan data
        this.currentScanData = null
        
        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' })
    }

    // Utility methods
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes'
        const k = 1024
        const sizes = ['Bytes', 'KB', 'MB', 'GB']
        const i = Math.floor(Math.log(bytes) / Math.log(k))
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
    }

    formatDate(dateString) {
        const date = new Date(dateString)
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        })
    }
}
