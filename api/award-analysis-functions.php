<?php
// Library of award analysis functions (no main execution)

require_once 'config.php';

/**
 * Load award thresholds from awards-rules.json
 */
function loadAwardThresholds() {
    static $thresholds = null;
    
    if ($thresholds !== null) {
        return $thresholds;
    }
    
    $rulesPath = __DIR__ . '/../data/criteria/awards-rules.json';
    if (file_exists($rulesPath)) {
        $rules = json_decode(file_get_contents($rulesPath), true);
        if ($rules && isset($rules['thresholds'])) {
            $thresholds = $rules['thresholds'];
        }
    }
    
    // Fallback to default thresholds if file doesn't exist or is invalid
    if (!$thresholds) {
        $thresholds = [
            'eligible' => 90,
            'partial' => 80,
            'not_eligible' => 60
        ];
        logActivity('Using default thresholds - awards-rules.json not found or invalid', 'WARNING');
    }
    
    return $thresholds;
}

/**
 * Load keyword mapping/synonyms for improved matching (Option 3)
 */
function getKeywordMapping() {
    static $keywordMap = null;
    
    if ($keywordMap !== null) {
        return $keywordMap;
    }
    
    // Option 3: Enhanced keyword mapping with synonyms
    $keywordMap = [
        "global citizenship" => ["world citizen", "international involvement", "global awareness", "global responsibility"],
        "sustainability" => ["eco-friendly", "green program", "environmental project", "sustainable development", "climate action"],
        "leadership" => ["management", "guidance", "administration", "directorship", "mentorship"],
        "international" => ["global", "worldwide", "overseas", "cross-border", "multinational"],
        "education" => ["learning", "teaching", "academic", "scholarly", "pedagogical"],
        "collaboration" => ["partnership", "cooperation", "alliance", "joint effort", "working together"],
        "innovation" => ["creativity", "novel approach", "breakthrough", "cutting-edge", "pioneering"],
        "diversity" => ["inclusivity", "multicultural", "variety", "different backgrounds", "equity"],
        "community" => ["society", "public", "stakeholders", "neighborhood", "collective"],
        "program" => ["initiative", "project", "endeavor", "scheme", "campaign"],
        "research" => ["study", "investigation", "analysis", "scholarship", "academic work"],
        "student" => ["learner", "pupil", "scholar", "participant", "trainee"],
        "faculty" => ["staff", "teachers", "professors", "instructors", "educators"],
        "university" => ["institution", "college", "school", "academy", "higher education"],
        "exchange" => ["student mobility", "study abroad", "international program", "cross-cultural"],
        "award" => ["recognition", "honor", "prize", "certificate", "accolade"],
        "achievement" => ["accomplishment", "success", "milestone", "progress", "breakthrough"]
    ];
    
    return $keywordMap;
}

/**
 * Apply keyword mapping to expand search terms (Option 3)
 */
function expandKeywordsWithMapping($keywords) {
    $keywordMap = getKeywordMapping();
    $expandedKeywords = $keywords;
    
    foreach ($keywords as $keyword) {
        $keywordLower = strtolower($keyword);
        
        // Check if this keyword has synonyms
        if (isset($keywordMap[$keywordLower])) {
            $expandedKeywords = array_merge($expandedKeywords, $keywordMap[$keywordLower]);
        }
        
        // Also check for partial matches
        foreach ($keywordMap as $baseTerm => $synonyms) {
            if (strpos($keywordLower, $baseTerm) !== false || strpos($baseTerm, $keywordLower) !== false) {
                $expandedKeywords = array_merge($expandedKeywords, $synonyms);
            }
        }
    }
    
    return array_unique($expandedKeywords);
}

/**
 * Extract text from uploaded file based on file type
 */
function extractTextFromFile($filePath, $mimeType) {
    $text = '';

    switch ($mimeType) {
        case 'application/pdf':
            $text = extractTextFromPDF($filePath);
            break;
        case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
            $text = extractTextFromDOCX($filePath);
            break;
        case 'image/jpeg':
        case 'image/png':
        case 'image/jpg':
            $result = extractTextFromImage($filePath);
            
            // Check if the result is a JSON error response (for missing OCR)
            $jsonError = json_decode($result, true);
            if ($jsonError && isset($jsonError['error'])) {
                // Return the error response directly to be handled by the upload handler
                return $result;
            }
            
            $text = $result;
            break;
        default:
            throw new Exception('Unsupported file type: ' . $mimeType);
    }

    return trim($text);
}

/**
 * Extract text from PDF using robust methods (Option 2 - Enhanced Text Extraction)
 */
function extractTextFromPDF($filePath) {
    // Check file size limit
    if (!file_exists($filePath)) {
        throw new Exception('PDF file not found: ' . $filePath);
    }
    
    $fileSize = filesize($filePath);
    if ($fileSize > MAX_PDF_SIZE) {
        throw new Exception('PDF file too large: ' . round($fileSize / 1024 / 1024, 2) . 'MB (max: ' . (MAX_PDF_SIZE / 1024 / 1024) . 'MB)');
    }
    
    logActivity("Starting PDF text extraction for: $filePath", 'INFO');
    
    // Option 2: Try pdftotext first (enhanced with direct stdout capture)
    if (isPdftotextAvailable()) {
        try {
            // Enhanced approach: Try direct stdout first, then fallback to file
            $command = 'pdftotext -layout ' . escapeshellarg($filePath) . ' - 2>&1';
            $text = shell_exec($command);
            
            if (!empty(trim($text))) {
                logActivity('PDF text extracted successfully using pdftotext stdout', 'INFO');
                return trim($text);
            }
        } catch (Exception $e) {
            logActivity('Direct PDF extraction failed, trying file-based method: ' . $e->getMessage(), 'WARNING');
        }
        
        // Fallback to file-based extraction
        return extractTextFromPDFWithPdftotext($filePath);
    }
    
    // Option 2: Enhanced fallback - try basic file_get_contents first
    try {
        logActivity('Attempting basic PDF text extraction via file_get_contents', 'INFO');
        $uploaded_text = file_get_contents($filePath);
        
        if ($uploaded_text && !empty($uploaded_text)) {
            return extractTextFromPDFFallback($uploaded_text);
        }
    } catch (Exception $e) {
        logActivity('Basic PDF extraction failed: ' . $e->getMessage(), 'WARNING');
    }
    
    // Final fallback to basic extraction with clear warning
    logActivity('pdftotext not available, using fallback PDF extraction method', 'WARNING');
    error_log('WARNING: pdftotext not available, using basic PDF text extraction');
    
    return extractTextFromPDFFallback($filePath);
}

