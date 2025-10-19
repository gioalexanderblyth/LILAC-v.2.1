# LILAC v2.1 - Sample cURL Commands for Testing

## Prerequisites
- LILAC server running on `localhost:8080`
- Test files available in your working directory

## Commands for Testing Improvements

### 1. PDF Upload Test (Tests pdftotext integration)

```bash
curl -X POST http://localhost:8080/api/awards-upload.php \
  -H "Content-Type: multipart/form-data" \
  -F "file=@sample_award.pdf" \
  -F "title=Global Citizenship Award Application" \
  -F "description=Testing robust PDF text extraction with pdftotext" \
  -F "date=2024-12-19" \
  -v
```

### 2. DOCX Upload Test (Tests ZipArchive XML parsing)

```bash
curl -X POST http://localhost:8080/api/awards-upload.php \
  -H "Content-Type: multipart/form-data" \
  -F "file=@leadership_program.docx" \
  -F "title=Emerging Leadership Award Program" \
  -F "description=Testing DOCX extraction with proper XML parsing of w:t elements" \
  -F "date=2024-12-19" \
  -v
```

### 3. Image OCR Test (Tests Tesseract safety and dependency checking)

```bash
curl -X POST http://localhost:8080/api/awards-upload.php \
  -H "Content-Type: multipart/form-data" \
  -F "file=@award_certificate.png" \
  -F "title=Sustainability Award Certificate" \
  -F "description=Testing OCR with proper dependency checking and error handling" \
  -F "date=2024-12-19" \
  -v
```

### 4. File Security Test (Tests validation and safe filename generation)

```bash
curl -X POST http://localhost:8080/api/awards-upload.php \
  -H "Content-Type: multipart/form-data" \
  -F "file=@test_document.pdf" \
  -F "title=Security Test Document" \
  -F "description=Testing file validation, MIME type checking, and safe filename generation" \
  -F "date=2024-12-19" \
  -v
```

### 5. Fresh Analysis Test (Tests new upload always gets fresh analysis)

```bash
# First upload
curl -X POST http://localhost:8080/api/awards-upload.php \
  -H "Content-Type: multipart/form-data" \
  -F "file=@international_program.pdf" \
  -F "title=International Education Program" \
  -F "description=First upload for fresh analysis testing" \
  -F "date=2024-12-19" \
  -v

# Second upload of same file (should get fresh analysis)
curl -X POST http://localhost:8080/api/awards-upload.php \
  -H "Content-Type: multipart/form-data" \
  -F "file=@international_program.pdf" \
  -F "title=International Education Program - Second Upload" \
  -F "description=Second upload to verify fresh analysis enforcement" \
  -F "date=2024-12-19" \
  -v
```

### 6. Error Handling Tests

#### Large File Test
```bash
curl -X POST http://localhost:8080/api/awards-upload.php \
  -H "Content-Type: multipart/form-data" \
  -F "file=@large_file.pdf" \
  -F "title=Large File Test" \
  -F "description=Testing file size limits" \
  -v
```

#### Invalid File Type Test
```bash
curl -X POST http://localhost:8080/api/awards-upload.php \
  -H "Content-Type: multipart/form-data" \
  -F "file=@malicious.txt" \
  -F "title=Invalid File Type Test" \
  -F "description=Testing MIME type validation" \
  -v
```

#### Missing File Test
```bash
curl -X POST http://localhost:8080/api/awards-upload.php \
  -H "Content-Type: multipart/form-data" \
  -F "title=Missing File Test" \
  -F "description=Testing error handling for missing files" \
  -v
```

### 7. Analysis API Test (Tests centralized thresholds)

```bash
curl -X POST http://localhost:8080/api/analyze-award.php \
  -H "Content-Type: multipart/form-data" \
  -F "award_file=@sample_award.pdf" \
  -F "award_name=Test Award Analysis" \
  -F "description=Testing analysis with centralized thresholds from awards-rules.json" \
  -v
```

### 8. Re-analysis Test (Tests cached vs fresh analysis distinction)

```bash
curl -X POST http://localhost:8080/api/analyze-award.php \
  -H "Content-Type: multipart/form-data" \
  -F "award_name=Existing Award" \
  -F "description=Re-analysis test" \
  -F "reanalyze=true" \
  -F "original_file_path=/path/to/existing/file.pdf" \
  -v
```

## Expected Response Format

All successful requests should return JSON with this structure:

```json
{
  "success": true,
  "message": "File uploaded and analyzed successfully",
  "award_id": "unique_id",
  "analysis": [
    {
      "title": "Award Name",
      "category": "Category Name",
      "score": 85.5,
      "status": "Partially Eligible",
      "matched_keywords": ["keyword1", "keyword2"]
    }
  ],
  "eligible_count": 2,
  "partial_count": 1,
  "not_eligible_count": 0,
  "total_count": 3
}
```

## Error Response Format

Error responses should follow this format:

```json
{
  "error": "Clear error message describing what went wrong"
}
```

## Validation Checklist

For each test, verify:

1. **PDF Tests**: 
   - Response shows successful text extraction
   - Check logs for pdftotext usage or fallback warnings
   - No shell injection vulnerabilities

2. **DOCX Tests**:
   - Text properly extracted from XML structure
   - Logs show ZipArchive usage or fallback messages
   - Proper handling of complex document structures

3. **OCR Tests**:
   - Clear dependency checking
   - Proper error messages if Tesseract missing
   - Safe command execution with proper escaping

4. **Security Tests**:
   - File validation works correctly
   - Random filename generation
   - Proper MIME type checking

5. **Analysis Tests**:
   - Thresholds loaded from `awards-rules.json`
   - Fresh analysis for new uploads
   - Proper status determination based on centralized thresholds

## Debug Commands

Check server logs:
```bash
tail -f logs/activity.log
```

Verify file uploads:
```bash
ls -la uploads/awards/
```

Check configuration:
```bash
curl http://localhost:8080/api/awards-stats.php
```
