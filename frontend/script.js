// Backend URL - Update this with your Render backend URL
const BACKEND_URL = 'https://your-backend-service.onrender.com';

// ... rest of the script remains the same, but update the download function:

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
    
    // Update button text based on format
    const isTxt = conversionResult.format === 'txt';
    const originalText = downloadBtn.innerHTML;
    downloadBtn.innerHTML = `<span class="btn-icon">✅</span> ${isTxt ? 'Text File' : 'Word Document'} Downloaded!`;
    downloadBtn.style.background = '#48bb78';
    
    setTimeout(() => {
        downloadBtn.innerHTML = originalText;
        downloadBtn.style.background = '';
    }, 2000);
}
