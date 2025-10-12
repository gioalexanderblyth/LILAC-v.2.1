# Award Analysis Feature Documentation

## Overview

The Award Analysis feature allows users to upload award certificates (PDF, DOCX, JPG, PNG) and automatically analyze them against predefined award criteria to determine eligibility and provide recommendations.

## Features

### 🎯 Core Functionality
- **File Upload**: Support for PDF, DOCX, JPG, PNG files up to 10MB
- **OCR Text Extraction**: Automatic text extraction from images and scanned PDFs using Tesseract OCR
- **Award Matching**: Intelligent matching against ICONS Awards 2025 criteria dataset
- **Score Calculation**: Percentage-based scoring system with weighted criteria
- **Dynamic Results**: Real-time analysis results with visual progress indicators

### 📊 Analysis Results
- **Eligibility Status**: ✅ Eligible / ⚠️ Partially Met / ❌ Not Met
- **Score Visualization**: Progress bars showing match confidence
- **Criteria Breakdown**: Detailed list of matched and unmatched criteria
- **Recommendations**: AI-generated suggestions for improving eligibility
- **Extracted Text Preview**: Transparent display of OCR-extracted content

### 📁 Export Capabilities
- **PDF Reports**: Professional formatted reports with analysis results
- **DOCX Export**: Word document format for further editing
- **Text Export**: Plain text extraction for reference

## Technical Implementation

### Backend Components

#### API Endpoints
- `POST /api/analyze-award.php` - Main analysis endpoint
- `POST /api/export-analysis.php` - Export functionality

#### Database Schema
```sql
CREATE TABLE award_analysis (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    file_name TEXT,
    file_path TEXT,
    detected_text TEXT,
    analysis_results TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

#### File Processing
- **PDF**: Direct text extraction from text-based PDFs
- **DOCX**: XML parsing to extract document content
- **Images**: Tesseract OCR for text recognition
- **File Validation**: Type, size, and security checks

### Frontend Components

#### User Interface
- **Drag & Drop Upload**: Intuitive file upload with visual feedback
- **Progress Indicators**: Real-time upload and processing status
- **Results Display**: Dynamic cards showing award analysis
- **Modal Views**: Full-text preview and export options

#### JavaScript Functions
- `initializeAwardAnalysisForm()` - Form setup and event handlers
- `handleFormSubmission()` - API communication and progress tracking
- `displayAnalysisResults()` - Dynamic result rendering
- `exportAnalysis()` - Export functionality

## Award Criteria Dataset

The system uses the official ICONS Awards 2025 dataset (`assets/awards-criteria.json`) with 8 award categories:

### Institutional Awards
1. **Global Citizenship Award** - Intercultural understanding and student empowerment
2. **Outstanding International Education Program Award** - Accessibility and collaborative innovation
3. **Sustainability Award** - Integration of sustainability and UN SDGs
4. **Best ASEAN Awareness Initiative Award** - ASEAN identity and regional cooperation

### Individual Awards
5. **Emerging Leadership Award** - Innovation in internationalization and empowering leadership
6. **Internationalization Leadership Award** - Strategic vision and transformational leadership

### Special Awards
7. **Best CHED Regional Office for Internationalization Award** - Leadership in IZN promotion
8. **Most Promising Regional IRO Community Award** - Visionary leadership and collaboration

### Scoring Algorithm
- **Criteria Matching**: Exact, partial, and semantic matching against official criteria
- **Type-Based Weighting**: Individual (1.1x), Institutional (1.0x), Special (0.9x) awards
- **Semantic Analysis**: Advanced keyword matching with synonyms and related terms
- **Threshold System**: 80%+ (Eligible), 60-79% (Partially Eligible), <60% (Not Eligible)
- **Minimum Relevance**: Only awards with >25% match are included in results

## Setup Requirements

### Server Requirements
- PHP 7.4+ with PDO SQLite support
- Tesseract OCR installation
- File upload permissions
- 10MB+ file upload limit

### Installation Steps
1. Install Tesseract OCR (see `docs/OCR_SETUP.md`)
2. Ensure upload directories have write permissions
3. Configure database connection in `api/config.php`
4. Test file upload and OCR functionality

## Usage Workflow

1. **Upload Document**: User drags/drops or selects award certificate
2. **Text Extraction**: System extracts text using OCR or direct parsing
3. **Criteria Matching**: Text is analyzed against award criteria dataset
4. **Score Calculation**: Weighted scoring system determines eligibility
5. **Results Display**: Dynamic UI shows analysis results with recommendations
6. **Export Options**: User can export results in multiple formats

## Error Handling

- **File Validation**: Type, size, and security checks
- **OCR Failures**: Graceful fallback and error messaging
- **API Errors**: User-friendly error messages with retry options
- **Database Errors**: Transaction rollback and error logging

## Security Considerations

- **File Upload Security**: Type validation and size limits
- **SQL Injection Prevention**: Prepared statements and parameterized queries
- **XSS Protection**: Input sanitization and output escaping
- **File Storage**: Secure upload directory with restricted access

## Performance Optimizations

- **Lazy Loading**: Results loaded dynamically
- **Caching**: Award criteria cached for faster access
- **Progress Feedback**: Real-time progress indicators
- **Efficient Queries**: Optimized database queries

## Future Enhancements

- **Machine Learning**: Improved text analysis with ML models
- **Cloud OCR**: Integration with cloud OCR services
- **Batch Processing**: Multiple file analysis
- **Advanced Filtering**: More sophisticated criteria matching
- **API Integration**: External award database connections
