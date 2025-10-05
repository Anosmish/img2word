# Image to Word Converter

A web application that converts images to editable Microsoft Word (.docx) documents using OCR technology.

## 🚀 Features

- **Image Upload**: Drag & drop or file browser upload
- **OCR Text Extraction**: Uses Tesseract OCR to extract text from images
- **Word Document Generation**: Creates professional .docx files
- **Customizable Options**:
  - Document title
  - Multiple language support
  - Text formatting options
  - Font size selection
  - Include original image in document
  - Add timestamps
- **Responsive Design**: Works on desktop and mobile devices

## 🛠️ Technology Stack

### Frontend (Netlify)
- HTML5, CSS3, JavaScript (ES6+)
- Drag & Drop API
- Fetch API for backend communication

### Backend (Render with Docker)
- PHP 8.1 with GD library
- Tesseract OCR for text extraction
- PHPWord for Word document generation
- Apache web server

## 📁 Project Structure
image-to-word-converter/
├── frontend/ # Static files for Netlify
│ ├── index.html # Main application page
│ ├── style.css # Styles and responsive design
│ └── script.js # Frontend logic and API calls
└── backend/ # PHP backend for Render
├── convert.php # Main conversion endpoint
├── Dockerfile # Docker configuration
├── composer.json # PHP dependencies
└── .htaccess # Apache configuration

text

## 🚀 Deployment

### Frontend (Netlify)
1. Go to [Netlify](https://netlify.com)
2. Drag and drop the `frontend` folder or connect your GitHub repository
3. Set the publish directory to `frontend`
4. Update `BACKEND_URL` in `frontend/script.js` with your Render backend URL

### Backend (Render)
1. Go to [Render](https://render.com)
2. Create a new Web Service
3. Connect your GitHub repository
4. Set the root directory to `backend`
5. Choose "Docker" as the runtime
6. Deploy the service

## ⚙️ Configuration

### Backend URL Setup
Update these files with your actual URLs:

**frontend/script.js:**
```javascript