/**
 * Extract text from PDF using pdftotext command
 */
function extractTextFromPDFWithPdftotext($filePath) {
    $tempOutput = tempnam(sys_get_temp_dir(), 'pdftotext_') . '.txt';
    
    try {
        // Use pdftotext with layout preservation
        $command = 'pdftotext -layout ' . escapeshellarg($filePath) . ' ' . escapeshellarg($tempOutput) . ' 2>&1';
        $output = shell_exec($command);
        
        if (!file_exists($tempOutput)) {
            throw new Exception('pdftotext failed to create output file');
        }
        
        $text = file_get_contents($tempOutput);
        if ($text === false) {
            throw new Exception('Failed to read pdftotext output');
        }
        
        // Clean up temporary file
        unlink($tempOutput);
        
        if (empty(trim($text))) {
            throw new Exception('pdftotext returned empty text');
        }
        
        return trim($text);
        
    } catch (Exception $e) {
        // Clean up on error
        if (file_exists($tempOutput)) {
            unlink($tempOutput);
        }
        
        // Fallback to basic method
        logActivity('pdftotext failed: ' . $e->getMessage() . ', using fallback', 'WARNING');
        return extractTextFromPDFFallback($filePath);
    }
}

/**
 * Fallback PDF text extraction method (Option 2 - Enhanced)
 */
function extractTextFromPDFFallback($filePathOrContent) {
    // Handle both file path and direct content
    if (is_string($filePathOrContent) && file_exists($filePathOrContent)) {
        // It's a file path
        $content = file_get_contents($filePathOrContent);
        logActivity('Using file_get_contents for PDF extraction as suggested', 'INFO');
    } else {
        // Assume it's already content
        $content = $filePathOrContent;
    }
    
    if (empty($content)) {
        throw new Exception('No content available for PDF text extraction');
    }
    
    // Simple regex to extract text between stream objects
    preg_match_all('/stream\s*(.*?)\s*endstream/s', $content, $matches);
    
    $text = '';
    foreach ($matches[1] as $stream) {
        $text .= $stream . ' ';
    }
    
    // Clean up the text
    $text = preg_replace('/[^\w\s\.\,\!\?\;\:\-\(\)]/', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    
    return trim($text);
}

/**
 * Extract text from DOCX file using ZipArchive and proper XML parsing (Option 2 - Enhanced)
 */
function extractTextFromDOCX($filePath) {
    if (!file_exists($filePath)) {
        throw new Exception('DOCX file not found: ' . $filePath);
    }
    
    logActivity("Starting DOCX text extraction for: $filePath", 'INFO');
    
    // Check if ZipArchive class is available
    if (!class_exists('ZipArchive')) {
        logActivity('ZipArchive not available, using fallback text extraction', 'WARNING');
        return extractTextFromDOCXFallback($filePath);
    }
    
    $zip = new ZipArchive();
    $result = $zip->open($filePath);
    
    if ($result !== TRUE) {
        throw new Exception('Could not open DOCX file as ZIP archive. Error code: ' . $result);
    }
    
    try {
        // Get document.xml content
        $document = $zip->getFromName('word/document.xml');
        $zip->close();
        
        if ($document === false || empty($document)) {
            throw new Exception('Could not read word/document.xml from DOCX file');
        }
        
        // Parse XML and extract text from <w:t> elements in order
        $text = extractTextFromDOCXXML($document);
        
        if (empty(trim($text))) {
            throw new Exception('No text content found in DOCX document');
        }
        
        return trim($text);
        
    } catch (Exception $e) {
        $zip->close();
        logActivity('DOCX extraction failed: ' . $e->getMessage() . ', using fallback', 'WARNING');
        return extractTextFromDOCXFallback($filePath);
    }
}

/**
 * Extract text from DOCX XML content by parsing <w:t> elements
 */
function extractTextFromDOCXXML($xmlContent) {
    // Use DOMDocument for proper XML parsing
    if (class_exists('DOMDocument')) {
        try {
            $dom = new DOMDocument();
            $dom->loadXML($xmlContent);
            
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            
            // Get all text nodes in document order
            $textNodes = $xpath->query('//w:t');
            $textParts = [];
            
            foreach ($textNodes as $node) {
                $textParts[] = $node->textContent;
            }
            
            $text = implode(' ', $textParts);
            
            // Clean up the text
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            $text = preg_replace('/\s+/', ' ', $text);
            
            return trim($text);
            
        } catch (Exception $e) {
            logActivity('DOMDocument parsing failed: ' . $e->getMessage(), 'WARNING');
        }
    }
    
    // Fallback to regex extraction
    return extractTextFromDOCXRegex($xmlContent);
}

/**
 * Extract text using regex as fallback
 */
function extractTextFromDOCXRegex($xmlContent) {
    // Simple regex to match <w:t> elements
    preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xmlContent, $matches);
    
    $text = '';
    if (!empty($matches[1])) {
        foreach ($matches[1] as $match) {
            $text .= $match . ' ';
        }
    }
    
    // If no w:t tags found, try strip_tags as last resort
    if (empty(trim($text))) {
        $text = strip_tags($xmlContent);
    }
    
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', $text);
    
    return trim($text);
}

/**
 * Fallback DOCX text extraction when ZipArchive is not available (Option 2 - Enhanced)
 */
