# LILAC v2.1 - Test Plan for Backend Improvements

## Overview
This test plan validates the implemented improvements to the LILAC awards system backend, including robust PDF/DOCX extraction, OCR safety, fresh analysis enforcement, centralized thresholds, and secure file handling.

## Test Environment Setup

1. **Start LILAC Server**:
   ```bash
   cd C:\Users\Administrator\Documents\GitHub\LILAC-v.2.1
   php -S localhost:8080
   ```

2. **Verify Dependencies**:
   - Check if `pdftotext` is available: `which pdftotext` (Linux/Mac) or `where pdftotext` (Windows)
   - Check if `tesseract` is available: `which tesseract` (Linux/Mac) or `where tesseract` (Windows)
   - Verify PHP extensions: `php -m | grep -i zip` for ZipArchive support

## Test Cases

### Test 1: PDF Upload with pdftotext

**Objective**: Verify robust PDF text extraction using pdftotext command-line tool.

**Test File**: Create a sample PDF or use existing test PDF
**Expected**: Should use pdftotext if available, fallback to basic extraction if not

```bash
curl -X POST http://localhost:8080/api/awards-upload.php \
  -H "Content-Type: multipart/form-data" \
  -F "file=@test_document.pdf" \
  -F "title=Test PDF Document" \
  -F "description=Testing PDF text extraction" \
  -F "date=2024-12-19" \
  -v
```

**Validation Points**:
- Response includes `"success": true`
- `detected_text` field contains extracted text
- No error messages about pdftotext unavailability
- Logs show pdftotext usage or fallback warning

### Test 2: DOCX Upload with ZipArchive

**Objective**: Verify improved DOCX text extraction using ZipArchive and proper XML parsing.

**Test File**: Create a sample DOCX file with text content
**Expected**: Should use ZipArchive to extract text from word/document.xml, fallback if ZipArchive missing

```bash
curl -X POST http://localhost:8080/api/awards-upload.php \
  -H "Content-Type: multipart/form-data" \
  -F "file=@test_document.docx" \
  -F "title=Test DOCX Document" \
  -F "description=Testing DOCX text extraction with proper XML parsing" \
  -F "date=2024-12-19" \
  -v
```

**Validation Points**:
- Response includes `"success": true`
- `detected_text` contains properly extracted text from DOCX
- No XML parsing errors
- Fallback message if ZipArchive not available

### Test 3: Image Upload with OCR Safety

**Objective**: Verify OCR functionality with proper dependency checking and timeout handling.

**Test File**: Upload a PNG or JPG image with text content
**Expected**: Should check for Tesseract availability and provide clear error messages if missing

```bash
curl -X POST http://localhost:8080/api/awards-upload.php \
  -H "Content-Type: multipart/form-data" \
  -F "file=@test_image.png" \
  -F "title=Test Image OCR" \
  -F "description=Testing OCR with proper dependency checking" \
  -F "date=2024-12-19" \
  -v
```

**Validation Points**:
- If Tesseract available: OCR text extraction works
- If Tesseract missing: Clear error message with installation instructions
- No shell command injection vulnerabilities
- Proper temporary file cleanup

## Additional Validation Tests

### Test 4: File Security Validation

```bash
# Test oversized file upload
curl -X POST http://localhost:8080/api/awards-upload.php \
  -H "Content-Type: multipart/form-data" \
  -F "file=@large_file.pdf" \
  -F "title=Large File Test" \
  -F "description=Testing file size limits" \
  -v
```

### Test 5: MIME Type Validation

```bash
# Test with invalid file type
curl -X POST http://localhost:8080/api/awards-upload.php \
  -H "Content-Type: multipart/form-data" \
  -F "file=@malicious.txt" \
  -F "title=Invalid File Type" \
  -F "description=Testing MIME type validation" \
  -v
```

### Test 6: Fresh Analysis Enforcement

```bash
# Upload same file twice - should get fresh analysis both times
curl -X POST http://localhost:8080/api/awards-upload.php \
  -H "Content-Type: multipart/form-data" \
  -F "file=@same_document.pdf" \
  -F "title=Fresh Analysis Test" \
  -F "description=First upload" \
  -F "date=2024-12-19" \
  -v

# Wait 2 seconds, then upload again
curl -X POST http://localhost:8080/api/awards-upload.php \
  -H "Content-Type: multipart/form-data" \
  -F "file=@same_document.pdf" \
  -F "title=Fresh Analysis Test 2" \
  -F "description=Second upload - should be fresh analysis" \
  -F "date=2024-12-19" \
  -v
```

## Expected Results Summary

1. **PDF Processing**: Uses `pdftotext` command-line tool when available, falls back gracefully with logging
2. **DOCX Processing**: Uses ZipArchive with proper XML parsing of `word/document.xml`, extracts `<w:t>` elements in order
3. **OCR Safety**: Checks Tesseract availability, provides clear error messages, uses proper shell escaping
4. **File Security**: Validates MIME types, enforces size limits, uses randomized safe filenames
5. **Fresh Analysis**: Always analyzes new uploads, only uses cached results when `reanalyze=true`
6. **Centralized Thresholds**: Uses values from `assets/awards-rules.json` instead of hardcoded values

## Error Scenarios to Test

1. **Missing pdftotext**: Should log warning and use fallback extraction
2. **Missing ZipArchive**: Should log warning and use fallback text
3. **Missing Tesseract**: Should return clear JSON error with installation instructions
4. **Invalid file types**: Should reject with proper error message
5. **File size exceeded**: Should reject with size limit message
6. **Corrupted files**: Should handle gracefully with appropriate error messages

## Configuration Validation

Verify these configuration changes:
- `ENABLE_OCR` flag in `api/config.php` toggles OCR functionality
- Thresholds loaded from `assets/awards-rules.json` in all analysis functions
- Safe filename generation with proper sanitization
- Temporary file cleanup in all extraction methods

## Logging Validation

Check log files for:
- pdftotext availability detection and usage
- ZipArchive availability and XML parsing results
- Tesseract path detection and OCR execution
- File validation and security measures
- Error handling and fallback mechanisms

This test plan ensures all implemented improvements work correctly and maintain system security and reliability.
