# OCR Setup Guide for LILAC Awards System

This guide explains how to set up Tesseract OCR for the award analysis functionality.

## Windows Installation

1. Download Tesseract installer from: https://github.com/UB-Mannheim/tesseract/wiki
2. Run the installer and install to default location: `C:\Program Files\Tesseract-OCR\`
3. Add Tesseract to your PATH environment variable:
   - Open System Properties > Environment Variables
   - Add `C:\Program Files\Tesseract-OCR\` to your PATH
4. Restart your command prompt/server

## Linux Installation (Ubuntu/Debian)

```bash
sudo apt update
sudo apt install tesseract-ocr
```

## macOS Installation

```bash
brew install tesseract
```

## Testing Installation

You can test if Tesseract is properly installed by running:

```bash
tesseract --version
```

## Alternative: Docker Setup

If you prefer using Docker, you can use the Tesseract Docker image:

```bash
docker run -it --rm -v $(pwd):/workspace tesseractshadow/tesseract4re tesseract /workspace/input.jpg /workspace/output
```

## Notes

- The system will automatically detect Tesseract installation
- If Tesseract is not found, the system will show an error message
- For production environments, consider using cloud OCR services for better accuracy
- The current setup supports English text extraction by default

## Troubleshooting

### Common Issues:

1. **"Tesseract not found" error**
   - Ensure Tesseract is installed and in PATH
   - Check the file paths in `api/config.php`

2. **Poor OCR accuracy**
   - Use high-quality, high-resolution images
   - Ensure text is clearly visible and not rotated
   - Consider preprocessing images (deskewing, noise removal)

3. **Memory issues with large files**
   - The system limits file uploads to 10MB
   - For larger files, consider splitting them into smaller parts

## File Format Support

- **Images**: JPG, PNG (OCR extraction)
- **PDFs**: Text-based PDFs (direct text extraction)
- **Documents**: DOCX files (XML parsing)

For scanned PDFs, the system will attempt to extract text, but results may vary based on image quality.