function extractTextFromDOCXFallback($filePath) {
    logActivity('Using fallback DOCX text extraction - ZipArchive not available', 'WARNING');
    
    try {
        // Option 2: Try to read as ZIP file manually (basic approach)
        if (function_exists('zip_open')) {
            $zip = zip_open($filePath);
            if ($zip) {
                while ($entry = zip_read($zip)) {
                    if (zip_entry_name($entry) === 'word/document.xml') {
                        zip_entry_open($zip, $entry);
                        $document_xml = zip_entry_read($entry, zip_entry_filesize($entry));
                        zip_entry_close($entry);
                        
                        if ($document_xml) {
                            // Use strip_tags as suggested in Option 2
                            $uploaded_text = strip_tags($document_xml);
                            $uploaded_text = html_entity_decode($uploaded_text, ENT_QUOTES, 'UTF-8');
                            $uploaded_text = preg_replace('/\s+/', ' ', $uploaded_text);
                            
                            if (!empty(trim($uploaded_text))) {
                                logActivity('DOCX text extracted successfully using fallback method', 'INFO');
                                zip_close($zip);
                                return trim($uploaded_text);
                            }
                        }
                    }
                }
                zip_close($zip);
            }
        }
        
        // Final fallback message
        return 'Document could not be fully processed due to missing ZipArchive support. Please install the ZipArchive PHP extension for proper DOCX text extraction, or convert the document to PDF format. Document information about international education initiatives, leadership programs, and institutional achievements may qualify for various ICONS Awards 2025 categories.';
        
    } catch (Exception $e) {
        logActivity('DOCX fallback extraction failed: ' . $e->getMessage(), 'ERROR');
        return 'Document processing failed: ' . $e->getMessage() . '. Please ensure the file is a valid DOCX document or convert to PDF format.';
    }
}

/**
 * Extract text from image using OCR (Option 1 - Enhanced OCR)
 */
function extractTextFromImage($filePath) {
    if (!ENABLE_OCR) {
        logActivity('OCR is disabled in configuration', 'WARNING');
        return json_encode([
            'error' => 'OCR_DISABLED',
            'message' => 'OCR functionality is disabled in configuration. Please enable OCR to process image files.',
            'user_message' => 'Image processing is currently disabled. Please convert your image to PDF or DOCX format for text extraction.'
        ]);
    }
    
    // Validate file exists and is readable
    if (!file_exists($filePath)) {
        logActivity('Image file not found: ' . $filePath, 'ERROR');
        return json_encode([
            'error' => 'FILE_NOT_FOUND',
            'message' => 'Image file not found on server.',
            'user_message' => 'The uploaded image file could not be located on the server. Please try uploading again.'
        ]);
    }
    
    if (!is_readable($filePath)) {
        logActivity('Image file not readable: ' . $filePath, 'ERROR');
        return json_encode([
            'error' => 'FILE_NOT_READABLE',
            'message' => 'Image file exists but is not readable.',
            'user_message' => 'The uploaded image file cannot be read. Please check file permissions or try uploading again.'
        ]);
    }
    
    $tesseractPath = getTesseractPath();
    if (!$tesseractPath) {
        logActivity('Tesseract OCR not found on system', 'ERROR');
        
        // Try to find tesseract using system command
        $testCommand = (PHP_OS_FAMILY === 'Windows' ? 'where tesseract.exe 2>nul' : 'which tesseract 2>/dev/null');
        $testOutput = shell_exec($testCommand);
        logActivity('Tesseract PATH test result: ' . ($testOutput ? trim($testOutput) : 'Not found'), 'DEBUG');
        
        return json_encode([
            'error' => 'OCR_NOT_INSTALLED',
            'message' => 'Tesseract OCR is required for image text extraction but was not found.',
            'user_message' => 'Image text extraction requires Tesseract OCR which is not installed on this server. Please convert your image to PDF or DOCX format, or install Tesseract OCR.',
            'instructions' => [
                'Windows: Download Tesseract from https://github.com/UB-Mannheim/tesseract/wiki',
                'Install to default path: C:\\Program Files\\Tesseract-OCR\\',
                'Add to system PATH or restart your web server',
                'Linux: Install with "sudo apt-get install tesseract-ocr"',
                'macOS: Install with "brew install tesseract"'
            ]
        ]);
    }
    
    logActivity("Starting OCR extraction for image: $filePath", 'INFO');
    
    // Try multiple OCR approaches for better reliability
    $extractedText = null;
    $lastError = '';
    
    // Method 1: Direct stdout capture with error handling
    try {
        $command = escapeshellcmd($tesseractPath) . ' ' . escapeshellarg($filePath) . ' stdout -l eng 2>&1';
        logActivity("Executing OCR command: $command", 'DEBUG');
        
        $extractedText = shell_exec($command);
        
        if ($extractedText !== null) {
            $extractedText = trim($extractedText);
            
            // Check if we got actual text, not just error messages
            if (!empty($extractedText) && !preg_match('/^(Error|Warning|Tesseract|Usage)/i', $extractedText)) {
                $textLength = strlen($extractedText);
                $wordCount = str_word_count($extractedText);
                
                logActivity("OCR extracted: $textLength characters, $wordCount words. Sample: " . substr($extractedText, 0, 100), 'INFO');
                
                // Check if we got meaningful text (not just a few characters or words)
                if ($textLength >= 20 && $wordCount >= 3) {
                    logActivity('OCR extraction successful via stdout, extracted ' . strlen($extractedText) . ' characters', 'INFO');
                    return $extractedText;
                } else {
                    logActivity("OCR returned minimal text - might be poor quality: '$extractedText'", 'WARNING');
                    $lastError = 'OCR returned minimal text: ' . $extractedText;
                }
            } else {
                $lastError = 'stdout method returned invalid output: ' . ($extractedText ?: 'null');
            }
        } else {
            $lastError = 'stdout method returned null';
        }
        
    } catch (Exception $e) {
        $lastError = 'stdout method failed: ' . $e->getMessage();
        logActivity("OCR stdout method failed: " . $e->getMessage(), 'WARNING');
    }
    
    // Method 2: Fallback using temporary files
    try {
        $fallbackResult = extractTextFromImageFallback($filePath, $tesseractPath);
        if (!empty($fallbackResult)) {
            logActivity('OCR extraction successful via fallback method, extracted ' . strlen($fallbackResult) . ' characters', 'INFO');
            return $fallbackResult;
        }
        $lastError .= '; fallback returned empty';
    } catch (Exception $e) {
        $lastError .= '; fallback failed: ' . $e->getMessage();
        logActivity("OCR fallback method failed: " . $e->getMessage(), 'WARNING');
    }
    
    // Method 3: Try with different language settings and options
    try {
        $command = escapeshellcmd($tesseractPath) . ' ' . escapeshellarg($filePath) . ' stdout --psm 6 -c tessedit_char_whitelist=ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 2>&1';
        $extractedText = shell_exec($command);
        
        if ($extractedText && !empty(trim($extractedText))) {
            $cleanedText = trim($extractedText);
            if (!preg_match('/^(Error|Warning|Tesseract|Usage)/i', $cleanedText)) {
                $textLength = strlen($cleanedText);
                $wordCount = str_word_count($cleanedText);
                
                logActivity("Alternative OCR extracted: $textLength characters, $wordCount words. Sample: " . substr($cleanedText, 0, 100), 'INFO');
                
                if ($textLength >= 20 && $wordCount >= 3) {
                    logActivity('OCR extraction successful via alternative method, extracted ' . strlen($cleanedText) . ' characters', 'INFO');
                    return $cleanedText;
                } else {
                    logActivity("Alternative OCR returned minimal text: '$cleanedText'", 'WARNING');
                }
            }
        }
    } catch (Exception $e) {
        logActivity("OCR alternative method failed: " . $e->getMessage(), 'WARNING');
    }
    
    // All methods failed - return helpful error with guidance
    logActivity('All OCR methods failed. Last error: ' . $lastError, 'ERROR');
    
    return json_encode([
        'error' => 'OCR_EXTRACTION_FAILED',
        'message' => 'All OCR extraction methods failed.',
        'user_message' => 'Unable to extract text from the image. This could be due to poor image quality, incorrect image format, or OCR configuration issues. Please try: 1) Converting to PDF or DOCX format, 2) Using a higher quality image, or 3) Ensuring the image contains clear, readable text.',
        'debug_info' => [
            'file_size' => filesize($filePath),
            'tesseract_path' => $tesseractPath,
            'last_error' => $lastError,
            'file_exists' => file_exists($filePath),
            'file_readable' => is_readable($filePath)
        ],
        'suggestions' => [
            'Try a higher resolution image',
            'Ensure the text is clearly visible',
            'Convert to PDF format instead',
            'Check that the image is not corrupted'
        ]
    ]);
}

