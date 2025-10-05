// Backend URL - Update this with your Render backend URL
const BACKEND_URL = 'https://your-image-to-word-backend.onrender.com';

document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('fileInput');
    const previewSection = document.getElementById('previewSection');
    const previewImage = document.getElementById('previewImage');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const imageDimensions = document.getElementById('imageDimensions');
    const documentTitle = document.getElementById('documentTitle');
    const languageSelect = document.getElementById('language');
    const formattingSelect = document.getElementById('formatting');
    const fontSizeSelect = document.getElementById('fontSize');
    const includeImageCheckbox = document.getElementById('includeImage');
    const addTimestampCheckbox = document.getElementById('addTimestamp');
    const convertBtn = document.getElementById('convertBtn');
    const resultSection = document.getElementById('resultSection');
    const outputFileName = document.getElementById('outputFileName');
    const outputFileSize = document.getElementById('outputFileSize');
    const conversionInfo = document.getElementById('conversionInfo');
    const downloadBtn = document.getElementById('downloadBtn');
    const convertAnotherBtn = document.getElementById('convertAnotherBtn');
    const loadingOverlay = document.getElementById('loadingOverlay');
    
    let uploadedFile = null;
    let conversionResult = null;

    // Event Listeners
    uploadArea.addEventListener('click', () => fileInput.click());
    
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });
    
    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('dragover');
    });
    
    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        
        if (e.dataTransfer.files.length) {
            handleFile(e.dataTransfer.files[0]);
        }
    });
    
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length) {
            handleFile(e.target.files[0]);
        }
    });
    
    convertBtn.addEventListener('click', convertToWord);
    downloadBtn.addEventListener('click', downloadWordDocument);
    convertAnotherBtn.addEventListener('click', resetConverter);

    function handleFile(file) {
        // Validate file type
        if (!file.type.match('image.*')) {
            alert('Please select an image file (JPG, PNG, GIF, BMP, WebP)');
            return;
        }
        
        // Check file size (limit to 8MB for better processing)
        if (file.size > 8 * 1024 * 1024) {
            alert('Please select an image smaller than 8MB for optimal processing');
            return;
        }
        
        uploadedFile = file;
        
        // Display file info
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        
        // Create preview
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImage.src = e.target.result;
            previewSection.style.display = 'block';
            
            // Get image dimensions
            const img = new Image();
            img.onload = function() {
                imageDimensions.textContent = `${img.width} × ${img.height} pixels`;
                
                // Set default document title based on filename
                const baseName = file.name.replace(/\.[^/.]+$/, "");
                documentTitle.value = baseName || 'Extracted Document';
                
                // Enable convert button
                convertBtn.disabled = false;
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    function convertToWord() {
        if (!uploadedFile) return;
        
        const formData = new FormData();
        formData.append('image', uploadedFile);
        formData.append('title', documentTitle.value);
        formData.append('language', languageSelect.value);
        formData.append('formatting', formattingSelect.value);
        formData.append('fontSize', fontSizeSelect.value);
        formData.append('includeImage', includeImageCheckbox.checked ? '1' : '0');
        formData.append('addTimestamp', addTimestampCheckbox.checked ? '1' : '0');
        
        // Show loading state
        loadingOverlay.style.display = 'flex';
        convertBtn.disabled = true;
        
        // Send to backend
        fetch(`${BACKEND_URL}/convert.php`, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Server response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                conversionResult = data;
                showConversionResult(data);
            } else {
                throw new Error(data.error || 'Conversion failed');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred during conversion: ' + error.message);
        })
        .finally(() => {
            loadingOverlay.style.display = 'none';
            convertBtn.disabled = false;
        });
    }

    function showConversionResult(data) {
        outputFileName.textContent = data.filename;
        outputFileSize.textContent = formatFileSize(data.fileSize);
        conversionInfo.textContent = `Contains ${data.wordCount} words extracted from image`;
        
        resultSection.style.display = 'block';
        resultSection.scrollIntoView({ behavior: 'smooth' });
    }

    function downloadWordDocument() {
        if (!conversionResult) return;
        
        // Create download link
        const downloadUrl = `${BACKEND_URL}/${conversionResult.filepath}`;
        const a = document.createElement('a');
        a.href = downloadUrl;
        a.download = conversionResult.filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        
        // Visual feedback
        const originalText = downloadBtn.innerHTML;
        downloadBtn.innerHTML = '<span class="btn-icon">✅</span> Download Complete!';
        downloadBtn.style.background = '#48bb78';
        
        setTimeout(() => {
            downloadBtn.innerHTML = originalText;
            downloadBtn.style.background = '';
        }, 2000);
    }

    function resetConverter() {
        // Reset form
        uploadedFile = null;
        conversionResult = null;
        fileInput.value = '';
        previewSection.style.display = 'none';
        resultSection.style.display = 'none';
        convertBtn.disabled = true;
        
        // Reset to default values
        documentTitle.value = 'Extracted Document';
        languageSelect.value = 'eng';
        formattingSelect.value = 'preserve';
        fontSizeSelect.value = '12';
        includeImageCheckbox.checked = true;
        addTimestampCheckbox.checked = true;
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
});