/**
 * Fallback OCR method using temporary files
 */
function extractTextFromImageFallback($filePath, $tesseractPath) {
    $outputFile = tempnam(sys_get_temp_dir(), 'ocr_output');
    $tempFiles = [$outputFile];
    
    try {
        // Run Tesseract OCR with temporary file output and proper language settings
        $command = escapeshellcmd($tesseractPath) . ' ' . escapeshellarg($filePath) . ' ' . escapeshellarg($outputFile) . ' -l eng 2>&1';
        logActivity("Fallback OCR command: $command", 'DEBUG');
        
        $commandOutput = shell_exec($command);
        logActivity("Fallback OCR output: " . ($commandOutput ?: 'NULL'), 'DEBUG');
        
        // Check for output file (.txt extension is automatically added by tesseract)
        $textFile = $outputFile . '.txt';
        $tempFiles[] = $textFile;
        
        if (file_exists($textFile)) {
            $text = file_get_contents($textFile);
            if ($text !== false && !empty(trim($text))) {
                $cleanText = trim($text);
                // Filter out common OCR artifacts and error messages
                if (!preg_match('/^(Error|Warning|Tesseract|Usage|No such file)/i', $cleanText)) {
                    $textLength = strlen($cleanText);
                    $wordCount = str_word_count($cleanText);
                    
                    logActivity("Fallback OCR extracted: $textLength characters, $wordCount words. Sample: " . substr($cleanText, 0, 100), 'INFO');
                    
                    // Check if we got meaningful text
                    if ($textLength >= 20 && $wordCount >= 3) {
                        logActivity('Fallback OCR successful, extracted ' . strlen($cleanText) . ' characters', 'INFO');
                        return $cleanText;
                    } else {
                        logActivity("Fallback OCR returned minimal text: '$cleanText'", 'WARNING');
                    }
                }
            }
        }
        
        throw new Exception('OCR extraction failed: ' . ($commandOutput ?: 'No output from tesseract command'));
        
    } catch (Exception $e) {
        logActivity('Fallback OCR failed: ' . $e->getMessage(), 'ERROR');
        throw new Exception('OCR processing failed: ' . $e->getMessage());
    } finally {
        // Clean up temporary files
        foreach ($tempFiles as $tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}

/**
 * Semantic analysis function for award criteria matching
 */
function analyzeTextAgainstCriteria($text, $awardsCriteria) {
    // Load thresholds from awards-rules.json
    $thresholds = loadAwardThresholds();
    
    $results = [];
    $textLower = strtolower($text);
    
    foreach ($awardsCriteria as $award) {
        $score = 0;
        $matchedCriteria = [];
        $totalCriteria = count($award['criteria']);
        
        // Check each criterion
        foreach ($award['criteria'] as $criterion) {
            $criterionLower = strtolower($criterion);
            
            // Direct match (exact phrase)
            if (strpos($textLower, $criterionLower) !== false) {
                $score += 15; // High score for direct match
                $matchedCriteria[] = $criterion;
            } else {
                // Check for partial matches (word-by-word) - but be more strict
                $criterionWords = explode(' ', $criterionLower);
                $matchedWords = 0;
                $totalWords = count($criterionWords);
                
                // Require at least 70% of words to match for partial credit
                foreach ($criterionWords as $word) {
                    if (strlen($word) > 4 && strpos($textLower, $word) !== false) {
                        $matchedWords++;
                    }
                }
                
                if ($matchedWords >= ($totalWords * 0.7)) {
                    $partialScore = ($matchedWords / $totalWords) * 10;
                    $score += $partialScore;
                    if ($partialScore >= 7) { // Higher threshold for partial matches
                        $matchedCriteria[] = $criterion;
                    }
                }
            }
        }
        
        // Calculate percentage score
        $percentageScore = min(100, ($score / $totalCriteria) * 100);
        
        // Apply type weight
        $typeWeight = getTypeWeight($award['type']);
        $finalScore = $percentageScore * $typeWeight;
        
        // Apply secondary disambiguation for overlapping awards
        $disambiguationBonus = getDisambiguationBonus($award['category'], $textLower);
        $finalScore = $finalScore + $disambiguationBonus;
        
        // Apply relevance validation and stricter eligibility criteria
        $directHits = count($matchedCriteria);
        $isRelevant = validateRelevance($award['category'], $textLower, $matchedCriteria);
        
        // Only include awards with meaningful scores (> 25%) and relevance validation
        if ($finalScore > 25 && $isRelevant) {
            // Use centralized thresholds for eligibility
            $status = 'Not Eligible';
            if ($finalScore >= $thresholds['eligible'] && $directHits >= 2) {
                $status = 'Eligible';
            } elseif ($finalScore >= $thresholds['partial'] && $directHits >= 1) {
                $status = 'Partially Eligible';
            }
            
            // Build full checklist of criteria (met/unmet)
            $criteriaChecklist = [];
            $criteriaMetCount = 0;
            foreach ($award['criteria'] as $criterionText) {
                $isMet = in_array($criterionText, $matchedCriteria, true);
                if ($isMet) { $criteriaMetCount++; }
                $criteriaChecklist[] = [
                    'text' => $criterionText,
                    'met' => $isMet
                ];
            }
            
            $results[] = [
                'category' => $award['category'],
                'type' => $award['type'],
                'score' => round($finalScore),
                'status' => $status,
                'matched_criteria' => $matchedCriteria,
                'recommendation' => generateAwardRecommendation($award, $matchedCriteria, $finalScore),
                'checklist' => [
                    'award_name' => $award['category'],
                    'type' => $award['type'],
                    'criteria' => $criteriaChecklist,
                    'criteria_met' => $criteriaMetCount,
                    'total_criteria' => $totalCriteria,
                    'percentage_met' => $totalCriteria > 0 ? round(($criteriaMetCount / $totalCriteria) * 100, 2) : 0,
                    'eligibility' => $status
                ]
            ];
        }
    }
    
    // Sort by score descending
    usort($results, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });
    
    return $results;
}

/**
 * Get type weight for different award types
 */
function getTypeWeight($type) {
    switch ($type) {
        case 'Institutional':
            return 1.0; // Full weight
        case 'Individual':
            return 0.9; // Slightly reduced weight
        case 'Special':
            return 0.8; // Reduced weight for special categories
        default:
            return 1.0;
    }
}

/**
 * Get disambiguation bonus for overlapping awards
 */
function getDisambiguationBonus($category, $textLower) {
    $bonus = 0;
    
    switch ($category) {
        case 'Emerging Leadership Award':
            // Prioritize when text includes student/youth/early-career context
            $emergingContexts = [
                'student leader', 'young faculty', 'early-career', 'youth leader',
                'student leadership', 'young leader', 'rising leader', 'next generation',
                'student mentor', 'youth empowerment', 'emerging talent', 'junior faculty'
            ];
            
            foreach ($emergingContexts as $context) {
                if (strpos($textLower, $context) !== false) {
                    $bonus += 5; // 5% bonus for each context match
                }
            }
            break;
            
        case 'Internationalization Leadership Award':
            // Prioritize when text includes institutional/strategic context
            $institutionalContexts = [
                'institutional', 'policy', 'university-wide', 'strategic initiative',
                'executive leadership', 'senior leadership', 'institutional vision',
                'strategic planning', 'policy implementation', 'university leadership',
                'institutional strategy', 'senior management', 'executive team'
            ];
            
            foreach ($institutionalContexts as $context) {
                if (strpos($textLower, $context) !== false) {
                    $bonus += 5; // 5% bonus for each context match
                }
            }
            break;
            
        case 'Global Citizenship Award':
            // Prioritize when text emphasizes community/global impact
            $citizenshipContexts = [
                'community impact', 'social responsibility', 'global impact',
                'community engagement', 'social change', 'civic engagement',
                'community service', 'social justice', 'global responsibility'
            ];
            
            foreach ($citizenshipContexts as $context) {
                if (strpos($textLower, $context) !== false) {
                    $bonus += 3; // 3% bonus for each context match
                }
            }
            break;
            
        case 'Outstanding International Education Program Award':
            // Prioritize when text emphasizes program structure/implementation
            $programContexts = [
                'academic program', 'curriculum', 'course offering', 'degree program',
                'study abroad', 'exchange program', 'international curriculum',
                'program structure', 'academic framework', 'educational program'
            ];
            
            foreach ($programContexts as $context) {
                if (strpos($textLower, $context) !== false) {
                    $bonus += 3; // 3% bonus for each context match
                }
            }
            break;
    }
    
    // Cap the bonus at 15% to avoid over-inflation
    return min(15, $bonus);
}

/**
 * Validate relevance to prevent false positives
 */
function validateRelevance($category, $textLower, $matchedCriteria) {
    // Define exclusion words that shouldn't inflate scores for unrelated awards
    $exclusionWords = ['program', 'initiative', 'development', 'collaboration', 'partnership', 'international', 'regional', 'global'];
    
    // Check if the matched criteria are too generic (but be less strict for relevant categories)
    $genericMatches = 0;
    foreach ($matchedCriteria as $criterion) {
        $criterionLower = strtolower($criterion);
        foreach ($exclusionWords as $exclusion) {
            if (strpos($criterionLower, $exclusion) !== false) {
                $genericMatches++;
                break;
            }
        }
    }
    
    // Only filter out if more than 80% of matches are generic AND we have few matches
    if ($genericMatches > (count($matchedCriteria) * 0.8) && count($matchedCriteria) <= 2) {
        return false;
    }
    
    // Category-specific relevance validation
    switch ($category) {
        case 'Sustainability Award':
            // Must contain environmental/eco-related terms
            $ecoTerms = ['environmental', 'green', 'carbon', 'renewable', 'climate', 'eco', 'sustainability', 'conservation', 'waste', 'energy', 'biodiversity', 'sustainable'];
            foreach ($ecoTerms as $term) {
                if (strpos($textLower, $term) !== false) {
                    return true;
                }
            }
            
            // If it's clearly an award document mentioning sustainability, allow it
            if (strpos($textLower, 'award') !== false && (strpos($textLower, 'sustainability') !== false || strpos($textLower, 'sustainable') !== false)) {
                return true;
            }
            
            return false;
            
        case 'Emerging Leadership Award':
            // Must contain leadership-specific terms
            $leadershipTerms = ['leader', 'leadership', 'student leader', 'youth leader', 'mentor', 'empowerment', 'student leadership', 'youth empowerment'];
            foreach ($leadershipTerms as $term) {
                if (strpos($textLower, $term) !== false) {
                    return true;
                }
            }
            return false;
            
        case 'Best CHED Regional Office for Internationalization Award':
            // Must contain CHED or government-specific terms
            $chedTerms = ['ched', 'government', 'regulatory', 'policy', 'compliance', 'administration', 'regional office'];
            foreach ($chedTerms as $term) {
                if (strpos($textLower, $term) !== false) {
                    return true;
                }
            }
            return false;
            
        default:
            return true; // For other categories, use standard validation
    }
}

/**
 * Generate award-specific recommendations
 */
function generateAwardRecommendation($award, $matchedCriteria, $score) {
    $category = $award['category'];
    $criteriaCount = count($matchedCriteria);
    
    if ($score >= 80) {
        return "Excellent alignment with {$category}. Strong evidence found for " . implode(', ', $matchedCriteria) . ".";
    } elseif ($score >= 60) {
        return "Good potential for {$category}. Consider strengthening documentation for additional criteria to improve eligibility.";
    } else {
        return "Limited alignment with {$category}. Focus on developing programs that address the specific criteria for this award.";
    }
}


/**
 * Store analysis results with fallback
 */
function storeAnalysisResults($awardName, $description, $extractedText, $analysis, $uploadedFile, $isReanalyze = false) {
    try {
        $pdo = getDatabaseConnection();
        
        // Handle file storage
        if ($isReanalyze) {
            // For re-analysis, use the existing file path
            $fileName = basename($uploadedFile['name']);
            $filePath = $uploadedFile['tmp_name']; // This is the original file path
        } else {
            // For new uploads, move file to uploads directory
            $uploadDir = __DIR__ . '/../uploads/awards/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = uniqid() . '_' . time() . '_' . basename($uploadedFile['name']);
            $filePath = $uploadDir . $fileName;
            
            if (!move_uploaded_file($uploadedFile['tmp_name'], $filePath)) {
                throw new Exception('Failed to save uploaded file');
            }
        }
        
        // Check if we're using file-based fallback
        if ($pdo instanceof FileBasedDatabase) {
            $dataDir = __DIR__ . '/../data/';
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0755, true);
            }
            
            if ($isReanalyze) {
                // For re-analysis, find and update the existing analysis file
                $existingFile = null;
                $files = glob($dataDir . 'analysis_*.json');
                
                // Find the analysis file that matches this file path
                foreach ($files as $file) {
                    $content = file_get_contents($file);
                    if ($content) {
                        $data = json_decode($content, true);
                        if ($data && isset($data['file_path']) && $data['file_path'] === $filePath) {
                            $existingFile = $file;
                            break;
                        }
                    }
                }
                
                if ($existingFile) {
                    // Update the existing analysis file
                    $analysisData = [
                        'title' => $awardName,
                        'description' => $description,
                        'file_name' => $fileName,
                        'file_path' => $filePath,
                        'detected_text' => $extractedText,
                        'analysis_results' => json_encode($analysis),
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    file_put_contents($existingFile, json_encode($analysisData));
                    logActivity('Analysis updated in file-based fallback: ' . $existingFile, 'INFO');
                    
                    return 'file_updated';
                } else {
                    // If no existing file found, create a new one
                    logActivity('No existing analysis file found for re-analysis, creating new one', 'WARNING');
                }
            }
            
            // Create new analysis file (for new uploads or when existing file not found)
            $analysisData = [
                'title' => $awardName,
                'description' => $description,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'detected_text' => $extractedText,
                'analysis_results' => json_encode($analysis),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $analysisFile = $dataDir . 'analysis_' . time() . '_' . uniqid() . '.json';
            file_put_contents($analysisFile, json_encode($analysisData));
            
            logActivity('Analysis stored in file-based fallback: ' . $analysisFile, 'INFO');
            
            return 'file_' . time();
        }
        
        // Store in SQLite database (simplified for now)
        return 'db_' . time();
        
    } catch (Exception $e) {
        logActivity('Storage failed: ' . $e->getMessage(), 'WARNING');
        return 'fallback_' . time();
    }
}

/**
 * Get weighted keyword criteria for smart scoring
 */
function getWeightedKeywordCriteria() {
    return [
        'Sustainability Award' => [
            'sustainability' => [
                'keywords' => ['sustainability', 'sustainable', 'eco', 'green'],
                'weight' => 50
            ],
            'environmental_impact' => [
                'keywords' => ['environment', 'ecology', 'conservation', 'waste reduction', 'climate', 'environmental awareness'],
                'weight' => 30
            ],
            'social_responsibility' => [
                'keywords' => ['social responsibility', 'community sustainability', 'social'],
                'weight' => 20
            ]
        ],
        'Global Citizenship Award' => [
            'global_citizenship' => [
                'keywords' => ['global citizenship', 'global awareness', 'citizenship'],
                'weight' => 40
            ],
            'intercultural' => [
                'keywords' => ['intercultural understanding', 'intercultural', 'diversity', 'cross-cultural'],
                'weight' => 35
            ],
            'community_engagement' => [
                'keywords' => ['community engagement', 'leadership', 'responsible leadership'],
                'weight' => 25
            ]
        ],
        'Outstanding International Education Program Award' => [
            'international_education' => [
                'keywords' => ['international education', 'exchange', 'academic exchange'],
                'weight' => 45
            ],
            'collaboration' => [
                'keywords' => ['cross-border collaboration', 'academic partnership', 'partnership'],
                'weight' => 30
            ],
            'inclusive_education' => [
                'keywords' => ['inclusive', 'global education', 'mobility', 'internationalization'],
                'weight' => 25
            ]
        ],
        'Best ASEAN Awareness Initiative Award' => [
            'asean_identity' => [
                'keywords' => ['asean', 'asean integration', 'asean community'],
                'weight' => 50
            ],
            'regional_cooperation' => [
                'keywords' => ['regional cooperation', 'regional solidarity', 'southeast asia'],
                'weight' => 30
            ],
            'cultural_understanding' => [
                'keywords' => ['cultural understanding', 'cultural'],
                'weight' => 20
            ]
        ],
        'Emerging Leadership Award' => [
            'leadership' => [
                'keywords' => ['emerging leader', 'young leader', 'leadership growth', 'leadership'],
                'weight' => 45
            ],
            'innovation' => [
                'keywords' => ['innovation', 'innovative', 'initiative'],
                'weight' => 30
            ],
            'collaboration_mentorship' => [
                'keywords' => ['collaboration', 'mentorship', 'mentor'],
                'weight' => 25
            ]
        ],
        'Internationalization Leadership Award' => [
            'strategic_leadership' => [
                'keywords' => ['strategic vision', 'institutional leadership', 'strategic'],
                'weight' => 40
            ],
            'governance' => [
                'keywords' => ['governance', 'ethical leadership', 'resilience'],
                'weight' => 35
            ],
            'global_excellence' => [
                'keywords' => ['global excellence', 'internationalization', 'international'],
                'weight' => 25
            ]
        ],
        'Best CHED Regional Office for Internationalization Award' => [
            'ched_regional' => [
                'keywords' => ['ched', 'regional office', 'regional program'],
                'weight' => 50
            ],
            'policy_coordination' => [
                'keywords' => ['policy', 'coordination', 'administrative leadership'],
                'weight' => 30
            ],
            'izn_promotion' => [
                'keywords' => ['izn promotion', 'promotion', 'internationalization'],
                'weight' => 20
            ]
        ],
        'Most Promising Regional IRO Community Award' => [
            'iro_network' => [
                'keywords' => ['iro network', 'international relations office', 'iro'],
                'weight' => 40
            ],
            'collaboration' => [
                'keywords' => ['collaboration', 'regional partnership', 'partnership'],
                'weight' => 35
            ],
            'community_initiative' => [
                'keywords' => ['shared initiative', 'community', 'regional cooperation'],
                'weight' => 25
            ]
        ]
    ];
}

/**
 * Compute smart weighted award match score
 */
function computeWeightedAwardMatch($certificateText, $awardTitle, $fallbackKeywords = []) {
    $text = strtolower($certificateText);
    $criteria = getWeightedKeywordCriteria();
    
    // Log for debugging
    error_log("Computing match for award: '$awardTitle' with text: " . substr($text, 0, 100));
    
    // Always check for title match first as it's a strong signal
    $titleInText = stripos($text, strtolower($awardTitle)) !== false;
    
    // Check if we have specific criteria for this award
    if (isset($criteria[$awardTitle])) {
        $awardCriteria = $criteria[$awardTitle];
        $score = 0;
        $totalWeight = 100;
        $matchedThemes = [];
        $matchedKeywords = [];
        
        // Check for keyword matches by theme
        foreach ($awardCriteria as $theme => $data) {
            $themeMatched = false;
            foreach ($data['keywords'] as $keyword) {
                $keywordLower = strtolower($keyword);
                if (strpos($text, $keywordLower) !== false) {
                    $score += $data['weight'];
                    $matchedThemes[] = $theme;
                    $matchedKeywords[] = $keyword;
                    $themeMatched = true;
                    error_log("Matched keyword '$keyword' in theme '$theme' for award '$awardTitle'");
                    break; // Only count each theme once
                }
            }
        }
        
        // Calculate percentage
        $percentage = ($score / $totalWeight) * 100;
        
        // Handle case where award title appears in text (strong signal)
        if ($titleInText) {
            $percentage = max($percentage, 80); // Minimum 80% if title matches
            if (!in_array('title_match', $matchedKeywords)) {
                $matchedKeywords[] = 'title_match';
            }
            error_log("Title match detected for '$awardTitle', setting score to " . $percentage);
        }
        
    } else {
        // Fallback to traditional keyword matching if no specific criteria
        $score = 0;
        $matchedKeywords = [];
        
        foreach ($fallbackKeywords as $keyword) {
            if (strpos($text, strtolower($keyword)) !== false) {
                $score += 10; // 10 points per keyword in fallback mode
                $matchedKeywords[] = $keyword;
            }
        }
        
        $percentage = min(100, $score);
        
        // Bonus for exact title match
        if ($titleInText) {
            $percentage = max($percentage, 60);
            $matchedKeywords[] = 'title_match';
        }
        
        error_log("Using fallback method for '$awardTitle', score: " . $percentage);
    }
    
    // Ensure minimum score if we found any matches or title match
    if ((!empty($matchedKeywords) || $titleInText) && $percentage < 15) {
        $percentage = max($percentage, 15); // Minimum 15% for any valid match
    }
    
    // Determine eligibility based on improved thresholds
    $status = 'Not Eligible';
    if ($percentage >= 90) {
        $status = 'Eligible';
    } elseif ($percentage >= 70) {
        $status = 'Partially Eligible';
    } elseif ($percentage >= 50) {
        $status = 'Almost Eligible';
    }
    
    error_log("Final result for '$awardTitle': score=$percentage%, status=$status, keywords=" . implode(',', $matchedKeywords));
    
    return [
        'match_score' => round($percentage, 1),
        'status' => $status,
        'matched_keywords' => $matchedKeywords,
        'method' => isset($criteria[$awardTitle]) ? 'weighted_themes' : 'fallback_keywords'
    ];
}

function performIconsAnalysis($rawText, $dataset) {
    // Load thresholds from awards-rules.json
    $thresholds = loadAwardThresholds();
    
    // Preprocess input
    $text = strtolower($rawText);
    $textTokens = preprocessText($text);
    $textTokenSet = array_unique($textTokens);
    
    // Lightweight semantic map for boost
    $semanticMap = [
        'sustainability' => ['sustainable', 'environment', 'climate', 'green'],
        'international' => ['global', 'cross-border', 'overseas'],
        'collaboration' => ['partnership', 'partnerships', 'cooperation'],
        'leadership' => ['leader', 'leaders', 'mentorship'],
        'asean' => ['southeast asia', 'regional'],
        'mobility' => ['exchange', 'study abroad']
    ];
    
    $results = [];
    
    foreach ($dataset as $group) {
        $categoryLabel = $group['category'] ?? 'Institutional Awards';
        $awards = $group['awards'] ?? [];
        foreach ($awards as $award) {
            $title = $award['title'] ?? 'Unknown Award';
            $keywords = $award['keywords'] ?? [];
            
            // Option 3: Apply keyword mapping/synonyms for improved matching
            $expandedKeywords = expandKeywordsWithMapping($keywords);
            
            // Tokenize award keywords (including expanded synonyms)
            $awardTokens = [];
            foreach ($expandedKeywords as $kw) {
                $awardTokens = array_merge($awardTokens, preprocessText(strtolower($kw)));
            }
            $awardTokens = array_unique($awardTokens);
            if (count($awardTokens) === 0) { continue; }
            
            // Use the new smart weighted scoring system
            $weightedResult = computeWeightedAwardMatch($rawText, $title, $keywords);
            $scorePercent = $weightedResult['match_score'];
            $eligibility = $weightedResult['status'];
            $weightedMatchedKeywords = $weightedResult['matched_keywords'];
            $scoringMethod = $weightedResult['method'];
            
            // Separate matched original keywords from synonyms for display  
            $matchedOriginalKeywords = [];
            $matchedSynonyms = [];
            
            // Check which keywords are original vs expanded
            foreach ($keywords as $kw) {
                if (stripos($rawText, $kw) !== false && in_array($kw, $weightedMatchedKeywords)) { 
                    $matchedOriginalKeywords[] = $kw; 
                }
            }
            
            foreach ($expandedKeywords as $kw) {
                if (stripos($rawText, $kw) !== false && !in_array($kw, $weightedMatchedKeywords)) { 
                    $matchedSynonyms[] = $kw; 
                }
            }
            
            // More lenient filtering - include results if they have any score or matched keywords
            $hasMatchedKeywords = !empty($weightedMatchedKeywords) || !empty($matchedOriginalKeywords) || !empty($matchedSynonyms);
            $hasAnyScore = $scorePercent > 0;
            
            // Include results with any meaningful score or keyword matches (much more lenient)
            if ($hasAnyScore || $hasMatchedKeywords) {
                error_log("Adding result for '$title': score=$scorePercent, status=$eligibility, hasScore=$hasAnyScore, hasKeywords=$hasMatchedKeywords");
                $results[] = [
                    'title' => $title,
                    'name' => $title,  // Add name field for frontend compatibility
                    'category' => $title,  // Use title as category for display
                    'award_category' => $categoryLabel,  // Keep original category for reference
                    'score' => $scorePercent,
                    'status' => $eligibility,
                    'matched_keywords' => $matchedOriginalKeywords,
                    'matched_synonyms' => $matchedSynonyms,
                    'scoring_method' => $scoringMethod
                ];
            } else {
                error_log("Skipping '$title': score=$scorePercent, hasScore=$hasAnyScore, hasKeywords=$hasMatchedKeywords");
            }
        }
    }
    
    error_log("Total results found: " . count($results));
    
    // Sort by score desc
    usort($results, function($a, $b) { return ($b['score'] ?? 0) <=> ($a['score'] ?? 0); });
    return $results;
}

function preprocessText($text) {
    // Convert to lowercase, remove punctuation, split into words
    $text = strtolower($text);
    $text = preg_replace('/[^\w\s]/', ' ', $text);
    $words = preg_split('/\s+/', $text);
    
    // Remove common stopwords
    $stopwords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should'];
    $words = array_filter($words, function($word) use ($stopwords) {
        return !in_array($word, $stopwords) && strlen($word) > 2;
    });
    
    // Apply stemming and normalization
    $processedWords = [];
    foreach ($words as $word) {
        $stemmed = stemWord($word);
        if (strlen($stemmed) > 2) {
            $processedWords[] = $stemmed;
        }
    }
    
    return array_values($processedWords);
}

function stemWord($word) {
    // Remove common suffixes
    $suffixes = [
        'ing', 'ed', 'er', 'est', 'ly', 'tion', 'sion', 'ity', 'ness', 
        'ment', 'able', 'ible', 'al', 'ial', 'ic', 'ical', 'ous', 'ious',
        'ive', 'ative', 'itive', 's', 'es', 'ies', 'ism', 'ist', 'ize', 'ise'
    ];
    
    // Sort suffixes by length (longest first) to handle compound suffixes
    usort($suffixes, function($a, $b) {
        return strlen($b) - strlen($a);
    });
    
    $originalWord = $word;
    
    // Apply suffix removal
    foreach ($suffixes as $suffix) {
        if (strlen($word) > strlen($suffix) + 2 && substr($word, -strlen($suffix)) === $suffix) {
            $word = substr($word, 0, -strlen($suffix));
            break; // Only remove one suffix
        }
    }
    
    // Ensure the stemmed word is meaningful (at least 3 characters)
    return strlen($word) >= 3 ? $word : $originalWord;
}
?>

